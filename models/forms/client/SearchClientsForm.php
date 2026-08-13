<?php

declare(strict_types=1);

namespace app\models\forms\client;

use app\models\entities\Client;
use yii\base\Model;

/**
 * Вхідна модель параметрів серверного пошуку/фільтрації клієнтів.
 *
 * Контракт залишається явним і не перетворюється на універсальний query DSL:
 * frontend може керувати лише конкретними полями, які реально присутні
 * у затвердженому UI клієнтів.
 */
final class SearchClientsForm extends Model
{
    public const FIELD_NAME = 'name';
    public const FIELD_EMAIL = 'email';

    public const RELATION_AND = 'and';
    public const RELATION_OR = 'or';
    public const RELATION_OFF = 'off';

    public const BALANCE_SORT_ASC = 'asc';
    public const BALANCE_SORT_DESC = 'desc';

    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    public mixed $field = self::FIELD_NAME;
    public mixed $value = '';
    public mixed $like = '1';
    public mixed $status = null;
    public mixed $relation = self::RELATION_OFF;
    public mixed $balance_sort = null;
    public mixed $page = self::DEFAULT_PAGE;
    public mixed $per_page = self::DEFAULT_PER_PAGE;

    /**
     * Нормалізує та перевіряє тільки дозволені query-параметри.
     *
     * value може бути порожнім лише тоді, коли request все одно має
     * реальний критерій: status або balance-sort. Це зберігає старий
     * контракт звичайного text-search, але дозволяє UI виконувати
     * status-only та sort-only запити до того самого endpoint.
     */
    public function rules(): array
    {
        return [
            [['field', 'value', 'status', 'relation', 'balance_sort'], 'trim'],

            ['field', 'default', 'value' => self::FIELD_NAME],
            ['value', 'default', 'value' => ''],
            ['like', 'default', 'value' => '1'],
            ['relation', 'default', 'value' => self::RELATION_OFF],
            ['page', 'default', 'value' => self::DEFAULT_PAGE],
            ['per_page', 'default', 'value' => self::DEFAULT_PER_PAGE],

            [['field', 'like', 'relation'], 'required'],

            /**
             * Старий контракт /clients/search вимагав value.
             *
             * Під час frontend-інтеграції з'явилися два валідні сценарії
             * без текстового значення: фільтр лише за status і сортування
             * лише за balance. Тому послаблюємо правило тільки для них,
             * а порожній search request без жодного критерію як і раніше
             * вважаємо невалідним.
             */
            [
                'value',
                'required',
                'when' => static fn (self $model): bool =>
                    trim((string) ($model->status ?? '')) === ''
                    && trim((string) ($model->balance_sort ?? '')) === '',
                'message' => 'Значення пошуку не може бути порожнім.',
            ],

            [
                'field',
                'in',
                'range' => [
                    self::FIELD_NAME,
                    self::FIELD_EMAIL,
                ],
                'strict' => true,
                'message' => 'Поле пошуку має містити значення name або email.',
            ],

            [
                'value',
                'string',
                'max' => 255,
                'tooLong' => 'Значення пошуку не може перевищувати 255 символів.',
            ],

            [
                'like',
                'in',
                'range' => ['0', '1'],
                'strict' => true,
                'message' => 'Параметр like має містити значення 0 або 1.',
            ],

            [
                'status',
                'in',
                'range' => [
                    Client::STATUS_ACTIVE,
                    Client::STATUS_BLOCKED,
                ],
                'strict' => true,
                'skipOnEmpty' => true,
                'message' => 'Статус клієнта має містити значення active або blocked.',
            ],

            [
                'relation',
                'in',
                'range' => [
                    self::RELATION_AND,
                    self::RELATION_OR,
                    self::RELATION_OFF,
                ],
                'strict' => true,
                'message' => 'Relation має містити значення and, or або off.',
            ],

            [
                'balance_sort',
                'in',
                'range' => [
                    self::BALANCE_SORT_ASC,
                    self::BALANCE_SORT_DESC,
                ],
                'strict' => true,
                'skipOnEmpty' => true,
                'message' => 'Сортування balance має містити значення asc або desc.',
            ],

            [
                'page',
                'integer',
                'min' => self::DEFAULT_PAGE,
                'message' => 'Номер сторінки має бути цілим числом.',
                'tooSmall' => 'Номер сторінки має бути більшим за нуль.',
            ],

            [
                'per_page',
                'integer',
                'min' => 1,
                'max' => self::MAX_PER_PAGE,
                'message' => 'Розмір сторінки має бути цілим числом.',
                'tooSmall' => 'Розмір сторінки має бути більшим за нуль.',
                'tooBig' => 'Розмір сторінки не може перевищувати 100 записів.',
            ],
        ];
    }

    public function fieldName(): string
    {
        return (string) $this->field;
    }

    public function searchValue(): string
    {
        return (string) $this->value;
    }

    public function isLike(): bool
    {
        return (string) $this->like === '1';
    }

    public function statusFilter(): ?string
    {
        $status = (string) ($this->status ?? '');

        return $status === '' ? null : $status;
    }

    public function relationMode(): string
    {
        return (string) $this->relation;
    }

    public function balanceSort(): ?string
    {
        $sort = (string) ($this->balance_sort ?? '');

        return $sort === '' ? null : $sort;
    }

    public function pageNumber(): int
    {
        return (int) $this->page;
    }

    public function pageSize(): int
    {
        return (int) $this->per_page;
    }
}
