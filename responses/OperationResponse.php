<?php

declare(strict_types=1);

namespace app\responses;

use app\contracts\results\OperationError;
use yii\base\Model;

/**
 * Єдиний зовнішній формат результату application-операції.
 *
 * Не містить HTTP status, headers або іншої transport-specific логіки.
 * Визначає лише стабільну структуру даних, яку можна однаково
 * використовувати в API, Admin та інших зовнішніх точках входу.
 */
final class OperationResponse extends Model
{
    private bool $success;
    private mixed $data;
    private ?OperationError $error;

    private function __construct(bool $success, mixed $data, ?OperationError $error, array $config = [])
    {
        $this->success = $success;
        $this->data = $data;
        $this->error = $error;

        parent::__construct($config);
    }

    public static function success(mixed $data): self
    {
        return new self(
            success: true,
            data: $data,
            error: null,
        );
    }

    public static function failure(OperationError $error): self
    {
        return new self(
            success: false,
            data: null,
            error: $error,
        );
    }

    public function fields(): array
    {
        return [
            'success' => fn (): bool => $this->success,
            'data' => fn (): mixed => $this->data,
            'error' => fn (): ?array => $this->serializeError(),
        ];
    }

    /**
     * Перетворює application-помилку у зовнішнє представлення.
     *
     * @return array{code: string, details: array<string, mixed>}|null
     */
    private function serializeError(): ?array
    {
        if ($this->error === null) {
            return null;
        }

        return [
            'code' => $this->error->code,
            'details' => $this->error->details,
        ];
    }
}
