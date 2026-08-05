<?php

declare(strict_types=1);

namespace app\resources;

use app\models\entities\Client;
use yii\base\Model;

/**
 * Зовнішнє представлення клієнта.
 *
 * Resource не містить business logic та не працює з помилками операцій.
 * Його завдання — визначити набір даних Client, доступний зовнішнім
 * споживачам через Serializer.
 */
final class ClientResource extends Model
{
    public int $id;
    public string $name;
    public string $email;
    public string $balance;
    public string $status;

    public function __construct(Client $client, array $config = [])
    {
        $this->id = (int) $client->id;
        $this->name = $client->name;
        $this->email = $client->email;
        $this->balance = $client->balance;
        $this->status = $client->status;

        parent::__construct($config);
    }

    public function fields(): array
    {
        return [
            'id',
            'name',
            'email',
            'balance',
            'status',
        ];
    }
}
