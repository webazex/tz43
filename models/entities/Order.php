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

    public function rules(): array
    {
        return [
            [['client_id', 'amount', 'description'], 'required'],

            ['client_id', 'integer', 'min' => 1],

            [
                'amount',
                'match',
                'pattern' => '/^\d{1,10}(?:\.\d{1,2})?$/',
                'message' => 'Сума замовлення має бути невід’ємним десятковим числом не більше ніж з 2 знаками після крапки.',
            ],

            ['description', 'trim'],
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
