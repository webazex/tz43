<?php

declare(strict_types=1);

namespace app\commands\init;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Налаштовує постійний yii2-queue worker як systemd service.
 *
 * Основні команди:
 *
 * php yii init/queue-worker/install
 * php yii init/queue-worker/check
 *
 * Install є idempotent:
 * повторний запуск оновить unit-файл, виконає daemon-reload
 * та переконається, що service enabled + running.
 */
final class QueueWorkerController extends Controller
{
    /**
     * Ім'я systemd service без обов'язкового суфікса .service.
     */
    public string $serviceName = 'tz43-queue';

    /**
     * OS user, від якого працюватиме worker.
     *
     * Якщо не передано явно, команда спробує визначити власника yii-файлу.
     */
    public ?string $user = null;

    public function options($actionID): array
    {
        return array_merge(
            parent::options($actionID),
            [
                'serviceName',
                'user',
            ]
        );
    }

    /**
     * Встановлює та запускає постійний Queue Worker.
     */
    public function actionInstall(): int
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->stderr(
                "Автоматичне встановлення Queue Worker підтримується тільки на Linux.\n",
                Console::FG_RED
            );

            $this->printManualInstructions();

            return ExitCode::UNAVAILABLE;
        }

        if (
            !$this->commandExists('systemctl')
            || !is_dir('/run/systemd/system')
        ) {
            $this->stderr(
                "systemd не знайдено або він не є активним init system.\n",
                Console::FG_RED
            );

            $this->printManualInstructions();

            return ExitCode::UNAVAILABLE;
        }

        if (!$this->commandExists('install')) {
            $this->stderr(
                "Системна команда `install` недоступна.\n",
                Console::FG_RED
            );

            return ExitCode::UNAVAILABLE;
        }

        $serviceName = $this->normalizeServiceName(
            $this->serviceName
        );

        if ($serviceName === null) {
            $this->stderr(
                "Некоректне ім'я systemd service.\n",
                Console::FG_RED
            );

            return ExitCode::USAGE;
        }

        $appPath = realpath(
            Yii::getAlias('@app')
        );

        if ($appPath === false) {
            $this->stderr(
                "Не вдалося визначити application path.\n",
                Console::FG_RED
            );

            return ExitCode::OSFILE;
        }

        $yiiPath = $appPath . DIRECTORY_SEPARATOR . 'yii';

        if (!is_file($yiiPath)) {
            $this->stderr(
                "Не знайдено console entry point: {$yiiPath}\n",
                Console::FG_RED
            );

            return ExitCode::NOINPUT;
        }

        $phpBinary = realpath(PHP_BINARY);

        if ($phpBinary === false || !is_file($phpBinary)) {
            $this->stderr(
                "Не вдалося визначити PHP CLI binary.\n",
                Console::FG_RED
            );

            return ExitCode::NOINPUT;
        }

        $workerUser = $this->resolveWorkerUser(
            $yiiPath
        );

        if ($workerUser === null) {
            return ExitCode::USAGE;
        }

        if (!$this->systemUserExists($workerUser)) {
            $this->stderr(
                "OS user `{$workerUser}` не існує.\n",
                Console::FG_RED
            );

            return ExitCode::NOUSER;
        }

        $unitName = $serviceName . '.service';
        $unitPath = '/etc/systemd/system/' . $unitName;

        $temporaryUnitPath = sprintf(
            '%s/%s.%d',
            sys_get_temp_dir(),
            $unitName,
            getmypid()
        );

        $unitContents = $this->buildUnitFile(
            $workerUser,
            $appPath,
            $phpBinary,
            $yiiPath
        );

        if (
            file_put_contents(
                $temporaryUnitPath,
                $unitContents
            ) === false
        ) {
            $this->stderr(
                "Не вдалося створити тимчасовий systemd unit.\n",
                Console::FG_RED
            );

            return ExitCode::CANTCREAT;
        }

        $this->stdout(
            "\nQueue Worker configuration:\n",
            Console::FG_CYAN,
            Console::BOLD
        );

        $this->stdout("  Application: {$appPath}\n");
        $this->stdout("  PHP:         {$phpBinary}\n");
        $this->stdout("  User:        {$workerUser}\n");
        $this->stdout("  Service:     {$unitName}\n\n");

        try {
            if (
                $this->runCommand(
                    [
                        'install',
                        '-m',
                        '0644',
                        $temporaryUnitPath,
                        $unitPath,
                    ],
                    true
                ) !== ExitCode::OK
            ) {
                $this->stderr(
                    "Не вдалося встановити systemd unit.\n",
                    Console::FG_RED
                );

                return ExitCode::CANTCREAT;
            }

            $this->stdout(
                "✓ systemd unit installed\n",
                Console::FG_GREEN
            );

            if (
                $this->runCommand(
                    [
                        'systemctl',
                        'daemon-reload',
                    ],
                    true
                ) !== ExitCode::OK
            ) {
                $this->stderr(
                    "Не вдалося виконати systemctl daemon-reload.\n",
                    Console::FG_RED
                );

                return ExitCode::OSERR;
            }

            $this->stdout(
                "✓ systemd daemon reloaded\n",
                Console::FG_GREEN
            );

            if (
                $this->runCommand(
                    [
                        'systemctl',
                        'enable',
                        '--now',
                        $unitName,
                    ],
                    true
                ) !== ExitCode::OK
            ) {
                $this->stderr(
                    "Не вдалося enable/start Queue Worker.\n",
                    Console::FG_RED
                );

                return ExitCode::OSERR;
            }

            $this->stdout(
                "✓ Queue Worker enabled\n",
                Console::FG_GREEN
            );

            if (
                $this->runCommand(
                    [
                        'systemctl',
                        'is-active',
                        '--quiet',
                        $unitName,
                    ]
                ) !== ExitCode::OK
            ) {
                $this->stderr(
                    "\nQueue Worker встановлено, але service не active.\n",
                    Console::FG_RED,
                    Console::BOLD
                );

                $this->stderr(
                    "Перевірте:\n"
                    . "  systemctl status {$unitName}\n"
                    . "  journalctl -u {$unitName} -n 50 --no-pager\n"
                );

                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout(
                "✓ Queue Worker is active\n",
                Console::FG_GREEN
            );
        } finally {
            @unlink($temporaryUnitPath);
        }

        return ExitCode::OK;
    }

    /**
     * Перевіряє systemd service Queue Worker.
     */
    public function actionCheck(): int
    {
        if (
            PHP_OS_FAMILY !== 'Linux'
            || !$this->commandExists('systemctl')
        ) {
            $this->stderr(
                "systemctl недоступний.\n",
                Console::FG_RED
            );

            return ExitCode::UNAVAILABLE;
        }

        $serviceName = $this->normalizeServiceName(
            $this->serviceName
        );

        if ($serviceName === null) {
            return ExitCode::USAGE;
        }

        $unitName = $serviceName . '.service';

        $enabled = $this->runCommand(
                [
                    'systemctl',
                    'is-enabled',
                    '--quiet',
                    $unitName,
                ]
            ) === ExitCode::OK;

        $active = $this->runCommand(
                [
                    'systemctl',
                    'is-active',
                    '--quiet',
                    $unitName,
                ]
            ) === ExitCode::OK;

        $this->stdout(
            sprintf(
                "Service: %s\nEnabled: %s\nActive:  %s\n",
                $unitName,
                $enabled ? 'yes' : 'no',
                $active ? 'yes' : 'no'
            )
        );

        return $enabled && $active
            ? ExitCode::OK
            : ExitCode::UNSPECIFIED_ERROR;
    }

    private function resolveWorkerUser(
        string $yiiPath
    ): ?string {
        if ($this->user !== null) {
            $user = trim($this->user);

            return $this->isValidUserName($user)
                ? $user
                : null;
        }

        $detectedUser = $this->detectFileOwner(
            $yiiPath
        );

        if (
            $detectedUser === 'root'
            && !$this->isRoot()
        ) {
            $currentUser = $this->detectCurrentUser();

            if ($currentUser !== null) {
                $detectedUser = $currentUser;
            }
        }

        if (!$this->interactive) {
            if (
                $detectedUser === null
                || $detectedUser === 'root'
            ) {
                $this->stderr(
                    "Не вдалося безпечно визначити OS user для Queue Worker.\n"
                    . "Передайте його явно через --user.\n",
                    Console::FG_RED
                );

                return null;
            }

            return $detectedUser;
        }

        $options = [];

        if (
            $detectedUser !== null
            && $detectedUser !== 'root'
        ) {
            $options['default'] = $detectedUser;
        }

        $workerUser = trim(
            (string) $this->prompt(
                'OS user для Queue Worker',
                $options
            )
        );

        if (!$this->isValidUserName($workerUser)) {
            $this->stderr(
                "Некоректне ім'я OS user.\n",
                Console::FG_RED
            );

            return null;
        }

        return $workerUser;
    }

    private function detectFileOwner(
        string $path
    ): ?string {
        $ownerId = @fileowner($path);

        if (
            $ownerId !== false
            && function_exists('posix_getpwuid')
        ) {
            $owner = posix_getpwuid($ownerId);

            if (
                is_array($owner)
                && isset($owner['name'])
                && is_string($owner['name'])
            ) {
                return $owner['name'];
            }
        }

        $output = [];
        $exitCode = ExitCode::UNSPECIFIED_ERROR;

        exec(
            'stat -c %U '
            . escapeshellarg($path)
            . ' 2>/dev/null',
            $output,
            $exitCode
        );

        if (
            $exitCode === ExitCode::OK
            && isset($output[0])
            && trim($output[0]) !== ''
        ) {
            return trim($output[0]);
        }

        return null;
    }

    private function detectCurrentUser(): ?string
    {
        if (
            function_exists('posix_geteuid')
            && function_exists('posix_getpwuid')
        ) {
            $user = posix_getpwuid(
                posix_geteuid()
            );

            if (
                is_array($user)
                && isset($user['name'])
                && is_string($user['name'])
            ) {
                return $user['name'];
            }
        }

        $output = [];
        $exitCode = ExitCode::UNSPECIFIED_ERROR;

        exec(
            'id -un 2>/dev/null',
            $output,
            $exitCode
        );

        return $exitCode === ExitCode::OK
        && isset($output[0])
            ? trim($output[0])
            : null;
    }

    private function systemUserExists(
        string $user
    ): bool {
        $output = [];
        $exitCode = ExitCode::UNSPECIFIED_ERROR;

        exec(
            'id -u '
            . escapeshellarg($user)
            . ' >/dev/null 2>&1',
            $output,
            $exitCode
        );

        return $exitCode === ExitCode::OK;
    }

    private function isValidUserName(
        string $user
    ): bool {
        return preg_match(
                '/^[a-z_][a-z0-9_-]*[$]?$/i',
                $user
            ) === 1;
    }

    private function normalizeServiceName(
        string $serviceName
    ): ?string {
        $serviceName = trim($serviceName);

        if (str_ends_with($serviceName, '.service')) {
            $serviceName = substr(
                $serviceName,
                0,
                -strlen('.service')
            );
        }

        if (
            preg_match(
                '/^[a-zA-Z0-9_.@-]+$/',
                $serviceName
            ) !== 1
        ) {
            return null;
        }

        return $serviceName;
    }

    private function buildUnitFile(
        string $workerUser,
        string $workingDirectory,
        string $phpBinary,
        string $yiiPath
    ): string {
        $workingDirectory = $this->quoteSystemdValue(
            $workingDirectory
        );

        $phpBinary = $this->quoteSystemdValue(
            $phpBinary
        );

        $yiiPath = $this->quoteSystemdValue(
            $yiiPath
        );

        return <<<UNIT
[Unit]
Description=TZ43 Yii2 Queue Worker
After=network.target

[Service]
Type=simple
User={$workerUser}
WorkingDirectory={$workingDirectory}
ExecStart={$phpBinary} {$yiiPath} queue/listen
Restart=always
RestartSec=3
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target

UNIT;
    }

    private function quoteSystemdValue(
        string $value
    ): string {
        $escaped = str_replace(
            [
                '\\',
                '"',
            ],
            [
                '\\\\',
                '\\"',
            ],
            $value
        );

        return '"' . $escaped . '"';
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(
        array $arguments,
        bool $privileged = false
    ): int {
        $command = implode(
            ' ',
            array_map(
                static fn (string $argument): string =>
                escapeshellarg($argument),
                $arguments
            )
        );

        if ($privileged && !$this->isRoot()) {
            if (!$this->commandExists('sudo')) {
                $this->stderr(
                    "Для встановлення systemd service потрібні root-права, "
                    . "але команда sudo недоступна.\n",
                    Console::FG_RED
                );

                return ExitCode::NOPERM;
            }

            $command = 'sudo -- ' . $command;
        }

        passthru(
            $command,
            $exitCode
        );

        return is_int($exitCode)
            ? $exitCode
            : ExitCode::UNSPECIFIED_ERROR;
    }

    private function commandExists(
        string $command
    ): bool {
        $output = [];
        $exitCode = ExitCode::UNSPECIFIED_ERROR;

        exec(
            'command -v '
            . escapeshellarg($command)
            . ' >/dev/null 2>&1',
            $output,
            $exitCode
        );

        return $exitCode === ExitCode::OK;
    }

    private function isRoot(): bool
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid() === 0;
        }

        $output = [];
        $exitCode = ExitCode::UNSPECIFIED_ERROR;

        exec(
            'id -u 2>/dev/null',
            $output,
            $exitCode
        );

        return $exitCode === ExitCode::OK
            && isset($output[0])
            && trim($output[0]) === '0';
    }

    private function printManualInstructions(): void
    {
        $this->stdout(
            "\nНалаштуйте постійний worker через process manager:\n\n"
            . "  php yii queue/listen\n\n"
            . "Наприклад, через systemd або Supervisor.\n"
        );
    }
}
