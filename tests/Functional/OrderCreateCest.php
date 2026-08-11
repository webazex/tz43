<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\entities\Client;
use app\models\entities\Order;
use app\models\entities\enums\ClientPendingProcessingStatus;
use app\models\entities\enums\OrderStatus;
use app\tests\Support\FunctionalTester;
use JsonException;

/**
 * Functional-тести REST endpoint створення замовлення.
 *
 * Кожен business scenario самостійно формує необхідний стан test database
 * і виконує запит через реальний HTTP transport Yii2 test application.
 *
 * Тест не залежить від:
 * - адміністративного User;
 * - session authentication;
 * - існуючих локальних клієнтів;
 * - фіксованих AUTO_INCREMENT ID;
 * - порядку запуску інших тестів.
 */
final class OrderCreateCest
{
    private const API_TOKEN = 'functional-test-token';

    /**
     * Налаштовує технічну передумову доступу до REST API.
     *
     * Bearer token не є частиною business scenario замовлення.
     * Він лише дозволяє запиту пройти реальну authentication policy API.
     */
    public function _before(FunctionalTester $I): void
    {
        $I->haveHttpHeader(
            'Authorization',
            'Bearer ' . self::API_TOKEN
        );
    }

    /**
     * Перевіряє граничний успішний випадок:
     * баланс клієнта точно дорівнює сумі нового замовлення.
     *
     * Business invariant:
     *
     * balance >= order amount
     * → коштів достатньо
     * → order.status = paid
     * → balance зменшується на amount.
     *
     * Рівність вибрана навмисно, щоб сценарій виявляв помилку
     * строгого порівняння `>` замість `>=`.
     *
     * @throws JsonException
     */
    public function createPaidOrderWhenBalanceExactlyEqualsAmount(FunctionalTester $I): void
    {
        /**
         * GIVEN
         *
         * Створюємо business-передумову безпосередньо в test database.
         *
         * POST /clients тут навмисно не використовується:
         * працездатність тесту Order endpoint не повинна залежати
         * від працездатності іншого API endpoint.
         *
         * haveRecord() зберігає AR без validation, тому lifecycle-поля,
         * які впливають на OrderService, задаємо явно.
         */
        $clientId = (int) $I->haveRecord(Client::class, [
            'name' => 'Exact Balance Client',
            'email' => 'exact-balance@example.test',
            'balance' => '40.00',
            'status' => Client::STATUS_ACTIVE,
            'pending_processing_status' => ClientPendingProcessingStatus::Idle->value,
        ]);

        /**
         * WHEN
         *
         * Виконуємо реальний JSON POST через Functional helper.
         */
        $I->sendJsonPostRequest('/orders', [
            'client_id' => $clientId,
            'amount' => '40.00',
            'description' => 'Exact balance order',
        ]);

        /**
         * THEN — HTTP contract.
         *
         * Успішне створення нового Order повинно повернути 201 Created.
         */
        $I->seeResponseCodeIs(201);

        /**
         * THEN — response contract.
         *
         * Декодуємо фактичний HTTP body, а не звертаємося
         * до Controller або Resource напряму.
         */
        $response = json_decode(
            $I->grabPageSource(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $I->assertIsArray($response);
        $I->assertArrayHasKey('success', $response);
        $I->assertArrayHasKey('data', $response);
        $I->assertArrayHasKey('error', $response);

        $I->assertTrue($response['success']);
        $I->assertNull($response['error']);
        $I->assertIsArray($response['data']);

        $data = $response['data'];

        $I->assertArrayHasKey('id', $data);
        $I->assertArrayHasKey('clientId', $data);
        $I->assertArrayHasKey('amount', $data);
        $I->assertArrayHasKey('description', $data);
        $I->assertArrayHasKey('status', $data);
        $I->assertArrayHasKey('createdAt', $data);

        $I->assertSame($clientId, $data['clientId']);
        $I->assertSame('40.00', $data['amount']);
        $I->assertSame('Exact balance order', $data['description']);
        $I->assertSame(OrderStatus::Paid->value, $data['status']);

        $orderId = (int) $data['id'];

        $I->assertGreaterThan(0, $orderId);
        $I->assertGreaterThan(0, (int) $data['createdAt']);

        /**
         * THEN — persistence contract Order.
         *
         * Окремо перевіряємо database state:
         * коректний JSON сам по собі не гарантує успішний persistence.
         */
        $I->seeRecord(Order::class, [
            'id' => $orderId,
            'client_id' => $clientId,
            'amount' => '40.00',
            'description' => 'Exact balance order',
            'status' => OrderStatus::Paid->value,
        ]);

        /**
         * THEN — persistence contract Client.
         *
         * Оскільки початковий баланс і amount однакові,
         * після атомарної оплати залишок повинен бути рівно 0.00.
         */
        $I->seeRecord(Client::class, [
            'id' => $clientId,
            'balance' => '0.00',
        ]);
    }
}
