<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\forms\auth\LoginForm;
use app\tests\Support\Fixtures\UserFixture;
use Yii;
use yii\base\Security;

/**
 * Перевіряє application contract LoginForm.
 *
 * Валідний користувач надається через UserFixture, тому позитивні
 * та негативні password-сценарії працюють з реальною DB-backed
 * User entity, а не зі старими demo/demo даними Yii Basic template.
 */
final class LoginFormTest extends \Codeception\Test\Unit
{
    /**
     * Завантажує адміністративного користувача до test database.
     *
     * @return array<string, class-string>
     */
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    /**
     * Гарантує, що session state одного тесту не впливає
     * на наступний тест цього класу.
     */
    protected function _after(): void
    {
        Yii::$app->user->logout();
    }

    /**
     * Невідомий username не повинен створювати Yii session.
     */
    public function testLoginNoUser(): void
    {
        $model = new LoginForm(
            new Security(),
            [
                'username' => 'not_existing_username',
                'password' => 'not_existing_password',
            ],
        );

        self::assertFalse($model->login());
        self::assertTrue(Yii::$app->user->isGuest);
    }

    /**
     * Перевіряє саме неправильний пароль існуючого користувача.
     *
     * Fixture гарантує існування username, тому failure не може
     * бути випадково спричинений відсутністю User у test database.
     */
    public function testLoginWrongPassword(): void
    {
        $model = new LoginForm(
            new Security(),
            [
                'username' => UserFixture::ACTIVE_ADMIN_USERNAME,
                'password' => 'WrongPassword123!',
            ],
        );

        self::assertFalse($model->login());
        self::assertTrue(Yii::$app->user->isGuest);
        self::assertArrayHasKey('password', $model->errors);
    }

    /**
     * Перевіряє успішну авторизацію з реальним password_hash
     * fixture-користувача.
     */
    public function testLoginCorrect(): void
    {
        $model = new LoginForm(
            new Security(),
            [
                'username' => UserFixture::ACTIVE_ADMIN_USERNAME,
                'password' => UserFixture::ACTIVE_ADMIN_PASSWORD,
            ],
        );

        self::assertTrue($model->login());
        self::assertFalse(Yii::$app->user->isGuest);
        self::assertArrayNotHasKey('password', $model->errors);
    }
}
