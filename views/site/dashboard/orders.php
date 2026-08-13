<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Url;

$this->title = 'Замовлення · TZ43';
?>
<section id="page-content">
    <header class="page-head">
        <div class="page-head__main">
            <div class="page-kicker">Orders / list</div>
            <h1 class="page-title">Замовлення</h1>
            <p class="page-subtitle">Серверна фільтрація, пагінація та доступні дії над замовленнями.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn--primary" href="<?= Url::to(['site/order-create']) ?>">Створити замовлення</a>
        </div>
    </header>

    <div class="toolbar orders-filter-toolbar" aria-label="Фільтри замовлень">
        <div class="toolbar__group orders-filter-row">
            <select class="select orders-status-select" id="orders-status-filter" aria-label="Статус">
                <option value="">Усі статуси</option>
                <option value="pending">pending</option>
                <option value="paid">paid</option>
                <option value="canceled">canceled</option>
            </select>
            <select class="select orders-client-select" id="orders-client-filter" aria-label="Клієнт"></select>
            <button class="btn btn--ghost orders-filter-clear" id="orders-filter-clear" type="button">Скинути</button>
        </div>
    </div>

    <div class="table-wrap" id="orders-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created at</th>
                    <th style="text-align:right">Дії</th>
                </tr>
            </thead>
            <tbody id="orders-body"></tbody>
        </table>
    </div>

    <div class="card empty-state" id="orders-empty" style="display:none">
        <div>
            <div class="empty-state__icon">∅</div>
            <h3>Немає замовлень</h3>
            <p>Спробуйте інші фільтри або створіть нове замовлення.</p>
        </div>
    </div>

    <div class="pagination-bar">
        <span id="orders-count">—</span>
        <div class="pagination" id="orders-pagination" aria-label="Пагінація"></div>
    </div>
</section>
