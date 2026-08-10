<?php

declare(strict_types=1);

namespace app\contracts\results;

/**
 * Результат синхронної частини поповнення балансу.
 *
 * balanceAfterTopUp — це баланс одразу після зарахування коштів,
 * але до асинхронної оплати pending-замовлень.
 *
 * Фінальний баланс після виконання Queue Job потрібно отримувати
 * окремим запитом GET /clients/{id}.
 */
final readonly class TopUpResult
{
    public function __construct(
        public string $creditedAmount,
        public string $oldBalance,
        public string $balanceAfterTopUp
    ) {
    }
}
