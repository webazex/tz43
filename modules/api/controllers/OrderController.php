<?php

declare(strict_types=1);

namespace app\modules\api\controllers;

use app\contracts\results\OperationError;
use app\contracts\results\OperationResult;
use app\models\entities\Order;
use app\models\forms\order\CreateOrderForm;
use app\models\forms\order\ListOrdersForm;
use app\modules\api\security\ApiTokenAuthenticator;
use app\resources\OrderResource;
use app\responses\OperationResponse;
use app\services\OrderService;

/**
 * REST-контролер замовлень.
 *
 * Реалізує створення, пагінований список, читання та скасування замовлень.
 * Business rules і транзакції делегуються OrderService.
 */
final class OrderController extends ApiController
{
    private const HTTP_CREATED = 201;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_CONFLICT = 409;
    private const HTTP_UNPROCESSABLE_ENTITY = 422;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

    public function __construct(
        $id,
        $module,
        ApiTokenAuthenticator $tokenAuthenticator,
        private readonly OrderService $orderService,
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
            'view' => ['GET'],
            'create' => ['POST'],
            'cancel' => ['POST'],
        ];
    }

    /**
     * Повертає сторінку списку замовлень.
     */
    public function actionIndex(): OperationResponse
    {
        $form = new ListOrdersForm();
        $form->load(
            [
                'status' => $this->request->get('status'),
                'client_id' => $this->request->get('client_id'),
                'page' => $this->request->get(
                    'page',
                    ListOrdersForm::DEFAULT_PAGE,
                ),
                'per_page' => $this->request->get(
                    'per-page',
                    ListOrdersForm::DEFAULT_PER_PAGE,
                ),
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

        $result = $this->orderService->getList(
            $page,
            $perPage,
            $form->clientIdFilter(),
            $form->statusFilter(),
        );

        if ($result->isFailure()) {
            $error = $result->error();

            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        /**
         * @var array{
         *     items: list<Order>,
         *     totalCount: int
         * } $pageData
         */
        $pageData = $result->value();
        $totalCount = $pageData['totalCount'];

        $pageCount = $totalCount === 0
            ? 0
            : (int) ceil($totalCount / $perPage);

        $items = array_map(
            static fn (Order $order): OrderResource => new OrderResource($order),
            $pageData['items'],
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
     * Повертає одне замовлення за його ID.
     *
     * Controller не виконує SQL-запити напряму: отримання сутності
     * делегується OrderService, а зовнішній формат визначає OrderResource.
     */
    public function actionView(int $id): OperationResponse
    {
        $result = $this->orderService->getById($id);

        if ($result->isFailure()) {
            $error = $result->error();

            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        return OperationResponse::success(
            new OrderResource($result->value())
        );
    }

    /**
     * Створює нове замовлення.
     */
    public function actionCreate(): OperationResponse
    {
        $result = $this->executeCreate($this->request->bodyParams);

        return $this->buildCreateResponse($result);
    }

    /**
     * Скасовує замовлення, якщо воно все ще має статус pending.
     *
     * Request body не потрібний: ID надходить із маршруту, а допустимість
     * переходу статусу перевіряється всередині OrderService.
     */
    public function actionCancel(int $id): OperationResponse
    {
        $result = $this->orderService->cancel($id);

        if ($result->isFailure()) {
            $error = $result->error();

            $this->response->statusCode = $this->resolveFailureStatusCode($error);

            return OperationResponse::failure($error);
        }

        return OperationResponse::success(
            new OrderResource($result->value())
        );
    }

    /**
     * Перевіряє input і запускає application use case.
     *
     * @param array<string, mixed> $data
     * @return OperationResult<Order>
     */
    private function executeCreate(array $data): OperationResult
    {
        $form = new CreateOrderForm();
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

        return $this->orderService->create(
            (int) $form->client_id,
            (string) $form->amount,
            (string) $form->description,
        );
    }

    /**
     * Перетворює application-result у зовнішній HTTP response.
     *
     * @param OperationResult<Order> $result
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
            new OrderResource($result->value())
        );
    }

    /**
     * Додає стандартні метадані пагінації до HTTP headers.
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
     * Визначає HTTP status відповідно до application-помилки.
     */
    private function resolveFailureStatusCode(OperationError $error): int
    {
        return match ($error->code) {
            OperationError::CODE_VALIDATION_FAILED,
            OrderService::ERROR_INVALID_AMOUNT,
            OrderService::ERROR_CREATE_FAILED => self::HTTP_UNPROCESSABLE_ENTITY,

            OrderService::ERROR_CLIENT_NOT_FOUND,
            OrderService::ERROR_NOT_FOUND => self::HTTP_NOT_FOUND,

            OrderService::ERROR_CLIENT_BLOCKED,
            OrderService::ERROR_CLIENT_BALANCE_PROCESSING,
            OrderService::ERROR_NOT_PENDING => self::HTTP_CONFLICT,

            default => self::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
