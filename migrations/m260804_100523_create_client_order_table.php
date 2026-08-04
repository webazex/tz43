<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%client_order}}`.
 */
class m260804_100523_create_client_order_table extends Migration
{
    private const TABLE_NAME = '{{%client_order}}';
    private const CLIENT_TABLE_NAME = '{{%client}}';
    private const FIFO_INDEX = 'index_client_order_fifo';
    private const CLIENT_FOREIGN_KEY = 'foreign_key_client_order_client';

    /**
     * Створює таблицю замовлень, FIFO-індекс і зовнішній ключ
     * на клієнта.
     */
    public function safeUp(): void
    {
        $this->createTable(
            self::TABLE_NAME,
            [
                'id' => $this->primaryKey(),

                'client_id' => $this->integer()
                    ->notNull(),

                /**
                 * DECIMAL гарантує точне зберігання суми замовлення
                 * без похибок двійкової арифметики FLOAT.
                 */
                'amount' => $this->decimal(12, 2)
                    ->notNull(),

                'description' => $this->text()
                    ->notNull(),

                'status' => $this->string(16)
                    ->notNull()
                    ->defaultValue('pending'),

                'created_at' => $this->integer()
                    ->unsigned()
                    ->notNull(),

                'updated_at' => $this->integer()
                    ->unsigned()
                    ->notNull(),
            ],
            $this->getTableOptions()
        );

        /**
         * Індекс відповідає запиту Job, який оброблятиме pending-замовлення
         * конкретного клієнта в порядку FIFO:
         *
         * WHERE client_id = :clientId
         *   AND status = 'pending'
         * ORDER BY created_at ASC, id ASC
         *
         * Поле id використовується як додатковий критерій сортування,
         * тому порядок залишається детермінованим, навіть якщо декілька
         * замовлень мають однаковий created_at.
         *
         * Оскільки client_id є першою колонкою складеного індексу,
         * цей самий індекс також підтримує:
         * - вибірку всіх замовлень клієнта;
         * - роботу зовнішнього ключа client_id.
         *
         * Тому окремий індекс лише на client_id не потрібний.
         */
        $this->createIndex(
            self::FIFO_INDEX,
            self::TABLE_NAME,
            ['client_id', 'status', 'created_at', 'id']
        );

        /**
         * RESTRICT не дозволяє видалити клієнта, доки в нього існують
         * замовлення. Це захищає історію фінансових операцій.
         *
         * CASCADE зберігає зв'язок у малоймовірному випадку зміни
         * первинного ключа клієнта.
         */
        $this->addForeignKey(
            self::CLIENT_FOREIGN_KEY,
            self::TABLE_NAME,
            'client_id',
            self::CLIENT_TABLE_NAME,
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    /**
     * Видаляє зовнішній ключ, індекс і таблицю замовлень.
     */
    public function safeDown(): void
    {
        // Залежності видаляються у порядку, зворотному до їх створення.
        $this->dropForeignKey(
            self::CLIENT_FOREIGN_KEY,
            self::TABLE_NAME
        );

        $this->dropIndex(
            self::FIFO_INDEX,
            self::TABLE_NAME
        );

        $this->dropTable(self::TABLE_NAME);
    }

    /**
     * Повертає параметри таблиці для MySQL/MariaDB.
     */
    private function getTableOptions(): ?string
    {
        if ($this->db->driverName !== 'mysql') {
            return null;
        }

        return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
    }
}
