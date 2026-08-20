<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Services\CustomerService;

class CustomersController extends ApiController
{
    private CustomerService $customers;
    public function __construct() { $this->customers = new CustomerService(); }

    public function index(): never
    {
        $this->authenticate(['customers:read'], true);
        [$page, $perPage, $offset] = $this->paginate();
        $result = $this->customers->list(['pagination' => [$page, $perPage, $offset], 'search' => $this->input('search')]);
        ApiResponse::success($result['items'], 200, ['page' => $result['page'], 'per_page' => $result['per_page'], 'pages' => $result['pages'], 'total' => $result['total']]);
    }

    public function store(): never
    {
        $this->authenticate(['customers:write'], true);
        ApiResponse::success($this->customers->upsert($this->body()), 201);
    }

    public function show(string $id): never
    {
        $this->authenticate(['customers:read'], true);
        $customer = $this->customers->find((int) $id);
        if (!$customer) $this->notFound('Cliente não encontrado.');
        ApiResponse::success($customer);
    }

    public function loyalty(string $id): never
    {
        $this->authenticate(['loyalty:read'], true);
        ApiResponse::success($this->customers->loyalty((int) $id));
    }
}
