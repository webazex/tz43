/*
 * TZ43 dashboard — production jQuery integration
 * --------------------------------------------------------------------------
 * Цей файл є presentation/client layer адміністративної панелі.
 *
 * Важливі межі відповідальності:
 * - frontend збирає input і викликає REST API;
 * - business rules не дублюються у JavaScript;
 * - paid/pending, списання balance, допустимість cancel та інші рішення
 *   завжди визначає backend;
 * - application data не зберігається в localStorage;
 * - localStorage використовується тільки для UI-state sidebar.
 *
 * Користувацькі рядки рендеряться через .text(), а не через HTML-конкатенацію.
 */

(function ($, window, document) {
    'use strict';

    var SIDEBAR_KEY = 'tz43.sidebar.collapsed';
    var CLIENTS_PER_PAGE = 20;
    var ORDERS_PER_PAGE = 20;
    var REMOTE_CLIENTS_LIMIT = 10;
    var SEARCH_DELAY = 280;

    var config = window.TZ43_CONFIG || {};
    var baseUrl = String(config.baseUrl || '').replace(/\/$/, '');

    var ICON_CLOSE = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

    /* ----------------------------------------------------------------------
     * URL / API transport
     * ---------------------------------------------------------------------- */

    /**
     * Формує URL відносно baseUrl Yii application.
     * Це дозволяє dashboard працювати не тільки в корені домену.
     */
    function appUrl(path) {
        path = String(path || '').replace(/^\//, '');
        return baseUrl + '/' + path;
    }

    /**
     * CSRF token береться зі штатних meta tags Yii.
     * Для session-auth write requests token передається у header.
     */
    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    /**
     * Нормалізує будь-яку API-помилку в один frontend-об'єкт.
     *
     * OperationResponse повертає error.code/error.details, але 401/403 або
     * framework-level exception можуть мати стандартний Yii JSON без envelope.
     */
    function normalizeApiError(xhr, response) {
        var payload = response || (xhr && xhr.responseJSON) || {};
        var operationError = payload.error || {};

        return {
            status: xhr ? Number(xhr.status || 0) : 0,
            code: operationError.code || payload.code || '',
            details: operationError.details || {},
            message: payload.message || ''
        };
    }

    /**
     * Єдиний невеликий AJAX helper. Окремий ApiClient/Repository не створюємо,
     * оскільки для тестового це не дає додаткової відповідальності.
     *
     * @param {string} method HTTP method.
     * @param {string} path Relative application path.
     * @param {Object|null} data JSON body for write requests.
     * @param {Object|null} query Query params for GET requests.
     * @returns {JQuery.Promise}
     */
    function apiRequest(method, path, data, query) {
        var deferred = $.Deferred();
        var upperMethod = String(method || 'GET').toUpperCase();
        var options = {
            url: appUrl(path),
            method: upperMethod,
            dataType: 'json',
            headers: {
                Accept: 'application/json'
            }
        };

        if (upperMethod === 'GET') {
            options.data = query || {};
        } else {
            options.contentType = 'application/json; charset=UTF-8';
            options.data = JSON.stringify(data || {});
            options.headers['X-CSRF-Token'] = csrfToken();
        }

        $.ajax(options)
            .done(function (response, textStatus, xhr) {
                if (!response || response.success !== true) {
                    deferred.reject(normalizeApiError(xhr, response));
                    return;
                }

                deferred.resolve(response.data, xhr);
            })
            .fail(function (xhr) {
                deferred.reject(normalizeApiError(xhr));
            });

        return deferred.promise();
    }

    /**
     * Людське повідомлення для application/framework error.
     * Детальні field-errors обробляються окремо біля конкретної форми.
     */
    function errorMessage(error) {
        var messages = {
            VALIDATION_FAILED: 'Перевірте введені дані.',
            CLIENT_DATA_CONFLICT: 'Клієнт з такими унікальними даними вже існує.',
            CLIENT_NOT_FOUND: 'Клієнта не знайдено.',
            CLIENT_UPDATE_FAILED: 'Не вдалося оновити клієнта.',
            CLIENT_TOP_UP_INVALID_AMOUNT: 'Некоректна сума поповнення.',
            CLIENT_BALANCE_LIMIT_EXCEEDED: 'Після поповнення баланс перевищить допустиму межу.',
            CLIENT_TOP_UP_FAILED: 'Не вдалося виконати поповнення балансу.',
            CLIENT_BLOCKED: 'Заблокований клієнт не може створювати нові замовлення.',
            CLIENT_BALANCE_PROCESSING: 'Баланс клієнта зараз обробляється чергою. Повторіть дію пізніше.',
            ORDER_NOT_FOUND: 'Замовлення не знайдено.',
            ORDER_NOT_PENDING: 'Скасувати можна тільки pending-замовлення.',
            ORDER_INVALID_AMOUNT: 'Некоректна сума замовлення.',
            ORDER_CREATE_FAILED: 'Не вдалося створити замовлення.'
        };

        if (error && error.code && messages[error.code]) {
            return messages[error.code];
        }

        if (error && error.status === 401) {
            return 'Сесія авторизації недоступна. Оновіть сторінку та увійдіть знову.';
        }

        if (error && error.status === 403) {
            return 'Запит відхилено політикою безпеки. Оновіть сторінку та повторіть дію.';
        }

        if (error && error.message) {
            return error.message;
        }

        return 'Не вдалося виконати запит. Спробуйте ще раз.';
    }

    /* ----------------------------------------------------------------------
     * Shared UI helpers
     * ---------------------------------------------------------------------- */

    function money(value) {
        var number = Number(value || 0);
        return number.toLocaleString('uk-UA', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ₴';
    }

    function formatDate(timestamp) {
        var numeric = Number(timestamp || 0);
        if (!numeric) {
            return '—';
        }

        return new Date(numeric * 1000).toLocaleString('uk-UA', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function initials(value) {
        return String(value || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(function (part) {
                return part.charAt(0).toUpperCase();
            })
            .join('') || '—';
    }

    function statusBadge(status) {
        return $('<span>', {
            'class': 'status status--' + String(status || ''),
            text: String(status || '—')
        });
    }

    function toast(title, message, type) {
        var $toast = $('<div>', { 'class': 'toast toast--' + (type || 'info') });
        var $copy = $('<div>');

        $toast.append($('<div>', { 'class': 'toast__line' }));
        $copy.append($('<strong>').text(title));
        $copy.append($('<p>').text(message));
        $toast.append($copy);
        $('#toast-host').append($toast);

        window.setTimeout(function () {
            $toast.fadeOut(180, function () {
                $toast.remove();
            });
        }, 3800);
    }

    function openModal(options) {
        var $layer = $('#modal-layer').empty().addClass('is-open').attr('aria-hidden', 'false');
        var $modal = $('<section>', {
            'class': 'modal' + (options.small ? ' modal--sm' : ''),
            role: 'dialog',
            'aria-modal': 'true',
            'aria-labelledby': 'modal-title'
        });
        var $head = $('<header>', { 'class': 'modal__head' });
        var $titles = $('<div>');
        var $body = $('<div>', { 'class': 'modal__body' });
        var $foot = $('<footer>', { 'class': 'modal__foot' });

        $('body').addClass('is-modal-open');

        $titles.append($('<h2>', {
            id: 'modal-title',
            'class': 'modal__title'
        }).text(options.title || 'Дія'));

        if (options.subtitle) {
            $titles.append($('<p>', { 'class': 'modal__subtitle' }).text(options.subtitle));
        }

        $head.append($titles);
        $head.append($('<button>', {
            type: 'button',
            'class': 'modal-close modal-close--icon',
            'aria-label': 'Закрити'
        }).html(ICON_CLOSE));

        if (typeof options.buildBody === 'function') {
            options.buildBody($body);
        }

        $foot.append($('<button>', {
            type: 'button',
            'class': 'btn modal-close'
        }).text('Скасувати'));

        if (options.submitText) {
            $foot.append($('<button>', {
                type: 'button',
                'class': 'btn btn--primary',
                id: 'modal-submit'
            }).text(options.submitText));
        }

        $modal.append($head, $body, $foot);
        $layer.append($modal);

        if (typeof options.onSubmit === 'function') {
            $('#modal-submit').on('click', function () {
                options.onSubmit($body, $(this));
            });
        }

        window.setTimeout(function () {
            $modal.find('input,select,textarea,button').filter(':visible').first().trigger('focus');
        }, 0);
    }

    function closeModal() {
        $('#modal-layer').removeClass('is-open').attr('aria-hidden', 'true').empty();
        $('body').removeClass('is-modal-open');
    }

    function formField(label, name, value, options) {
        options = options || {};

        var $group = $('<div>', {
            'class': 'field-group' + (options.full ? ' field-group--full' : '')
        });
        var $label = $('<label>', {
            'class': 'field-label',
            'for': name
        }).text(label);
        var $field;

        if (options.hint) {
            $label.append($('<span>', { 'class': 'field-hint' }).text(options.hint));
        }

        $group.append($label);

        if (options.type === 'select') {
            $field = $('<select>', {
                'class': 'select',
                id: name,
                name: name
            });

            $.each(options.items || [], function (_, item) {
                $field.append($('<option>', { value: item.value }).text(item.label));
            });

            $field.val(value);
        } else if (options.type === 'textarea') {
            $field = $('<textarea>', {
                'class': 'textarea',
                id: name,
                name: name,
                placeholder: options.placeholder || ''
            }).val(value || '');
        } else {
            $field = $('<input>', {
                'class': 'input',
                id: name,
                name: name,
                type: options.type || 'text',
                placeholder: options.placeholder || '',
                min: options.min,
                step: options.step
            }).val(value == null ? '' : value);
        }

        $group.append($field);
        $group.append($('<div>', { 'class': 'field-error' }));

        return $group;
    }

    function clearFormErrors($scope) {
        $scope.find('.field-group')
            .removeClass('has-error')
            .find('.field-error')
            .text('');
    }

    function setFieldError($scope, name, message) {
        var $field = $scope.find('[name="' + name + '"]');
        var $group = $field.closest('.field-group');

        $group.toggleClass('has-error', Boolean(message));
        $group.find('.field-error').text(message || '');
    }

    /**
     * Переносить server-side Form Model errors у відповідні поля UI.
     */
    function applyValidationErrors($scope, error) {
        var fields = error && error.details && error.details.fields
            ? error.details.fields
            : null;

        if (!fields) {
            return false;
        }

        $.each(fields, function (name, messages) {
            var message = Array.isArray(messages) ? messages[0] : messages;
            setFieldError($scope, name, String(message || 'Некоректне значення.'));
        });

        return true;
    }

    function setBusy($button, busy) {
        if (!$button || !$button.length) {
            return;
        }

        if (busy) {
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }
            $button.prop('disabled', true).text('Зачекайте…');
            return;
        }

        $button.prop('disabled', false).text($button.data('original-text') || 'Зберегти');
    }

    function renderTableLoading($body, columns) {
        var $row = $('<tr>');
        var $cell = $('<td>', {
            colspan: columns,
            'class': 'muted'
        }).text('Завантаження…');

        $row.append($cell);
        $body.empty().append($row);
    }

    function updatePaginationCopy($host, pagination) {
        var total = Number(pagination.totalCount || 0);
        var page = Number(pagination.page || 1);
        var perPage = Number(pagination.perPage || 1);

        if (!total) {
            $host.text('Немає результатів');
            return;
        }

        var from = (page - 1) * perPage + 1;
        var to = Math.min(page * perPage, total);
        $host.text('Показано ' + from + '–' + to + ' із ' + total);
    }

    function renderPagination($host, current, total, onChange) {
        $host.empty();

        if (total <= 1) {
            return;
        }

        function button(label, page, active, disabled) {
            var $button = $('<button>', {
                type: 'button',
                'class': 'page-btn' + (active ? ' is-active' : ''),
                disabled: disabled,
                text: label
            });

            if (!disabled && !active) {
                $button.on('click', function () {
                    onChange(page);
                });
            }

            return $button;
        }

        $host.append(button('‹', Math.max(1, current - 1), false, current === 1));

        var candidates = [1, current - 2, current - 1, current, current + 1, current + 2, total]
            .filter(function (page) {
                return page >= 1 && page <= total;
            })
            .filter(function (page, index, pages) {
                return pages.indexOf(page) === index;
            })
            .sort(function (a, b) {
                return a - b;
            });

        var previous = null;
        $.each(candidates, function (_, page) {
            if (previous !== null && page - previous > 1) {
                $host.append($('<span>', { 'class': 'page-gap', text: '…' }));
            }

            $host.append(button(String(page), page, page === current, false));
            previous = page;
        });

        $host.append(button('›', Math.min(total, current + 1), false, current === total));
    }

    /* ----------------------------------------------------------------------
     * Dashboard shell events
     * ---------------------------------------------------------------------- */

    function initShell() {
        var collapsed = false;

        try {
            collapsed = window.localStorage.getItem(SIDEBAR_KEY) === '1';
        } catch (error) {
            collapsed = false;
        }

        $('body').toggleClass('sidebar-collapsed', collapsed);

        $(document).on('click', '.desktop-collapse-btn', function () {
            var nextCollapsed = !$('body').hasClass('sidebar-collapsed');
            $('body').toggleClass('sidebar-collapsed', nextCollapsed);

            try {
                window.localStorage.setItem(SIDEBAR_KEY, nextCollapsed ? '1' : '0');
            } catch (error) {
                // Sidebar лишається працездатним навіть без localStorage.
            }
        });

        $(document).on('click', '.mobile-menu-btn', function () {
            $('body').addClass('is-mobile-nav-open');
        });

        $(document).on('click', '.mobile-backdrop', function () {
            $('body').removeClass('is-mobile-nav-open');
        });

        $(document).on('click', '[data-nav-group="orders"]', function () {
            $(this).closest('.nav-group').toggleClass('is-open');
        });

      /**
       * Модальні вікна закриваються тільки явною дією користувача:
       * кнопкою закриття, кнопкою "Скасувати" або після успішної операції.
       *
       * Закриття через backdrop та Escape навмисно не використовується.
       * У формах створення/редагування це могло призвести до випадкової
       * втрати вже введених користувачем даних.
       */
      $(document).on('click', '.modal-close', closeModal);

      /**
       * Escape залишаємо тільки для закриття мобільної навігації.
       * Модальне вікно цей global handler більше не змінює.
       */
      $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
          $('body').removeClass('is-mobile-nav-open');
        }
      });
    }

    /* ----------------------------------------------------------------------
     * Client create / update modal
     * ---------------------------------------------------------------------- */

    function openClientModal(client, onSaved) {
        var editing = Boolean(client);

        openModal({
            title: editing ? 'Редагувати клієнта' : 'Створити клієнта',
            subtitle: editing
                ? 'Змінюються тільки ім’я, email та статус.'
                : 'Початковий баланс задається лише під час створення.',
            submitText: editing ? 'Зберегти' : 'Створити',
            buildBody: function ($body) {
                var $form = $('<div>', { 'class': 'form-grid' });

                $form.append(formField('Ім’я', 'name', editing ? client.name : '', {
                    placeholder: 'Ім’я клієнта'
                }));
                $form.append(formField('Email', 'email', editing ? client.email : '', {
                    type: 'email',
                    placeholder: 'client@example.com'
                }));

                if (!editing) {
                    $form.append(formField('Початковий баланс', 'balance', '0.00', {
                        type: 'number',
                        min: '0',
                        step: '0.01',
                        placeholder: '0.00'
                    }));
                }

                $form.append(formField('Статус', 'status', editing ? client.status : 'active', {
                    type: 'select',
                    items: [
                        { value: 'active', label: 'active' },
                        { value: 'blocked', label: 'blocked' }
                    ]
                }));

                $body.append($form);
            },
            onSubmit: function ($body, $button) {
                clearFormErrors($body);

                var payload = {
                    name: $.trim($body.find('[name="name"]').val()),
                    email: $.trim($body.find('[name="email"]').val()),
                    status: $body.find('[name="status"]').val()
                };

                if (!editing) {
                    payload.balance = String($body.find('[name="balance"]').val() || '0');
                }

                setBusy($button, true);

                apiRequest(
                    editing ? 'PATCH' : 'POST',
                    editing ? 'clients/' + Number(client.id) : 'clients',
                    payload
                )
                    .done(function (savedClient) {
                        closeModal();
                        toast(
                            editing ? 'Клієнта оновлено' : 'Клієнта створено',
                            savedClient.name + ' · #' + savedClient.id,
                            'success'
                        );

                        if (typeof onSaved === 'function') {
                            onSaved(savedClient);
                        }
                    })
                    .fail(function (error) {
                        applyValidationErrors($body, error);

                        if (error.code === 'CLIENT_DATA_CONFLICT') {
                            setFieldError($body, 'email', 'Такий email уже використовується.');
                        }

                        toast('Помилка', errorMessage(error), 'danger');
                        setBusy($button, false);
                    });
            }
        });
    }

    /* ----------------------------------------------------------------------
     * Clients list
     * ---------------------------------------------------------------------- */

    function initClientsPage() {
        var page = 1;
        var balanceSort = 'desc';
        var clientsById = {};
        var searchTimer = null;

        function queryParams() {
            return {
                field: $('#client-search-field').val(),
                value: $.trim($('#client-search-value').val()),
                like: '1',
                status: $('#client-status-filter').val(),
                relation: $('input[name="client-filter-logic"]:checked').val() || 'off',
                'balance-sort': balanceSort,
                page: page,
                'per-page': CLIENTS_PER_PAGE
            };
        }

        function syncBalanceSortControl() {
            var descending = balanceSort === 'desc';

            $('#client-balance-sort')
                .attr('data-direction', balanceSort)
                .attr(
                    'title',
                    descending
                        ? 'Сортувати баланс за зростанням'
                        : 'Сортувати баланс за спаданням'
                )
                .find('.sort-indicator')
                .text(descending ? '↓' : '↑');

            $('#client-balance-sort-cell')
                .attr('aria-sort', descending ? 'descending' : 'ascending');
        }

        function renderClients(items, pagination) {
            var $body = $('#clients-body').empty();
            clientsById = {};

            $.each(items || [], function (_, client) {
                clientsById[Number(client.id)] = client;

                var $row = $('<tr>');
                var $name = $('<td>');
                var $actions = $('<td>');
                var $buttons = $('<div>', { 'class': 'table-actions' });

                $name.append($('<span>', { 'class': 'cell-main' }).text(client.name));
                $name.append($('<span>', { 'class': 'cell-sub' }).text('#' + client.id));

                $row.append($('<td>', { 'class': 'mono' }).text(client.id));
                $row.append($name);
                $row.append($('<td>').text(client.email));
                $row.append($('<td>', { 'class': 'nowrap' }).text(money(client.balance)));
                $row.append($('<td>').append(statusBadge(client.status)));

                $buttons.append($('<a>', {
                    'class': 'table-link',
                    href: appUrl('dashboard/clients/' + Number(client.id))
                }).text('Профіль'));

                $buttons.append($('<button>', {
                    type: 'button',
                    'class': 'table-link js-edit-client',
                    'data-id': Number(client.id)
                }).text('Редагувати'));

                $actions.append($buttons);
                $row.append($actions);
                $body.append($row);
            });

            var hasItems = Boolean(items && items.length);
            $('#clients-table-wrap').toggle(hasItems);
            $('#clients-empty').toggle(!hasItems);

            updatePaginationCopy($('#clients-count'), pagination);
            renderPagination(
                $('#clients-pagination'),
                Number(pagination.page || 1),
                Number(pagination.pageCount || 0),
                function (nextPage) {
                    page = nextPage;
                    loadClients();
                }
            );
        }

        function loadClients() {
            renderTableLoading($('#clients-body'), 6);
            $('#clients-table-wrap').show();
            $('#clients-empty').hide();

            apiRequest('GET', 'clients/search', null, queryParams())
                .done(function (data) {
                    renderClients(data.items || [], data.pagination || {});
                })
                .fail(function (error) {
                    $('#clients-body').empty();
                    $('#clients-table-wrap').hide();
                    $('#clients-empty').show();
                    $('#clients-count').text('Помилка завантаження');
                    $('#clients-pagination').empty();
                    toast('Клієнти не завантажені', errorMessage(error), 'danger');
                });
        }

        $('#create-client').on('click', function () {
            openClientModal(null, function () {
                page = 1;
                loadClients();
            });
        });

        $(document).on('click', '.js-edit-client', function () {
            var client = clientsById[Number($(this).data('id'))];
            if (!client) {
                return;
            }

            openClientModal(client, function () {
                loadClients();
            });
        });

        $('#client-search-field, #client-status-filter').on('change', function () {
            page = 1;
            loadClients();
        });

        $('input[name="client-filter-logic"]').on('change', function () {
            page = 1;
            loadClients();
        });

        $('#client-search-value').on('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                page = 1;
                loadClients();
            }, SEARCH_DELAY);
        });

        $('#client-search-clear').on('click', function () {
            $('#client-search-field').val('name');
            $('#client-search-value').val('');
            $('#client-status-filter').val('');
            $('input[name="client-filter-logic"][value="off"]').prop('checked', true);
            page = 1;
            loadClients();
        });

        $('#client-balance-sort').on('click', function () {
            balanceSort = balanceSort === 'desc' ? 'asc' : 'desc';
            page = 1;
            syncBalanceSortControl();
            loadClients();
        });

        syncBalanceSortControl();
        loadClients();
    }

    /* ----------------------------------------------------------------------
     * Single client page
     * ---------------------------------------------------------------------- */

    function initClientPage() {
        var clientId = Number($('#page-content').data('client-id') || 0);
        var currentClient = null;
        var ordersPage = 1;

        if (!clientId) {
            toast('Некоректний URL', 'ID клієнта не визначено.', 'danger');
            return;
        }

        function renderClient(client) {
            currentClient = client;
            $('#client-initials').text(initials(client.name));
            $('#client-name').text(client.name);
            $('#client-email').text(client.email);
            $('#client-id').text(client.id);
            $('#client-balance').text(money(client.balance));
            $('#client-status').empty().append(statusBadge(client.status));
        }

        function loadClient() {
            return apiRequest('GET', 'clients/' + clientId)
                .done(function (client) {
                    renderClient(client);
                })
                .fail(function (error) {
                    toast('Клієнт не завантажений', errorMessage(error), 'danger');
                });
        }

        function renderOrders(items, pagination) {
            var $body = $('#client-orders-body').empty();

            $.each(items || [], function (_, order) {
                var $row = $('<tr>');
                $row.append($('<td>', { 'class': 'mono' }).text(order.id));
                $row.append($('<td>').text(money(order.amount)));
                $row.append($('<td>').append(statusBadge(order.status)));
                $row.append($('<td>', { 'class': 'nowrap' }).text(formatDate(order.createdAt)));
                $body.append($row);
            });

            var hasItems = Boolean(items && items.length);
            $('#client-orders-wrap').toggle(hasItems);
            $('#client-orders-empty').toggle(!hasItems);
            $('.client-orders-pagination-bar').toggle(Boolean(pagination.totalCount));

            updatePaginationCopy($('#client-orders-count'), pagination);
            renderPagination(
                $('#client-orders-pagination'),
                Number(pagination.page || 1),
                Number(pagination.pageCount || 0),
                function (nextPage) {
                    ordersPage = nextPage;
                    loadOrders();
                }
            );
        }

        function loadOrders() {
            renderTableLoading($('#client-orders-body'), 4);
            $('#client-orders-wrap').show();
            $('#client-orders-empty').hide();

            apiRequest('GET', 'orders', null, {
                client_id: clientId,
                page: ordersPage,
                'per-page': ORDERS_PER_PAGE
            })
                .done(function (data) {
                    renderOrders(data.items || [], data.pagination || {});
                })
                .fail(function (error) {
                    $('#client-orders-body').empty();
                    $('#client-orders-wrap').hide();
                    $('#client-orders-empty').show();
                    toast('Замовлення не завантажені', errorMessage(error), 'danger');
                });
        }

        $('#client-edit').on('click', function () {
            if (!currentClient) {
                return;
            }

            openClientModal(currentClient, function (savedClient) {
                renderClient(savedClient);
            });
        });

        $('#client-topup').on('click', function () {
            if (!currentClient) {
                return;
            }

            openTopUpModal(currentClient, function (result) {
                /**
                 * Показуємо синхронний результат top-up. Queue може одразу після
                 * цього оплатити pending-orders, тому це не оголошується фінальним
                 * балансом асинхронної обробки.
                 */
                currentClient.balance = result.balanceAfterTopUp;
                renderClient(currentClient);
                ordersPage = 1;
                loadOrders();
            });
        });

        $('#client-order-form').on('submit', function (event) {
            event.preventDefault();
            var $form = $(this);

            createOrder($form, clientId)
                .done(function (order) {
                    $form[0].reset();
                    toast(
                        'Замовлення створено',
                        'Order #' + order.id + ' · статус ' + order.status + '.',
                        'success'
                    );
                    ordersPage = 1;
                    loadClient();
                    loadOrders();
                });
        });

        loadClient();
        loadOrders();
    }

    function openTopUpModal(client, onSaved) {
        openModal({
            title: 'Поповнити баланс',
            subtitle: client.name + ' · поточний баланс ' + money(client.balance),
            submitText: 'Поповнити',
            small: true,
            buildBody: function ($body) {
                $body.append(formField('Сума', 'amount', '', {
                    type: 'number',
                    min: '0.01',
                    step: '0.01',
                    placeholder: '100.00'
                }));
                $body.append($('<div>', { 'class': 'notice' }).text(
                    'Після зарахування pending-замовлення обробляються Queue Job. Баланс у відповіді — стан одразу після top-up.'
                ));
            },
            onSubmit: function ($body, $button) {
                clearFormErrors($body);
                setBusy($button, true);

                apiRequest('POST', 'clients/' + Number(client.id) + '/topup', {
                    amount: String($body.find('[name="amount"]').val() || '')
                })
                    .done(function (result) {
                        closeModal();
                        toast(
                            'Баланс поповнено',
                            money(result.oldBalance) + ' → ' + money(result.balanceAfterTopUp) + '. Pending processing поставлено в чергу.',
                            'success'
                        );

                        if (typeof onSaved === 'function') {
                            onSaved(result);
                        }
                    })
                    .fail(function (error) {
                        applyValidationErrors($body, error);
                        toast('Поповнення не виконано', errorMessage(error), 'danger');
                        setBusy($button, false);
                    });
            }
        });
    }

    /* ----------------------------------------------------------------------
     * Orders common helpers
     * ---------------------------------------------------------------------- */

    function createOrder($form, clientId) {
        var deferred = $.Deferred();
        clearFormErrors($form);

        if (!clientId) {
            setFieldError($form, 'client_id', 'Оберіть клієнта.');
            deferred.reject({ code: 'VALIDATION_FAILED' });
            return deferred.promise();
        }

        var payload = {
            client_id: Number(clientId),
            amount: String($form.find('[name="amount"]').val() || ''),
            description: $.trim($form.find('[name="description"]').val())
        };
        var $button = $form.find('[type="submit"]');

        setBusy($button, true);

        apiRequest('POST', 'orders', payload)
            .done(function (order) {
                deferred.resolve(order);
            })
            .fail(function (error) {
                applyValidationErrors($form, error);
                toast('Замовлення не створено', errorMessage(error), 'danger');
                deferred.reject(error);
            })
            .always(function () {
                setBusy($button, false);
            });

        return deferred.promise();
    }

    function openConfirmCancel(order, onSaved) {
        openModal({
            title: 'Скасувати замовлення #' + order.id + '?',
            subtitle: 'Ця дія допустима тільки для pending-order.',
            submitText: 'Скасувати замовлення',
            small: true,
            buildBody: function ($body) {
                $body.append($('<div>', { 'class': 'notice notice--danger' }).text(
                    'Якщо стан змінився після завантаження сторінки, backend відхилить операцію.'
                ));
            },
            onSubmit: function ($body, $button) {
                setBusy($button, true);

                apiRequest('POST', 'orders/' + Number(order.id) + '/cancel', {})
                    .done(function (savedOrder) {
                        closeModal();
                        toast(
                            'Замовлення скасовано',
                            'Order #' + savedOrder.id + ' отримав статус canceled.',
                            'success'
                        );

                        if (typeof onSaved === 'function') {
                            onSaved(savedOrder);
                        }
                    })
                    .fail(function (error) {
                        closeModal();

                        toast(
                            error.status === 409 ? 'Стан замовлення змінився' : 'Скасування не виконано',
                            errorMessage(error),
                            error.status === 409 ? 'warning' : 'danger'
                        );

                        if (typeof onSaved === 'function' && error.status === 409) {
                            onSaved(null);
                        }
                    });
            }
        });
    }

    /* ----------------------------------------------------------------------
     * Remote searchable client select
     * ---------------------------------------------------------------------- */

    /**
     * Searchable select не завантажує всю таблицю clients.
     *
     * Query routing:
     * - numeric input -> GET /clients/{id};
     * - input з @      -> /clients/search по email;
     * - інший text     -> /clients/search по name.
     *
     * Native select залишається джерелом client_id для форми.
     */
    function createRemoteClientSelect($select, options) {
        options = options || {};

        var allowAll = Boolean(options.allowAll);
        var selectedClient = null;
        var requestSequence = 0;
        var searchTimer = null;
        var defaultLabel = allowAll ? 'Усі клієнти' : 'Оберіть клієнта';

        $select.empty().append($('<option>', { value: '' }).text(defaultLabel));

        var $wrapper = $('<div>', { 'class': 'search-select' });
        var $control = $('<button>', {
            type: 'button',
            'class': 'search-select__control',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false'
        });
        var $value = $('<span>', { 'class': 'search-select__value' }).text(defaultLabel);
        var $chevron = $('<span>', {
            'class': 'search-select__chevron',
            'aria-hidden': 'true'
        }).text('⌄');
        var $menu = $('<div>', { 'class': 'search-select__menu' });
        var $search = $('<input>', {
            type: 'search',
            'class': 'search-select__search',
            placeholder: options.searchPlaceholder || 'Введіть ім’я, email або ID клієнта…',
            autocomplete: 'off',
            'aria-label': 'Пошук клієнта'
        });
        var $options = $('<div>', {
            'class': 'search-select__options',
            role: 'listbox'
        });

        $control.append($value, $chevron);
        $menu.append($search, $options);
        $wrapper.append($control, $menu);
        $select.after($wrapper).addClass('is-search-enhanced');

        function close() {
            $wrapper.removeClass('is-open');
            $control.attr('aria-expanded', 'false');
        }

        function renderHint(text) {
            $options.empty().append($('<div>', {
                'class': 'search-select__empty'
            }).text(text));
        }

        function selectClient(client) {
            selectedClient = client || null;
            $select.empty().append($('<option>', { value: '' }).text(defaultLabel));

            if (selectedClient) {
                $select.append($('<option>', {
                    value: Number(selectedClient.id)
                }).text(selectedClient.name + ' · #' + selectedClient.id));
                $select.val(String(selectedClient.id));
                $value.text(selectedClient.name + ' · #' + selectedClient.id);
            } else {
                $select.val('');
                $value.text(defaultLabel);
            }

            $select.trigger('change', [selectedClient]);
            close();
        }

        function renderClients(clients) {
            $options.empty();

            if (allowAll) {
                var $all = $('<button>', {
                    type: 'button',
                    'class': 'search-select__option' + (!selectedClient ? ' is-selected' : ''),
                    role: 'option'
                }).text('Усі клієнти');

                $all.on('click', function () {
                    selectClient(null);
                });
                $options.append($all);
            }

            if (!clients.length) {
                if (!allowAll) {
                    renderHint('Клієнта не знайдено.');
                } else {
                    $options.append($('<div>', {
                        'class': 'search-select__empty'
                    }).text('Клієнта не знайдено.'));
                }
                return;
            }

            $.each(clients, function (_, client) {
                var selected = selectedClient && Number(selectedClient.id) === Number(client.id);
                var $item = $('<button>', {
                    type: 'button',
                    'class': 'search-select__option' + (selected ? ' is-selected' : ''),
                    role: 'option',
                    'aria-selected': String(Boolean(selected))
                });

                $item.text(client.name + ' · #' + client.id + ' · ' + client.email);
                $item.on('click', function () {
                    selectClient(client);
                    $control.trigger('focus');
                });
                $options.append($item);
            });
        }

        function searchClients(query) {
            var needle = $.trim(query || '');
            var sequence = ++requestSequence;

            if (!needle) {
                if (allowAll) {
                    renderClients([]);
                    $options.find('.search-select__empty')
                        .text('Почніть вводити ім’я, email або ID клієнта.');
                } else {
                    renderHint('Почніть вводити ім’я, email або ID клієнта.');
                }
                return;
            }

            renderHint('Пошук…');

            var request;
            if (/^\d+$/.test(needle)) {
                request = apiRequest('GET', 'clients/' + Number(needle));
            } else {
                request = apiRequest('GET', 'clients/search', null, {
                    field: needle.indexOf('@') !== -1 ? 'email' : 'name',
                    value: needle,
                    like: '1',
                    relation: 'off',
                    page: 1,
                    'per-page': REMOTE_CLIENTS_LIMIT
                });
            }

            request
                .done(function (data) {
                    if (sequence !== requestSequence) {
                        return;
                    }

                    /**
                     * GET /clients/{id} повертає один ClientResource,
                     * /clients/search — page object з items.
                     */
                    var clients = Array.isArray(data && data.items)
                        ? data.items
                        : (data && data.id ? [data] : []);

                    renderClients(clients);
                })
                .fail(function (error) {
                    if (sequence !== requestSequence) {
                        return;
                    }

                    if (error.status === 404) {
                        renderClients([]);
                        return;
                    }

                    renderHint('Не вдалося виконати пошук.');
                });
        }

        function setClientById(clientId) {
            if (!clientId) {
                selectClient(null);
                return $.Deferred().resolve(null).promise();
            }

            return apiRequest('GET', 'clients/' + Number(clientId))
                .done(function (client) {
                    selectClient(client);
                });
        }

        $control.on('click', function () {
            var open = !$wrapper.hasClass('is-open');

            $('.search-select.is-open')
                .not($wrapper)
                .removeClass('is-open')
                .find('.search-select__control')
                .attr('aria-expanded', 'false');

            $wrapper.toggleClass('is-open', open);
            $control.attr('aria-expanded', String(open));

            if (open) {
                $search.val('');
                searchClients('');
                window.setTimeout(function () {
                    $search.trigger('focus');
                }, 0);
            }
        });

        $search.on('input', function () {
            var query = $(this).val();
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                searchClients(query);
            }, SEARCH_DELAY);
        });

        $search.on('keydown', function (event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close();
                $control.trigger('focus');
            }
        });

        $(document).on('click.search-select-' + $select.attr('id'), function (event) {
            if (!$(event.target).closest($wrapper).length) {
                close();
            }
        });

        return {
            getClient: function () {
                return selectedClient;
            },
            clear: function () {
                selectClient(null);
            },
            setClientById: setClientById
        };
    }

    /* ----------------------------------------------------------------------
     * Orders list
     * ---------------------------------------------------------------------- */

    function initOrdersPage() {
        var page = 1;
        var ordersById = {};
        var clientSelect = createRemoteClientSelect($('#orders-client-filter'), {
            allowAll: true,
            searchPlaceholder: 'Введіть ім’я, email або ID клієнта…'
        });

        function loadOrders() {
            renderTableLoading($('#orders-body'), 6);
            $('#orders-table-wrap').show();
            $('#orders-empty').hide();

            var client = clientSelect.getClient();
            var query = {
                page: page,
                'per-page': ORDERS_PER_PAGE
            };
            var status = $('#orders-status-filter').val();

            if (status) {
                query.status = status;
            }
            if (client) {
                query.client_id = Number(client.id);
            }

            apiRequest('GET', 'orders', null, query)
                .done(function (data) {
                    renderOrders(data.items || [], data.pagination || {});
                })
                .fail(function (error) {
                    $('#orders-body').empty();
                    $('#orders-table-wrap').hide();
                    $('#orders-empty').show();
                    $('#orders-count').text('Помилка завантаження');
                    $('#orders-pagination').empty();
                    toast('Замовлення не завантажені', errorMessage(error), 'danger');
                });
        }

        function renderOrders(items, pagination) {
            var $body = $('#orders-body').empty();
            ordersById = {};

            $.each(items || [], function (_, order) {
                ordersById[Number(order.id)] = order;

                var $row = $('<tr>');
                var $clientCell = $('<td>');
                var $actions = $('<td>');
                var $buttons = $('<div>', { 'class': 'table-actions' });

                $clientCell.append($('<a>', {
                    'class': 'table-link',
                    href: appUrl('dashboard/clients/' + Number(order.clientId))
                }).text('Client #' + order.clientId));

                $row.append($('<td>', { 'class': 'mono' }).text(order.id));
                $row.append($clientCell);
                $row.append($('<td>').text(money(order.amount)));
                $row.append($('<td>').append(statusBadge(order.status)));
                $row.append($('<td>', { 'class': 'nowrap' }).text(formatDate(order.createdAt)));

                $buttons.append($('<a>', {
                    'class': 'table-link',
                    href: appUrl('dashboard/clients/' + Number(order.clientId))
                }).text('Клієнт'));

                if (order.status === 'pending') {
                    $buttons.append($('<button>', {
                        type: 'button',
                        'class': 'table-link table-link--danger js-cancel-order',
                        'data-id': Number(order.id)
                    }).text('Скасувати'));
                }

                $actions.append($buttons);
                $row.append($actions);
                $body.append($row);
            });

            var hasItems = Boolean(items && items.length);
            $('#orders-table-wrap').toggle(hasItems);
            $('#orders-empty').toggle(!hasItems);

            updatePaginationCopy($('#orders-count'), pagination);
            renderPagination(
                $('#orders-pagination'),
                Number(pagination.page || 1),
                Number(pagination.pageCount || 0),
                function (nextPage) {
                    page = nextPage;
                    loadOrders();
                }
            );
        }

        $('#orders-status-filter').on('change', function () {
            page = 1;
            loadOrders();
        });

        $('#orders-client-filter').on('change', function () {
            page = 1;
            loadOrders();
        });

        $('#orders-filter-clear').on('click', function () {
            $('#orders-status-filter').val('');
            page = 1;
            // clear() тригерить change native select і викликає один loadOrders().
            clientSelect.clear();
        });

        $(document).on('click', '.js-cancel-order', function () {
            var order = ordersById[Number($(this).data('id'))];
            if (!order || order.status !== 'pending') {
                loadOrders();
                return;
            }

            openConfirmCancel(order, function () {
                loadOrders();
            });
        });

        loadOrders();
    }

    /* ----------------------------------------------------------------------
     * Order create page
     * ---------------------------------------------------------------------- */

    function initOrderCreatePage() {
        var $select = $('#order-client');
        var clientSelect = createRemoteClientSelect($select, {
            allowAll: false,
            searchPlaceholder: 'Введіть ім’я, email або ID клієнта…'
        });

        function renderClientHint(client) {
            var $hint = $('#order-client-hint').empty();

            if (!client) {
                $hint.text('Після вибору клієнта тут буде показано його баланс і статус.');
                return;
            }

            $hint.append($('<span>').text('Баланс: ' + money(client.balance) + ' · статус: '));
            $hint.append(statusBadge(client.status));
        }

        $select.on('change', function (event, client) {
            renderClientHint(client || clientSelect.getClient());
        });

        var preselected = Number(new URLSearchParams(window.location.search).get('client_id') || 0);
        if (preselected) {
            clientSelect.setClientById(preselected)
                .fail(function (error) {
                    toast('Клієнта не знайдено', errorMessage(error), 'warning');
                });
        }

        $('#order-create-form').on('submit', function (event) {
            event.preventDefault();

            var $form = $(this);
            var selected = clientSelect.getClient();

            createOrder($form, selected ? Number(selected.id) : 0)
                .done(function (order) {
                    showOrderResult(order, selected);
                    $form.find('[name="amount"], [name="description"]').val('');

                    /**
                     * Якщо order став paid, backend уже міг змінити balance.
                     * Повторно читаємо ClientResource замість локального розрахунку.
                     */
                    clientSelect.setClientById(order.clientId);
                });
        });
    }

    function showOrderResult(order, client) {
        $('#result-id').text('#' + order.id);
        $('#result-client').text(client ? client.name : 'Client #' + order.clientId);
        $('#result-amount').text(money(order.amount));
        $('#result-status').empty().append(statusBadge(order.status));
        $('#order-result').addClass('is-visible');

        $('#order-result-copy').text(
            order.status === 'paid'
                ? 'Backend підтвердив paid: кошти списані в межах application use case.'
                : 'Backend повернув pending: баланс не змінено, замовлення очікує подальшої оплати.'
        );

        toast(
            'Замовлення створено',
            'Order #' + order.id + ' · статус ' + order.status + '.',
            'success'
        );
    }

    /* ----------------------------------------------------------------------
     * Bootstrap
     * ---------------------------------------------------------------------- */

    $(function () {
        /**
         * На login сторінці немає demo JS-auth: форму обробляє SiteController.
         */
        if ($('body').hasClass('auth-page')) {
            return;
        }

        initShell();

        switch ($('body').data('page')) {
            case 'clients':
                initClientsPage();
                break;
            case 'client':
                initClientPage();
                break;
            case 'orders':
                initOrdersPage();
                break;
            case 'order-create':
                initOrderCreatePage();
                break;
            case 'profile':
                // Profile повністю server-rendered із поточного Yii identity.
                break;
        }
    });

}(jQuery, window, document));
