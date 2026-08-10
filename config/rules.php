<?php

declare(strict_types=1);

return [
    // Frontend
    '' => 'site/index',

    'GET,POST login' => 'site/login',
    'POST logout' => 'site/logout',

    'GET,POST contact' => 'site/contact',
    'GET about' => 'site/about',
    'GET dashboard' => 'site/dashboard',

    'GET captcha' => 'site/captcha',

    // API
    'POST clients/<id:\d+>/topup' => 'api/client/top-up',
    'GET clients/<id:\d+>' => 'api/client/view',
    'GET clients' => 'api/client/index',
    'POST clients' => 'api/client/create',

    // API: orders
    'GET orders/<id:\d+>' => 'api/order/view',
    'GET orders' => 'api/order/index',
    'POST orders' => 'api/order/create',
];
