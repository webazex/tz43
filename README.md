# TZ43 — Clients & Orders Management Service

Тестовий проєкт на Yii2 Framework 2.0 для керування клієнтами, балансами та замовленнями.

Проєкт реалізує REST API та server-rendered адміністративну панель для основних бізнес-сценаріїв:

- створення та перегляд клієнтів;
- редагування нефінансових даних клієнта;
- пошук та фільтрація клієнтів;
- створення замовлень;
- автоматичне списання коштів за наявності достатнього балансу;
- створення `pending`-замовлень при недостатньому балансі;
- поповнення балансу;
- асинхронна обробка `pending`-замовлень через `yii2-queue`;
- скасування `pending`-замовлень;
- web-dashboard для адміністратора;
- Unit та Functional tests на Codeception.

---

## Технології

- PHP 8.2+
- Yii2 Framework 2.0
- Yii2 Active Record
- Yii2 Basic Application + окремий API module
- yii2-queue
- MySQL / MariaDB
- Codeception 5
- HTML
- CSS
- jQuery

---

# Встановлення та запуск

## 1. Клонування репозиторію

```bash
git clone https://github.com/webazex/tz43.git
cd tz43
```

---

## 2. Встановлення залежностей

```bash
composer install
```

Під час першого `composer install` файл:

```text
config/local/web.example.php
```

автоматично копіюється в:

```text
config/local/web.php
```

Після цього Composer генерує унікальний `cookieValidationKey`.

Вже наявний локальний `config/local/web.php` повторно не перезаписується.

---

## 3. Створення бази даних

Створіть окрему MySQL / MariaDB database для застосунку.

Наприклад:

```sql
CREATE DATABASE test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Скопіюйте приклад локальної конфігурації:

```bash
cp config/local/db.example.php config/local/db.php
```

Відредагуйте:

```text
config/local/db.php
```

Приклад:

```php
<?php

return [
    'dsn' => 'mysql:host=localhost;dbname=tz43',
    'username' => 'test',
    'password' => 'change-me',
];
```

`config/local/db.php` містить environment-specific credentials та не повинен потрапляти до Git.

---

## 4. Налаштування REST API token

Зовнішні REST API-запити можуть авторизуватися через Bearer token.

Створіть локальний params-файл:

```bash
cp config/local/params.example.php config/local/params.php
```

Приклад:

```php
<?php

declare(strict_types=1);

