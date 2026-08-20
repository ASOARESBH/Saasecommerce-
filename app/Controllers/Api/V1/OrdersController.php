<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiRequest;
use App\Core\ApiResponse;
use App\Services\OrderService;

class OrdersController extends ApiController
{
    private OrderService $orders;
    public function __construct() { $this->orders = new OrderService(); }

    public function index(): never
    {
        $this->authenticate(['orders:read'], true);
        [$page, $perPage, $offset] = $this->paginate();
        $result = $this->orders->list(['pagination' => [$page, $perPage, $offset], 'status' => $this->input('status'), 'source' => $this->input('source')]);
        ApiResponse::success($result['items'], 200, ['page' => $result['page'], 'per_page' => $result['per_page'], 'pages' => $result['pages'], 'total' => $result['total']]);
    }

    public function store(): never
    {
        $this->authenticate(['orders:write'], true);
        $payload = $this->body();
        $payload['idempotency_key'] = $payload['idempotency_key'] ?? ApiRequest::header('Idempotency-Key');
        $payload['external_order_id'] = $payload['external_order_id'] ?? ApiRequest::header('X-External-Order-Id');
        $order = $this->orders->create($payload, (string) ($payload['source'] ?? 'api'));
        ApiResponse::success($order, 201);
    }

    public function show(string $id): never
    {
        $this->authenticate(['orders:read'], true);
        $order = $this->orders->find((int) $id);
        if (!$order) $this->notFound('Pedido não encontrado.');
        ApiResponse::success($order);
    }

    public function cancel(string $id): never
    {
        $this->authenticate(['orders:write'], true);
        ApiResponse::success($this->orders->updateStatus((int) $id, 'cancelled', (string) ($this->body()['reason'] ?? ''), 'api'));
    }

    public function status(string $id): never
    {
        $this->authenticate(['orders:write'], true);
        $body = $this->body();
        ApiResponse::success($this->orders->updateStatus((int) $id, (string) ($body['status'] ?? ''), $body['note'] ?? null, 'api'));
    }
}
