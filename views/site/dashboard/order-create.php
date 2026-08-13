<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'Створити замовлення · TZ43';
?>
<section id="page-content">
    <header class="page-head">
        <div class="page-head__main">
            <div class="page-kicker">Orders / create</div>
            <h1 class="page-title">Створити замовлення</h1>
            <p class="page-subtitle">Оберіть клієнта, вкажіть суму та опис замовлення.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn--ghost" href="<?= Url::to(['site/orders']) ?>">До списку</a>
        </div>
    </header>

    <div class="grid grid--2">
        <section class="card card--accent">
            <header class="card__head">
                <h2>Нове замовлення</h2>
            </header>
            <div class="card__body">
                <form id="order-create-form" class="form-grid form-grid--1" novalidate>
                    <div class="field-group">
                        <label class="field-label" for="order-client">Клієнт</label>
                        <select class="select client-search-select" id="order-client" name="client_id"></select>
                        <div class="field-error"></div>
                    </div>

                    <div id="order-client-hint" class="notice">Після вибору клієнта тут буде показано його баланс і статус.</div>

                    <div class="field-group">
                        <label class="field-label" for="order-amount">Сума</label>
                        <input class="input" id="order-amount" name="amount" type="number" step="0.01" min="0.01" placeholder="50.00">
                        <div class="field-error"></div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="order-description">Опис</label>
                        <textarea class="textarea" id="order-description" name="description" placeholder="Оплата послуг"></textarea>
                        <div class="field-error"></div>
                    </div>

                    <div class="inline">
                        <button class="btn btn--primary" type="submit">Створити</button>
                        <span class="muted" style="font-size:10px">paid / pending визначає backend</span>
                    </div>
                </form>
            </div>
        </section>

        <aside class="card">
            <header class="card__head">
                <h2>Business rule</h2>
                <span class="muted mono">server-side</span>
            </header>
            <div class="card__body stack">
                <div class="notice">
                    Якщо balance ≥ amount — кошти списуються одразу, order отримує статус <strong>paid</strong>.
                </div>
                <div class="notice notice--warning">
                    Якщо balance &lt; amount — balance не змінюється, order створюється як <strong>pending</strong>.
                </div>
                <div class="notice notice--danger">
                    Blocked client не може створювати нове замовлення. Остаточна перевірка лишається на backend.
                </div>
            </div>
        </aside>
    </div>

    <section class="result-panel" id="order-result" aria-live="polite">
        <div class="inline" style="justify-content:space-between;margin-bottom:10px">
            <strong>Результат операції</strong>
        </div>
        <div class="result-panel__grid">
            <div class="result-item"><span>ID</span><strong id="result-id">—</strong></div>
            <div class="result-item"><span>Клієнт</span><strong id="result-client">—</strong></div>
            <div class="result-item"><span>Сума</span><strong id="result-amount">—</strong></div>
            <div class="result-item"><span>Статус</span><strong id="result-status">—</strong></div>
        </div>
        <p class="muted" id="order-result-copy" style="margin:10px 0 0;font-size:10px"></p>
    </section>
</section>
