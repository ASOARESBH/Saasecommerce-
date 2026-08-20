<?php
namespace App\Core;

use PDOException;

class RateLimiter
{
    public static function enforce(string $bucket, int $limit = 120, int $windowSeconds = 60): void
    {
        $tenantId = TenantContext::id();
        $window = date('Y-m-d H:i:00', (int) (floor(time() / $windowSeconds) * $windowSeconds));
        $pdo = Database::getInstance();
        try {
            $stmt = $pdo->prepare("INSERT INTO rate_limits (tenant_id, bucket_key, window_started_at, request_count)
                VALUES (:tenant_id, :bucket, :window_started, 1)
                ON DUPLICATE KEY UPDATE request_count = request_count + 1");
            $stmt->execute([
                'tenant_id' => $tenantId,
                'bucket' => $bucket,
                'window_started' => $window,
            ]);
            $count = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
            $lookup = $pdo->prepare('SELECT request_count FROM rate_limits WHERE ' . ($tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = :tenant_id') . ' AND bucket_key = :bucket AND window_started_at = :window_started LIMIT 1');
            $params = ['bucket' => $bucket, 'window_started' => $window];
            if ($tenantId !== null) $params['tenant_id'] = $tenantId;
            $lookup->execute($params);
            $count = (int) $lookup->fetchColumn();
            if ($count > $limit) {
                header('Retry-After: ' . $windowSeconds);
                ApiResponse::error('Limite de requisições excedido. Tente novamente mais tarde.', 429, 'RATE_LIMITED');
            }
        } catch (PDOException $e) {
            Logger::error('Falha no rate limiting', ['message' => $e->getMessage()]);
            // Falha do mecanismo técnico não bloqueia a operação de negócio.
        }
    }
}
