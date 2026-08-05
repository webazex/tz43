<?php

declare(strict_types=1);

namespace app\modules\api;

use yii\base\Module as BaseModule;

/**
 * REST API модуль застосунку.
 *
 * Відокремлює HTTP/API transport-рівень від web-контролерів,
 * не дублюючи application та domain logic.
 */
final class Module extends BaseModule
{
    public $controllerNamespace = 'app\modules\api\controllers';
}
