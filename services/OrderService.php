<?php

declare(strict_types=1);

namespace app\services;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\models\entities\Client;
use app\models\entities\Order;
use app\models\entities\enums\ClientPendingProcessingStatus;
use app\models\entities\enums\OrderStatus;
use app\models\valueObjects\Money;
use InvalidArgumentException;
use OverflowException;
use Throwable;
use Yii;

/**
 * Application service операцій із замовленнями.
 *
 * Координує Client та Order, визначає межу транзакції та повертає
 * transport-independent OperationResult. HTTP status і зовнішній JSON
 * до відповідальності сервісу не належать.
 */
final class OrderService
{
    public const ERROR_CLIENT_NOT_FOUND = 'CLIENT_NOT_FOUND';
    public const ERROR_CLIENT_BLOCKED = 'CLIENT_BLOCKED';
    public const ERROR_CLIENT_BALANCE_PROCESSING = 'CLIENT_BALANCE_PROCESSING';
    public const ERROR_INVALID_AMOUNT = 'ORDER_INVALID_AMOUNT';
    public const ERROR_CREATE_FAILED = 'ORDER_CREATE_FAILED';
    public const ERROR_PERSISTENCE_FAILED = 'ORDER_PERSISTENCE_FAILED';
    public const ERROR_LIST_FAILED = 'ORDER_LIST_FAILED';

    public const ERROR_NOT_FOUND = 'ORDER_NOT_FOUND';

    /**
     * Створює замовлення та визначає його початковий статус.
     *
     * Якщо балансу достатньо, замовлення і списання коштів зберігаються
     * атомарно, а замовлення отримує статус paid. Якщо коштів бракує,
     * створюється pending-замовлення без зміни балансу.
     *
     * Рядок клієнта блокується через SELECT ... FOR UPDATE до завершення
     * транзакції. Тому паралельні фінансові операції не можуть прийняти
     * рішення на підставі одного й того самого актуального балансу.
     *
     * @return OperationResult<Order>
     */
    public function create(int $clientId, string $amount, string $description): OperationResult
    {
        try {
            $orderAmount = Money::fromDecimal($amount);
        } catch (InvalidArgumentException | OverflowException) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_INVALID_AMOUNT,
                    details: [
                        'amount' => $amount,
                    ],
                )
            );
        }

        /**
         * Service зберігає власний захист use case, оскільки його можна
         * викликати не тільки з HTTP-контролера, а й з CLI, Job або тесту.
         */
        if ($orderAmount->isZero()) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_INVALID_AMOUNT,
                    details: [
                        'amount' => $amount,
                    ],
                )
            );
        }

        $transaction = null;

        try {
            $transaction = Client::getDb()->beginTransaction();

            /** @var Client|null $client */
            $client = Client::findBySql(
                'SELECT * FROM {{%client}} WHERE [[id]] = :id FOR UPDATE',
                [
                    ':id' => $clientId,
                ],
            )->one();

            if ($client === null) {
                $transaction->rollBack();

                return OperationResult::failure(
                    new OperationError(
                        code: self::ERROR_CLIENT_NOT_FOUND,
                        details: [
                            'id' => $clientId,
                        ],
                    )
                );
            }

            if ($client->isBlocked()) {
                $transaction->rollBack();

                return OperationResult::failure(
                    new OperationError(
                        code: self::ERROR_CLIENT_BLOCKED,
                        details: [
                            'id' => $clientId,
                        ],
                    )
                );
            }

            /**
             * Поки Queue Job обробляє pending-замовлення, нове списання
             * не повинно конкурувати з ним за баланс клієнта. Статус failed
             * також вимагає окремого відновлення, тому створення дозволене
             * тільки у стабільному стані idle.
             */
            if (
                $client->pending_processing_status
                !== ClientPendingProcessingStatus::Idle->value
            ) {
                $transaction->rollBack();

                return OperationResult::failure(
                    new OperationError(
                        code: self::ERROR_CLIENT_BALANCE_PROCESSING,
                        details: [
                            'id' => $clientId,
                            'pendingProcessingStatus' => $client->pending_processing_status,
                        ],
                    )
                );
            }

            $currentBalance = Money::fromDecimal($client->balance);
            $canBePaid = $currentBalance->isEnoughFor($orderAmount);

            $order = new Order([
                'client_id' => $clientId,
                'amount' => $orderAmount->toDecimal(),
                'description' => $description,
                'status' => $canBePaid
                    ? OrderStatus::Paid->value
                    : OrderStatus::Pending->value,
            ]);

            /**
             * Entity-валідація залишається останнім захистом інваріантів,
             * навіть якщо HTTP input вже перевірив CreateOrderForm.
             */
            if (!$order->save()) {
                $transaction->rollBack();

                return OperationResult::failure(
                    new OperationError(
                        code: self::ERROR_CREATE_FAILED,
                        details: [
                            'validationErrors' => $order->getErrors(),
                        ],
                    )
                );
            }

            if ($canBePaid) {
                $client->balance = $currentBalance
                    ->subtract($orderAmount)
                    ->toDecimal();

                /**
                 * Зберігаються лише змінений баланс і updated_at.
                 * Решта атрибутів клієнта в цьому use case не змінюється.
                 */
                if (!$client->save(false, ['balance', 'updated_at'])) {
                    $transaction->rollBack();

                    return OperationResult::failure(
                        new OperationError(
                            code: self::ERROR_PERSISTENCE_FAILED,
                        )
                    );
                }
            }

            $transaction->commit();

            return OperationResult::success($order);
        } catch (Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            Yii::error($exception, __METHOD__);

            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_PERSISTENCE_FAILED,
                )
            );
        }
    }

    /**
     * Повертає замовлення за його primary key.
     *
     * Операція є read-only: не блокує рядок, не відкриває транзакцію
     * та не змінює баланс клієнта або lifecycle замовлення.
     *
     * Пошук за primary key виконується одним SQL-запитом.
     *
     * @return OperationResult<Order>
     */
    public function getById(int $id): OperationResult
    {
        $order = Order::findOne($id);

        if ($order === null) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_NOT_FOUND,
                    details: [
                        'id' => $id,
                    ],
                )
            );
        }

        return OperationResult::success($order);
    }

    /**
     * Повертає сторінку замовлень із необов'язковими фільтрами.
     *
     * Для count і вибірки використовується однаково відфільтрований query.
     * Сортування за created_at та id робить порядок стабільним, навіть якщо
     * кілька замовлень створено протягом однієї секунди.
     *
     * @return OperationResult<array{items: list<Order>, totalCount: int}>
     */
    public function getList(int $page, int $perPage, ?int $clientId, ?string $status): OperationResult
    {
        try {
            $query = Order::find();

            if ($clientId !== null) {
                $query->andWhere(['client_id' => $clientId]);
            }

            if ($status !== null) {
                $query->andWhere(['status' => $status]);
            }

            $query->orderBy([
                'created_at' => SORT_DESC,
                'id' => SORT_DESC,
            ]);

            /**
             * Пагінація потребує двох SQL-запитів:
             * COUNT(*) для метаданих і SELECT тільки поточної сторінки.
             * Дані клієнта не підвантажуються, тому N+1 запитів немає.
             */
            $totalCount = (int) (clone $query)->count();

            /** @var list<Order> $items */
            $items = $query
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->all();

            return OperationResult::success([
                'items' => $items,
                'totalCount' => $totalCount,
            ]);
        } catch (Throwable $exception) {
            Yii::error($exception, __METHOD__);

            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_LIST_FAILED,
                )
            );
        }
    }
}