return [
    'api' => [
        'accessTokens' => [
            'postman' => 'CHANGE_ME',
        ],
    ],
];
```

Для зовнішнього API-клієнта передавайте token:

```http
Authorization: Bearer CHANGE_ME
Accept: application/json
```

Адміністративний dashboard не зберігає API token у JavaScript.

Після авторизації через web-форму dashboard використовує штатну Yii session authentication та CSRF protection.

---

## 5. Початкове налаштування

Проєкт містить єдину setup-команду:

```bash
php yii init/setup
```

Команда послідовно:

1. застосовує application migrations;
2. застосовує офіційні DB migrations `yii2-queue`;
3. запускає інтерактивне створення адміністратора.

Під час створення адміністратора потрібно ввести:

- login;
- email;
- password.

Setup навмисно є інтерактивним, оскільки пароль адміністратора не повинен бути захардкоджений у deployment-скриптах.

За потреби міграції можна виконати окремо:

```bash
php yii migrate --interactive=0
```

---

## 6. Запуск web application

Document Root web-сервера повинен вказувати на:

```text
/path/to/tz43/web
```

Застосунок використовує Yii Pretty URLs, тому запити до неіснуючих фізичних файлів повинні передаватися в:

```text
web/index.php
```

Для локальної розробки можна використати вбудований Yii server:

```bash
php yii serve --port=8080
```

Після запуску:

```text
http://localhost:8080/
```

відкриє форму авторизації.

---

# Демонстраційний стенд

Поточний тестовий стенд:

```text
https://tz42.latul.space/
```

Демонстраційний обліковий запис адміністратора:

```text
Login:    webazex
Password: Alohomora*001
```

Credentials наведені в README навмисно: це окремий тестовий стенд тестового завдання, а не production credentials.

Persistent `remember me` для адміністративної панелі не використовується. Авторизація діє в межах Yii session.

---

# Business rules

## Створення замовлення

При створенні замовлення сервер сам визначає його статус.

Якщо баланс клієнта достатній:

```text
balance >= order amount
```

кошти списуються одразу, а замовлення отримує статус:

```text
paid
```

Якщо коштів недостатньо:

```text
balance < order amount
```

баланс не змінюється, а замовлення створюється зі статусом:

```text
pending
```

Клієнт зі статусом:

```text
blocked
```

не може створювати нові замовлення.

При цьому blocked-клієнту не заборонено поповнювати баланс або оплачувати вже створені раніше pending-замовлення.

---

## Поповнення балансу

Поповнення виконується через:

```http
POST /clients/{id}/topup
```

Синхронна частина операції:

1. блокує фінансовий стан клієнта;
2. збільшує баланс;
3. переводить pending-processing lifecycle у стан обробки;
4. ставить `ProcessPendingOrdersJob` у DB Queue;
5. повертає HTTP `202 Accepted`.

Відповідь містить баланс **одразу після top-up**, але до гарантованого завершення Queue Job.

Фінальний баланс потрібно отримувати окремим:

```http
GET /clients/{id}
```

---

## Обробка pending orders

Pending orders обробляються Queue Job у порядку:

```text
created_at ASC, id ASC
```

Використовується **нестрогий FIFO**.

Якщо для конкретного старішого order поточного балансу недостатньо, він залишається `pending`, але processor продовжує перевіряти наступні orders.

Наприклад:

```text
balance = 25

orders:
30
10
```

Результат:

```text
30 → pending
10 → paid
balance → 15
```

Такий варіант дозволяє не блокувати оплату наступного доступного замовлення одним великим order.

Обробка виконується транзакційно та використовує row-level locking клієнта, щоб паралельні фінансові операції одного клієнта не змінювали баланс одночасно.

---

## Скасування замовлення

Скасувати можна тільки order зі статусом:

```text
pending
```

Спроба скасувати вже `paid` або `canceled` order повертає HTTP `409 Conflict`.

---

# REST API

API навмисно залишається без `/api/v1` prefix у межах поточного тестового завдання.

Основні routes:

| Method | Endpoint | Призначення |
|---|---|---|
| GET | `/clients` | Список клієнтів |
| GET | `/clients/search` | Пошук / фільтрація клієнтів |
| GET | `/clients/{id}` | Один клієнт |
| POST | `/clients` | Створення клієнта |
| PATCH | `/clients/{id}` | Редагування клієнта |
| POST | `/clients/{id}/topup` | Поповнення балансу |
| GET | `/orders` | Список замовлень |
| GET | `/orders/{id}` | Одне замовлення |
| POST | `/orders` | Створення замовлення |
| POST | `/orders/{id}/cancel` | Скасування pending order |

---

# Формат API response

Для application responses використовується єдиний envelope.

Success:

```json
{
    "success": true,
    "data": {},
    "error": null
}
```

Failure:

```json
{
    "success": false,
    "data": null,
    "error": {
        "code": "SOME_ERROR",
        "details": {}
    }
}
```

HTTP status та application error code використовуються разом.

Типові status codes:

```text
200 OK
201 Created
202 Accepted
401 Unauthorized
404 Not Found
409 Conflict
422 Unprocessable Entity
500 Internal Server Error
```

---

# Приклади API-запитів

У прикладах:

```text
BASE_URL=https://tz42.latul.space
API_TOKEN=<your-token>
```

## Створити клієнта

```bash
curl -X POST "$BASE_URL/clients" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Test Client",
        "email": "client@example.com",
        "balance": "100.00",
        "status": "active"
    }'
