<?php

declare(strict_types=1);

namespace app\models\entities;

use app\models\entities\enums\ClientPendingProcessingStatus;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * ActiveRecord-сутність клієнта.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $balance
 * @property string $status
 * @property string $pending_processing_status
 * @property int $created_at
 * @property int $updated_at
 */
final class Client extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BLOCKED = 'blocked';

    public static function tableName(): string
    {
        return '{{%client}}';
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
            [['name', 'email', 'balance', 'status'], 'required'],

            [['name', 'email'], 'trim'],

            ['name', 'string', 'max' => 255],

            ['email', 'email'],
            ['email', 'string', 'max' => 255],

            [
                'balance',
                'match',
                'pattern' => '/^\d{1,10}(?:\.\d{1,2})?$/',
                'message' => 'Баланс має бути невід’ємним десятковим числом не більше ніж з 2 знаками після крапки.',
            ],

            [
                'status',
                'in',
                'range' => [
                    self::STATUS_ACTIVE,
                    self::STATUS_BLOCKED,
                ],
            ],

            [
                'pending_processing_status',
                'default',
                'value' => ClientPendingProcessingStatus::Idle->value,
            ],
            [
                'pending_processing_status',
                'in',
                'range' => array_column(ClientPendingProcessingStatus::cases(), 'value'),
            ],

            [['created_at', 'updated_at'], 'integer'],
        ];
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}
