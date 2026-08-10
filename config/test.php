<?php

declare(strict_types=1);

use app\models\entities\User;
use app\modules\api\Module as ApiModule;
use app\tests\Support\MailerBootstrap;
use yii\symfonymailer\Mailer;
use yii\symfonymailer\Message;
use yii\web\JsonParser;

$params = require __DIR__ . '/params.php';
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
 * Відмінності обмежені тестовою БД, файловою поштою та вимкненим CSRF.
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
     * API-модуль обов’язково реєструється у тестовому застосуванні.
     * Без цього route api/order/cancel та інші API routes
     * не можуть бути розв’язані під час functional-тестів.
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
         * Це дозволяє перевіряти атомарність:
         *
         * top-up → queued → Queue Job record.
         */
        'queue' => $queue,

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
             * CSRF вимкнений тільки в тестовому середовищі.
             * Production policy залишається в ApiController:
             * session → CSRF, Bearer token → без CSRF.
             */
            'enableCsrfValidation' => false,

            /**
             * Functional-тести повинні перевіряти той самий JSON input,
             * який приймають реальні API endpoints.
             */
            'parsers' => [
                'application/json' => JsonParser::class,
            ],
        ],

        /**
         * Підключаємо справжні API URL з config/rules.php.
         * Тести перевірятимуть зовнішній endpoint, а не прямий виклик action.
         */
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => $rules,
        ],
    ],

    'params' => $params,
];
