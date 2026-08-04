<?php

/**
 * Приклад локальної web-конфігурації.
 *
 * Під час першого `composer install` файл автоматично копіюється
 * як config/local/web.php, після чого Composer генерує унікальний
 * cookieValidationKey.
 *
 * Наявний локальний файл та вже згенерований ключ
 * під час повторного встановлення не перезаписуються.
 */
return [
    'components' => [
        'request' => [
            'cookieValidationKey' => '',
        ],
    ],
];
