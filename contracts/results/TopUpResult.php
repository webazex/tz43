<?php

declare(strict_types=1);

namespace app\contracts\results;

final readonly class TopUpResult
{
    public function __construct(
        public string $oldBalance,
        public string $newBalance
    ) {
    }
}
