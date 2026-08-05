<?php

declare(strict_types=1);

namespace app\contracts\results;

/**
 * Application-level error of an operation.
 *
 * Не містить в собі специфічних transport-data
 * HTTP status, JSON, redirect, HTML и т.д.
 */
final readonly class OperationError
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(public string $code, public array $details = []) {}
}
