<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\jobs\ProcessPendingOrdersJob;
use app\models\entities\Client;
use app\models\entities\enums\ClientPendingProcessingStatus;
use app\tests\Support\FunctionalTester;
use JsonException;
use Yii;

/**
 * Functional-тести REST endpoint поповнення балансу клієнта.
 *
 * Цей Cest перевіряє синхронну частину top-up use case:
 *
 * HTTP request
 * → валідація
 * → ClientService
 * → зміна Client
 * → постановка ProcessPendingOrdersJob у DB Queue
 * → HTTP response.
 *
 * Виконання Queue Job тут навмисно не запускається.
 * Фінансовий flow PendingOrdersProcessor перевіряється окремо,
 * оскільки зовнішній worker не повинен залежати від транзакції
 * поточного Functional-тесту.
 */
final class ClientTopUpCest
{
    private const API_TOKEN = 'functional-test-token';

    /**
     * Додає test-only Bearer token до кожного REST request цього Cest.
     *
     * Authentication залишається частиною реального API pipeline,
     * але не є business-предметом конкретного top-up scenario.
     */
    public function _before(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . self::API_TOKEN);
    }

    /**
     * Перевіряє успішне поповнення балансу та постановку
     * асинхронної обробки pending-замовлень у DB Queue.
     *
     * Business invariant:
     *
     * successful top-up
     * → balance збільшено на credited amount
     * → pending_processing_status = queued
     * → ProcessPendingOrdersJob додано до DB Queue
     * → HTTP 202 повертає результат саме синхронного зарахування.
     *
     * @throws JsonException
     */
    public function topUpCreditsBalanceAndEnqueuesProcessing(FunctionalTester $I): void
    {
        /**
         * GIVEN
         *
         * Створюємо окремого active-клієнта у стабільному lifecycle.
         * Початковий баланс 10.00 дозволяє однозначно перевірити
         * арифметичний результат після поповнення на 50.00.
         */
        $clientId = (int) $I->haveRecord(Client::class, [
            'name' => 'Top Up Client',
            'email' => 'top-up@example.test',
            'balance' => '10.00',
            'status' => Client::STATUS_ACTIVE,
            'pending_processing_status' => ClientPendingProcessingStatus::Idle->value,
        ]);

        /**
         * Не припускаємо, що queue-таблиця порожня.
         *
         * У локальній test database можуть існувати записи,
         * створені попередніми ручними перевірками.
         * Тому інваріант тесту — поява рівно однієї НОВОЇ Job.
         */
        $queueCountBefore = (int) Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM {{%queue}}')
            ->queryScalar();

        /**
         * WHEN
         *
         * Виконуємо реальний JSON POST через Functional transport helper.
         */
        $I->sendJsonPostRequest(
            sprintf('/clients/%d/topup', $clientId),
            [
                'amount' => '50.00',
            ]
        );

        /**
         * THEN — HTTP contract.
         *
         * 202 Accepted означає:
         * синхронне зарахування вже завершене,
         * але асинхронна Queue Job ще не зобов'язана бути виконаною.
         */
        $I->seeResponseCodeIs(202);

        /**
         * THEN — response contract.
         *
         * balanceAfterTopUp не є фінальним балансом після Queue worker.
         * Це лише стан одразу після зарахування 50.00.
         */
        $response = json_decode(
            $I->grabPageSource(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $I->assertIsArray($response);
        $I->assertTrue($response['success']);
        $I->assertNull($response['error']);
        $I->assertIsArray($response['data']);

        $I->assertSame('50.00', $response['data']['creditedAmount']);
        $I->assertSame('10.00', $response['data']['oldBalance']);
        $I->assertSame('60.00', $response['data']['balanceAfterTopUp']);

        /**
         * THEN — Client persistence contract.
         *
         * Перевіряємо фактичний database state окремо від HTTP response.
         * Зарахований баланс та lifecycle повинні бути збережені разом.
         */
        $I->seeRecord(Client::class, [
            'id' => $clientId,
            'balance' => '60.00',
            'pending_processing_status' => ClientPendingProcessingStatus::Queued->value,
        ]);

        /**
         * THEN — Queue persistence contract.
         *
         * Успішний top-up повинен створити рівно одну нову DB Queue Job.
         * Якщо enqueue буде випадково видалений із ClientService,
         * ця перевірка зробить тест червоним навіть при правильному balance.
         */
        $queueCountAfter = (int) Yii::$app->db
            ->createCommand('SELECT COUNT(*) FROM {{%queue}}')
            ->queryScalar();

        $I->assertSame(
            $queueCountBefore + 1,
            $queueCountAfter
        );

        /**
         * Знаходимо саме новостворену Job.
         *
         * Поки worker не запускався, запис повинен залишатися
         * в default channel та бути доступним для подальшої обробки.
         */
        $queueRecord = Yii::$app->db
            ->createCommand(
                'SELECT [[job]], [[channel]]
                 FROM {{%queue}}
                 ORDER BY [[id]] DESC
                 LIMIT 1'
            )
            ->queryOne();

        $I->assertIsArray($queueRecord);
        $I->assertSame('default', $queueRecord['channel']);

        /**
         * Поточна DB Queue використовує стандартну PHP-серіалізацію Job.
         *
         * Перевіряємо не лише факт INSERT у queue, а й те,
         * що endpoint поставив ProcessPendingOrdersJob саме
         * для клієнта поточного business scenario.
         *
         * allowed_classes обмежує відновлення лише очікуваним Job-класом.
         */
        $job = unserialize(
            $queueRecord['job'],
            [
                'allowed_classes' => [
                    ProcessPendingOrdersJob::class,
                ],
            ]
        );

        $I->assertInstanceOf(ProcessPendingOrdersJob::class, $job);
        $I->assertSame($clientId, $job->clientId);
    }
}
