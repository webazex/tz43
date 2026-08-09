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

    /**
     * Перевіряє інваріанти клієнта перед збереженням.
     *
     * Нормалізація рядкових значень та встановлення значень
     * за замовчуванням виконуються до required-валідації.
     * Завдяки цьому рядки з пробілів не проходять перевірку,
     * а відсутній status коректно перетворюється на active.
     */
    public function rules(): array
    {
        return [
            [['name', 'email'], 'trim'],

            [
                'status',
                'default',
                'value' => self::STATUS_ACTIVE,
            ],
            [
                'pending_processing_status',
                'default',
                'value' => ClientPendingProcessingStatus::Idle->value,
            ],

            [['name', 'email', 'balance', 'status'], 'required'],

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
                'in',
                'range' => array_column(ClientPendingProcessingStatus::cases(), 'value'),
            ],

            [['created_at', 'updated_at'], 'integer'],
        ];
    }

    /**
     * Перевіряє, чи заборонено клієнту створювати нові замовлення.
     *
     * Блокування не забороняє поповнювати баланс або оплачувати
     * вже створені pending-замовлення.
     */
    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}