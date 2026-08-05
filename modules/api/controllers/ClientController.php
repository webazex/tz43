<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\models\entities\Client;
use app\models\forms\client\CreateClientForm;
use app\resources\ClientResource;
use app\responses\OperationResponse;
use app\services\ClientService;
use yii\rest\Controller;

final class ClientController extends Controller
{
    private const HTTP_CREATED = 201;
    private const HTTP_CONFLICT = 409;
    private const HTTP_UNPROCESSABLE_ENTITY = 422;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

    public function __construct($id, $module, private readonly ClientService $clientService, $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * Визначає дозволені HTTP-методи для actions контролера.
     */
    protected function verbs(): array
    {
        return [
            'create' => ['POST'],
        ];
    }

    /**
     * Створює нового клієнта.
     */
    public function actionCreate(): OperationResponse
    {
        $result = $this->executeCreate($this->request->bodyParams);

        return $this->buildCreateResponse($result);
    }

    /**
     * Виконує application use case створення клієнта.
     *
     * @param array<string, mixed> $data
     * @return OperationResult<Client>
     */
    private function executeCreate(array $data): OperationResult
    {
        $form = new CreateClientForm();
        $form->load($data, '');

        if (!$form->validate()) {
            return OperationResult::failure(
                new OperationError(
                    code: OperationError::CODE_VALIDATION_FAILED,
                    details: [
                        'fields' => $form->getErrors(),
                    ],
                )
            );
        }

        return $this->clientService->create(
            (string) $form->name,
            (string) $form->email,
            (string) $form->balance,
            (string) $form->status,
        );
    }

    /**
     * Перетворює application-result у зовнішній response.
     *
     * @param OperationResult<Client> $result
     */
    private function buildCreateResponse(OperationResult $result): OperationResponse
    {
        if ($result->isFailure()) {
            $error = $result->error();

            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        $this->response->statusCode = self::HTTP_CREATED;

        return OperationResponse::success(
            new ClientResource($result->value())
        );
    }

    /**
     * Визначає HTTP status відповідно до application-помилки.
     */
    private function resolveFailureStatusCode(OperationError $error): int
    {
        return match ($error->code) {
            OperationError::CODE_VALIDATION_FAILED,
            ClientService::ERROR_CREATE_FAILED => self::HTTP_UNPROCESSABLE_ENTITY,

            ClientService::ERROR_DATA_CONFLICT => self::HTTP_CONFLICT,

            default => self::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
