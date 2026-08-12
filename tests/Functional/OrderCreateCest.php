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

    /**
     * Перевіряє граничний випадок недостатнього балансу:
     * клієнту бракує рівно однієї копійки для оплати замовлення.
     *
     * Business invariant:
     *
     * balance < order amount
     * → коштів недостатньо
     * → order.status = pending
     * → balance не змінюється.
     *
     * Значення 39.99 і 40.00 вибрані навмисно.
     * Вони перевіряють межу прийняття рішення з точністю до копійки,
     * а не лише очевидний випадок великої нестачі коштів.
     *
     * @throws JsonException
     */
    public function createPendingOrderWhenBalanceIsInsufficient(FunctionalTester $I): void
    {
        /**
         * GIVEN
         *
         * Клієнт має на одну копійку менше, ніж необхідно
         * для повної оплати нового замовлення.
         */
        $clientId = (int) $I->haveRecord(Client::class, [
            'name' => 'Insufficient Balance Client',
            'email' => 'insufficient-balance@example.test',
            'balance' => '39.99',
            'status' => Client::STATUS_ACTIVE,
            'pending_processing_status' => ClientPendingProcessingStatus::Idle->value,
        ]);

        /**
         * WHEN
         *
         * Створюємо замовлення на 40.00 через той самий REST endpoint.
         */
        $I->sendJsonPostRequest('/orders', [
            'client_id' => $clientId,
            'amount' => '40.00',
            'description' => 'Insufficient balance order',
        ]);

        /**
         * THEN — HTTP contract.
         *
         * Недостатній баланс не є validation або transport error.
         * Order успішно створюється, але переходить у pending,
         * тому endpoint все одно повинен повернути 201 Created.
         */
        $I->seeResponseCodeIs(201);

        /**
         * THEN — business response.
         *
         * Повну схему success-response вже перевіряє перший сценарій.
         * Тут перевіряємо тільки поля, які доводять правильність
         * альтернативної business-гілки.
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

        $data = $response['data'];

        $I->assertSame($clientId, $data['clientId']);
        $I->assertSame('40.00', $data['amount']);
        $I->assertSame(
            'Insufficient balance order',
            $data['description']
        );
        $I->assertSame(
            OrderStatus::Pending->value,
            $data['status']
        );

        $orderId = (int) $data['id'];

        $I->assertGreaterThan(0, $orderId);

        /**
         * THEN — persistence contract Order.
         *
         * Замовлення повинно реально зберегтися як pending,
         * а не лише серіалізуватися з таким статусом у response.
         */
        $I->seeRecord(Order::class, [
            'id' => $orderId,
            'client_id' => $clientId,
            'amount' => '40.00',
            'description' => 'Insufficient balance order',
            'status' => OrderStatus::Pending->value,
        ]);

        /**
         * THEN — persistence contract Client.
         *
         * Pending-замовлення не резервує і не списує кошти.
         * Баланс після створення повинен залишитися точно 39.99.
         */
        $I->seeRecord(Client::class, [
            'id' => $clientId,
            'balance' => '39.99',
        ]);
    }

    /**
     * Перевіряє заборону створення нового замовлення
     * для клієнта зі статусом blocked.
     *
     * Business invariant:
     *
     * client.status = blocked
     * → створення нового Order заборонене
     * → HTTP 409 CLIENT_BLOCKED
     * → Order не створюється
     * → balance не змінюється.
     *
     * Достатній баланс задається навмисно:
     * тест повинен довести, що саме статус клієнта блокує операцію,
     * а не нестача коштів.
     *
     * @throws JsonException
     */
    public function rejectOrderCreationForBlockedClient(FunctionalTester $I): void
    {
        /**
         * GIVEN
         *
         * Заблокований клієнт має достатньо коштів для оплати.
         * Без business-перевірки blocked такий Order став би paid,
         * тому сценарій чутливий до втрати відповідного guard.
         */
        $clientId = (int) $I->haveRecord(Client::class, [
            'name' => 'Blocked Client',
            'email' => 'blocked-order@example.test',
            'balance' => '100.00',
            'status' => Client::STATUS_BLOCKED,
            'pending_processing_status' => ClientPendingProcessingStatus::Idle->value,
        ]);

        /**
         * WHEN
         *
         * Виконуємо звичайний валідний POST /orders.
         * Єдиною причиною відмови повинен бути статус blocked.
         */
        $I->sendJsonPostRequest('/orders', [
            'client_id' => $clientId,
            'amount' => '40.00',
            'description' => 'Blocked client order',
        ]);

        /**
         * THEN — HTTP contract.
         *
         * Business conflict представляється як 409 Conflict.
         */
        $I->seeResponseCodeIs(409);

        /**
         * THEN — failure response contract.
         *
         * Перевіряємо application error code, а не текст повідомлення:
         * саме code є стабільною частиною зовнішнього контракту.
         */
        $response = json_decode(
            $I->grabPageSource(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $I->assertIsArray($response);
        $I->assertFalse($response['success']);
        $I->assertNull($response['data']);
        $I->assertIsArray($response['error']);

        $I->assertSame(
            'CLIENT_BLOCKED',
            $response['error']['code']
        );

        $I->assertSame(
            $clientId,
            $response['error']['details']['id']
        );

        /**
         * THEN — persistence contract Order.
         *
         * Business failure не повинен залишати частково
         * виконану операцію в database.
         */
        $I->dontSeeRecord(Order::class, [
            'client_id' => $clientId,
            'description' => 'Blocked client order',
        ]);

        /**
         * THEN — persistence contract Client.
         *
         * Відхилений запит не має права змінити баланс.
         */
        $I->seeRecord(Client::class, [
            'id' => $clientId,
            'balance' => '100.00',
            'status' => Client::STATUS_BLOCKED,
        ]);
    }
}
