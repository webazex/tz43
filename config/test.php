<?php

declare(strict_types=1);

use app\models\entities\User;
use app\modules\api\Module as ApiModule;
use app\tests\Support\MailerBootstrap;
use yii\caching\FileCache;
use yii\symfonymailer\Mailer;
use yii\symfonymailer\Message;
use yii\web\JsonParser;

/**
 * Functional REST-тести використовують окремий детермінований Bearer token.
 *
 * Список замінюється повністю, а не доповнюється:
 * test environment не повинен залежати від API-токенів,
 * які розробник може мати у config/local/params.php.
 *
 * Значення не є production secret і використовується виключно
 * всередині ізольованого test application.
 */
$params['api']['accessTokens'] = [
    'functional-test-token',
];

$db = require __DIR__ . '/test_db.php';
$queue = require __DIR__ . '/queue.php';
$rules = is_file(__DIR__ . '/rules.php')
    ? require __DIR__ . '/rules.php'
    : [];

$diFactory = require __DIR__ . '/di.php';
$di = $diFactory($params);

/**
 * Конфігурація web-застосунку для functional-тестів.
 *
 * Тестове застосування використовує ті самі API-модуль, маршрути,
 * DI-контракти та DB Queue, що й основне web-застосування.
 *
 * Відмінності обмежені тестовою БД, файловою поштою
 * та окремими налаштуваннями тестового середовища.
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),

    'container' => [
        /**
         * Functional-тести повинні створювати application services
         * через ті самі DI definitions, що й production web-застосування.
         */
        'singletons' => $di,
    ],

    'bootstrap' => [
        MailerBootstrap::class,
    ],

    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],

    /**
     * API-модуль обов'язково реєструється у тестовому застосунку.
     *
     * Завдяки цьому functional-тести можуть проходити через реальні
     * REST routes, controllers та transport layer застосунку.
     */
    'modules' => [
        'api' => [
            'class' => ApiModule::class,
        ],
    ],

    'language' => 'en-US',

    'components' => [
        /**
         * Усі persistence-перевірки виконуються тільки в test database.
         */
        'db' => $db,

        /**
         * DB Queue використовує ту саму тестову БД.
         *
         * Це дозволяє перевіряти атомарність сценарію:
         *
         * top-up → client balance → processing lifecycle → Queue Job.
         */
        'queue' => $queue,

        /**
         * Стандартний файловий кеш Yii потрібен інфраструктурним
         * компонентам web-застосунку, зокрема URL Manager.
         *
         * Це НЕ business-кеш балансу клієнта і не реалізація
         * бонусного Redis-завдання з ТЗ.
         */
        'cache' => [
            'class' => FileCache::class,
        ],

        'mailer' => [
            'class' => Mailer::class,
            'messageClass' => Message::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],

        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],

        'user' => [
            'identityClass' => User::class,
        ],

        'request' => [
            'cookieValidationKey' => 'test',

            /**
             * CSRF вимкнений тільки в поточному functional-test environment.
             *
             * Через це цей suite не перевіряє production CSRF policy.
             * Якщо така перевірка знадобиться, її слід реалізувати
             * окремим security-сценарієм, а не змішувати з business-tests.
             */
            'enableCsrfValidation' => false,

            /**
             * Functional-тести повинні приймати той самий JSON input,
             * який обробляють реальні REST endpoints.
             */
            'parsers' => [
                'application/json' => JsonParser::class,
            ],
        ],

        /**
         * Використовуємо справжні API URL з config/rules.php.
         *
         * Тест має перевіряти transport contract endpoint-а,
         * а не викликати controller action напряму.
         */
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => $rules,
        ],
    ],
    'params' => $params,
];
