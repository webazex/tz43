<?php

declare(strict_types=1);

return [
    // Frontend
    '' => 'site/index',

    'GET,POST login' => 'site/login',
    'POST logout' => 'site/logout',

    'GET,POST contact' => 'site/contact',
    'GET about' => 'site/about',

    /**
     * Dashboard навмисно винесено під /dashboard, щоб web routes
     * не конфліктували з API /clients та /orders під час тестового.
     */
    'GET dashboard' => 'site/dashboard',
    'GET dashboard/profile' => 'site/profile',
    'GET dashboard/clients/<id:\d+>' => 'site/client',
    'GET dashboard/clients' => 'site/clients',
    'GET dashboard/orders/create' => 'site/order-create',
    'GET dashboard/orders' => 'site/orders',

    'GET captcha' => 'site/captcha',

    // API: clients
    'GET clients/search' => 'api/client/search',
    'POST clients/<id:\d+>/topup' => 'api/client/top-up',
    'PATCH clients/<id:\d+>' => 'api/client/update',
    'GET clients/<id:\d+>' => 'api/client/view',
    'GET clients' => 'api/client/index',
    'POST clients' => 'api/client/create',

    // API: orders
    'POST orders/<id:\d+>/cancel' => 'api/order/cancel',
    'GET orders/<id:\d+>' => 'api/order/view',
    'GET orders' => 'api/order/index',
    'POST orders' => 'api/order/create',
];
