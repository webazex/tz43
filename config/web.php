<?php

use yii\helpers\ArrayHelper;
use yii\web\JsonParser;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$rules = is_file(__DIR__ . '/rules.php') ? require __DIR__ . '/rules.php' : [];
$queue = require __DIR__ . '/queue.php';
$diFactory = require __DIR__ . '/di.php';
$di = $diFactory($params);

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'container' => [
        'singletons' => $di,
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '',
            'parsers' => [
                'application/json' => JsonParser::class,
            ]
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\entities\User::class,
            'enableAutoLogin' => false,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => \yii\mail\MailerInterface::class,
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'queue' => $queue,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => $rules
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}
//rest-api-шечку веземо

$config['modules']['api'] = [
    'class' => \app\modules\api\Module::class,
];
$localConfigFile = __DIR__ . '/local/web.php';

/**
 * Локальні web-налаштування підключаються останніми,
 * тому мають найвищий пріоритет.
 *
 * Тут зберігаються секрети та параметри конкретного середовища:
 * cookieValidationKey, SMTP, зовнішні API тощо.
 */
if (is_file($localConfigFile)) {
    $config = ArrayHelper::merge(
        $config,
        require $localConfigFile
    );
}

return $config;
