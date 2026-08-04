<?php

declare(strict_types=1);

namespace app\commands\init;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Оркестратор початкового налаштування застосунку.
 *
 * Контролер не містить власної логіки міграцій або створення користувача.
 * Він послідовно запускає вже наявні консольні команди та контролює
 * їхні коди завершення.
 *
 * Основний виклик:
 *
 * php yii init/setup
 */
final class SetupController extends Controller
{
    /**
     * Виконує початкове налаштування застосунку.
     *
     * Етапи:
     * 1. Застосування всіх нових міграцій.
     * 2. Інтерактивне створення адміністративного користувача.
     */
    public function actionIndex(): int
    {
        /**
         * Створення адміністратора потребує введення логіна, email
         * та пароля. Тому повністю неінтерактивний запуск Setup
         * для поточної реалізації не підтримується.
         */
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
            "[1/2] Застосування міграцій...\n",
            Console::FG_YELLOW,
            Console::BOLD
        );

        /**
         * Міграції запускаються без додаткового підтвердження,
         * оскільки сам Setup уже є явною командою налаштування.
         */
        $migrationExitCode = $this->normalizeExitCode(
            $this->run('/migrate/up', ['interactive' => false])
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
            "\n[2/2] Створення адміністратора...\n",
            Console::FG_YELLOW,
            Console::BOLD
        );

        /**
         * Юзаємо команду створення адміністратора.
         *
         * Setup не дублює запити даних, валідацію пароля,
         * генерацію password_hash або збереження User.
         */
        $administratorExitCode = $this->normalizeExitCode(
            $this->run('/init/default-user/create')
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
            "\n=== Налаштування застосунку успішно завершено ===\n",
            Console::FG_GREEN,
            Console::BOLD
        );

        return ExitCode::OK;
    }

    /**
     * Нормалізує результат виконання вкладеної console action.
     *
     * Controller::run() формально повертає mixed, хоча обидві наші
     * команди повинні повертати ціле число — CLI exit code.
     * Неочікуваний результат == помилка виконання.
     */
    private function normalizeExitCode(mixed $exitCode): int
    {
        return is_int($exitCode)
            ? $exitCode
            : ExitCode::UNSPECIFIED_ERROR;
    }
}
