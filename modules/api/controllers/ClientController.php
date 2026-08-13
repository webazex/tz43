<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\contracts\results\TopUpResult;
use app\models\entities\Client;
use app\models\forms\client\CreateClientForm;
use app\models\forms\client\SearchClientsForm;
use app\models\forms\client\TopUpClientForm;
use app\models\forms\client\UpdateClientForm;
use app\modules\api\security\ApiTokenAuthenticator;
use app\resources\ClientResource;
use app\responses\OperationResponse;
use app\services\ClientService;

final class ClientController extends ApiController
{
    private const HTTP_CREATED = 201;
    private const HTTP_ACCEPTED = 202;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_CONFLICT = 409;
    private const HTTP_UNPROCESSABLE_ENTITY = 422;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

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
        parent::__construct($id, $module, $tokenAuthenticator, $config);
    }

    /**
     * Визначає дозволені HTTP-методи для actions контролера.
     */
    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'search' => ['GET'],
            'create' => ['POST'],
            'update' => ['PATCH'],
            'view' => ['GET'],
            'top-up' => ['POST'],
        ];
    }

    /**
     * Повертає сторінку списку клієнтів без фільтрів.
     */
    public function actionIndex(): OperationResponse
    {
        $page = max(
            self::DEFAULT_PAGE,
            (int) $this->request->get('page', self::DEFAULT_PAGE),
        );

        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $this->request->get('per-page', self::DEFAULT_PER_PAGE)),
        );

        $result = $this->clientService->getList($page, $perPage);
        $totalCount = $result['totalCount'];
        $pageCount = $totalCount === 0 ? 0 : (int) ceil($totalCount / $perPage);

        $items = array_map(
            static fn (Client $client): ClientResource => new ClientResource($client),
            $result['items'],
        );

        $this->setPaginationHeaders($page, $perPage, $pageCount, $totalCount);

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
     * Виконує серверний пошук/фільтрацію клієнтів.
     *
     * Controller лише адаптує query string до Form Model. Семантика SQL
     * залишається в ClientService, а transport validation — у Form Model.
     */
    public function actionSearch(): OperationResponse
    {
        $form = new SearchClientsForm();
        $form->load(
            [
                'field' => $this->request->get('field'),
                'value' => $this->request->get('value'),
                'like' => $this->request->get('like'),
                'status' => $this->request->get('status'),
                'relation' => $this->request->get('relation'),
                'balance_sort' => $this->request->get('balance-sort'),
                'page' => $this->request->get('page', SearchClientsForm::DEFAULT_PAGE),
                'per_page' => $this->request->get('per-page', SearchClientsForm::DEFAULT_PER_PAGE),
            ],
            '',
        );

        if (!$form->validate()) {
            $this->response->statusCode = self::HTTP_UNPROCESSABLE_ENTITY;

            return OperationResponse::failure(
                new OperationError(
                    code: OperationError::CODE_VALIDATION_FAILED,
                    details: [
                        'fields' => $form->getErrors(),
                    ],
                )
            );
        }

        $page = $form->pageNumber();
        $perPage = $form->pageSize();

        $result = $this->clientService->search(
            $page,
            $perPage,
            $form->fieldName(),
            $form->searchValue(),
            $form->isLike(),
            $form->statusFilter(),
            $form->relationMode(),
            $form->balanceSort(),
        );

        $totalCount = $result['totalCount'];
        $pageCount = $totalCount === 0 ? 0 : (int) ceil($totalCount / $perPage);

        $items = array_map(
            static fn (Client $client): ClientResource => new ClientResource($client),
            $result['items'],
        );

        $this->setPaginationHeaders($page, $perPage, $pageCount, $totalCount);

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
        return $this->buildCreateResponse(
            $this->executeCreate($this->request->bodyParams)
        );
    }

    /**
     * Оновлює тільки дозволені нефінансові поля клієнта.
     */
    public function actionUpdate(int $id): OperationResponse
    {
        $result = $this->executeUpdate($id, $this->request->bodyParams);

        if ($result->isFailure()) {
            $error = $result->error();
            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        return OperationResponse::success(
            new ClientResource($result->value())
        );
    }

    /**
     * Повертає одного клієнта за ID.
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

    /**
     * Поповнює баланс та запускає асинхронну обробку pending-orders.
     */
    public function actionTopUp(int $id): OperationResponse
    {
        return $this->buildTopUpResponse(
            $this->executeTopUp($id, $this->request->bodyParams)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return OperationResult<Client>
     */
    private function executeCreate(array $data): OperationResult
    {
        $form = new CreateClientForm();
        $form->load($data, '');

        if (!$form->validate()) {
            return $this->validationFailure($form->getErrors());
        }

        return $this->clientService->create(
            (string) $form->name,
            (string) $form->email,
            (string) $form->balance,
            (string) $form->status,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return OperationResult<Client>
     */
    private function executeUpdate(int $id, array $data): OperationResult
    {
        $form = new UpdateClientForm();
        $form->load($data, '');

        if (!$form->validate()) {
            return $this->validationFailure($form->getErrors());
        }

        return $this->clientService->update(
            $id,
            $form->nameValue(),
            $form->emailValue(),
            $form->statusValue(),
        );
    }

    /**
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
     * @param array<string, list<string>> $errors
     * @return OperationResult<never>
     */
    private function validationFailure(array $errors): OperationResult
    {
        return OperationResult::failure(
            new OperationError(
                code: OperationError::CODE_VALIDATION_FAILED,
                details: [
                    'fields' => $errors,
                ],
            )
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return OperationResult<TopUpResult>
     */
    private function executeTopUp(int $clientId, array $data): OperationResult
    {
        $form = new TopUpClientForm();
        $form->load($data, '');

        if (!$form->validate()) {
            return $this->validationFailure($form->getErrors());
        }

        return $this->clientService->topUp($clientId, (string) $form->amount);
    }

    /**
     * @param OperationResult<TopUpResult> $result
     */
    private function buildTopUpResponse(OperationResult $result): OperationResponse
    {
        if ($result->isFailure()) {
            $error = $result->error();
            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        /** @var TopUpResult $topUpResult */
        $topUpResult = $result->value();
        $this->response->statusCode = self::HTTP_ACCEPTED;

        /**
         * balanceAfterTopUp — баланс одразу після зарахування, а не гарантований
         * фінальний баланс після виконання Queue Job.
         */
        return OperationResponse::success([
            'creditedAmount' => $topUpResult->creditedAmount,
            'oldBalance' => $topUpResult->oldBalance,
            'balanceAfterTopUp' => $topUpResult->balanceAfterTopUp,
        ]);
    }

    /**
     * Додає стандартні metadata pagination у response headers.
     */
    private function setPaginationHeaders(int $page, int $perPage, int $pageCount, int $totalCount): void
    {
        $headers = $this->response->headers;
        $headers->set('X-Pagination-Current-Page', (string) $page);
        $headers->set('X-Pagination-Per-Page', (string) $perPage);
        $headers->set('X-Pagination-Page-Count', (string) $pageCount);
        $headers->set('X-Pagination-Total-Count', (string) $totalCount);
    }

    /**
     * HTTP status визначається transport layer і не потрапляє в OperationError.
     */
    private function resolveFailureStatusCode(OperationError $error): int
    {
        return match ($error->code) {
            OperationError::CODE_VALIDATION_FAILED,
            ClientService::ERROR_CREATE_FAILED,
            ClientService::ERROR_UPDATE_FAILED,
            ClientService::ERROR_TOP_UP_INVALID_AMOUNT,
            ClientService::ERROR_BALANCE_LIMIT_EXCEEDED => self::HTTP_UNPROCESSABLE_ENTITY,

            ClientService::ERROR_NOT_FOUND => self::HTTP_NOT_FOUND,
            ClientService::ERROR_DATA_CONFLICT => self::HTTP_CONFLICT,
            ClientService::ERROR_TOP_UP_FAILED => self::HTTP_INTERNAL_SERVER_ERROR,
            default => self::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
