<?php

declare(strict_types=1);

namespace app\models\entities\enums;

/**
 * Стан замовлення.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Canceled = 'canceled';
}
