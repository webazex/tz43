# TZ43 — frontend integration overlay

База, относительно которой подготовлены исходники:

```text
repository: webazex/tz43
branch:     main
commit:     0eb53d58f9dab852d1c7649492190aec87ea6be3
```

## Назначение

Каталог является **overlay**, а не отдельным Yii2-проектом. Файлы нужно копировать
в корень актуального checkout `tz43`, сохраняя относительные пути.

Пример из корня локального репозитория:

```bash
rsync -av /path/to/tz43-frontend-integration/ ./ \
  --exclude INTEGRATION-NOTES.md
```

Либо скопировать файлы вручную по приведённым ниже путям.

## Новые файлы

```text
assets/DashboardAsset.php
models/forms/client/UpdateClientForm.php
views/layouts/dashboard/auth.php
views/site/dashboard/profile.php
views/site/dashboard/clients.php
views/site/dashboard/client.php
views/site/dashboard/orders.php
views/site/dashboard/order-create.php
web/css/dashboard.css
web/js/dashboard.js
web/images/dashboard/logo-02.png
web/images/dashboard/logo-02-transparent.png
```

## Изменяемые файлы

```text
config/rules.php
controllers/SiteController.php
models/forms/client/SearchClientsForm.php
modules/api/controllers/ClientController.php
services/ClientService.php
views/layouts/dashboard/main.php
views/site/login.php
```

## Что реализовано

- утверждённая статическая v6 перенесена в Yii server-rendered views;
- dashboard использует отдельный `DashboardAsset`, Bootstrap для него не нужен;
- jQuery берётся из штатного `YiiAsset`, vendored jQuery не переносится;
- login работает через существующий `LoginForm` и Yii session;
- `/dashboard/*` закрыт существующим `AccessControl`;
- session AJAX пишет с Yii CSRF token, Bearer token в браузере не хранится;
- clients list использует серверную пагинацию, поиск, status, AND/OR/off и balance sort;
- создание клиента использует `POST /clients`;
- добавлен узкий `PATCH /clients/{id}` только для `name`, `email`, `status`;
- `balance` не редактируется через PATCH и остаётся отдельным top-up use case;
- client profile использует существующие client/order/top-up endpoints;
- orders list использует серверные `status`/`client_id` фильтры и pagination;
- client в orders table показывается как `Client #ID` без N+1 запросов;
- searchable client select выполняет удалённый lookup по name/email/ID;
- create order не вычисляет paid/pending на frontend;
- cancel доступен в UI только для pending, но окончательная проверка остаётся backend;
- demo/localStorage application state удалён; localStorage оставлен только для sidebar state.

## Минимальное расширение `/clients/search`

Поддержаны query parameters:

```text
field=name|email
value=...
like=0|1
status=active|blocked
relation=and|or|off
balance-sort=asc|desc
page=1
per-page=20
```

`value` может быть пустым только при наличии другого реального критерия (`status`
или `balance-sort`). Голый `/clients/search` без search/filter/sort критерия по-прежнему
не считается валидным text-search.

## Проверки после копирования в рабочий репозиторий

Сначала синтаксис:

```bash
find assets controllers models modules services views -name '*.php' -print0 \
  | xargs -0 -n1 php -l
node --check web/js/dashboard.js
```

Потом существующие тесты:

```bash
vendor/bin/codecept run Unit
vendor/bin/codecept run Functional
```

И ручной smoke под реальным web server:

```text
/login
/dashboard
/dashboard/profile
/dashboard/clients
/dashboard/clients/{id}
/dashboard/orders
/dashboard/orders/create
```

Сценарии smoke:

```text
login
clients list + pagination
client text/status/relation filters + balance sort
create client
edit client (name/email/status only)
client profile
top-up
create order
orders filters
cancel pending order
logout
```

## Что сознательно не добавлялось

```text
/api/v1
Vue/React/Vite
frontend repository/service classes
Redis
webhooks
idempotency/event architecture
новый auth filter
RBAC
polling queue
N+1 client lookup для orders
прямое административное изменение balance
```

## Проверено в подготовленном overlay

- `php -l` для всех PHP-файлов overlay;
- `node --check web/js/dashboard.js`;
- отсутствие demo `STORAGE_KEY`/fake login/local business calculations;
- vendored jQuery отсутствует;
- исходные PNG logo скопированы из финального `tz43-ui-static-v6(1).zip`.

Полный runtime/Codeception прогон здесь не выполнялся: активная среда содержит
подготовленный overlay и доступ к GitHub через connector, но не содержит локального
checkout репозитория с установленным `vendor` и test DB. Эти команды нужно выполнить
после копирования overlay в рабочий checkout проекта.
