<?php
namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;
use RuntimeException;

class ApiClientService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::getInstance(); }

    public function create(string $name, array $scopes = []): array
    {
        $name = trim($name);
        if ($name === '') throw new RuntimeException('Nome do canal é obrigatório.', 422);
        $allowed = ['catalog:read','settings:read','orders:read','orders:write','customers:read','customers:write','delivery:read','coupons:read','loyalty:read','loyalty:manage','marketing:read','marketing:manage','dashboard:read','reports:read','drivers:read','drivers:manage','payments:manage','inventory:read','inventory:manage','finance:read','*'];
        $scopes = array_values(array_intersect($scopes, $allowed));
        if ($scopes === []) $scopes = ['catalog:read','settings:read','orders:write','customers:write','delivery:read','coupons:read'];
        $key = 'sk_' . bin2hex(random_bytes(18));
        $secret = 'ss_' . bin2hex(random_bytes(24));
        $prefix = substr($key, 0, 12);
        $stmt = $this->pdo->prepare('INSERT INTO api_clients (tenant_id, name, key_prefix, key_hash, secret_hash, scopes_json) VALUES (:tenant_id, :name, :key_prefix, :key_hash, :secret_hash, :scopes_json)');
        $stmt->execute(['tenant_id' => TenantContext::requireId(), 'name' => $name, 'key_prefix' => $prefix, 'key_hash' => hash('sha256', $key), 'secret_hash' => hash('sha256', $secret), 'scopes_json' => json_encode($scopes)]);
        return ['id' => (int) $this->pdo->lastInsertId(), 'name' => $name, 'key' => $key, 'secret' => $secret, 'scopes' => $scopes, 'warning' => 'Guarde o key e o secret agora; eles não serão exibidos novamente.'];
    }

    public function list(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, key_prefix, scopes_json, status, last_used_at, created_at FROM api_clients WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => TenantContext::requireId()]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) $item['scopes'] = json_decode((string) $item['scopes_json'], true) ?: [];
        return $items;
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE api_clients SET status = 'revoked' WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $id, 'tenant_id' => TenantContext::requireId()]);
    }
}
