<?php

declare(strict_types=1);

namespace app\resources;

use app\models\entities\Order;
use yii\base\Model;

/**
 * Зовнішнє REST-представлення замовлення.
 *
 * Resource не містить persistence, business logic або HTTP-кодів.
 * Він лише визначає стабільний набір полів успішної відповіді.
 */
final class OrderResource extends Model
{
    public int $id;
    public int $clientId;
    public string $amount;
    public string $description;
    public string $status;
    public int $createdAt;

    public function __construct(Order $order, array $config = [])
    {
        $this->id = (int) $order->id;
        $this->clientId = (int) $order->client_id;
        $this->amount = $order->amount;
        $this->description = $order->description;
        $this->status = $order->status;
        $this->createdAt = (int) $order->created_at;

        parent::__construct($config);
    }

    public function fields(): array
    {
        return [
            'id',
            'clientId',
            'amount',
            'description',
            'status',
            'createdAt',
        ];
    }
}