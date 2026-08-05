<?php

declare(strict_types=1);

use yii\helpers\ArrayHelper;

$params = [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    'api' => [
        'accessTokens' => [],
    ],
];

$localParamsFile = __DIR__ . '/local/params.php';

if (is_file($localParamsFile)) {
    $params = ArrayHelper::merge(
        $params,
        require $localParamsFile
    );
}

return $params;
