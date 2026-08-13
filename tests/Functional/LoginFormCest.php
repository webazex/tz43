<?php

declare(strict_types=1);

namespace app\tests\Functional;

use app\models\entities\User;
use app\tests\Support\Fixtures\UserFixture;
use app\tests\Support\FunctionalTester;
use RuntimeException;

/**
 * Functional-тести авторизації адміністративного користувача.
 *
 * UserFixture створює відомий стан таблиці user перед кожним тестом.
 *
 * Сценарії не залежать:
 * - від адміністратора, створеного через init/setup;
 * - від локальних даних розробника;
 * - від порядку запуску тестів;
 * - від конкретного AUTO_INCREMENT ID.
 */
final class LoginFormCest
{
    private User $admin;

    /**
     * Оголошує fixture, яку Yii2 Codeception module завантажує
     * до виконання конкретного тестового сценарію.
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
     * Готує загальну передумову login-сценаріїв.
     *
     * Fixture вже завантажена до моменту виконання _before(),
     * тому тут ми лише отримуємо створену User entity за alias.
     */
    public function _before(FunctionalTester $I): void
    {
        $admin = $I->grabFixture(
            'users',
            UserFixture::ACTIVE_ADMIN_ALIAS
        );

        /**
         * grabFixture() має mixed return type на рівні Codeception API.
         * Явна runtime-перевірка не дозволяє тесту мовчки працювати
         * з некоректно налаштованою fixture.
         */
        if (!$admin instanceof User) {
            throw new RuntimeException(
                'UserFixture не повернула очікувану User entity.'
            );
        }

        $this->admin = $admin;

        $I->amOnRoute('site/login');
    }

    /**
     * Перевіряє доступність актуальної сторінки авторизації.
     *
     * Functional-тест фіксує користувацький контракт сторінки:
     * форма login повинна бути присутня, а заголовок має відповідати
     * затвердженому українському інтерфейсу dashboard.
     */
    public function openLoginPage(FunctionalTester $I): void
    {
        $I->see('Авторизація', 'h1');
        $I->seeElement('#login-form');
    }

    /**
     * Перевіряє внутрішню Yii-авторизацію за реальним primary key.
     *
     * Критична відмінність від Yii Basic scaffold:
     * тест використовує ID, фактично створений fixture,
     * а не припускає існування користувача з ID = 100.
     */
    public function internalLoginById(FunctionalTester $I): void
    {
        $I->amLoggedInAs($this->admin->getId());
        $I->amOnPage('/');

        $I->see(
            'Logout (' . UserFixture::ACTIVE_ADMIN_USERNAME . ')'
        );
    }

    /**
     * Перевіряє авторизацію через готову IdentityInterface entity.
     */
    public function internalLoginByInstance(FunctionalTester $I): void
    {
        $I->amLoggedInAs($this->admin);
        $I->amOnPage('/');

        $I->see(
            'Logout (' . UserFixture::ACTIVE_ADMIN_USERNAME . ')'
        );
    }

    /**
     * Перевіряє required validation login-форми.
     *
     * Fixture у цьому сценарії безпосередньо не потрібна,
     * але спільний baseline класу залишається однаковим
     * для всіх login-сценаріїв.
     */
    public function loginWithEmptyCredentials(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', []);

        $I->expectTo('see validation errors');
        $I->see('Username cannot be blank.');
        $I->see('Password cannot be blank.');
    }

    /**
     * Перевіряє саме неправильний пароль для існуючого користувача.
     *
     * Це важливіше за старий scaffold-тест:
     * username гарантовано існує в БД, тому негативний результат
     * спричинений password validation, а не відсутністю User.
     */
    public function loginWithWrongCredentials(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => UserFixture::ACTIVE_ADMIN_USERNAME,
            'LoginForm[password]' => 'WrongPassword123!',
        ]);

        $I->expectTo('see validation errors');
        $I->see('Incorrect username or password.');
    }

    /**
     * Перевіряє повний успішний login flow через web-форму.
     *
     * Сценарій проходить через реальні:
     *
     * HTTP form
     * → SiteController
     * → LoginForm
     * → User::findByUsername()
     * → Yii Security password validation
     * → Yii session authentication.
     */
    public function loginSuccessfully(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => UserFixture::ACTIVE_ADMIN_USERNAME,
            'LoginForm[password]' => UserFixture::ACTIVE_ADMIN_PASSWORD,
        ]);

        $I->see(
            'Logout (' . UserFixture::ACTIVE_ADMIN_USERNAME . ')'
        );
        $I->dontSeeElement('form#login-form');
    }
}
