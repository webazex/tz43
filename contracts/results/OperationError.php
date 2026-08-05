<?php

declare(strict_types=1);

namespace app\contracts\results;

/**
 * Помилка виконання application-операції.
 *
 * Не містить transport-specific даних:
 * HTTP status, JSON, redirect, HTML тощо.
 */
final readonly class OperationError
{
    public const CODE_VALIDATION_FAILED = 'VALIDATION_FAILED';

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(public string $code, public array $details = []) {}
}
