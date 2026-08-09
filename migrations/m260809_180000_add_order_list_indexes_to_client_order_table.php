<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Додає індекси для пагінованого списку та фільтрів замовлень.
 */
final class m260809_180000_add_order_list_indexes_to_client_order_table extends Migration
{
    private const TABLE_NAME = '{{%client_order}}';
    private const CREATED_INDEX = 'index_client_order_created';
    private const CLIENT_CREATED_INDEX = 'index_client_order_client_created';
    private const STATUS_CREATED_INDEX = 'index_client_order_status_created';

    /**
     * Створює індекси відповідно до реальних query-shapes GET /orders.
     */
    public function safeUp(): void
    {
        /**
         * Підтримує глобальний список:
         *
         * ORDER BY created_at DESC, id DESC
         */
        $this->createIndex(
            self::CREATED_INDEX,
            self::TABLE_NAME,
            ['created_at', 'id']
        );

        /**
         * Підтримує список замовлень конкретного клієнта:
         *
         * WHERE client_id = :clientId
         * ORDER BY created_at DESC, id DESC
         */
        $this->createIndex(
            self::CLIENT_CREATED_INDEX,
            self::TABLE_NAME,
            ['client_id', 'created_at', 'id']
        );

        /**
         * Підтримує окрему фільтрацію за статусом:
         *
         * WHERE status = :status
         * ORDER BY created_at DESC, id DESC
         *
         * Комбінацію client_id + status вже обслуговує FIFO-індекс
         * із попередньої міграції.
         */
        $this->createIndex(
            self::STATUS_CREATED_INDEX,
            self::TABLE_NAME,
            ['status', 'created_at', 'id']
        );
    }

    /**
     * Видаляє індекси у зворотному порядку.
     */
    public function safeDown(): void
    {
        $this->dropIndex(
            self::STATUS_CREATED_INDEX,
            self::TABLE_NAME
        );

        $this->dropIndex(
            self::CLIENT_CREATED_INDEX,
            self::TABLE_NAME
        );

        $this->dropIndex(
            self::CREATED_INDEX,
            self::TABLE_NAME
        );
    }
}