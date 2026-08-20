<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\Audit\AuditLogger;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\OrderService;
use PDO;
use RuntimeException;

class OperationsController extends ApiController
{
    public function kitchen(): never
    {
        $this->authenticate(['orders:read'], true); $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, order_number, status, source, total, created_at FROM orders WHERE tenant_id = :tenant_id AND status IN ('received','confirmed','preparing','ready') ORDER BY created_at"); $stmt->execute(['tenant_id' => TenantContext::requireId()]);
        ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function dispatch(): never
    {
        $this->authenticate(['delivery:read'], true); $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT d.id, d.order_id, d.status, d.estimated_min, d.estimated_max, d.assigned_at, d.out_at, o.order_number, o.total, o.payment_method, o.payment_status, o.delivery_address_json, c.name AS customer_name, c.phone AS customer_phone, dr.name AS driver_name FROM deliveries d JOIN orders o ON o.id = d.order_id AND o.tenant_id = d.tenant_id LEFT JOIN customers c ON c.id = o.customer_id AND c.tenant_id = o.tenant_id LEFT JOIN drivers dr ON dr.id = d.driver_id AND dr.tenant_id = d.tenant_id WHERE d.tenant_id = :tenant_id AND d.status NOT IN ('delivered','failed') ORDER BY d.created_at"); $stmt->execute(['tenant_id' => TenantContext::requireId()]); $items = $stmt->fetchAll(PDO::FETCH_ASSOC); foreach ($items as &$item) $item['delivery_address'] = $item['delivery_address_json'] ? json_decode($item['delivery_address_json'], true) : null; ApiResponse::success($items);
    }

    public function drivers(): never
    {
        $this->authenticate(['drivers:read'], true); $stmt = Database::getInstance()->prepare('SELECT id, name, phone, document, status, created_at FROM drivers WHERE tenant_id = :tenant_id ORDER BY status, name'); $stmt->execute(['tenant_id' => TenantContext::requireId()]); ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function assignDriver(string $deliveryId): never
    {
        $this->authenticate(['drivers:manage'], true); $body = $this->body(); $driverId = (int) ($body['driver_id'] ?? 0); if ($driverId <= 0) throw new RuntimeException('driver_id é obrigatório.', 422); $pdo = Database::getInstance();
        $pdo->beginTransaction(); try {
            $check = $pdo->prepare("SELECT id, order_id FROM deliveries WHERE id = :id AND tenant_id = :tenant_id AND status IN ('pending','assigned') LIMIT 1"); $check->execute(['id' => $deliveryId, 'tenant_id' => TenantContext::requireId()]); $delivery = $check->fetch(); if (!$delivery) throw new RuntimeException('Entrega não encontrada.', 404);
            $driver = $pdo->prepare("SELECT id FROM drivers WHERE id = :id AND tenant_id = :tenant_id AND status <> 'inactive' LIMIT 1"); $driver->execute(['id' => $driverId, 'tenant_id' => TenantContext::requireId()]); if (!$driver->fetch()) throw new RuntimeException('Entregador não encontrado.', 404);
            $pdo->prepare("UPDATE deliveries SET driver_id = :driver_id, status = 'assigned', assigned_at = NOW() WHERE id = :id AND tenant_id = :tenant_id")->execute(['driver_id' => $driverId, 'id' => $deliveryId, 'tenant_id' => TenantContext::requireId()]);
            $pdo->prepare("UPDATE orders SET assigned_driver_id = :driver_id WHERE id = :order_id AND tenant_id = :tenant_id")->execute(['driver_id' => $driverId, 'order_id' => $delivery->order_id, 'tenant_id' => TenantContext::requireId()]); $pdo->prepare("UPDATE drivers SET status = 'busy' WHERE id = :id AND tenant_id = :tenant_id")->execute(['id' => $driverId, 'tenant_id' => TenantContext::requireId()]); $pdo->commit(); AuditLogger::log('delivery.driver_assigned', 'delivery', (int) $deliveryId, ['driver_id' => $driverId]); ApiResponse::success(['assigned' => true]);
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    public function deliveryStatus(string $deliveryId): never
    {
        $this->authenticate(['delivery:manage'], true); $status = (string) ($this->body()['status'] ?? ''); $allowed = ['ready','out_for_delivery','delivered','failed']; if (!in_array($status, $allowed, true)) throw new RuntimeException('Status de entrega inválido.', 422); $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT order_id, driver_id FROM deliveries WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'); $stmt->execute(['id' => $deliveryId, 'tenant_id' => TenantContext::requireId()]); $delivery = $stmt->fetch(); if (!$delivery) throw new RuntimeException('Entrega não encontrada.', 404);
        $columns = ['ready' => "status = 'ready'", 'out_for_delivery' => "status = 'out_for_delivery', out_at = NOW()", 'delivered' => "status = 'delivered', delivered_at = NOW()", 'failed' => "status = 'failed'"];
        $pdo->exec('UPDATE deliveries SET ' . $columns[$status] . ' WHERE id = ' . (int) $deliveryId . ' AND tenant_id = ' . TenantContext::requireId());
        if (in_array($status, ['out_for_delivery','delivered'], true)) (new OrderService($pdo))->updateStatus((int) $delivery->order_id, $status, null, 'dispatch');
        if ($status === 'delivered' && $delivery->driver_id) $pdo->prepare("UPDATE drivers SET status = 'available' WHERE id = :id AND tenant_id = :tenant_id")->execute(['id' => $delivery->driver_id, 'tenant_id' => TenantContext::requireId()]);
        AuditLogger::log('delivery.status_changed', 'delivery', (int) $deliveryId, ['status' => $status]); ApiResponse::success(['updated' => true]);
    }

    public function payment(string $orderId): never
    {
        $this->authenticate(['payments:manage'], true); $body = $this->body(); $status = (string) ($body['status'] ?? ''); if (!in_array($status, ['pending','authorized','paid','failed','refunded','cancelled'], true)) throw new RuntimeException('Status de pagamento inválido.', 422); $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE payments SET status = :status, provider = COALESCE(:provider, provider), transaction_id = COALESCE(:transaction_id, transaction_id), paid_at = IF(:payment_status = "paid", NOW(), paid_at), metadata_json = COALESCE(:metadata, metadata_json) WHERE order_id = :order_id AND tenant_id = :tenant_id'); $stmt->execute(['status' => $status, 'payment_status' => $status, 'provider' => $body['provider'] ?? null, 'transaction_id' => $body['transaction_id'] ?? null, 'metadata' => isset($body['metadata']) ? json_encode($body['metadata']) : null, 'order_id' => $orderId, 'tenant_id' => TenantContext::requireId()]); $pdo->prepare('UPDATE orders SET payment_status = :status WHERE id = :id AND tenant_id = :tenant_id')->execute(['status' => $status, 'id' => $orderId, 'tenant_id' => TenantContext::requireId()]); AuditLogger::log('payment.status_changed', 'order', (int) $orderId, ['status' => $status, 'provider' => $body['provider'] ?? null]); ApiResponse::success(['updated' => true]);
    }

    public function inventory(): never
    {
        $this->authenticate(['inventory:read'], true); $stmt = Database::getInstance()->prepare('SELECT id, name, sku, unit, minimum_stock, average_cost, current_stock, (current_stock <= minimum_stock) AS below_minimum FROM ingredients WHERE tenant_id = :tenant_id AND active = 1 ORDER BY below_minimum DESC, name'); $stmt->execute(['tenant_id' => TenantContext::requireId()]); ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function finance(): never
    {
        $this->authenticate(['finance:read'], true); $stmt = Database::getInstance()->prepare('SELECT type, COUNT(*) AS entries, COALESCE(SUM(amount),0) AS total FROM financial_transactions WHERE tenant_id = :tenant_id AND occurred_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01") GROUP BY type ORDER BY type'); $stmt->execute(['tenant_id' => TenantContext::requireId()]); ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
