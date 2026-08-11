<?php

declare(strict_types=1);

namespace app\tests\Support\Fixtures;

use app\models\entities\User;
use yii\test\ActiveFixture;

/**
 * Fixture адміністративних користувачів.
 *
 * Використовується тестами, для яких користувач є стабільною
 * технічною передумовою, а не предметом самого тестування.
 *
 * Fixture навмисно не містить фіксованого primary key:
 * ID призначає тестова БД під час завантаження даних.
 * Завдяки цьому тести не залежать від конкретного AUTO_INCREMENT.
 */
final class UserFixture extends ActiveFixture
{
    /**
     * Alias запису активного адміністратора у fixture data.
     *
     * Alias є стабільним ідентифікатором саме для тестового коду
     * та не має відношення до primary key таблиці user.
     */
    public const ACTIVE_ADMIN_ALIAS = 'activeAdmin';

    public const ACTIVE_ADMIN_USERNAME = 'test_admin';

    public const ACTIVE_ADMIN_EMAIL = 'test-admin@example.com';

    /**
     * Відкритий пароль допустимо зберігати тут, оскільки це
     * виключно тестові дані без доступу до реального середовища.
     *
     * Значення використовується для перевірки реального login flow.
     */
    public const ACTIVE_ADMIN_PASSWORD = 'TestPassword123!';

    /**
     * ActiveFixture отримує назву таблиці через User::tableName()
     * та повертає через grabFixture() повноцінний User ActiveRecord.
     */
    public $modelClass = User::class;

    /**
     * Дані зберігаються у спільному Codeception data-каталозі,
     * заданому в codeception.yml як tests/Support/data.
     */
    public $dataFile = '@app/tests/Support/data/user.php';
}
