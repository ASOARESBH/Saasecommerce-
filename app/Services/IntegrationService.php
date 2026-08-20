<?php
namespace App\Services;

use App\Core\ApiRequest;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\TenantContext;
use PDO;
use RuntimeException;
use Throwable;

class IntegrationService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::getInstance(); }

    public function receive(string $tenantSlug, string $provider, string $eventName, array $payload, ?string $signature, ?string $timestamp, ?string $externalEventId): array
    {
        $tenantStmt = $this->pdo->prepare("SELECT * FROM tenants WHERE slug = :slug AND status = 'active' LIMIT 1");
        $tenantStmt->execute(['slug' => $tenantSlug]);
        $tenant = $tenantStmt->fetch();
        if (!$tenant) throw new RuntimeException('Tenant não encontrado.', 404);
        TenantContext::set($tenant);
        RateLimiter::enforce('webhook:' . $provider, (int) ($_ENV['WEBHOOK_RATE_LIMIT'] ?? 60), 60);
        $connection = $this->connection($provider);
        $raw = ApiRequest::rawBody();
        $signatureValid = $this->verifyWebhook($connection, $raw, $signature, $timestamp);
        if (!$signatureValid) throw new RuntimeException('Assinatura de webhook inválida.', 401);

        $insert = $this->pdo->prepare('INSERT INTO webhooks (tenant_id, provider_slug, event_name, external_event_id, signature_valid, payload_json) VALUES (:tenant_id, :provider, :event_name, :external_id, 1, :payload)');
        try {
            $insert->execute(['tenant_id' => TenantContext::requireId(), 'provider' => $provider, 'event_name' => $eventName, 'external_id' => $externalEventId, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'duplicate')) return ['accepted' => true, 'duplicate' => true];
            throw $e;
        }
        $webhookId = (int) $this->pdo->lastInsertId();
        try {
            $result = $this->handleInboundEvent($eventName, $payload, $provider);
            $done = $this->pdo->prepare("UPDATE webhooks SET status = 'processed', processed_at = NOW() WHERE id = :id AND tenant_id = :tenant_id");
            $done->execute(['id' => $webhookId, 'tenant_id' => TenantContext::requireId()]);
            return ['accepted' => true, 'webhook_id' => $webhookId, 'result' => $result];
        } catch (Throwable $e) {
            $failed = $this->pdo->prepare("UPDATE webhooks SET status = 'failed', error = :error WHERE id = :id AND tenant_id = :tenant_id");
            $failed->execute(['error' => mb_substr($e->getMessage(), 0, 1000), 'id' => $webhookId, 'tenant_id' => TenantContext::requireId()]);
            throw $e;
        }
    }

    public function processOutbox(int $limit = 25): array
    {
        $limit = min(100, max(1, $limit));
        $stmt = $this->pdo->query("SELECT * FROM outbox_events WHERE status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()) ORDER BY created_at LIMIT {$limit}");
        $events = $stmt->fetchAll(); $sent = 0; $failed = 0;
        foreach ($events as $event) {
            try { $this->deliverEvent($event); $sent++; } catch (Throwable $e) { $failed++; Logger::error('Falha na entrega de evento de integração', ['event_id' => $event->id, 'message' => $e->getMessage()]); }
        }
        return ['processed' => count($events), 'sent' => $sent, 'failed' => $failed];
    }

    private function deliverEvent(object $event): void
    {
        $connections = $this->pdo->prepare("SELECT * FROM integration_connections WHERE tenant_id = :tenant_id AND status = 'active'");
        $connections->execute(['tenant_id' => $event->tenant_id]);
        $connectionRows = $connections->fetchAll();
        $connectionCount = count($connectionRows);
        $delivered = false;
        foreach ($connectionRows as $connection) {
            $settings = json_decode((string) ($connection->settings_json ?? '{}'), true) ?: [];
            $url = $settings['webhook_url'] ?? $settings['events_url'] ?? null;
            if (!$url) continue;
            $credentials = $this->credentials($connection);
            $body = json_encode(['event' => $event->event_name, 'data' => json_decode($event->payload_json, true)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers = ['Content-Type: application/json', 'X-Integration-Event: ' . $event->event_name];
            if (!empty($credentials['token'])) $headers[] = 'Authorization: Bearer ' . $credentials['token'];
            if (!empty($credentials['api_key'])) $headers[] = 'X-API-Key: ' . $credentials['api_key'];
            $signature = !empty($credentials['secret']) ? hash_hmac('sha256', $body, $credentials['secret']) : null;
            if ($signature) $headers[] = 'X-Signature: ' . $signature;
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => (int) ($_ENV['INTEGRATION_CONNECT_TIMEOUT'] ?? 5), CURLOPT_TIMEOUT => (int) ($_ENV['INTEGRATION_TIMEOUT'] ?? 20), CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body]);
            $responseBody = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
            $this->log($event->tenant_id, $connection->id, 'outbound', $url, 'POST', ['event' => $event->event_name], $responseBody ? json_decode($responseBody, true) : null, $status, $event->aggregate_id, $error ?: null, (int) $event->attempts);
            if ($status >= 200 && $status < 300) $delivered = true;
        }
        $attempts = (int) $event->attempts + 1;
        $maxRetries = (int) ($_ENV['INTEGRATION_MAX_RETRIES'] ?? 5);
        if ($delivered || $connectionCount === 0) {
            $update = $this->pdo->prepare("UPDATE outbox_events SET status = 'sent', attempts = :attempts, sent_at = NOW() WHERE id = :id"); $update->execute(['attempts' => $attempts, 'id' => $event->id]); return;
        }
        $status = $attempts >= $maxRetries ? 'failed' : 'pending';
        $delay = min(3600, 2 ** min($attempts, 10));
        $update = $this->pdo->prepare("UPDATE outbox_events SET status = :status, attempts = :attempts, next_attempt_at = DATE_ADD(NOW(), INTERVAL {$delay} SECOND), last_error = :error WHERE id = :id");
        $update->execute(['status' => $status, 'attempts' => $attempts, 'error' => 'Nenhuma integração confirmou o evento.', 'id' => $event->id]);
        if ($status === 'failed') throw new RuntimeException('Evento excedeu o limite de retries.');
    }

    private function handleInboundEvent(string $eventName, array $payload, string $provider): array
    {
        if ($eventName === 'order.created' && !empty($payload['items'])) {
            $order = (new OrderService($this->pdo))->create($payload, $provider);
            return ['order_id' => $order['id']];
        }
        if (str_starts_with($eventName, 'order.') && isset($payload['order_id'], $payload['status'])) {
            $order = (new OrderService($this->pdo))->updateStatus((int) $payload['order_id'], (string) $payload['status'], $payload['note'] ?? null, $provider);
            return ['order_id' => $order['id'], 'status' => $order['status']];
        }
        return ['ignored' => true];
    }

    private function connection(string $provider): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM integration_connections WHERE tenant_id = :tenant_id AND provider_slug = :provider AND status = 'active' ORDER BY id LIMIT 1");
        $stmt->execute(['tenant_id' => TenantContext::requireId(), 'provider' => $provider]);
        return $stmt->fetch() ?: null;
    }

    private function verifyWebhook(?object $connection, string $raw, ?string $signature, ?string $timestamp): bool
    {
        if (!$connection) return false;
        $credentials = $this->credentials($connection);
        $secret = $credentials['webhook_secret'] ?? $credentials['secret'] ?? null;
        if (!$secret || !$signature) return false;
        $message = $timestamp ? $timestamp . "\n" . $raw : $raw;
        $expected = hash_hmac('sha256', $message, $secret);
        $signature = preg_replace('/^sha256=/', '', trim($signature));
        if ($timestamp && (!$timestamp || abs(time() - (int) $timestamp) > 300)) return false;
        return hash_equals($expected, $signature);
    }

    private function credentials(object $connection): array
    {
        $encrypted = json_decode((string) ($connection->credentials_json ?? '{}'), true) ?: [];
        $result = [];
        foreach ($encrypted as $key => $value) {
            try { $result[$key] = Crypto::decrypt((string) $value); } catch (Throwable) { $result[$key] = null; }
        }
        return $result;
    }

    private function log(int $tenantId, int $connectionId, string $direction, string $endpoint, string $method, ?array $request, ?array $response, ?int $status, ?string $externalId, ?string $error, int $retryCount): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO integration_logs (tenant_id, connection_id, direction, endpoint, method, request_json, response_json, status_code, external_id, error, retry_count) VALUES (:tenant_id, :connection_id, :direction, :endpoint, :method, :request_json, :response_json, :status_code, :external_id, :error, :retry_count)');
        $stmt->execute(['tenant_id' => $tenantId, 'connection_id' => $connectionId, 'direction' => $direction, 'endpoint' => $endpoint, 'method' => $method, 'request_json' => $request ? json_encode($request, JSON_UNESCAPED_UNICODE) : null, 'response_json' => $response ? json_encode($response, JSON_UNESCAPED_UNICODE) : null, 'status_code' => $status, 'external_id' => $externalId, 'error' => $error, 'retry_count' => $retryCount]);
    }
}
