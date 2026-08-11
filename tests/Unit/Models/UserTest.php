<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\entities\User;
use app\tests\Support\Fixtures\UserFixture;
use RuntimeException;

/**
 * Перевіряє IdentityInterface contract адміністративного User.
 *
 * User відповідає тільки за session/cookie authentication
 * адміністративної панелі.
 *
 * Bearer token REST API навмисно не є відповідальністю User
 * і обробляється окремим ApiTokenAuthenticator.
 */
final class UserTest extends \Codeception\Test\Unit
{
    public mixed $tester = null;

    /**
     * Завантажує стабільну User entity для identity-сценаріїв.
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
     * Перевіряє пошук активного адміністратора за реальним
     * primary key, створеним test database.
     */
    public function testFindUserById(): void
    {
        $fixtureUser = $this->grabAdmin();

        $user = User::findIdentity($fixtureUser->getId());

        self::assertInstanceOf(User::class, $user);
        self::assertSame(
            UserFixture::ACTIVE_ADMIN_USERNAME,
            $user->username
        );

        /**
         * Fixture очищає таблицю перед завантаженням,
         * тому наступний ID гарантовано не належить fixture user.
         */
        $unknownId = (int) $fixtureUser->getId() + 1;

        self::assertNull(User::findIdentity($unknownId));
    }

    /**
     * Перевіряє важливу межу відповідальності authentication layer.
     *
     * User не повинен використовуватися для Bearer token REST API.
     * Навіть auth_key Yii cookie-auth не є API access token.
     */
    public function testFindUserByAccessToken(): void
    {
        $fixtureUser = $this->grabAdmin();

        self::assertNull(
            User::findIdentityByAccessToken($fixtureUser->auth_key)
        );

        self::assertNull(
            User::findIdentityByAccessToken('external-api-token')
        );
    }

    /**
     * Перевіряє пошук активного адміністратора за username.
     */
    public function testFindUserByUsername(): void
    {
        $fixtureUser = $this->grabAdmin();

        $user = User::findByUsername(
            UserFixture::ACTIVE_ADMIN_USERNAME
        );

        self::assertInstanceOf(User::class, $user);
        self::assertSame($fixtureUser->getId(), $user->getId());

        self::assertNull(
            User::findByUsername('not_existing_username')
        );
    }

    /**
     * Перевіряє Yii auth_key contract для remember-me cookie.
     *
     * Валідним є тільки auth_key поточного User.
     */
    public function testValidateAuthKey(): void
    {
        $user = $this->grabAdmin();

        self::assertTrue(
            $user->validateAuthKey($user->auth_key)
        );

        self::assertFalse(
            $user->validateAuthKey(
                'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
            )
        );
    }

    /**
     * Повертає User entity за стабільним fixture alias.
     *
     * Primary key навмисно не фіксується:
     * його призначає test database під час завантаження fixture.
     */
    private function grabAdmin(): User
    {
        $user = $this->tester->grabFixture(
            'users',
            UserFixture::ACTIVE_ADMIN_ALIAS
        );

        if (!$user instanceof User) {
            throw new RuntimeException(
                'UserFixture не повернула очікувану User entity.'
            );
        }

        return $user;
    }
}
