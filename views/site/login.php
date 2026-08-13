<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\forms\auth\LoginForm $model */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Вхід · TZ43';
?>
<main class="auth-card">
    <img
        class="auth-logo"
        src="<?= Url::to('@web/images/dashboard/logo-02-transparent.png') ?>"
        alt="AL"
    >
    <p class="auth-eyebrow">TZ ShNet / Admin</p>
    <h1 class="auth-title">Авторизація</h1>
    <p class="auth-subtitle">Внутрішня панель керування клієнтами та замовленнями.</p>

    <?= Html::beginForm(['site/login'], 'post', ['id' => 'login-form', 'class' => 'stack', 'novalidate' => true]) ?>
        <div class="field-group<?= $model->hasErrors('username') ? ' has-error' : '' ?>">
            <?= Html::activeLabel($model, 'username', ['class' => 'field-label', 'label' => 'Логін']) ?>
            <?= Html::activeTextInput($model, 'username', [
                'class' => 'input',
                'id' => 'login',
                'autocomplete' => 'username',
                'placeholder' => 'webazex',
                'autofocus' => true,
            ]) ?>
            <?= Html::error($model, 'username', ['class' => 'field-error']) ?>
        </div>

        <div class="field-group<?= $model->hasErrors('password') ? ' has-error' : '' ?>">
            <?= Html::activeLabel($model, 'password', ['class' => 'field-label', 'label' => 'Пароль']) ?>
            <?= Html::activePasswordInput($model, 'password', [
                'class' => 'input',
                'id' => 'password',
                'autocomplete' => 'current-password',
                'placeholder' => '••••••••',
            ]) ?>
            <?= Html::error($model, 'password', ['class' => 'field-error']) ?>
        </div>

        <button class="btn btn--primary btn--wide" type="submit">Увійти</button>
    <?= Html::endForm() ?>
</main>
