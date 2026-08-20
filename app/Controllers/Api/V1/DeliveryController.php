<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Services\DeliveryService;

class DeliveryController extends ApiController
{
    private DeliveryService $delivery;
    public function __construct() { $this->delivery = new DeliveryService(); }

    public function areas(): never
    {
        $this->authenticate(['delivery:read'], true);
        ApiResponse::success($this->delivery->areas());
    }

    public function check(): never
    {
        $this->authenticate(['delivery:read'], true);
        $body = $this->body();
        ApiResponse::success($this->delivery->check((array) ($body['address'] ?? $body), (float) ($body['subtotal'] ?? 0)));
    }
}
