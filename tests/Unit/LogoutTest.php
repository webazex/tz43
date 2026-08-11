<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\controllers\SiteController;
use app\models\entities\User;
use app\tests\Support\Fixtures\UserFixture;
use app\tests\Support\UnitTester;
use RuntimeException;
use Yii;
use yii\base\Security;
use yii\web\IdentityInterface;
use yii\web\View;

/**
 * Перевіряє відображення logout-елементів для авторизованого
 * адміністративного користувача.
 *
 * Тест використовує UserFixture замість припущення про існування
 * користувача з фіксованим ID або username у test database.
 */
final class LogoutTest extends \Codeception\Test\Unit
{
    public mixed $tester = null;

    /**
     * Завантажує стабільного адміністративного користувача
     * перед виконанням кожного тестового сценарію класу.
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
     * Перевіряє повний session logout flow на рівні Yii application.
     *
     * Передумова:
     * fixture містить активного адміністратора.
     *
     * Очікування:
     * - Yii приймає User як IdentityInterface;
     * - після login layout показує logout-посилання;
     * - logout виконується через POST;
     * - після actionLogout посилання більше не відображається.
     */
    public function testRenderLogoutLinkWhenUserIsLoggedIn(): void
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

        self::assertInstanceOf(
            IdentityInterface::class,
            $user,
            'Fixture user повинен реалізовувати Yii IdentityInterface.'
        );

        $controller = new SiteController(
            'site',
            Yii::$app,
            Yii::$app->mailer,
            new Security(),
        );

        $view = new View(['context' => $controller]);

        self::assertTrue(
            Yii::$app->user->login($user),
            'Yii повинен успішно авторизувати fixture user.'
        );

        $expectedLogoutLabel = 'Logout ('
            . UserFixture::ACTIVE_ADMIN_USERNAME
            . ')';

        $html = $view->render(
            '//layouts/main.php',
            ['content' => 'Hello World°']
        );

        self::assertStringContainsString(
            $expectedLogoutLabel,
            $html,
            'Logout-посилання повинно містити username авторизованого користувача.'
        );

        self::assertStringContainsString(
            'data-method="post"',
            $html,
            'Logout-посилання повинно використовувати POST method.'
        );

        $controller->actionLogout();

        $html = $view->render(
            '//layouts/main.php',
            ['content' => 'Hello World°']
        );

        self::assertStringNotContainsString(
            $expectedLogoutLabel,
            $html,
            'Після logout посилання авторизованого користувача не повинно відображатися.'
        );
    }
}
