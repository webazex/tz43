<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var int $clientId */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Профіль клієнта · TZ43';
?>
<section id="page-content" data-client-id="<?= Html::encode((string) $clientId) ?>">
    <div id="client-page-content">
        <header class="page-head">
            <div class="page-head__main">
                <div class="page-kicker">Clients / details</div>
                <h1 class="page-title">Профіль клієнта</h1>
                <p class="page-subtitle">Дані клієнта, баланс та його замовлення.</p>
            </div>
            <div class="page-actions">
                <a class="btn btn--ghost" href="<?= Url::to(['site/clients']) ?>">До списку</a>
            </div>
        </header>

        <div class="entity-layout">
            <aside class="card card--accent entity-card">
                <div class="entity-avatar" id="client-initials">—</div>
                <h2 class="entity-name" id="client-name">—</h2>
                <p class="entity-email" id="client-email">—</p>
                <div id="client-status"></div>

                <div class="entity-kv">
                    <div class="entity-kv__row">
                        <span>ID</span>
                        <strong class="mono" id="client-id">—</strong>
                    </div>
                    <div class="entity-kv__row">
                        <span>Баланс</span>
                        <strong class="balance-value" id="client-balance">—</strong>
                    </div>
                </div>

                <div class="entity-actions">
                    <button class="btn btn--primary" id="client-topup" type="button">Поповнити</button>
                    <button class="btn" id="client-edit" type="button">Редагувати</button>
                </div>
            </aside>

            <div class="stack">
                <section class="card">
                    <header class="card__head">
                        <h2>Створити замовлення</h2>
                    </header>
                    <div class="card__body">
                        <form id="client-order-form" class="form-grid" novalidate>
                            <div class="field-group">
                                <label class="field-label" for="client-order-amount">Сума</label>
                                <input
                                    class="input"
                                    id="client-order-amount"
                                    name="amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="50.00"
                                >
                                <div class="field-error"></div>
                            </div>
                            <div class="field-group field-group--full">
                                <label class="field-label" for="client-order-description">Опис</label>
                                <textarea
                                    class="textarea"
                                    id="client-order-description"
                                    name="description"
                                    placeholder="Оплата послуг"
                                ></textarea>
                                <div class="field-error"></div>
                            </div>
                            <div class="field-group field-group--full">
                                <button class="btn btn--primary" type="submit">Створити замовлення</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="card">
                    <header class="card__head">
                        <h2>Замовлення клієнта</h2>
                    </header>
                    <div class="table-wrap" id="client-orders-wrap" style="border:0;border-radius:0">
                        <table class="data-table" style="min-width:620px">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created at</th>
                                </tr>
                            </thead>
                            <tbody id="client-orders-body"></tbody>
                        </table>
                    </div>
                    <div class="empty-state" id="client-orders-empty" style="display:none;min-height:150px">
                        <div>
                            <h3>Замовлень немає</h3>
                            <p>Створіть перше замовлення у формі вище.</p>
                        </div>
                    </div>
                    <div class="pagination-bar client-orders-pagination-bar" style="display:none">
                        <span id="client-orders-count">—</span>
                        <div class="pagination" id="client-orders-pagination" aria-label="Пагінація"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
