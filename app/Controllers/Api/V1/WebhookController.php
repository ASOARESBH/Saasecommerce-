<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiRequest;
use App\Core\ApiResponse;
use App\Services\IntegrationService;

class WebhookController extends ApiController
{
    private IntegrationService $integrations;
    public function __construct() { $this->integrations = new IntegrationService(); }

    public function receive(string $tenantSlug, string $provider, string $event): never
    {
        $signature = ApiRequest::header('X-Signature') ?? ApiRequest::header('X-Webhook-Signature');
        $timestamp = ApiRequest::header('X-Timestamp') ?? ApiRequest::header('X-Webhook-Timestamp');
        $externalId = ApiRequest::header('X-Event-Id') ?? ApiRequest::header('X-Webhook-Id');
        $result = $this->integrations->receive($tenantSlug, $provider, $event, $this->body(), $signature, $timestamp, $externalId);
        ApiResponse::success($result, 202);
    }
}
