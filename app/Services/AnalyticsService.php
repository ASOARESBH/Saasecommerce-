<?php
namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;

class AnalyticsService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::getInstance(); }

    public function dashboard(): array
    {
        $tenantId = TenantContext::requireId();
        $statusStmt = $this->pdo->prepare('SELECT status, COUNT(*) AS quantity FROM orders WHERE tenant_id = :tenant_id AND created_at >= CURDATE() GROUP BY status');
        $statusStmt->execute(['tenant_id' => $tenantId]);
        $statuses = [];
        foreach ($statusStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $statuses[$row['status']] = (int) $row['quantity'];
        $summary = $this->pdo->prepare('SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS average_ticket, COALESCE(SUM(status = "cancelled"),0) AS cancellations FROM orders WHERE tenant_id = :tenant_id AND created_at >= CURDATE()');
        $summary->execute(['tenant_id' => $tenantId]);
        $summaryRow = $summary->fetch(PDO::FETCH_ASSOC) ?: [];
        $times = $this->pdo->prepare('SELECT AVG(TIMESTAMPDIFF(MINUTE, confirmed_at, ready_at)) AS preparation_minutes, AVG(TIMESTAMPDIFF(MINUTE, ready_at, delivered_at)) AS delivery_minutes FROM orders WHERE tenant_id = :tenant_id AND created_at >= CURDATE() AND delivered_at IS NOT NULL');
        $times->execute(['tenant_id' => $tenantId]);
        $timeRow = $times->fetch(PDO::FETCH_ASSOC) ?: [];
        $sources = $this->pdo->prepare('SELECT source, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue FROM orders WHERE tenant_id = :tenant_id AND created_at >= CURDATE() GROUP BY source ORDER BY orders DESC');
        $sources->execute(['tenant_id' => $tenantId]);
        return [
            'date' => date('Y-m-d'),
            'orders' => (int) ($summaryRow['orders'] ?? 0),
            'revenue' => (float) ($summaryRow['revenue'] ?? 0),
            'average_ticket' => (float) ($summaryRow['average_ticket'] ?? 0),
            'cancellations' => (int) ($summaryRow['cancellations'] ?? 0),
            'preparation_minutes' => round((float) ($timeRow['preparation_minutes'] ?? 0), 1),
            'delivery_minutes' => round((float) ($timeRow['delivery_minutes'] ?? 0), 1),
            'status' => $statuses,
            'sources' => $sources->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function report(string $type, array $filters = []): array
    {
        $tenantId = TenantContext::requireId();
        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d 23:59:59');
        return match ($type) {
            'products' => $this->productsReport($tenantId, $from, $to),
            'customers' => $this->customersReport($tenantId, $from, $to),
            'delivery' => $this->deliveryReport($tenantId, $from, $to),
            default => $this->salesReport($tenantId, $from, $to),
        };
    }

    private function salesReport(int $tenantId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS average_ticket FROM orders WHERE tenant_id = :tenant_id AND created_at BETWEEN :from AND :to GROUP BY DATE(created_at) ORDER BY day');
        $stmt->execute(['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]);
        return ['type' => 'sales', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function productsReport(int $tenantId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT oi.product_name, SUM(oi.quantity) AS quantity, SUM(oi.total_price) AS revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id AND o.tenant_id = oi.tenant_id WHERE oi.tenant_id = :tenant_id AND o.created_at BETWEEN :from AND :to GROUP BY oi.product_name ORDER BY quantity DESC');
        $stmt->execute(['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]);
        return ['type' => 'products', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function customersReport(int $tenantId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.name, c.phone, c.orders_count, c.average_ticket, c.last_order_at FROM customers c WHERE c.tenant_id = :tenant_id AND c.last_order_at BETWEEN :from AND :to ORDER BY c.average_ticket DESC');
        $stmt->execute(['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]);
        return ['type' => 'customers', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    private function deliveryReport(int $tenantId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare('SELECT d.status, COUNT(*) AS quantity, AVG(TIMESTAMPDIFF(MINUTE, d.out_at, d.delivered_at)) AS average_minutes FROM deliveries d WHERE d.tenant_id = :tenant_id AND d.created_at BETWEEN :from AND :to GROUP BY d.status');
        $stmt->execute(['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]);
        return ['type' => 'delivery', 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }
}
