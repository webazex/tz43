<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use app\modules\api\security\ApiTokenAuthenticator;
use Yii;
use yii\filters\AccessControl;
use yii\rest\Controller;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Базовий контролер REST API.
 *
 * Визначає спільну transport/security policy management API:
 * JSON response, authentication та CSRF policy.
 */
abstract class ApiController extends Controller
{
    public $enableCsrfValidation = true;

    private bool $accessTokenAuthenticated = false;

    public function __construct(
        $id,
        $module,
        private readonly ApiTokenAuthenticator $tokenAuthenticator,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function beforeAction($action): bool
    {
        $this->accessTokenAuthenticated = $this->tokenAuthenticator->authenticate(
            $this->request
        );

        if ($this->accessTokenAuthenticated) {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

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
                    'matchCallback' => fn (): bool =>
                        !Yii::$app->user->isGuest
                        || $this->accessTokenAuthenticated,
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
