<?php

declare(strict_types=1);

namespace app\models\valueObjects;

use InvalidArgumentException;
use OverflowException;

/**
 * Незмінний об'єкт точного грошового значення.
 *
 * Усередині сума зберігається в цілих копійках. Це дозволяє
 * виконувати арифметику без похибок FLOAT.
 *
 * Зовнішнє десяткове значення приймається лише як string.
 * Негативне значення може з'явитися лише внаслідок внутрішньої
 * операції subtract() і має бути явно перевірене через isNegative().
 */
final class Money
{
    /**
     * Максимальне значення DECIMAL(12,2), представлене в копійках:
     *
     * 9 999 999 999.99 = 999 999 999 999 копійок.
     */
    private const MAX_CENTS = 999_999_999_999;

    private function __construct(private readonly int $cents)
    {
        if (abs($this->cents) > self::MAX_CENTS) {
            throw new OverflowException(
                'Грошове значення перевищує допустимий діапазон DECIMAL(12,2).'
            );
        }
    }

    /**
     * Створює Money з невід'ємного десяткового string-значення.
     *
     * Float навмисно не приймається, оскільки він може вже містити
     * похибку двійкового представлення.
     */
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

        return new self(((int) $whole * 100) + (int) $fraction);
    }

    /**
     * Повертає новий об'єкт із сумою двох грошових значень.
     */
    public function add(self $money): self
    {
        return new self($this->cents + $money->cents);
    }

    /**
     * Повертає новий об'єкт із результатом віднімання.
     *
     * Метод не забороняє від'ємний результат: бізнес-сценарій
     * має перевірити достатність коштів до виконання списання.
     */
    public function subtract(self $money): self
    {
        return new self($this->cents - $money->cents);
    }

    /**
     * Перевіряє, чи достатньо поточного значення для списання.
     */
    public function isEnoughFor(self $money): bool
    {
        return $this->cents >= $money->cents;
        //return $this->cents > $money->cents;// розкоментувати для функціонального тесту Orders
        // на неправильне значення грошей
    }

    /**
     * Перевіряє нульове значення без прямого доступу до cents.
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * Перевіряє, чи є значення від'ємним.
     */
    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    /**
     * Повертає внутрішнє представлення в копійках.
     *
     * Метод корисний для низькорівневих порівнянь і тестування.
     */
    public function cents(): int
    {
        return $this->cents;
    }

    /**
     * Повертає нормалізоване десяткове представлення.
     *
     * Приклади:
     * 100   → 100.00
     * 100.5 → 100.50
     * 0.01  → 0.01
     */
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
