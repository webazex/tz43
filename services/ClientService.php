<?php

declare(strict_types=1);

namespace app\services;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\contracts\results\TopUpResult;
use app\jobs\ProcessPendingOrdersJob;
use app\models\entities\Client;
use app\models\entities\enums\ClientPendingProcessingStatus;
use app\models\valueObjects\Money;
use InvalidArgumentException;
use OverflowException;
use RuntimeException;
use Throwable;
use Yii;
use yii\db\IntegrityException;
use yii\queue\Queue;

final class ClientService
{
    public const ERROR_CREATE_FAILED = 'CLIENT_CREATE_FAILED';
    public const ERROR_DATA_CONFLICT = 'CLIENT_DATA_CONFLICT';
    public const ERROR_NOT_FOUND = 'CLIENT_NOT_FOUND';
    public const ERROR_TOP_UP_INVALID_AMOUNT = 'CLIENT_TOP_UP_INVALID_AMOUNT';
    public const ERROR_BALANCE_LIMIT_EXCEEDED = 'CLIENT_BALANCE_LIMIT_EXCEEDED';
    public const ERROR_TOP_UP_FAILED = 'CLIENT_TOP_UP_FAILED';

    /**
     * Queue передається через DI та посилається
     * на application-компонент `queue`.
     */
    public function __construct(private readonly Queue $queue)
    {
    }

