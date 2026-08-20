<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiAuth;
use App\Core\ApiRequest;
use App\Core\ApiResponse;
use App\Services\IntegrationService;

class WorkerController extends ApiController
{
    public function processOutbox(): never
    {
        $configured = (string) ($_ENV['OUTBOX_WORKER_SECRET'] ?? '');
        $provided = ApiRequest::header('X-Worker-Secret');
        if ($configured === '' || !$provided || !hash_equals($configured, $provided)) {
            ApiAuth::require(['integrations:manage'], true);
        }
        ApiResponse::success((new IntegrationService())->processOutbox((int) ($this->input('limit') ?? ($_ENV['OUTBOX_BATCH_SIZE'] ?? 25))));
    }
}
