<?php

use yii\db\Connection;
use yii\helpers\ArrayHelper;

/**
 * Базова конфігурація підключення до бази даних.
 *
 * Параметри, що залежать від конкретного середовища
 * (DSN, логін і пароль), зберігаються у config/local/db.php.
 */
$config = [
    'class' => Connection::class,
    'charset' => 'utf8mb4',

    // Кешування схеми можна ввімкнути у production-середовищі.
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];

$localConfigFile = __DIR__ . '/local/db.php';

/**
 * Локальна конфігурація має вищий пріоритет:
 * її значення замінюють відповідні значення базового конфіга.
 */
if (is_file($localConfigFile)) {
    $config = ArrayHelper::merge(
        $config,
        require $localConfigFile
    );
}

return $config;
