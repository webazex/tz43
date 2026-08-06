<?php

declare(strict_types=1);

namespace app\models\entities\enums;

/**
 * Стан обробки pending-замовлень клієнта.
 */
enum ClientPendingProcessingStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Processing = 'processing';
    case Failed = 'failed';
}
