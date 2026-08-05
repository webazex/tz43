<?php

declare(strict_types=1);

use app\modules\api\security\ApiTokenAuthenticator;
use yii\mail\MailerInterface;
use yii\symfonymailer\Mailer;

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
    ];
};
