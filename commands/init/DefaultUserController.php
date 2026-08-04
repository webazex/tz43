<?php

declare(strict_types=1);

namespace app\commands\init;

use app\models\entities\User;
use RuntimeException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\IntegrityException;
use yii\helpers\Console;

/**
 * Команди початкової ініціалізації користувачів системи.
 *
 * Контролер розташований у вкладеному namespace `app\commands\init`,
 * тому Yii2 формує для нього маршрут:
 *
 * php yii init/default-user/create
 */
final class DefaultUserController extends Controller
{
    /**
     * Інтерактивно створює користувача адміністративної панелі.
     *
     * Команда призначена для первинного налаштування середовища.
     * Відкритий пароль не передається аргументом командного рядка
     * та не зберігається в базі даних.
     */
    public function actionCreate(): int
    {
        /**
         * Команду не можна виконувати з --interactive=0, оскільки
         * облікові дані навмисно не передаються через CLI-аргументи.
         */
        if (!$this->interactive) {
            $this->stderr("Команда потребує інтерактивного режиму.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $this->stdout(
            "\n=== Створення адміністративного запису ===\n\n",
            Console::FG_CYAN,
            Console::BOLD
        );

        $username = trim($this->prompt(
            'Введіть імʼя адміністративного користувача:',
            [
                'required' => true,
                'validator' => static function (string $input, ?string &$error): bool {
                    if (mb_strlen(trim($input)) < 3) {
                        $error = 'Логін повинен містити щонайменше 3 символи.';

                        return false;
                    }

                    return true;
                },
            ]
        ));

        $email = trim($this->prompt(
            'Введіть email адміністратора:',
            [
                'required' => true,
                'validator' => static function (string $input, ?string &$error): bool {
                    if (filter_var(trim($input), FILTER_VALIDATE_EMAIL) === false) {
                        $error = 'Введіть коректну email-адресу.';

                        return false;
                    }

                    return true;
                },
            ]
        ));

        try {
            $password = $this->promptPassword();
        } catch (RuntimeException $exception) {
            $this->stderr(
                $exception->getMessage() . PHP_EOL,
                Console::FG_RED
            );

            return ExitCode::CONFIG;
        }

        $this->stdout(PHP_EOL);

        if (!$this->confirm(
            sprintf(
                'Створити адміністратора "%s" з email "%s"?',
                $username,
                $email
            )
        )) {
            $this->stdout(
                "Операцію скасовано користувачем.\n",
                Console::FG_YELLOW
            );

            /**
             * Для окремого виклику скасування не є технічною помилкою.
             * Але для Setup відсутність адміністратора означає, що початкове
             * налаштування не було завершено.
             */
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $user = new User([
            'username' => $username,
            'email' => $email,
            'status' => User::STATUS_ACTIVE,
        ]);

        /**
         * Використовуємо методи самої сутності User, щоб правила
         * створення password_hash та auth_key не дублювалися
         * в різних контролерах і сервісах.
         */
        $security = Yii::$app->getSecurity();

        $user->setPassword($password, $security);
        $user->generateAuthKey($security);

        try {
            if (!$user->save()) {
                $this->printValidationErrors($user);

                return ExitCode::DATAERR;
            }
        } catch (IntegrityException) {
            /**
             * Перехоплюємо можливе порушення UNIQUE-індексу.
             *
             * Звичайні дублікати знайде валідатор ActiveRecord, але
             * індекс БД залишається остаточним захистом від одночасного
             * створення однакових облікових записів.
             */
            $this->stderr(
                "Користувач із таким логіном або email вже існує.\n",
                Console::FG_RED
            );

            return ExitCode::DATAERR;
        }

        $this->stdout(
            sprintf(
                "\nАдміністратора успішно створено. ID: %d\n",
                $user->getId()
            ),
            Console::FG_GREEN,
            Console::BOLD
        );

        return ExitCode::OK;
    }

    /**
     * Запитує пароль та його підтвердження.
     *
     * Мінімальна довжина перевіряється тут, оскільки відкритий пароль
     * не є атрибутом ActiveRecord-моделі User і не повинен потрапляти
     * до її властивостей.
     */
    private function promptPassword(): string
    {
        while (true) {
            $password = $this->readHiddenInput(
                'Введіть пароль адміністратора'
            );

            if (mb_strlen($password) < 12) {
                $this->stderr(
                    "Пароль повинен містити щонайменше 12 символів.\n",
                    Console::FG_RED
                );

                continue;
            }

            $confirmation = $this->readHiddenInput(
                'Повторіть пароль адміністратора'
            );

            if ($password !== $confirmation) {
                $this->stderr(
                    "Введені паролі не збігаються. Спробуйте ще раз.\n",
                    Console::FG_RED
                );

                continue;
            }

            return $password;
        }
    }

    /**
     * Читає значення зі STDIN із вимкненим відображенням символів.
     *
     * Yii2 не підтримує параметр `hidden` у методі prompt(), тому
     * для Linux/macOS тимчасово вимикається terminal echo через stty.
     *
     * Початковий режим термінала обов'язково відновлюється у finally.
     */
    private function readHiddenInput(string $label): string
    {
        if (
            Console::isRunningOnWindows()
            || !function_exists('shell_exec')
        ) {
            throw new RuntimeException(
                'Поточне середовище не підтримує приховане введення пароля.'
            );
        }

        $terminalMode = trim(
            (string) shell_exec('stty -g 2>/dev/null')
        );

        if ($terminalMode === '') {
            throw new RuntimeException(
                'Не вдалося отримати поточні налаштування термінала.'
            );
        }

        $this->stdout($label . ': ');

        shell_exec('stty -echo 2>/dev/null');

        try {
            return (string) Console::stdin();
        } finally {
            shell_exec(
                'stty '
                . escapeshellarg($terminalMode)
                . ' 2>/dev/null'
            );

            $this->stdout(PHP_EOL);
        }
    }

    /**
     * Виводить помилки валідації ActiveRecord у зрозумілому вигляді.
     */
    private function printValidationErrors(User $user): void
    {
        $this->stderr(
            "Адміністратора не створено:\n",
            Console::FG_RED,
            Console::BOLD
        );

        foreach ($user->getFirstErrors() as $attribute => $error) {
            $this->stderr(
                sprintf("- %s: %s\n", $attribute, $error),
                Console::FG_RED
            );
        }
    }
}
