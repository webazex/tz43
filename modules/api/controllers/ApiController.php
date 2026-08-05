<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use yii\filters\AccessControl;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Базовий контролер REST API.
 *
 * Визначає спільні transport-specific правила для management API:
 * JSON response, авторизацію через поточну Yii session та CSRF-захист.
 */
abstract class ApiController extends Controller
{
    public $enableCsrfValidation = true;

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator']['formats'] = [
            'application/json' => Response::FORMAT_JSON,
        ];

        $behaviors['contentNegotiator']['formatParam'] = null;

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
