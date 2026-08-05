<?php

declare(strict_types=1);

namespace app\models\forms\client;

use app\models\entities\Client;
use yii\base\Model;

/**
 * Вхідна модель для створення клієнта.
 *
 * Приймає сирі дані незалежно від джерела запиту,
 * нормалізує їх та перевіряє перед передачею в ClientService.
 */
final class CreateClientForm extends Model
{
    public mixed $name = null;
    public mixed $email = null;
    public mixed $balance = null;
    public mixed $status = null;

    public function rules(): array
    {
        return [
            [['name', 'email'], 'trim'],

            ['balance', 'default', 'value' => '0.00'],
            ['status', 'default', 'value' => Client::STATUS_ACTIVE],

            [['name', 'email'], 'required'],

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
                    Client::STATUS_ACTIVE,
                    Client::STATUS_BLOCKED,
                ],
            ],
        ];
    }
}
