<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%user}}`.
 */
class m260804_100443_create_user_table extends Migration
{
    private const TABLE_NAME = '{{%user}}';

    /**
     * Зрозумілі імена індексів зберігаються в константах, щоб однакові
     * значення використовувалися під час створення та видалення схеми.
     */
    private const UNIQUE_USERNAME_INDEX = 'unique_user_username';
    private const UNIQUE_EMAIL_INDEX = 'unique_user_email';

    /**
     * Створює таблицю користувачів адміністративної панелі.
     */
    public function safeUp(): void
    {
        $this->createTable(
            self::TABLE_NAME,
            [
                'id' => $this->primaryKey(),

                /**
                 * Username використовується як логін адміністратора.
                 * Обмеження унікальності додається окремим індексом нижче.
                 */
                'username' => $this->string(64)->notNull(),

                /**
                 * Email є контактною адресою працівника та каналом
                 * для майбутнього відновлення доступу до панелі.
                 */
                'email' => $this->string(255)->notNull(),

                /**
                 * Auth key використовується Yii для перевірки автентичності
                 * довготривалої cookie в сценарії remember me.
                 */
                'auth_key' => $this->string(32)->notNull(),

                /**
                 * Зберігається тільки безпечний хеш пароля.
                 * Пароль у відкритому вигляді до БД не записується.
                 */
                'password_hash' => $this->string(255)->notNull(),

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
         * Унікальний індекс не дозволяє створити два облікові записи
         * з однаковим логіном.
         *
         * Четвертий аргумент true означає, що створюється саме
         * UNIQUE INDEX, а не звичайний пошуковий індекс.
         */
        $this->createIndex(
            self::UNIQUE_USERNAME_INDEX,
            self::TABLE_NAME,
            'username',
            true
        );

        /**
         * Один email може належати тільки одному користувачу панелі.
         * Це усуває неоднозначність під час відновлення пароля.
         */
        $this->createIndex(
            self::UNIQUE_EMAIL_INDEX,
            self::TABLE_NAME,
            'email',
            true
        );
    }

    /**
     * Видаляє створені об'єкти схеми у зворотному порядку.
     */
    public function safeDown(): void
    {
        $this->dropIndex(
            self::UNIQUE_EMAIL_INDEX,
            self::TABLE_NAME
        );

        $this->dropIndex(
            self::UNIQUE_USERNAME_INDEX,
            self::TABLE_NAME
        );

        $this->dropTable(self::TABLE_NAME);
    }

    /**
     * Повертає параметри таблиці лише для MySQL/MariaDB.
     *
     * Для інших драйверів Yii передає null і використовує їхні штатні
     * налаштування, не додаючи несумісний SQL.
     */
    private function getTableOptions(): ?string
    {
        if ($this->db->driverName !== 'mysql') {
            return null;
        }

        return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
    }
}
