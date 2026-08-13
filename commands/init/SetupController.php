<?php

declare(strict_types=1);

namespace app\commands\init;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Оркестратор початкового налаштування застосунку.
 *
 * Основний виклик:
 *
 * php yii init/setup
 */
final class SetupController extends Controller
{
    /**
     * Дозволяє явно пропустити systemd Queue Worker,
     * наприклад у локальному development environment.
     *
     * php yii init/setup --skipQueueWorker=1
     */
    public int $skipQueueWorker = 0;

    public function options($actionID): array
    {
        return array_merge(
            parent::options($actionID),
            [
                'skipQueueWorker',
            ]
        );
    }

    public function actionIndex(): int
    {
        if (!$this->interactive) {
            $this->stderr(
                "Початкове налаштування потребує інтерактивного режиму.\n",
                Console::FG_RED
            );

            return ExitCode::USAGE;
        }

        $this->stdout(
            "\n=== Початкове налаштування застосунку ===\n\n",
            Console::FG_CYAN,
            Console::BOLD
        );

        $this->stdout(
            "[1/3] Застосування міграцій...\n",
            Console::FG_YELLOW,
            Console::BOLD
        );

        $migrationExitCode = $this->normalizeExitCode(
            $this->run(
                '/migrate/up',
                [
                    'interactive' => false,
                ]
            )
        );

        if ($migrationExitCode !== ExitCode::OK) {
            $this->stderr(
                "\nНалаштування зупинено: не вдалося застосувати міграції.\n",
                Console::FG_RED,
                Console::BOLD
            );

            return $migrationExitCode;
        }

        $this->stdout(
            "\n[2/3] Створення адміністратора...\n",
            Console::FG_YELLOW,
            Console::BOLD
        );

        $administratorExitCode = $this->normalizeExitCode(
            $this->run(
                '/init/default-user/create'
            )
        );

        if ($administratorExitCode !== ExitCode::OK) {
            $this->stderr(
                "\nНалаштування не завершено: адміністратора не створено.\n",
                Console::FG_RED,
                Console::BOLD
            );

            return $administratorExitCode;
        }

        $this->stdout(
            "\n[3/3] Налаштування Queue Worker...\n",
            Console::FG_YELLOW,
            Console::BOLD
        );

        if ($this->skipQueueWorker === 1) {
            $this->stdout(
                "Queue Worker пропущено через --skipQueueWorker=1.\n",
                Console::FG_YELLOW
            );
        } else {
            $workerExitCode = $this->normalizeExitCode(
                $this->run(
                    '/init/queue-worker/install'
                )
            );

            if ($workerExitCode !== ExitCode::OK) {
                $this->stderr(
                    "\nБазове налаштування виконано, "
                    . "але постійний Queue Worker не встановлено.\n",
                    Console::FG_RED,
                    Console::BOLD
                );

                $this->stderr(
                    "Без Queue Worker асинхронна обробка "
                    . "pending-замовлень працювати не буде.\n"
                );

                return $workerExitCode;
            }
        }

        $this->stdout(
            "\n=== Налаштування застосунку успішно завершено ===\n",
            Console::FG_GREEN,
            Console::BOLD
        );

        return ExitCode::OK;
    }

    private function normalizeExitCode(
        mixed $exitCode
    ): int {
        return is_int($exitCode)
            ? $exitCode
            : ExitCode::UNSPECIFIED_ERROR;
    }
}
