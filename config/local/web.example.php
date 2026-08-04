<?php

/**
 * Приклад локальної web-конфігурації.
 *
 * Скопіюйте файл як config/local/web.php
 * та згенеруйте унікальний cookieValidationKey.
 */
return [
    'components' => [
        'request' => [
            'cookieValidationKey' => 'replace-with-random-secret-key',
        ],
    ],
];
