<?php

declare(strict_types=1);

namespace app\services;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\models\entities\Client;
use yii\db\IntegrityException;

final class ClientService
{
    public const ERROR_CREATE_FAILED = 'CLIENT_CREATE_FAILED';
    public const ERROR_DATA_CONFLICT = 'CLIENT_DATA_CONFLICT';
    public const ERROR_NOT_FOUND = 'CLIENT_NOT_FOUND';

    /**
     * Беремо вже готовий набір даних про клієнта і створюємо клієнта.
     * Метод не знає і не має знати джерело даних, виконувати якісь транспортні операції чи специфічну обробку.
     * API, CLI, умовний Admin, і т.д. - виконують один і той самий app-use-case.
     * Тому власне тут і є OperationResult
     *
     * @return OperationResult<Client>
     */
    public function create(string $name, string $email, string $balance, string $status): OperationResult
    {
        $client = new Client([
            'name' => $name,
            'email' => $email,
            'balance' => $balance,
            'status' => $status,
        ]);

        try {
            if (!$client->save()) {
                return OperationResult::failure(
                    new OperationError(
                        code: self::ERROR_CREATE_FAILED,
                        details: [
                            'validationErrors' => $client->getErrors(),
                        ],
                    )
                );
            }
        } catch (IntegrityException) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_DATA_CONFLICT,
                )
            );
        }

        return OperationResult::success($client);
    }

    /**
     * Повертає клієнта за його ID.
     *
     * @return OperationResult<Client>
     */
    public function getById(int $id): OperationResult
    {
        $client = Client::findOne($id);

        if ($client === null) {
            return OperationResult::failure(
                new OperationError(
                    code: self::ERROR_NOT_FOUND,
                    details: [
                        'id' => $id,
                    ],
                )
            );
        }

        return OperationResult::success($client);
    }

    /**
     * Повертає сторінку списку клієнтів.
     *
     * @return array{items: list<Client>, totalCount: int}
     */
    public function getList(int $page, int $perPage): array
    {
        $query = Client::find()
            ->orderBy(['id' => SORT_ASC]);

        $totalCount = (int) (clone $query)->count();

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        return [
            'items' => $items,
            'totalCount' => $totalCount,
        ];
    }
}
