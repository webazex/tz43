<?php

declare(strict_types=1);

namespace app\models\forms\client;

use app\models\entities\Client;
use yii\base\Model;

/**
 * Вхідна модель вузького адміністративного update клієнта.
 *
 * Balance тут принципово відсутній: фінансовий стан клієнта змінюється
 * через окремий application use case top-up, а не звичайним CRUD-edit.
 *
 * PATCH допускає передачу одного або декількох дозволених полів:
 * name, email, status.
 */
final class UpdateClientForm extends Model
{
    public mixed $name = null;
    public mixed $email = null;
    public mixed $status = null;

    public function rules(): array
    {
        return [
            [['name', 'email'], 'trim'],

            ['name', 'validateAtLeastOneField'],

            [
                'name',
                'required',
                'when' => static fn (self $model): bool => $model->name !== null,
            ],
            [
                'email',
                'required',
                'when' => static fn (self $model): bool => $model->email !== null,
            ],
            [
                'status',
                'required',
                'when' => static fn (self $model): bool => $model->status !== null,
            ],

            ['name', 'string', 'max' => 255],

            ['email', 'email'],
            ['email', 'string', 'max' => 255],

            [
                'status',
                'in',
                'range' => [
                    Client::STATUS_ACTIVE,
                    Client::STATUS_BLOCKED,
                ],
                'strict' => true,
            ],
        ];
    }

    /**
     * Порожній PATCH не є завершеним update use case.
     */
    public function validateAtLeastOneField(): void
    {
        if ($this->name === null && $this->email === null && $this->status === null) {
            $this->addError('name', 'Передайте хоча б одне поле для оновлення клієнта.');
        }
    }

    public function nameValue(): ?string
    {
        return $this->name === null ? null : (string) $this->name;
    }

    public function emailValue(): ?string
    {
        return $this->email === null ? null : (string) $this->email;
    }

    public function statusValue(): ?string
    {
        return $this->status === null ? null : (string) $this->status;
    }
}
