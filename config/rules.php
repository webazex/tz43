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
    'GET clients/<id:\d+>' => 'api/client/view',
    'GET clients' => 'api/client/index',
    'POST clients' => 'api/client/create',

    // API: orders
    'POST orders' => 'api/order/create',
];