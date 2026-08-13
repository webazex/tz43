<?php

declare(strict_types=1);

/** @var yii\web\View $this */

$this->title = 'Клієнти · TZ43';
?>
<section id="page-content">
    <header class="page-head">
        <div class="page-head__main">
            <div class="page-kicker">Clients / list</div>
            <h1 class="page-title">Клієнти</h1>
            <p class="page-subtitle">Пошук, фільтрація, пагінація та робота з клієнтами.</p>
        </div>
        <div class="page-actions">
            <button class="btn btn--primary" id="create-client" type="button">Створити клієнта</button>
        </div>
    </header>

    <div class="toolbar clients-filter-toolbar" aria-label="Фільтри клієнтів">
        <div class="toolbar__group clients-filter-row">
            <select class="select clients-filter-field" id="client-search-field" aria-label="Поле пошуку">
                <option value="name">Ім’я</option>
                <option value="email">Email</option>
            </select>

            <input
                class="input search-input clients-filter-value"
                id="client-search-value"
                type="search"
                placeholder="Введіть значення…"
                autocomplete="off"
            >

            <fieldset class="logic-switch" aria-label="Логічна зв’язка між текстовою умовою та статусом">
                <legend class="sr-only">Логіка поєднання фільтрів</legend>
                <label class="logic-switch__option">
                    <input type="radio" name="client-filter-logic" value="and">
                    <span>І</span>
                </label>
                <label class="logic-switch__option">
                    <input type="radio" name="client-filter-logic" value="or">
                    <span>АБО</span>
                </label>
                <label class="logic-switch__option">
                    <input type="radio" name="client-filter-logic" value="off" checked>
                    <span title="Не додавати AND/OR між текстовою умовою та статусом">Неактивно</span>
                </label>
            </fieldset>

            <div class="status-filter">
                <label class="status-filter__label" for="client-status-filter">Статус</label>
                <select class="select status-filter__select" id="client-status-filter">
                    <option value="">Усі</option>
                    <option value="active">active</option>
                    <option value="blocked">blocked</option>
                </select>
            </div>
        </div>

        <div class="toolbar__group toolbar__group--actions">
            <button class="btn btn--ghost" id="client-search-clear" type="button">Очистити</button>
        </div>
    </div>

    <div class="table-wrap" id="clients-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ім’я</th>
                    <th>Email</th>
                    <th class="sortable-th" id="client-balance-sort-cell" aria-sort="descending">
                        <button
                            class="table-sort-button"
                            id="client-balance-sort"
                            type="button"
                            data-direction="desc"
                            title="Змінити напрям сортування балансу"
                        >
                            <span>Баланс</span>
                            <span class="sort-indicator" aria-hidden="true">↓</span>
                        </button>
                    </th>
                    <th>Статус</th>
                    <th style="text-align:right">Дії</th>
                </tr>
            </thead>
            <tbody id="clients-body"></tbody>
        </table>
    </div>

    <div class="card empty-state" id="clients-empty" style="display:none">
        <div>
            <div class="empty-state__icon">∅</div>
            <h3>Нічого не знайдено</h3>
            <p>Змініть поле, значення, логіку поєднання або статус.</p>
        </div>
    </div>

    <div class="pagination-bar">
        <span id="clients-count">—</span>
        <div class="pagination" id="clients-pagination" aria-label="Пагінація"></div>
    </div>
</section>
