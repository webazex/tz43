<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\DashboardAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

DashboardAsset::register($this);

/**
 * Layout є єдиним server-rendered shell адміністративної панелі.
 * Сторінкові views містять тільки робочий content, а sidebar/topbar
 * не дублюються між клієнтами, замовленнями та профілем.
 */
$actionId = Yii::$app->controller->action->id;
$page = match ($actionId) {
    'profile' => 'profile',
    'clients' => 'clients',
    'client' => 'client',
    'orders' => 'orders',
    'order-create' => 'order-create',
    default => 'clients',
};

$pageMeta = [
    'profile' => ['Профіль', 'Особисті дані адміністратора'],
    'clients' => ['Клієнти', 'Список та операції з клієнтами'],
    'client' => ['Профіль клієнта', 'Баланс, статус та замовлення'],
    'orders' => ['Замовлення', 'Загальний список і фільтрація'],
    'order-create' => ['Створити замовлення', 'Новий клієнтський order'],
];

[$pageTitle, $pageSubtitle] = $pageMeta[$page];
$ordersOpen = in_array($page, ['orders', 'order-create'], true);
$clientsActive = in_array($page, ['clients', 'client'], true);

/** @var app\models\entities\User $admin */
$admin = Yii::$app->user->identity;
$adminUsername = (string) $admin->username;
$adminEmail = (string) $admin->email;
$adminInitials = mb_strtoupper(mb_substr($adminUsername, 0, 2));

$this->title = $this->title ?: $pageTitle . ' · TZ43';
?>
<?php $this->beginPage() ?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#05070b">
    <meta name="color-scheme" content="dark">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <script>window.TZ43_CONFIG = <?= Json::htmlEncode(['baseUrl' => Yii::$app->request->baseUrl]) ?>;</script>
    <?php $this->head() ?>
</head>
<body data-page="<?= Html::encode($page) ?>">
<?php $this->beginBody() ?>

<div class="app-shell">
    <aside class="sidebar" aria-label="Основна навігація">
        <div class="sidebar__brand">
            <img
                class="sidebar__logo"
                src="<?= Url::to('@web/images/dashboard/logo-02-transparent.png') ?>"
                alt="AL"
            >
            <div class="sidebar__brand-meta">
                <strong>TZ ShNet</strong>
                <span>Yii2 admin panel</span>
            </div>
        </div>

        <nav class="sidebar__nav">
            <div class="nav-label">Навігація</div>

            <a
                href="<?= Url::to(['site/profile']) ?>"
                class="nav-item<?= $page === 'profile' ? ' is-active' : '' ?>"
                data-tooltip="Профіль"
            >
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.5"/>
                    <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/>
                </svg>
                <span class="nav-item__text">Профіль</span>
            </a>

            <a
                href="<?= Url::to(['site/clients']) ?>"
                class="nav-item<?= $clientsActive ? ' is-active' : '' ?>"
                data-tooltip="Клієнти"
            >
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 20v-1.5A4.5 4.5 0 0 0 11.5 14h-4A4.5 4.5 0 0 0 3 18.5V20"/>
                    <circle cx="9.5" cy="7.5" r="3.5"/>
                    <path d="M17 11a3 3 0 1 0-2.2-5M18 14c2 .5 3 2 3 4v2"/>
                </svg>
                <span class="nav-item__text">Клієнти</span>
            </a>

            <div class="nav-group<?= $ordersOpen ? ' is-open' : '' ?>">
                <button
                    type="button"
                    class="nav-item<?= $ordersOpen ? ' is-active' : '' ?>"
                    data-nav-group="orders"
                    data-tooltip="Замовлення"
                >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 3h12v18H6z"/>
                        <path d="M9 7h6M9 11h6M9 15h4"/>
                    </svg>
                    <span class="nav-item__text">Замовлення</span>
                    <svg class="icon nav-item__chevron" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"/>
                    </svg>
                </button>

                <div class="nav-submenu">
                    <a
                        href="<?= Url::to(['site/orders']) ?>"
                        class="nav-subitem<?= $page === 'orders' ? ' is-active' : '' ?>"
                    >
                        <span class="nav-subitem__text">Список замовлень</span>
                    </a>
                    <a
                        href="<?= Url::to(['site/order-create']) ?>"
                        class="nav-subitem<?= $page === 'order-create' ? ' is-active' : '' ?>"
                    >
                        <span class="nav-subitem__text">Створити замовлення</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar__user">
                <div class="avatar"><?= Html::encode($adminInitials) ?></div>
                <div class="sidebar__user-meta">
                    <strong><?= Html::encode($adminUsername) ?></strong>
                    <span><?= Html::encode($adminEmail) ?></span>
                </div>
            </div>

            <form class="nav-form" method="post" action="<?= Url::to(['site/logout']) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <button class="nav-item nav-item--button" type="submit" data-tooltip="Вийти">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/>
                    </svg>
                    <span class="nav-item__text">Вийти</span>
                </button>
            </form>
        </div>
    </aside>

    <header class="topbar">
        <div class="topbar__left">
            <button class="btn btn--icon mobile-menu-btn" type="button" aria-label="Відкрити меню">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>
            <button class="btn btn--icon desktop-collapse-btn" type="button" aria-label="Згорнути sidebar">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </button>
            <div class="topbar__title">
                <strong><?= Html::encode($pageTitle) ?></strong>
                <span><?= Html::encode($pageSubtitle) ?></span>
            </div>
        </div>

        <div class="topbar__right">
            <span class="topbar-context muted mono">session / admin</span>
            <form class="topbar-logout-form" method="post" action="<?= Url::to(['site/logout']) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <button class="btn btn--ghost" type="submit">Вийти</button>
            </form>
        </div>
    </header>

    <main class="app-main" role="main">
        <div class="app-content">
            <?= $content ?>
        </div>
    </main>

    <div class="mobile-backdrop" aria-hidden="true"></div>
</div>

<div id="modal-layer" class="modal-layer" aria-hidden="true"></div>
<div id="toast-host" class="toast-host" aria-live="polite"></div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
