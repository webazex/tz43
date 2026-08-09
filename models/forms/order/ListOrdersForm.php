<?php

declare(strict_types=1);

namespace app\models\forms\order;

use app\models\entities\enums\OrderStatus;
use yii\base\Model;

/**
 * Вхідна модель параметрів списку замовлень.
 *
 * Валідує фільтри та пагінацію до виклику OrderService. Form Model
 * не створює SQL-запити й не залежить від HTTP response.
 */
final class ListOrdersForm extends Model
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    public mixed $status = null;
    public mixed $client_id = null;
    public mixed $page = self::DEFAULT_PAGE;
    public mixed $per_page = self::DEFAULT_PER_PAGE;

    public function rules(): array
    {
        return [
            ['status', 'trim'],

            [['status', 'client_id'], 'default', 'value' => null],
            ['page', 'default', 'value' => self::DEFAULT_PAGE],
            ['per_page', 'default', 'value' => self::DEFAULT_PER_PAGE],

            [
                'status',
                'in',
                'range' => array_column(OrderStatus::cases(), 'value'),
                'strict' => true,
                'message' => 'Статус замовлення має містити одне зі значень: pending, paid або canceled.',
            ],

            [
                'client_id',
                'integer',
                'min' => 1,
                'message' => 'Ідентифікатор клієнта має бути цілим числом.',
                'tooSmall' => 'Ідентифікатор клієнта має бути більшим за нуль.',
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

    public function statusFilter(): ?string
    {
        return $this->status === null
            ? null
            : (string) $this->status;
    }

    public function clientIdFilter(): ?int
    {
        return $this->client_id === null
            ? null
            : (int) $this->client_id;
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