```

`balance` необов'язковий та за замовчуванням дорівнює:

```text
0.00
```

`status` необов'язковий та за замовчуванням:

```text
active
```

Приклад успішної відповіді:

```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Test Client",
        "email": "client@example.com",
        "balance": "100.00",
        "status": "active"
    },
    "error": null
}
```

HTTP status:

```text
201 Created
```

---

## Отримати клієнта

```bash
curl "$BASE_URL/clients/1" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json"
```

---

## Список клієнтів

```bash
curl "$BASE_URL/clients?page=1&per-page=20" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json"
```

Відповідь містить:

```json
{
    "success": true,
    "data": {
        "items": [],
        "pagination": {
            "page": 1,
            "perPage": 20,
            "pageCount": 0,
            "totalCount": 0
        }
    },
    "error": null
}
```

Pagination metadata також передаються HTTP headers:

```text
X-Pagination-Current-Page
X-Pagination-Per-Page
X-Pagination-Page-Count
X-Pagination-Total-Count
```

---

## Пошук клієнтів

```bash
curl "$BASE_URL/clients/search?field=name&value=Test&like=1&relation=off&page=1&per-page=20" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json"
```

Dashboard також використовує цей endpoint для server-side search, filters та balance sorting.

---

## Редагувати клієнта

Фінансовий balance через edit endpoint не змінюється.

```bash
curl -X PATCH "$BASE_URL/clients/1" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Updated Client",
        "email": "updated@example.com",
        "status": "active"
    }'
```

Balance змінюється тільки через окремий top-up use case.

---

## Створити замовлення

`amount` передається decimal-string, щоб transport layer не вносив FLOAT-похибку.

```bash
curl -X POST "$BASE_URL/orders" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
        "client_id": 1,
        "amount": "50.00",
        "description": "Оплата послуг"
    }'
```

Статус не передається клієнтом API.

Його визначає backend:

```text
paid | pending
```

Приклад:

```json
{
    "success": true,
    "data": {
        "id": 1,
        "clientId": 1,
        "amount": "50.00",
        "description": "Оплата послуг",
        "status": "paid",
        "createdAt": 1780000000
    },
    "error": null
}
```

HTTP status:

```text
201 Created
```

---

## Список замовлень

```bash
curl "$BASE_URL/orders?page=1&per-page=20" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json"
```

Доступна фільтрація:

```text
status
client_id
page
per-page
```

Наприклад:

```bash
curl "$BASE_URL/orders?client_id=1&status=pending&page=1&per-page=20" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json"
```

---

## Поповнити баланс

```bash
curl -X POST "$BASE_URL/clients/1/topup" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
        "amount": "100.00"
    }'
```

Приклад response:

```json
{
    "success": true,
    "data": {
        "creditedAmount": "100.00",
        "oldBalance": "25.00",
        "balanceAfterTopUp": "125.00"
    },
    "error": null
}
```

HTTP status:

```text
202 Accepted
```

`balanceAfterTopUp` не є гарантованим фінальним балансом після асинхронної оплати pending orders.

---

## Скасувати замовлення

```bash
curl -X POST "$BASE_URL/orders/1/cancel" \
    -H "Authorization: Bearer $API_TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{}'
```

Скасування допустиме тільки для `pending` order.

---

# Queue

Проєкт використовує:

```text
yii2-queue
```

з DB driver.

Queue table створюється офіційними migrations пакета `yii2-queue`, які підключені до стандартної application migration-команди.

Для одноразової обробки поточної черги:

```bash
php yii queue/run
```

Для постійного worker:

```bash
php yii queue/listen
```

У production-подібному середовищі `queue/listen` потрібно запускати через process manager, наприклад:

```text
systemd
Supervisor
```

Queue Job не містить самостійної фінансової бізнес-логіки.

Він передає `clientId` у application processor, який виконує транзакцію, row locking та обробку pending orders.

---

# Тести

Проєкт містить Unit та Functional tests на Codeception.

Найбільш важливі business scenarios перевіряються Functional suite через реальні:

```text
routes
controllers
Form Models
Service Layer
database
DB Queue
```

без виклику REST controller methods напряму.

## Налаштування test database

Створіть окрему БД:

```sql
CREATE DATABASE db_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

