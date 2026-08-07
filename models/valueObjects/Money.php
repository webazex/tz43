<?php

declare(strict_types=1);

namespace app\models\valueObjects;

use InvalidArgumentException;

final class Money
{
    private function __construct(
        private readonly int $cents,
    ) {
    }

    public static function fromDecimal(string $amount): self
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException(
                sprintf('Invalid money amount: "%s"', $amount)
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '00'
        );

        $fraction = str_pad($fraction, 2, '0');

        return new self(
            ((int) $whole * 100) + (int) $fraction
        );
    }

    public function add(self $money): self
    {
        return new self(
            $this->cents + $money->cents
        );
    }

    public function subtract(self $money): self
    {
        return new self(
            $this->cents - $money->cents
        );
    }

    public function isEnoughFor(self $money): bool
    {
        return $this->cents >= $money->cents;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function toDecimal(): string
    {
        $whole = intdiv(abs($this->cents), 100);
        $fraction = abs($this->cents) % 100;

        return sprintf(
            '%s%d.%02d',
            $this->cents < 0 ? '-' : '',
            $whole,
            $fraction
        );
    }
}
