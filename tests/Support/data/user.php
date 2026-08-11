<?php

declare(strict_types=1);

use app\models\entities\User;
use app\tests\Support\Fixtures\UserFixture;

/**
 * Дані fixture адміністративних користувачів.
 *
 * ActiveFixture записує ці значення безпосередньо до test database.
 * Тому тут явно присутні всі NOT NULL поля таблиці user,
 * включно з created_at та updated_at.
 *
 * Пароль для password_hash:
 *
 * TestPassword123!
 *
 * Це виключно тестовий пароль. Він не є секретом і не повинен
 * використовуватися у dev, staging або production середовищах.
 */
return [
    UserFixture::ACTIVE_ADMIN_ALIAS => [
        'username' => UserFixture::ACTIVE_ADMIN_USERNAME,
        'email' => UserFixture::ACTIVE_ADMIN_EMAIL,

        /**
         * Фіксований auth_key робить fixture детермінованою.
         * Його довжина відповідає полю auth_key VARCHAR(32).
         */
        'auth_key' => '0123456789abcdef0123456789abcdef',

        /**
         * Валідний bcrypt hash для:
         *
         * TestPassword123!
         *
         * LoginForm перевірятиме його штатним Yii Security,
         * тому ми тестуємо реальну password validation.
         */
        'password_hash' => '$2y$12$h1kqJmLg3dRH0ZCOFDN4LuE8lCEwxPIbwv9toe7Zh2G85dyhk0fUa',

        'status' => User::STATUS_ACTIVE,

        /**
         * Час тут не є предметом login-тестів.
         * Використовуємо фіксоване валідне значення замість time(),
         * щоб fixture завжди описувала однаковий початковий стан.
         */
        'created_at' => 1700000000,
        'updated_at' => 1700000000,
    ],
];