За потреби створіть локальну конфігурацію:

```bash
cp config/local/test_db.example.php config/local/test_db.php
```

За замовчуванням test database:

```text
tz43_test
```

а username/password успадковуються з основного:

```text
config/local/db.php
```

Застосуйте migrations до test DB:

```bash
php tests/Support/bin/yii migrate --interactive=0
```

---

## Unit tests

```bash
vendor/bin/codecept run Unit
```

---

## Functional tests

```bash
vendor/bin/codecept run Functional
```

Functional tests використовують окремий deterministic API token, визначений тільки в:

```text
config/test.php
```

та не залежать від production/local API tokens.

---

## Додаткові quality checks

PHPStan:

```bash
composer static
```

PHP CodeSniffer:

```bash
composer cs
```

Автоматичне виправлення допустимих code-style помилок:

```bash
composer cs-fix
```

---

# Структура проєкту

Основні каталоги:

```text
assets/
    AppAsset.php
    DashboardAsset.php

commands/
    init/
        DefaultUserController.php
        SetupController.php

config/
    local/
        db.example.php
        params.example.php
        test_db.example.php
        web.example.php

    console.php
    db.php
    di.php
    params.php
    queue.php
    rules.php
    test.php
    test_db.php
    web.php

contracts/
    results/
        OperationError.php
        OperationResult.php
        TopUpResult.php

controllers/
    SiteController.php

jobs/
    ProcessPendingOrdersJob.php

migrations/
    application migrations

models/
    entities/
        Client.php
        Order.php
        User.php
        enums/

    forms/
        auth/
        client/
        order/

    valueObjects/
        Money.php

modules/
    api/
        controllers/
            ApiController.php
            ClientController.php
            OrderController.php

        security/
            ApiTokenAuthenticator.php

resources/
    ClientResource.php
    OrderResource.php

responses/
    OperationResponse.php

services/
    ClientService.php
    OrderService.php
    PendingOrdersProcessor.php

tests/
    Functional/
    Unit/
    Support/

views/
    layouts/
        dashboard/

    site/
        dashboard/

web/
    css/
    images/
    js/
    index.php
```

---

# Архітектурний підхід

Проєкт навмисно не перетворювався на велику багатошарову систему.

Для тестового завдання використано мінімальний набір abstractions, які мають конкретну відповідальність.

## Controller

REST controller відповідає за:

```text
HTTP request
↓
Form Model
↓
Service
↓
OperationResult
↓
HTTP response
```

Controller не містить фінансової business logic.

---

## Form Models

Окремі Form Models визначають дозволений transport input та виконують validation.

Наприклад:

```text
CreateClientForm
UpdateClientForm
TopUpClientForm
SearchClientsForm
CreateOrderForm
ListOrdersForm
```

HTTP input не завантажується напряму у persistence entities.

---

## Service Layer

Основні application use cases знаходяться у:

```text
ClientService
OrderService
```

Service Layer відповідає за:

- orchestration business operation;
- transaction boundaries;
- persistence;
- financial consistency;
- application errors.

---

## Resources

REST representation відокремлена від ActiveRecord через:

```text
ClientResource
OrderResource
```

Внутрішні поля entities не стають частиною API автоматично.

---

## OperationResult

Service Layer не повертає HTTP Response та не визначає HTTP status.

Результат use case передається через:

```text
OperationResult
OperationError
```

а mapping:

```text
application result → HTTP status / JSON
```

залишається відповідальністю controller layer.

---

## Money

Грошові значення не обчислюються через PHP `float`.

Для фінансової arithmetic використовується:

```text
Money
```

REST API також очікує decimal values як strings:

```json
{
    "amount": "100.00"
}
```

---

# Web dashboard

Frontend реалізований без SPA framework:

