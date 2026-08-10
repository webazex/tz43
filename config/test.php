<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';
$queue = require __DIR__ . '/queue.php';
$diFactory = require __DIR__ . '/di.php';
$di = $diFactory($params);

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),
    'container' => [
        /**
         * Функціональні тести використовують ті самі DI-контракти,
         * що й основний web-застосунок.
         */
        'singletons' => $di,
    ],
    'bootstrap' => [
        \app\tests\Support\MailerBootstrap::class,
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        /**
         * DB Queue працює з тестовою БД через компонент `db`.
         * Це дозволить перевіряти атомарність top-up та постановки Job.
         */
        'queue' => $queue,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'messageClass' => \yii\symfonymailer\Message::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'user' => [
            'identityClass' => \app\models\entities\User::class,
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            // but if you absolutely need it set cookie domain to localhost
            /*
            'csrfCookie' => [
                'domain' => 'localhost',
            ],
            */
        ],
    ],
    'params' => $params,
];
