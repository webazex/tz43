<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use yii\filters\AccessControl;
use yii\rest\Controller;
use yii\web\UnauthorizedHttpException;

/**
 * Базовий контролер REST API.
 *
 * Визначає спільні transport-specific правила для managem:contentReference[oaicite:0]{index=0}через поточну Yii session та CSRF-захист.
 */
abstract class ApiController extends Controller
{
    public $enableCsrfValidation = true;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@'],
                ],
            ],
            'denyCallback' => static function (): never {
                throw new UnauthorizedHttpException(
                    'Для доступу до API необхідна авторизація.'
                );
            },
        ];

        return $behaviors;
    }
}
