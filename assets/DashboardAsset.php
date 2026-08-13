<?php

declare(strict_types=1);

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\YiiAsset;

/**
 * AssetBundle адміністративної панелі TZ43.
 *
 * Dashboard навмисно не залежить від Bootstrap: затверджена верстка має
 * власний компактний UI і не потребує додаткового CSS/JS framework.
 *
 * YiiAsset залишаємо єдиною залежністю, оскільки він підключає штатний jQuery,
 * який використовується production-версією dashboard.js.
 */
final class DashboardAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';

    public $css = [
        'css/dashboard.css',
    ];

    public $js = [
        'js/dashboard.js',
    ];

    public $depends = [
        YiiAsset::class,
    ];
}