    /**
     * Беремо вже готовий набір даних про клієнта і створюємо клієнта.
     *
     * Метод не залежить від джерела даних і не виконує транспортних операцій.
     * API, CLI та Admin використовують один application use case.
     *
     * @return OperationResult<Client>
     */
    public function create(string $name, string $email, string $balance, string $status): OperationResult
    {
        $client = new Client([
            'name' => $name,
            'email' => $email,
            'balance' => $balance,
            'status' => $status,
        ]);

        try {
            if (!$client->save()) {
                return OperationResult::failure(
                    new OperationError(
                        code: self::ERROR_CREATE_FAILED,
                        details: [
                            'validationErrors' => $client->getErrors(),
                        ],
                    )
                );
            }
        } catch (IntegrityException) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_DATA_CONFLICT,
                )
            );
        }

        return OperationResult::success($client);
    }

    /**
     * Повертає клієнта за його ID.
     *
     * @return OperationResult<Client>
     */
    public function getById(int $id): OperationResult
    {
        $client = Client::findOne($id);

        if ($client === null) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_NOT_FOUND,
                    details: [
                        'id' => $id,
                    ],
                )
            );
        }

        return OperationResult::success($client);
    }

    /**
     * Повертає сторінку списку клієнтів.
     *
     * Опціональний параметр search виконує substring-пошук
     * одночасно за ім'ям та email клієнта.
     *
     * Порожній search не змінює поведінку списку:
     * повертається звичайна сторінка без фільтрації.
     *
     * Фільтр застосовується ДО підрахунку totalCount,
     * тому pagination metadata завжди описує саме
     * відфільтрований набір записів.
     *
     * @return array{items: list<Client>, totalCount: int}
     */
    public function getList(int $page, int $perPage, ?string $search = null): array
    {
        /**
         * Нормалізація виконується всередині application service,
         * щоб поведінка пошуку не залежала від конкретного transport layer.
         *
         * API, CLI або майбутня admin-панель отримають однакову семантику:
         * пробіли по краях не є частиною пошукового значення.
         */
        $search = $search === null
            ? null
            : trim($search);

        /**
         * Порожній рядок після trim прирівнюється до відсутнього фільтра.
         */
        if ($search === '') {
            $search = null;
        }

        $query = Client::find()
            ->andFilterWhere([
                'or',
                ['like', 'name', $search],
                ['like', 'email', $search],
            ])
            ->orderBy(['id' => SORT_ASC]);

        /**
         * COUNT виконується вже після застосування search-фільтра.
         * Це критично для правильної pagination:
         * totalCount має містити кількість знайдених клієнтів,
         * а не загальну кількість записів у таблиці.
         */
        $totalCount = (int) (clone $query)->count();

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        return [
            'items' => $items,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * Поповнює баланс клієнта та ставить асинхронну обробку
     * pending-замовлень у DB Queue.
     *
     * Зміна балансу, lifecycle-статусу та створення Queue Job виконуються
     * в одній DB-транзакції. Якщо Job не вдалося поставити в чергу,
     * поповнення балансу також буде відкочено.
     *
     * Заблокованому клієнту поповнення дозволено: блокування забороняє
     * лише створення нових замовлень.
     *
     * @return OperationResult<TopUpResult>
     */
    public function topUp(int $clientId, string $amount): OperationResult
    {
        try {
            $topUpAmount = Money::fromDecimal($amount);
        } catch (InvalidArgumentException) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_TOP_UP_INVALID_AMOUNT,
                    details: [
                        'amount' => $amount,
                    ],
                )
            );
        }

        if ($topUpAmount->isZero()) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_TOP_UP_INVALID_AMOUNT,
                    details: [
                        'amount' => $amount,
                    ],
                )
            );
        }

        try {
            return Client::getDb()->transaction(
                function () use ($clientId, $topUpAmount): OperationResult {
                    /**
                     * Блокування рядка серіалізує top-up, створення замовлень
                     * і виконання PendingOrdersProcessor для одного клієнта.
                     *
                     * @var Client|null $client
                     */
                    $client = Client::findBySql(
                        'SELECT * FROM {{%client}} WHERE [[id]] = :id FOR UPDATE',
                        [
                            ':id' => $clientId,
                        ],
                    )->one();

                    if ($client === null) {
                        return OperationResult::failure(
                            new OperationError(
                                code: self::ERROR_NOT_FOUND,
                                details: [
                                    'id' => $clientId,
                                ],
                            )
                        );
                    }

                    $oldBalance = Money::fromDecimal($client->balance);

                    try {
                        $balanceAfterTopUp = $oldBalance->add($topUpAmount);
                    } catch (OverflowException) {
                        return OperationResult::failure(
                            new OperationError(
                                code: self::ERROR_BALANCE_LIMIT_EXCEEDED,
                            )
                        );
                    }

                    $client->balance = $balanceAfterTopUp->toDecimal();
                    $client->pending_processing_status =
                        ClientPendingProcessingStatus::Queued->value;

                    /**
                     * Зберігаємо тільки атрибути поточного use case.
                     * TimestampBehavior самостійно оновить updated_at.
                     */
                    if (
                        !$client->save(
                            false,
                            [
                                'balance',
                                'pending_processing_status',
                                'updated_at',
                            ],
                        )
                    ) {
                        throw new RuntimeException(
                            'Не вдалося зберегти поповнений баланс клієнта.'
                        );
                    }

                    /**
                     * У payload зберігається тільки clientId.
                     * Processor прочитає актуальний баланс і pending-замовлення
                     * вже під час виконання Job.
                     */
                    $jobId = $this->queue->push(
                        new ProcessPendingOrdersJob([
                            'clientId' => $clientId,
                        ])
                    );

                    /**
                     * Queue::push() може повернути null, якщо постановку Job
                     * перехопив EVENT_BEFORE_PUSH. Такий результат не можна
                     * вважати успішним поповненням.
                     */
                    if ($jobId === null) {
                        throw new RuntimeException(
                            'Queue не повернула ідентифікатор створеної Job.'
                        );
                    }

                    return OperationResult::success(
                        new TopUpResult(
                            creditedAmount: $topUpAmount->toDecimal(),
                            oldBalance: $oldBalance->toDecimal(),
                            balanceAfterTopUp: $balanceAfterTopUp->toDecimal(),
                        )
                    );
                }
            );
        } catch (Throwable $exception) {
            /**
             * transaction() уже відкотив зміну Client і вставку в queue.
             * Технічні подробиці залишаються в application log,
             * а зовнішній transport отримає стабільний application-код.
             */
            Yii::error($exception, __METHOD__);

            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_TOP_UP_FAILED,
                    details: [
                        'clientId' => $clientId,
                    ],
                )
            );
        }
    }
}