```text
Yii server-rendered views
HTML
CSS
jQuery
```

Dashboard працює поверх того самого application API.

Browser authentication:

```text
Yii session
+
CSRF
```

API token не зберігається в:

```text
localStorage
sessionStorage
JavaScript constants
```

Основні dashboard pages:

```text
/dashboard/profile
/dashboard/clients
/dashboard/clients/{id}
/dashboard/orders
/dashboard/orders/create
```

---

# Важливо для deployment: ModSecurity / OWASP CRS та HTTP 406

Під час перевірки demo-середовища:

```text
Nginx
+
ModSecurity 3
+
OWASP Core Rule Set 4
```

було виявлено два environment-specific сценарії, які можуть повертати:

```text
406 Not Acceptable
```

ще до передачі request у Yii.

Це не application response.

---

## 1. PATCH та CRS rule 911100

Редагування клієнта реалізовано через REST endpoint:

```http
PATCH /clients/{id}
```

У базовій конфігурації OWASP CRS на сервері список дозволених methods був:

```text
GET HEAD POST OPTIONS
```

Через це `PATCH` блокувався:

```text
ruleId: 911100
message: Method is not allowed by policy
```

Request не доходив до Yii controller.

Правильне рішення для REST-середовища — не вимикати `911100`, а додати потрібний HTTP method до WAF policy:

```apache
SecAction \
    "id:900200,\
    phase:1,\
    pass,\
    t:none,\
    nolog,\
    setvar:'tx.allowed_methods=GET HEAD POST OPTIONS PATCH'"
```

На demo server це зроблено як persistent DirectAdmin CustomBuild override.

Таким чином:

```text
PATCH
```

дозволений WAF, але конкретні Yii routes усе одно контролюються application-level HTTP verb policy.

Наприклад, дозвіл PATCH у ModSecurity не створює автоматично PATCH endpoints у Yii.

На поточному сервері додаткові methods:

```text
PUT
DELETE
```

глобально не дозволялися, оскільки application contract їх не потребує.

---

## 2. Yii persistent identity cookie та CRS rule 942550

Під час початкової реалізації Yii `remember me` створював persistent:

```text
_identity
```

cookie.

Його штатний Yii payload був помилково визначений OWASP CRS як:

```text
ruleId: 942550
JSON-Based SQL Injection
```

Це був WAF false positive.

Persistent login не входить до вимог тестового завдання, тому application було переведено на session-only authentication:

```text
enableAutoLogin = false
```

та `rememberMe` було видалено з LoginForm/UI.

Спеціальний WAF exception для `_identity` навмисно не створювався.

Якщо persistent `remember me` буде потрібний у іншому deployment, для нього потрібно окремо проаналізувати WAF policy та створити максимально вузьке exception, а не вимикати SQLi rules глобально.

---

# Осознані обмеження

Проєкт є тестовим завданням, тому навмисно не реалізовувалися компоненти, які не потрібні основному сценарію.

Зокрема, зараз відсутні:

- Redis balance cache;
- webhook при переході order у `paid`;
- повний Idempotency-Key layer;
- окрема event architecture;
- RBAC з декількома admin roles;
- Docker infrastructure;
- Kubernetes;
- горизонтальне масштабування;
- frontend SPA;
- складний analytics dashboard.

Ці можливості можуть бути додані окремими use cases без 
необхідності переписувати базовий flow клієнтів, 
balance та orders.

---

# Що можна розвивати далі

Як наступні етапи логічно розглядати:

1. `Idempotency-Key` для write operations;
2. webhook `OrderPaid`;
3. Redis caching для balance;
4. domain/application events:
    - `OrderPaid`;
    - `BalanceUpdated`;
    - `PendingOrdersProcessed`;
5. integration/load tests з конкурентними запитами;
6. окремий OpenAPI contract;
7. containerized development environment.

Ці задачі не додавалися в основну реалізацію лише 
заради демонстрації abstractions, оскільки вони не
потрібні для завершення основного business scenario 
тестового завдання.
