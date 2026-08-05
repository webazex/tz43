<?php
declare(strict_types=1);
use app\modules\api\security\ApiTokenAuthenticator;
return [
    \yii\mail\MailerInterface::class => [
        'class' => \yii\symfonymailer\Mailer::class,
        // send all mails to a file by default.
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
