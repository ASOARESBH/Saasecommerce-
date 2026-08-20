<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\ApiClientService;
use RuntimeException;

class IntegrationAdminController extends ApiController
{
    private ApiClientService $clients;
    public function __construct() { $this->clients = new ApiClientService(); }

    public function clients(): never
    {
        $this->authenticate(['integrations:manage'], true);
        ApiResponse::success($this->clients->list());
    }

    public function createClient(): never
    {
        $this->authenticate(['integrations:manage'], true);
        $body = $this->body();
        ApiResponse::success($this->clients->create((string) ($body['name'] ?? ''), (array) ($body['scopes'] ?? [])), 201);
    }

    public function revokeClient(string $id): never
    {
        $this->authenticate(['integrations:manage'], true);
        $this->clients->revoke((int) $id);
        ApiResponse::success(['revoked' => true]);
    }

    public function connections(): never
    {
        $this->authenticate(['integrations:manage'], true);
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, provider_slug, name, base_url, auth_type, settings_json, status, last_sync_at, created_at, updated_at FROM integration_connections WHERE tenant_id = :tenant_id ORDER BY provider_slug, name');
        $stmt->execute(['tenant_id' => TenantContext::requireId()]);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) $item->settings_json = json_decode((string) $item->settings_json, true) ?: [];
        ApiResponse::success($items);
    }

    public function storeConnection(): never
    {
        $this->authenticate(['integrations:manage'], true);
        $body = $this->body();
        $provider = trim((string) ($body['provider_slug'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        if ($provider === '' || $name === '') throw new RuntimeException('provider_slug e name são obrigatórios.', 422);
        $credentials = [];
        foreach ((array) ($body['credentials'] ?? []) as $key => $value) $credentials[$key] = Crypto::encrypt((string) $value);
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO integration_connections (tenant_id, provider_slug, name, base_url, auth_type, credentials_json, settings_json) VALUES (:tenant_id, :provider, :name, :base_url, :auth_type, :credentials, :settings) ON DUPLICATE KEY UPDATE base_url = VALUES(base_url), auth_type = VALUES(auth_type), credentials_json = VALUES(credentials_json), settings_json = VALUES(settings_json), status = "active"');
        $stmt->execute(['tenant_id' => TenantContext::requireId(), 'provider' => $provider, 'name' => $name, 'base_url' => $body['base_url'] ?? null, 'auth_type' => $body['auth_type'] ?? 'none', 'credentials' => json_encode($credentials), 'settings' => json_encode((array) ($body['settings'] ?? []), JSON_UNESCAPED_UNICODE)]);
        ApiResponse::success(['saved' => true], 201);
    }
}
