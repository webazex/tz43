<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%client}}`.
 */
class m260804_100508_create_client_table extends Migration
{
    private const TABLE_NAME = '{{%client}}';
    private const NAME_INDEX = 'index_client_name';
    private const UNIQUE_EMAIL_INDEX = 'unique_client_email';

    /**
     * Створює таблицю клієнтів та індекси для пошуку й контролю
     * унікальності email.
     */
    public function safeUp(): void
    {
        $this->createTable(
            self::TABLE_NAME,
            [
                'id' => $this->primaryKey(),

                'name' => $this->string(255)
                    ->notNull(),

                'email' => $this->string(255)
                    ->notNull(),

                /**
                 * DECIMAL використовується замість FLOAT, щоб уникнути
                 * похибок під час фінансових розрахунків.
                 */
                'balance' => $this->decimal(12, 2)
                    ->notNull()
                    ->defaultValue('0.00'),

                'status' => $this->string(16)
                    ->notNull()
                    ->defaultValue('active'),

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
         * Індекс підтримує пошук клієнтів за ім'ям.
         *
         * Він навмисно не є унікальним: різні клієнти можуть мати
         * однакові імена.
         *
         * Звичайний B-tree індекс буде корисним для точного або
         * префіксного пошуку:
         *
         * name = 'Антон'
         * name LIKE 'Ант%'
         *
         * Для пошуку виду LIKE '%тон%' цей індекс зазвичай
         * використовуватися не буде.
         */
        $this->createIndex(
            self::NAME_INDEX,
            self::TABLE_NAME,
            'name'
        );

        /**
         * Одна email-адреса може відповідати лише одному клієнту.
         *
         * UNIQUE-індекс одночасно:
         * - забороняє дублювання email;
         * - прискорює пошук клієнта за email;
         * - захищає БД, навіть якщо application validation буде обійдено.
         */
        $this->createIndex(
            self::UNIQUE_EMAIL_INDEX,
            self::TABLE_NAME,
            'email',
            true
        );
    }

    /**
     * Видаляє створені індекси та таблицю клієнтів.
     *
     * Під час загального rollback ця міграція повинна відкочуватися
     * після міграції client_order, щоб її зовнішній ключ уже був видалений.
     */
    public function safeDown(): void
    {
        // Залежності видаляються у порядку, зворотному до їх створення.
        $this->dropIndex(
            self::UNIQUE_EMAIL_INDEX,
            self::TABLE_NAME
        );

        $this->dropIndex(
            self::NAME_INDEX,
            self::TABLE_NAME
        );

        $this->dropTable(self::TABLE_NAME);
    }

    /**
     * Повертає параметри таблиці для MySQL/MariaDB.
     *
     * Для інших драйверів повертається null, щоб не передавати
     * несумісний SQL.
     */
    private function getTableOptions(): ?string
    {
        if ($this->db->driverName !== 'mysql') {
            return null;
        }

        return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
    }
}
