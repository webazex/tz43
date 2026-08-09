<?php

declare(strict_types=1);

namespace app\models\entities;

use app\models\entities\enums\OrderStatus;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord-сутність замовлення.
 *
 * @property int $id
 * @property int $client_id
 * @property string $amount
 * @property string $description
 * @property string $status
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read Client $client
 */
final class Order extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%client_order}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    /**
     * Перевіряє структуру та допустимі значення замовлення.
     *
     * Опис нормалізується до required-валідації, тому рядок,
     * що складається лише з пробілів, не може бути збережений.
     */
    public function rules(): array
    {
        return [
            ['description', 'trim'],

            [['client_id', 'amount', 'description'], 'required'],

            ['client_id', 'integer', 'min' => 1],

            [
                'amount',
                'match',
                /**
                 * Negative lookahead забороняє нульові значення:
                 * 0, 00, 0.0, 0.00 тощо.
                 *
                 * Решта виразу відповідає діапазону DECIMAL(12,2):
                 * не більше 10 цифр до крапки та 2 цифр після неї.
                 */
                'pattern' => '/^(?!0+(?:\.0{1,2})?$)\d{1,10}(?:\.\d{1,2})?$/',
                'message' => 'Сума замовлення має бути більшою за нуль, містити не більше 10 цифр до крапки та не більше 2 цифр після неї.',
            ],

            ['description', 'string'],

            [
                'status',
                'default',
                'value' => OrderStatus::Pending->value,
            ],
            [
                'status',
                'in',
                'range' => array_column(OrderStatus::cases(), 'value'),
            ],

            [['created_at', 'updated_at'], 'integer'],
        ];
    }

    /**
     * Повертає клієнта, якому належить замовлення.
     */
    public function getClient(): ActiveQuery
    {
        return $this->hasOne(
            Client::class,
            ['id' => 'client_id'],
        );
    }
}