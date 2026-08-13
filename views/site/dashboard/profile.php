<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

/** @var app\models\entities\User $admin */
$admin = Yii::$app->user->identity;
$initials = mb_strtoupper(mb_substr((string) $admin->username, 0, 2));
$this->title = 'Профіль · TZ43';
?>
<section id="page-content">
    <header class="page-head">
        <div class="page-head__main">
            <div class="page-kicker">Account / profile</div>
            <h1 class="page-title">Профіль</h1>
            <p class="page-subtitle">Дані поточного облікового запису адміністратора.</p>
        </div>
    </header>

    <section class="card card--accent profile-card">
        <div class="entity-avatar"><?= Html::encode($initials) ?></div>
        <div class="profile-card__body">
            <div class="page-kicker">Administrator account</div>
            <h2 class="entity-name"><?= Html::encode($admin->username) ?></h2>
            <p class="entity-email"><?= Html::encode($admin->email) ?></p>
            <div><?= Html::tag('span', Html::encode($admin->status), ['class' => 'status status--' . $admin->status]) ?></div>
        </div>
    </section>
</section>
