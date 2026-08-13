# TZ43 — Clients & Orders Management Service

Тестовий проєкт на Yii2 Framework 2.0 для керування клієнтами, балансами та замовленнями.

Основний сценарій:

- створення та перегляд клієнтів;
- створення замовлень;
- автоматичне списання коштів за наявності достатнього балансу;
- створення `pending`-замовлень при недостатньому балансі;
- поповнення балансу;
- асинхронна обробка pending-замовлень через `yii2-queue`;
- web-dashboard для роботи з клієнтами та замовленнями;
- REST API;
- Codeception Unit та Functional tests.

---

## Технології

- PHP 8.2+
- Yii2 Framework 2.0
- Yii2 Active Record
- yii2-queue
- MySQL / MariaDB
- Codeception 5
- HTML
- CSS
- jQuery

---

# Структура проєкту

Основні каталоги:

```text
commands/
    init/                   Console-команди первинного налаштування

config/
    local/                  Локальні конфігурації середовища
    db.php                  Базова конфігурація БД
    test_db.php             Конфігурація тестової БД
    queue.php               Конфігурація yii2-queue
    di.php                  DI definitions
    rules.php               URL rules
    web.php                 Web application config
    console.php             Console application config

contracts/
    results/                Контракти результатів application use cases

jobs/
                            Queue jobs

migrations/
                            Міграції БД

models/
    entities/               Active Record entities
    forms/                  Form Models / input validation
    valueObjects/           Value Objects

modules/
    api/                    REST API module

processors/
                            Application processors, включно з
                            обробкою pending orders

resources/
                            REST resources / external representations

responses/
                            Єдиний формат application response

services/
                            Service Layer:
                            ClientService
                            OrderService

tests/
    Functional/             Functional/API tests
    Unit/                   Unit tests
    Support/                Fixtures та test helpers

views/
    layouts/dashboard/      Layout адміністративної панелі
    site/dashboard/         Dashboard pages

web/
    css/
    js/
    images/
    index.php               Web entry point


## Демонстраційний доступ до адміністративної панелі

Для перевірки тестового стенду можна використати окремий демонстраційний обліковий запис адміністратора:

Login:    webazex
Password: Alohomora*001

## Важливо для deployment: ModSecurity / OWASP CRS та HTTP 406

Під час перевірки тестового стенду за Nginx + ModSecurity + OWASP Core Rule Set були виявлені два environment-specific
 сценарії, які можуть проявлятися як HTTP `406 Not Acceptable` ще **до передачі запиту в Yii**.

### 1. `PATCH` може блокуватися правилом CRS `911100`

Оновлення клієнта реалізовано через REST endpoint:

PATCH /clients/{id}
