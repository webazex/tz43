<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\entities\User;
use app\tests\Support\FunctionalTester;
use RuntimeException;
use Yii;

/**
 * Functional-тести авторизації адміністративного користувача.
 *
 * Кожен тест працює з реальним User ActiveRecord у тестовій БД.
 * Тести не залежать від локального адміністратора, фіксованого ID
 * або попереднього запуску init/setup.
 *
 * Yii2 module виконує functional-тести всередині test transaction,
 * тому створений користувач не повинен залишатися в БД після тесту.
 */
final class LoginFormCest
{
    private const USERNAME = 'test_admin';
    private const EMAIL = 'test-admin@example.com';
    private const PASSWORD = 'TestPassword123!';

    private User $user;

    /**
     * Створює незалежний тестовий контекст перед кожним сценарієм.
     *
     * Користувач створюється через ту саму ActiveRecord-сутність
     * та ті самі password/auth-key methods, які використовує
     * штатний процес створення адміністратора.
     */
    public function _before(FunctionalTester $I): void
    {
        $this->user = $this->createUser();

        $I->amOnRoute('site/login');
    }

    /**
     * Перевіряє, що сторінка входу доступна неавторизованому користувачу.
     */
    public function openLoginPage(FunctionalTester $I): void
    {
        $I->see('Login', 'h1');
    }

    /**
     * Перевіряє внутрішню авторизацію Yii за реальним primary key.
     *
     * На відміну від стандартного Yii Basic template, тест не очікує,
     * що в БД наперед існує користувач з ID = 100.
     */
    public function internalLoginById(FunctionalTester $I): void
    {
        $I->amLoggedInAs($this->user->getId());
        $I->amOnPage('/');

        $I->see('Logout (' . self::USERNAME . ')');
    }

    /**
     * Перевіряє внутрішню авторизацію Yii через IdentityInterface.
     *
     * Тут передається реальна User entity, яку тест створив сам,
     * тому сценарій не залежить від глобального test fixture.
     */
    public function internalLoginByInstance(FunctionalTester $I): void
    {
        $I->amLoggedInAs($this->user);
        $I->amOnPage('/');

        $I->see('Logout (' . self::USERNAME . ')');
    }

    /**
     * Перевіряє required-validation форми входу.
     */
    public function loginWithEmptyCredentials(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', []);

        $I->expectTo('see validation errors');
        $I->see('Username cannot be blank.');
        $I->see('Password cannot be blank.');
    }

    /**
     * Перевіряє відмову в авторизації при неправильному паролі.
     *
     * Важливо, що користувач із таким username реально існує.
     * Таким чином тест перевіряє саме неправильний пароль,
     * а не випадок відсутнього користувача.
     */
    public function loginWithWrongCredentials(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => self::USERNAME,
            'LoginForm[password]' => 'WrongPassword123!',
        ]);

        $I->expectTo('see validation errors');
        $I->see('Incorrect username or password.');
    }

    /**
     * Перевіряє повний login flow через web-форму.
     *
     * Сценарій проходить через:
     *
     * HTTP form
     * → SiteController
     * → LoginForm
     * → User::findByUsername()
     * → password hash validation
     * → Yii session authentication.
     */
    public function loginSuccessfully(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => self::USERNAME,
            'LoginForm[password]' => self::PASSWORD,
        ]);

        $I->see('Logout (' . self::USERNAME . ')');
        $I->dontSeeElement('form#login-form');
    }

    /**
     * Створює адміністративного користувача тільки для поточного тесту.
     *
     * Не використовує init-command, оскільки test setup не повинен
     * залежати від інтерактивного CLI або стану конкретного середовища.
     *
     * @throws RuntimeException якщо тестові передумови неможливо створити
     */
    private function createUser(): User
    {
        $user = new User([
            'username' => self::USERNAME,
            'email' => self::EMAIL,
            'status' => User::STATUS_ACTIVE,
        ]);

        $security = Yii::$app->getSecurity();

        $user->setPassword(self::PASSWORD, $security);
        $user->generateAuthKey($security);

        if (!$user->save()) {
            throw new RuntimeException(
                'Не вдалося створити test User: '
                . json_encode($user->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $user;
    }
}
