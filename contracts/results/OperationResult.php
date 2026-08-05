<?php

declare(strict_types=1);

namespace app\contracts\results;

use LogicException;

/**
 * Результат виконання операцій в додатку
 *
 * Має тільки два стани: успіх, помилка
 *
 * success -> value
 * failure -> OperationError
 *
 * @template-covariant T
 */
final readonly class OperationResult
{
    /**
     * @var T|null
     */
    private mixed $value;

    private ?OperationError $error;

    /**
     * @param T|null $value
     */
    private function __construct(private bool $success, mixed $value, ?OperationError $error) {
        $this->value = $value;
        $this->error = $error;
    }

    /**
     * @template TValue
     *
     * @param TValue $value
     *
     * @return self<TValue>
     */
    public static function success(mixed $value): self
    {
        return new self(
            success: true,
            value: $value,
            error: null,
        );
    }

    /**
     * @return self<never>
     */
    public static function failure(OperationError $error): self
    {
        return new self(
            success: false,
            value: null,
            error: $error,
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * @return T
     */
    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw new LogicException(
                'A failed operation result does not contain a value.'
            );
        }

        return $this->value;
    }

    public function error(): OperationError
    {
        if ($this->isSuccess() || $this->error === null) {
            throw new LogicException(
                'A successful operation result does not contain an error.'
            );
        }

        return $this->error;
    }
}
