<?php

declare(strict_types=1);

namespace app\models\entities;

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
                'message' => 'Balance must be a non-negative decimal value with up to 2 decimal places.',
            ],

            [
                'status',
                'in',
                'range' => [
                    self::STATUS_ACTIVE,
                    self::STATUS_BLOCKED,
                ],
            ],

            [['created_at', 'updated_at'], 'integer'],
        ];
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}
