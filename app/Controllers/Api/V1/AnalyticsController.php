<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Services\AnalyticsService;

class AnalyticsController extends ApiController
{
    private AnalyticsService $analytics;
    public function __construct() { $this->analytics = new AnalyticsService(); }

    public function dashboard(): never
    {
        $this->authenticate(['dashboard:read'], true);
        ApiResponse::success($this->analytics->dashboard());
    }

    public function report(string $type): never
    {
        $this->authenticate(['reports:read'], true);
        ApiResponse::success($this->analytics->report($type, ['from' => $this->input('from'), 'to' => $this->input('to') ?: date('Y-m-d 23:59:59')]));
    }
}
