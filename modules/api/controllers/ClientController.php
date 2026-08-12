<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\contracts\results\TopUpResult;
use app\models\entities\Client;
use app\models\forms\client\CreateClientForm;
use app\models\forms\client\TopUpClientForm;
use app\modules\api\security\ApiTokenAuthenticator;
use app\resources\ClientResource;
use app\responses\OperationResponse;
use app\services\ClientService;


final class ClientController extends ApiController
{
    /**
     * HTTP-статуси, які повертають actions контролера.
     */
    private const HTTP_CREATED = 201;
    private const HTTP_ACCEPTED = 202;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_CONFLICT = 409;
    private const HTTP_UNPROCESSABLE_ENTITY = 422;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

    /**
     * Налаштування пагінації списку клієнтів.
     *
     * Значення залишаються локальними для ClientController,
     * оскільки базовий ApiController не керує параметрами списків.
     */
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
            'top-up' => ['POST'],
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

        /**
         * Пошуковий рядок є опціональним query parameter.
         *
         * Application service самостійно виконує trim та визначає
         * поведінку порожнього значення, тому Controller лише передає
         * transport input у відповідний use case.
         *
         * Масив тут не приймаємо: конструкція на кшталт
         * ?search[]=value не є валідним форматом search-параметра
         * і не повинна спричиняти TypeError у Service Layer.
         */
        $search = $this->request->get('search');

        if (!is_string($search)) {
            $search = null;
        }

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
            ClientService::ERROR_CREATE_FAILED,
            ClientService::ERROR_TOP_UP_INVALID_AMOUNT,
            ClientService::ERROR_BALANCE_LIMIT_EXCEEDED => self::HTTP_UNPROCESSABLE_ENTITY,

            ClientService::ERROR_NOT_FOUND => self::HTTP_NOT_FOUND,

            ClientService::ERROR_DATA_CONFLICT => self::HTTP_CONFLICT,

            ClientService::ERROR_TOP_UP_FAILED => self::HTTP_INTERNAL_SERVER_ERROR,

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

    /**
     * Поповнює баланс клієнта та запускає асинхронну
     * обробку його pending-замовлень.
     */
    public function actionTopUp(int $id): OperationResponse
    {
        $result = $this->executeTopUp($id, $this->request->bodyParams);
        return $this->buildTopUpResponse($result);
    }

    /**
     * Перевіряє input і запускає application use case поповнення.
     *
     * @param array<string, mixed> $data
     * @return OperationResult<TopUpResult>
     */
    private function executeTopUp(int $clientId, array $data): OperationResult
    {
        $form = new TopUpClientForm();
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

        return $this->clientService->topUp(
            $clientId,
            (string) $form->amount,
        );
    }

    /**
     * Перетворює результат поповнення у зовнішній HTTP response.
     *
     * HTTP 202 показує, що синхронна частина вже завершена,
     * але Queue Job з оплатою pending-замовлень ще може виконуватися.
     *
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
         * balanceAfterTopUp — баланс одразу після зарахування.
         * Це не фінальний баланс після виконання Queue Job.
         */
        return OperationResponse::success([
            'creditedAmount' => $topUpResult->creditedAmount,
            'oldBalance' => $topUpResult->oldBalance,
            'balanceAfterTopUp' => $topUpResult->balanceAfterTopUp,
        ]);
    }
}
