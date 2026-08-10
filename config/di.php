<?php

declare(strict_types=1);

use app\modules\api\security\ApiTokenAuthenticator;
use yii\mail\MailerInterface;
use yii\symfonymailer\Mailer;
use yii\base\InvalidConfigException;
use yii\queue\Queue;

/**
 * Формуємо DI definitions застосунку.
 *
 * @param array<string, mixed> $params
 */
return static function (array $params): array {
    return [
        MailerInterface::class => [
            'class' => Mailer::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],

        ApiTokenAuthenticator::class => [
            'class' => ApiTokenAuthenticator::class,
            '__construct()' => [
                $params['api']['accessTokens'] ?? [],
            ],
        ],

        /**
         * Application services залежать від базового контракту Queue,
         * але отримують налаштований application-компонент `queue`.
         *
         * Це гарантує використання того самого DB Queue і того самого
         * компонента db, що й у web- та console-застосунках.
         */
        Queue::class => static function (): Queue {
            $queue = \Yii::$app->get('queue');

            if (!$queue instanceof Queue) {
                throw new InvalidConfigException(
                    'Application-компонент queue має реалізовувати yii\queue\Queue.'
                );
            }

            return $queue;
        },
    ];
};
