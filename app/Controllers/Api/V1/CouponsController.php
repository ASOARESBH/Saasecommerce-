<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Services\CouponService;

class CouponsController extends ApiController
{
    private CouponService $coupons;
    public function __construct() { $this->coupons = new CouponService(); }

    public function validate(): never
    {
        $this->authenticate(['coupons:read'], true);
        $body = $this->body();
        ApiResponse::success($this->coupons->validate((string) ($body['code'] ?? ''), (float) ($body['subtotal'] ?? 0), isset($body['customer_id']) ? (int) $body['customer_id'] : null, (float) ($body['delivery_fee'] ?? 0)));
    }
}
