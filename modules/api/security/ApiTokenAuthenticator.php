<?php

declare(strict_types=1);

namespace app\modules\api\security;

use yii\web\Request;

/**
 * Перевіряє Bearer token API-клієнта.
 *
 * Не знає про Yii params, controllers, session або CSRF.
 * Отримує дозволені токени через DI.
 */
final readonly class ApiTokenAuthenticator
{
    /**
     * @param array<string, string> $tokens
     */
    public function __construct(private array $tokens) {}

    public function authenticate(Request $request): bool
    {
        $authorization = $request->headers->get('Authorization');

        if (
            !is_string($authorization)
            || !preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)
        ) {
            return false;
        }

        $providedToken = trim($matches[1]);

        if ($providedToken === '') {
            return false;
        }

        foreach ($this->tokens as $configuredToken) {
            if (
                $configuredToken !== ''
                && hash_equals($configuredToken, $providedToken)
            ) {
                return true;
            }
        }

        return false;
    }
}
