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
use RuntimeException;
use Throwable;
use Yii;

/**
 * Обробляє pending-замовлення одного клієнта після поповнення балансу.
 *
 * Processor містить завершений application use case:
 *
 * 1. блокує клієнта;
 * 2. читає актуальний баланс;
 * 3. отримує актуальний список pending-замовлень;
 * 4. обходить їх за нестрогим FIFO;
 * 5. списує кошти за доступні замовлення;
 * 6. атомарно зберігає баланс і статуси замовлень.
 *
 * Queue Job надалі передаватиме сюди тільки clientId. Job не повинен
 * містити розрахунок балансу або власну реалізацію FIFO.
 */
final class PendingOrdersProcessor
{
    public const ERROR_CLIENT_NOT_FOUND = 'CLIENT_NOT_FOUND';
    public const ERROR_PROCESSING_FAILED = 'PENDING_ORDERS_PROCESSING_FAILED';

    /**
     * Обробляє всі актуальні pending-замовлення клієнта.
     *
     * Порядок обходу:
     *
     * created_at ASC, id ASC
     *
     * FIFO є нестрогим: якщо поточного балансу недостатньо для конкретного
     * замовлення, воно залишається pending, але processor продовжує перевіряти
     * наступні замовлення. Це дозволяє оплатити менше замовлення, яке було
     * створене пізніше за дорожче.
     *
     * Повторний запуск є безпечним: processor читає тільки замовлення зі
     * статусом pending. Уже оплачені замовлення повторно не списуються.
     *
     * @return OperationResult<int> Кількість оплачених замовлень.
     */
    public function process(int $clientId): OperationResult
    {
        $transaction = null;

        /**
         * Значення потрібні для безпечного переведення клієнта у failed,
         * якщо основна транзакція буде відкочена.
         *
         * @var array{
         *     balance: string,
         *     pendingProcessingStatus: string,
         *     updatedAt: int
         * }|null $failureGuard
         */
        $failureGuard = null;

        try {
            $transaction = Client::getDb()->beginTransaction();

            /**
             * Усі фінансові операції конкретного клієнта серіалізуються
             * блокуванням його рядка.
             *
             * Паралельний Job, top-up або створення замовлення чекатиме
             * завершення поточної транзакції та після цього прочитає вже
             * актуальний баланс.
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

            /**
             * Якщо основна транзакція завершиться помилкою, ці значення
             * дозволять змінити статус на failed тільки за умови, що після
             * rollback інша операція ще не встигла змінити клієнта.
             */
            $failureGuard = [
                'balance' => $client->balance,
                'pendingProcessingStatus' => $client->pending_processing_status,
                'updatedAt' => (int) $client->updated_at,
            ];

            $currentBalance = Money::fromDecimal($client->balance);

            /**
             * Завантажуються тільки дані, потрібні для розрахунку.
             *
             * Додаткове блокування кожного Order не потрібне: усі application
             * use cases, які змінюють фінансовий стан замовлення, спочатку
             * повинні блокувати того самого Client.
             *
             * @var list<array{id: int|string, amount: string}> $pendingOrders
             */
            $pendingOrders = Order::find()
                ->select([
                    'id',
                    'amount',
                ])
                ->where([
                    'client_id' => $clientId,
                    'status' => OrderStatus::Pending->value,
                ])
                ->orderBy([
                    'created_at' => SORT_ASC,
                    'id' => SORT_ASC,
                ])
                ->asArray()
                ->all();

            /** @var list<int> $paidOrderIds */
            $paidOrderIds = [];

            foreach ($pendingOrders as $pendingOrder) {
                $orderAmount = Money::fromDecimal(
                    (string) $pendingOrder['amount']
                );

                /**
                 * Нестрогий FIFO:
                 *
                 * недостатньо коштів → пропускаємо поточне замовлення;
                 * достатньо коштів   → списуємо суму та запам'ятовуємо ID.
                 */
                if (!$currentBalance->isEnoughFor($orderAmount)) {
                    continue;
                }

                $currentBalance = $currentBalance->subtract($orderAmount);
                $paidOrderIds[] = (int) $pendingOrder['id'];
            }

            if ($paidOrderIds !== []) {
                /**
                 * Усі відібрані замовлення оновлюються одним SQL-запитом.
                 *
                 * Додаткова умова status = pending захищає від повторного
                 * переходу вже оплаченого або скасованого замовлення.
                 */
                $updatedOrderCount = Order::updateAll(
                    [
                        'status' => OrderStatus::Paid->value,
                        'updated_at' => time(),
                    ],
                    [
                        'and',
                        [
                            'id' => $paidOrderIds,
                        ],
                        [
                            'client_id' => $clientId,
                        ],
                        [
                            'status' => OrderStatus::Pending->value,
                        ],
                    ],
                );

                /**
                 * За встановленої дисципліни блокування значення мають
                 * збігатися. Невідповідність означає конкурентну або
                 * позасистемну зміну даних, тому всю операцію відкочуємо.
                 */
                if ($updatedOrderCount !== count($paidOrderIds)) {
                    throw new RuntimeException(
                        'Не всі відібрані pending-замовлення були оновлені.'
                    );
                }
            }

            $client->balance = $currentBalance->toDecimal();
            $client->pending_processing_status =
                ClientPendingProcessingStatus::Idle->value;

            /**
             * Баланс, lifecycle-статус і статуси замовлень входять до однієї
             * транзакції. Неможливий частковий результат, за якого замовлення
             * вже paid, а кошти з балансу не списані, або навпаки.
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
                    'Не вдалося зберегти результат обробки клієнта.'
                );
            }

            $transaction->commit();

            return OperationResult::success(
                count($paidOrderIds)
            );
        } catch (Throwable $exception) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }

            Yii::error($exception, __METHOD__);

            /**
             * Основна фінансова транзакція вже відкочена. Окремо фіксуємо
             * технічний стан failed, не змінюючи баланс або замовлення.
             */
            $this->markClientAsFailed(
                $clientId,
                $failureGuard,
            );

            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_PROCESSING_FAILED,
                    details: [
                        'clientId' => $clientId,
                    ],
                )
            );
        }
    }

    /**
     * Позначає невдалу обробку, не перезаписуючи новіший стан клієнта.
     *
     * Умова містить баланс, попередній lifecycle-статус та updated_at.
     * Якщо після rollback вже виконалося нове поповнення або інший Job,
     * UPDATE не зачепить рядок і не перезапише актуальний стан на failed.
     *
     * @param array{
     *     balance: string,
     *     pendingProcessingStatus: string,
     *     updatedAt: int
     * }|null $failureGuard
     */
    private function markClientAsFailed(int $clientId, ?array $failureGuard): void
    {
        if ($failureGuard === null) {
            return;
        }

        try {
            Client::updateAll(
                [
                    'pending_processing_status' =>
                        ClientPendingProcessingStatus::Failed->value,
                    'updated_at' => time(),
                ],
                [
                    'id' => $clientId,
                    'balance' => $failureGuard['balance'],
                    'pending_processing_status' =>
                        $failureGuard['pendingProcessingStatus'],
                    'updated_at' => $failureGuard['updatedAt'],
                ],
            );
        } catch (Throwable $exception) {
            /**
             * Помилка фіксації failed не повинна приховати початкову помилку
             * processor. Обидві помилки потраплять до application log.
             */
            Yii::error($exception, __METHOD__);
        }
    }
}
