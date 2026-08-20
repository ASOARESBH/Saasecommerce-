<?php
namespace App\Core;

use PDO;
use RuntimeException;

class ApiAuth
{
    private static ?object $client = null;

    public static function require(array $scopes = [], bool $allowSession = false): object
    {
        try {
            $client = self::authenticate($allowSession);
            if ($scopes !== [] && !self::hasScopes($client, $scopes)) {
                ApiResponse::error('O cliente de API não possui o escopo necessário.', 403, 'INSUFFICIENT_SCOPE');
            }
            return $client;
        } catch (RuntimeException $e) {
            ApiResponse::error($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 401, 'UNAUTHENTICATED');
        }
    }

    public static function authenticate(bool $allowSession = false): object
    {
        if (self::$client !== null) return self::$client;

        $apiKey = ApiRequest::header('X-API-Key') ?? ApiRequest::bearerToken();
        if ($apiKey === null || $apiKey === '') {
            if ($allowSession && Auth::check() && TenantContext::isSet()) {
                return self::$client = (object) [
                    'id' => 0,
                    'tenant_id' => TenantContext::id(),
                    'name' => 'session',
                    'scopes_json' => json_encode(['*']),
                    'auth_type' => 'session',
                ];
            }
            throw new RuntimeException('Informe X-API-Key ou Authorization: Bearer.', 401);
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT * FROM api_clients WHERE key_hash = :key_hash AND status = 'active' LIMIT 1");
        $stmt->execute(['key_hash' => hash('sha256', $apiKey)]);
        $client = $stmt->fetch();
        if (!$client) throw new RuntimeException('Credencial de API inválida.', 401);

        $secret = ApiRequest::header('X-API-Secret');
        if ($client->secret_hash && (!$secret || !hash_equals($client->secret_hash, hash('sha256', $secret)))) {
            throw new RuntimeException('Segredo de API inválido.', 401);
        }

        $signature = ApiRequest::header('X-Signature');
        if ($signature) {
            $timestamp = ApiRequest::header('X-Timestamp');
            if (!$timestamp || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
                throw new RuntimeException('Timestamp de assinatura ausente ou expirado.', 401);
            }
            if (!$secret) throw new RuntimeException('X-API-Secret é obrigatório para HMAC.', 401);
            $expected = hash_hmac('sha256', $timestamp . "\n" . ApiRequest::rawBody(), $secret);
            if (!hash_equals($expected, $signature)) {
                throw new RuntimeException('Assinatura HMAC inválida.', 401);
            }
        }

        TenantContext::set(self::tenant($pdo, (int) $client->tenant_id));
        $pdo->prepare('UPDATE api_clients SET last_used_at = NOW() WHERE id = :id')->execute(['id' => $client->id]);
        return self::$client = $client;
    }

    private static function tenant(PDO $pdo, int $tenantId): object
    {
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => $tenantId]);
        $tenant = $stmt->fetch();
        if (!$tenant) throw new RuntimeException('Tenant inativo ou inexistente.', 403);
        return $tenant;
    }

    private static function hasScopes(object $client, array $required): bool
    {
        if ((Auth::user()?->role_slug ?? '') === 'superadmin') return true;
        if (($client->auth_type ?? '') === 'session') {
            $permissionMap = [
                'catalog:read' => 'manage_catalog', 'catalog:manage' => 'manage_catalog',
                'settings:read' => 'manage_tenant', 'orders:read' => 'manage_orders', 'orders:write' => 'manage_orders',
                'customers:read' => 'manage_customers', 'customers:write' => 'manage_customers',
                'delivery:read' => 'manage_delivery', 'delivery:manage' => 'manage_delivery',
                'coupons:read' => 'manage_coupons', 'loyalty:read' => 'manage_loyalty', 'loyalty:manage' => 'manage_loyalty',
                'marketing:read' => 'manage_marketing', 'marketing:manage' => 'manage_marketing',
                'dashboard:read' => 'view_dashboard', 'reports:read' => 'view_reports',
                'integrations:manage' => 'manage_integrations', 'inventory:read' => 'manage_inventory', 'inventory:manage' => 'manage_inventory',
                'drivers:read' => 'manage_drivers', 'drivers:manage' => 'manage_drivers', 'payments:manage' => 'manage_payments', 'finance:read' => 'manage_finance',
            ];
            foreach ($required as $scope) if (!Auth::can($permissionMap[$scope] ?? $scope)) return false;
            return true;
        }
        $scopes = json_decode((string) ($client->scopes_json ?? '[]'), true) ?: [];
        if (in_array('*', $scopes, true)) return true;
        foreach ($required as $scope) if (!in_array($scope, $scopes, true)) return false;
        return true;
    }

    public static function client(): ?object { return self::$client; }
}
