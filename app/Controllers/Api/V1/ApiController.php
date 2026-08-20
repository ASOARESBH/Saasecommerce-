<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiAuth;
use App\Core\ApiRequest;
use App\Core\RateLimiter;
use App\Core\TenantContext;
use App\Core\ApiResponse;

abstract class ApiController
{
    protected function authenticate(array $scopes = [], bool $allowSession = false): object
    {
        $client = ApiAuth::require($scopes, $allowSession);
        RateLimiter::enforce('api:' . ($client->id ?? 'anonymous'), (int) ($_ENV['API_RATE_LIMIT'] ?? 120), 60);
        return $client;
    }

    protected function paginate(): array { return ApiRequest::pagination(); }
    protected function body(): array { return ApiRequest::body(); }
    protected function input(string $key, mixed $default = null): mixed { return ApiRequest::input($key, $default); }
    protected function tenantId(): int { return TenantContext::requireId(); }

    protected function notFound(string $message = 'Registro não encontrado.'): never { ApiResponse::error($message, 404, 'NOT_FOUND'); }
}
