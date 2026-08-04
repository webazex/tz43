<?php

use yii\helpers\ArrayHelper;

/**
 * Тестова конфігурація наслідує драйвер, кодування та реквізити
 * доступу з основної конфігурації БД.
 *
 * DSN одразу замінюється на безпечну тестову базу, щоб тести
 * випадково не виконувалися у робочій базі даних.
 */
$config = ArrayHelper::merge(
    require __DIR__ . '/db.php',
    [
        'dsn' => 'mysql:host=localhost;dbname=tz43_test',
    ]
);

$localConfigFile = __DIR__ . '/local/test_db.php';

/**
 * За потреби локальний файл може перевизначити хост,
 * назву тестової БД або інші параметри підключення.
 */
if (is_file($localConfigFile)) {
    $config = ArrayHelper::merge(
        $config,
        require $localConfigFile
    );
}

return $config;
