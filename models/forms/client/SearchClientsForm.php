<?php

declare(strict_types=1);

namespace app\models\forms\client;

use yii\base\Model;

/**
 * Вхідна модель параметрів пошуку клієнтів.
 *
 * Модель описує transport-контракт endpoint:
 *
 * GET /clients/search
 *     ?field=name
 *     &value=Postman
 *     &like=1
 *     &page=1
 *     &per-page=20
 *
 * Form Model відповідає тільки за:
 *
 * - нормалізацію вхідних значень;
 * - перевірку дозволеного поля пошуку;
 * - перевірку режиму порівняння;
 * - валідацію параметрів пагінації.
 *
 * SQL-запити та вибір конкретного оператора порівняння
 * залишаються відповідальністю Service Layer.
 */
final class SearchClientsForm extends Model
{
    public const FIELD_NAME = 'name';
    public const FIELD_EMAIL = 'email';

    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    /**
     * Поле сутності Client, за яким виконується пошук.
     *
     * Значення навмисно проходить allowlist і не може бути
     * довільною назвою SQL-колонки.
     */
    public mixed $field = null;

    /**
     * Значення, яке необхідно знайти.
     */
    public mixed $value = null;

    /**
     * Режим порівняння:
     *
     * 0 — точне значення;
     * 1 — частковий збіг через LIKE.
     *
     * "Точне" тут означає відсутність wildcard-пошуку.
     * Чутливість до регістру визначається collation БД.
     */
    public mixed $like = null;

    /**
     * Номер сторінки результатів.
     */
    public mixed $page = self::DEFAULT_PAGE;

    /**
     * Кількість записів на сторінці.
     *
     * HTTP query parameter має ім'я `per-page`, але всередині
     * Form Model використовується PHP-сумісне ім'я `per_page`.
     */
    public mixed $per_page = self::DEFAULT_PER_PAGE;

    /**
     * Описує правила нормалізації та валідації search request.
     */
    public function rules(): array
    {
        return [
            /**
             * Пробіли по краях не є частиною ні назви поля,
             * ні пошукового значення.
             */
            [['field', 'value'], 'trim'],

            /**
             * Основні параметри search use case є обов'язковими.
             *
             * Пагінація може бути відсутня — для неї існують defaults.
             */
            [['field', 'value', 'like'], 'required'],

            /**
             * Не дозволяємо передавати довільне ім'я колонки.
             *
             * Це одночасно:
             * - робить API-контракт явним;
             * - не дозволяє transport input визначати SQL-структуру;
             * - спрощує подальше безпечне формування query у Service Layer.
             */
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

            /**
             * Для клієнта name та email мають максимальну довжину 255,
             * тому довше пошукове значення не має практичного сенсу.
             */
            [
                'value',
                'string',
                'min' => 1,
                'max' => 255,
                'tooShort' => 'Значення пошуку не може бути порожнім.',
                'tooLong' => 'Значення пошуку не може перевищувати 255 символів.',
            ],

            /**
             * Query parameters надходять із HTTP як рядки,
             * тому контракт навмисно приймає саме "0" або "1".
             */
            [
                'like',
                'in',
                'range' => ['0', '1'],
                'strict' => true,
                'message' => 'Параметр like має містити значення 0 або 1.',
            ],

            ['page', 'default', 'value' => self::DEFAULT_PAGE],
            ['per_page', 'default', 'value' => self::DEFAULT_PER_PAGE],

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

    /**
     * Повертає валідоване поле пошуку.
     */
    public function fieldName(): string
    {
        return (string) $this->field;
    }

    /**
     * Повертає нормалізоване пошукове значення.
     */
    public function searchValue(): string
    {
        return (string) $this->value;
    }

    /**
     * Визначає, чи потрібно виконувати частковий LIKE-пошук.
     *
     * false означає точне порівняння значення без wildcard.
     */
    public function isLike(): bool
    {
        return (string) $this->like === '1';
    }

    /**
     * Повертає валідований номер сторінки.
     */
    public function pageNumber(): int
    {
        return (int) $this->page;
    }

    /**
     * Повертає валідований розмір сторінки.
     */
    public function pageSize(): int
    {
        return (int) $this->per_page;
    }
}
