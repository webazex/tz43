<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\models\entities\Client;
use app\models\forms\client\CreateClientForm;
use app\modules\api\security\ApiTokenAuthenticator;
use app\resources\ClientResource;
use app\responses\OperationResponse;
use app\services\ClientService;

final class ClientController extends ApiController
{
    private const HTTP_CREATED = 201;
    private const HTTP_CONFLICT = 409;
    private const HTTP_UNPROCESSABLE_ENTITY = 422;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

    private const HTTP_NOT_FOUND = 404;

    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        $id,
        $module,
        ApiTokenAuthenticator $tokenAuthenticator,
        private readonly ClientService $clientService,
        $config = [],
    ) {
        parent::__construct(
            $id,
            $module,
            $tokenAuthenticator,
            $config,
        );
    }

    /**
     * Визначає дозволені HTTP-методи для actions контролера.
     */
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'create' => ['POST'],
            'view' => ['GET'],
        ];
    }

    /**
     * Повертає сторінку списку клієнтів.
     */
    public function actionIndex(): OperationResponse
    {
        $page = max(
            self::DEFAULT_PAGE,
            (int) $this->request->get('page', self::DEFAULT_PAGE),
        );

        $perPage = min(
            self::MAX_PER_PAGE,
            max(
                1,
                (int) $this->request->get('per-page', self::DEFAULT_PER_PAGE),
            ),
        );

        $result = $this->clientService->getList($page, $perPage);
        $totalCount = $result['totalCount'];

        $pageCount = $totalCount === 0
            ? 0
            : (int) ceil($totalCount / $perPage);

        $items = array_map(
            static fn (Client $client): ClientResource => new ClientResource($client),
            $result['items'],
        );

        $this->setPaginationHeaders(
            $page,
            $perPage,
            $pageCount,
            $totalCount,
        );

        return OperationResponse::success([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'pageCount' => $pageCount,
                'totalCount' => $totalCount,
            ],
        ]);
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
     * Додає метадані пагінації до HTTP headers.
     */
    private function setPaginationHeaders(
        int $page,
        int $perPage,
        int $pageCount,
        int $totalCount,
    ): void {
        $headers = $this->response->headers;

        $headers->set('X-Pagination-Current-Page', (string) $page);
        $headers->set('X-Pagination-Per-Page', (string) $perPage);
        $headers->set('X-Pagination-Page-Count', (string) $pageCount);
        $headers->set('X-Pagination-Total-Count', (string) $totalCount);
    }

    /**
     * Визначає HTTP status відповідно до application-помилки.
     */
    private function resolveFailureStatusCode(OperationError $error): int
    {
        return match ($error->code) {
            OperationError::CODE_VALIDATION_FAILED,
            ClientService::ERROR_CREATE_FAILED => self::HTTP_UNPROCESSABLE_ENTITY,
            ClientService::ERROR_NOT_FOUND => self::HTTP_NOT_FOUND,

            ClientService::ERROR_DATA_CONFLICT => self::HTTP_CONFLICT,

            default => self::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Повертає одного клієнта за його ID.
     */
    public function actionView(int $id): OperationResponse
    {
        $result = $this->clientService->getById($id);

        if ($result->isFailure()) {
            $error = $result->error();

            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        return OperationResponse::success(
            new ClientResource($result->value())
        );
    }
}
