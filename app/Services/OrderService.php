<?php
namespace App\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Auth;
use App\Core\Database;
use App\Core\TenantContext;
use PDO;
use RuntimeException;
use Throwable;

class OrderService
{
    private PDO $pdo;
    private CatalogService $catalog;
    private CustomerService $customers;
    private DeliveryService $delivery;
    private CouponService $coupons;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
        $this->catalog = new CatalogService($this->pdo);
        $this->customers = new CustomerService($this->pdo);
        $this->delivery = new DeliveryService($this->pdo);
        $this->coupons = new CouponService($this->pdo);
    }

    public function create(array $payload, string $source = 'api'): array
    {
        $tenantId = TenantContext::requireId();
        $externalId = $this->nullableString($payload['external_order_id'] ?? null);
        $idempotencyKey = $this->nullableString($payload['idempotency_key'] ?? null);
        $existing = $this->findByIdempotency($externalId, $idempotencyKey);
        if ($existing) return $this->find((int) $existing['id']) ?? $existing;

        $items = (array) ($payload['items'] ?? []);
        if ($items === []) throw new RuntimeException('O pedido precisa ter pelo menos um item.', 422);

        $this->pdo->beginTransaction();
        try {
            $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
            if (!empty($payload['customer']) && is_array($payload['customer'])) {
                $customer = $this->customers->upsert($payload['customer']);
                $customerId = (int) $customer['id'];
            }
            if ($customerId) $this->assertCustomer($customerId);

            $resolvedItems = [];
            $subtotal = 0.0;
            foreach ($items as $line) {
                if (!is_array($line)) throw new RuntimeException('Item de pedido inválido.', 422);
                $resolved = $this->catalog->resolveLine($line);
                $resolvedItems[] = $resolved;
                $subtotal += (float) $resolved['total_price'];
            }

            $address = is_array($payload['delivery_address'] ?? null) ? $payload['delivery_address'] : [];
            $deliveryFee = (float) ($payload['delivery_fee'] ?? 0);
            $deliveryQuote = null;
            if ($address !== []) {
                $deliveryQuote = $this->delivery->check($address, $subtotal);
                $deliveryFee = (float) $deliveryQuote['delivery_fee'];
            }

            $discount = 0.0;
            $coupon = null;
            if (!empty($payload['coupon_code'])) {
                $coupon = $this->coupons->validate((string) $payload['coupon_code'], $subtotal, $customerId, $deliveryFee);
                $discount = (float) $coupon['discount'];
                if ($coupon['discount_type'] === 'free_delivery') $deliveryFee = max(0, $deliveryFee - $discount);
            }
            $total = max(0, round($subtotal - ($coupon && $coupon['discount_type'] !== 'free_delivery' ? $discount : 0) + $deliveryFee, 2));
            $status = $source === 'api' ? 'received' : 'new';
            $orderNumber = $this->orderNumber();
            $paymentMethod = $this->paymentMethod($payload['payment_method'] ?? null);
            $utmJson = $this->jsonOrNull($payload['utm'] ?? null);
            $addressJson = $this->jsonOrNull($address ?: null);

            $stmt = $this->pdo->prepare('INSERT INTO orders (tenant_id, order_number, external_order_id, idempotency_key, customer_id, status, source, subtotal, discount, delivery_fee, total, payment_method, delivery_address_json, notes, utm_json, coupon_code, received_at) VALUES (:tenant_id, :order_number, :external_order_id, :idempotency_key, :customer_id, :status, :source, :subtotal, :discount, :delivery_fee, :total, :payment_method, :delivery_address_json, :notes, :utm_json, :coupon_code, IF(:initial_status = "received", NOW(), NULL))');
            $stmt->execute([
                'tenant_id' => $tenantId, 'order_number' => $orderNumber, 'external_order_id' => $externalId, 'idempotency_key' => $idempotencyKey,
                'customer_id' => $customerId ?: null, 'status' => $status, 'initial_status' => $status, 'source' => $this->nullableString($payload['source'] ?? $source) ?: $source,
                'subtotal' => $subtotal, 'discount' => $discount, 'delivery_fee' => $deliveryFee, 'total' => $total,
                'payment_method' => $paymentMethod, 'delivery_address_json' => $addressJson, 'notes' => $this->nullableString($payload['notes'] ?? null),
                'utm_json' => $utmJson, 'coupon_code' => $this->nullableString($payload['coupon_code'] ?? null),
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            foreach ($resolvedItems as $item) {
                $itemStmt = $this->pdo->prepare('INSERT INTO order_items (tenant_id, order_id, product_id, combo_id, product_name, quantity, unit_price, total_price, notes) VALUES (:tenant_id, :order_id, :product_id, :combo_id, :product_name, :quantity, :unit_price, :total_price, :notes)');
                $itemStmt->execute([
                    'tenant_id' => $tenantId, 'order_id' => $orderId, 'product_id' => $item['product_id'], 'combo_id' => $item['combo_id'],
                    'product_name' => $item['product_name'], 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'total_price' => $item['total_price'], 'notes' => $item['notes'],
                ]);
                $itemId = (int) $this->pdo->lastInsertId();
                foreach ($item['addons'] as $addon) {
                    $addonStmt = $this->pdo->prepare('INSERT INTO order_item_addons (tenant_id, order_item_id, addon_id, addon_name, quantity, unit_price, total_price) VALUES (:tenant_id, :order_item_id, :addon_id, :addon_name, :quantity, :unit_price, :total_price)');
                    $addonStmt->execute(['tenant_id' => $tenantId, 'order_item_id' => $itemId, 'addon_id' => $addon['addon_id'], 'addon_name' => $addon['addon_name'], 'quantity' => $addon['quantity'], 'unit_price' => $addon['unit_price'], 'total_price' => $addon['total_price']]);
                }
            }

            if ($paymentMethod) {
                $paymentStmt = $this->pdo->prepare('INSERT INTO payments (tenant_id, order_id, method, status, amount) VALUES (:tenant_id, :order_id, :method, "pending", :amount)');
                $paymentStmt->execute(['tenant_id' => $tenantId, 'order_id' => $orderId, 'method' => $paymentMethod, 'amount' => $total]);
            }
            if ($address !== []) {
                $deliveryStmt = $this->pdo->prepare('INSERT INTO deliveries (tenant_id, order_id, status, estimated_min, estimated_max) VALUES (:tenant_id, :order_id, "pending", :estimated_min, :estimated_max)');
                $deliveryStmt->execute(['tenant_id' => $tenantId, 'order_id' => $orderId, 'estimated_min' => $deliveryQuote['estimated_min'] ?? null, 'estimated_max' => $deliveryQuote['estimated_max'] ?? null]);
            }
            $history = $this->pdo->prepare('INSERT INTO order_status_history (tenant_id, order_id, from_status, to_status, user_id, source) VALUES (:tenant_id, :order_id, NULL, :to_status, :user_id, :source)');
            $history->execute(['tenant_id' => $tenantId, 'order_id' => $orderId, 'to_status' => $status, 'user_id' => Auth::user()?->id, 'source' => $source]);
            if ($customerId) $this->updateCustomerMetrics($customerId, $total);
            if ($coupon) $this->coupons->incrementUsage((int) $coupon['coupon_id']);

            $outbox = $this->pdo->prepare('INSERT INTO outbox_events (tenant_id, event_name, aggregate_type, aggregate_id, payload_json) VALUES (:tenant_id, "order.created", "order", :aggregate_id, :payload_json)');
            $outbox->execute(['tenant_id' => $tenantId, 'aggregate_id' => $orderId, 'payload_json' => json_encode(['order_id' => $orderId, 'order_number' => $orderNumber, 'status' => $status, 'source' => $source], JSON_UNESCAPED_UNICODE)]);
            $this->pdo->commit();
            AuditLogger::log('order.created', 'order', $orderId, ['source' => $source, 'total' => $total]);
            return $this->find($orderId) ?? throw new RuntimeException('Pedido não localizado após gravação.', 500);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT o.*, c.name AS customer_name, c.phone AS customer_phone FROM orders o LEFT JOIN customers c ON c.id = o.customer_id AND c.tenant_id = o.tenant_id WHERE o.id = :id AND o.tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => TenantContext::requireId()]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return null;
        $items = $this->pdo->prepare('SELECT oi.*, (SELECT JSON_ARRAYAGG(JSON_OBJECT("id", oia.id, "name", oia.addon_name, "quantity", oia.quantity, "unit_price", oia.unit_price)) FROM order_item_addons oia WHERE oia.order_item_id = oi.id AND oia.tenant_id = oi.tenant_id) AS addons_json FROM order_items oi WHERE oi.tenant_id = :tenant_id AND oi.order_id = :order_id ORDER BY oi.id');
        $items->execute(['tenant_id' => TenantContext::requireId(), 'order_id' => $id]);
        $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        $history = $this->pdo->prepare('SELECT from_status, to_status, source, note, created_at FROM order_status_history WHERE tenant_id = :tenant_id AND order_id = :order_id ORDER BY created_at');
        $history->execute(['tenant_id' => TenantContext::requireId(), 'order_id' => $id]);
        $order['status_history'] = $history->fetchAll(PDO::FETCH_ASSOC);
        $payments = $this->pdo->prepare('SELECT id, method, status, provider, transaction_id, amount, paid_at, created_at FROM payments WHERE tenant_id = :tenant_id AND order_id = :order_id ORDER BY id');
        $payments->execute(['tenant_id' => TenantContext::requireId(), 'order_id' => $id]);
        $order['payments'] = $payments->fetchAll(PDO::FETCH_ASSOC);
        $order['delivery_address'] = $order['delivery_address_json'] ? json_decode($order['delivery_address_json'], true) : null;
        $order['utm'] = $order['utm_json'] ? json_decode($order['utm_json'], true) : null;
        unset($order['delivery_address_json'], $order['utm_json']);
        return $order;
    }

    public function list(array $filters = []): array
    {
        [$page, $perPage, $offset] = $filters['pagination'] ?? [1, 25, 0];
        $where = ['tenant_id = :tenant_id']; $params = ['tenant_id' => TenantContext::requireId()];
        if (!empty($filters['status'])) { $where[] = 'status = :status'; $params['status'] = $filters['status']; }
        if (!empty($filters['source'])) { $where[] = 'source = :source'; $params['source'] = $filters['source']; }
        $sqlWhere = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM orders WHERE {$sqlWhere}"); $count->execute($params); $total = (int) $count->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT id, order_number, external_order_id, customer_id, status, source, subtotal, discount, delivery_fee, total, payment_method, payment_status, created_at, updated_at FROM orders WHERE {$sqlWhere} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int) ceil($total / max(1, $perPage))];
    }

    public function updateStatus(int $id, string $status, ?string $note = null, string $source = 'admin'): array
    {
        $allowed = ['new','received','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];
        if (!in_array($status, $allowed, true)) throw new RuntimeException('Status de pedido inválido.', 422);
        $current = $this->find($id);
        if (!$current) throw new RuntimeException('Pedido não encontrado.', 404);
        if ($current['status'] === 'cancelled' || $current['status'] === 'delivered') throw new RuntimeException('Este pedido não pode mais mudar de status.', 422);
        if ($status === 'cancelled' && $current['payment_status'] === 'paid') $status = 'cancelled';
        $timestampColumn = ['received' => 'received_at', 'confirmed' => 'confirmed_at', 'ready' => 'ready_at', 'delivered' => 'delivered_at', 'cancelled' => 'cancelled_at'][$status] ?? null;
        $set = 'status = :status'; if ($timestampColumn) $set .= ", {$timestampColumn} = NOW()";
        $stmt = $this->pdo->prepare("UPDATE orders SET {$set} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['status' => $status, 'id' => $id, 'tenant_id' => TenantContext::requireId()]);
        $history = $this->pdo->prepare('INSERT INTO order_status_history (tenant_id, order_id, from_status, to_status, user_id, source, note) VALUES (:tenant_id, :order_id, :from_status, :to_status, :user_id, :source, :note)');
        $history->execute(['tenant_id' => TenantContext::requireId(), 'order_id' => $id, 'from_status' => $current['status'], 'to_status' => $status, 'user_id' => Auth::user()?->id, 'source' => $source, 'note' => $this->nullableString($note)]);
        $outbox = $this->pdo->prepare('INSERT INTO outbox_events (tenant_id, event_name, aggregate_type, aggregate_id, payload_json) VALUES (:tenant_id, :event_name, "order", :aggregate_id, :payload_json)');
        $outbox->execute(['tenant_id' => TenantContext::requireId(), 'event_name' => 'order.' . $status, 'aggregate_id' => $id, 'payload_json' => json_encode(['order_id' => $id, 'status' => $status], JSON_UNESCAPED_UNICODE)]);
        AuditLogger::log('order.status_changed', 'order', $id, ['from' => $current['status'], 'to' => $status, 'source' => $source]);
        return $this->find($id) ?? [];
    }

    private function findByIdempotency(?string $externalId, ?string $idempotencyKey): ?array
    {
        if (!$externalId && !$idempotencyKey) return null;
        $conditions = []; $params = ['tenant_id' => TenantContext::requireId()];
        if ($externalId) { $conditions[] = 'external_order_id = :external_order_id'; $params['external_order_id'] = $externalId; }
        if ($idempotencyKey) { $conditions[] = 'idempotency_key = :idempotency_key'; $params['idempotency_key'] = $idempotencyKey; }
        $stmt = $this->pdo->prepare('SELECT id FROM orders WHERE tenant_id = :tenant_id AND (' . implode(' OR ', $conditions) . ') LIMIT 1'); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function assertCustomer(int $customerId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM customers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'); $stmt->execute(['id' => $customerId, 'tenant_id' => TenantContext::requireId()]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Cliente não pertence ao tenant atual.', 422);
    }

    private function updateCustomerMetrics(int $customerId, float $total): void
    {
        $stmt = $this->pdo->prepare('UPDATE customers SET average_ticket = ((average_ticket * orders_count) + :total) / (orders_count + 1), orders_count = orders_count + 1, last_order_at = NOW() WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['total' => $total, 'id' => $customerId, 'tenant_id' => TenantContext::requireId()]);
    }

    private function orderNumber(): string { return 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2))); }
    private function paymentMethod(mixed $method): ?string { $method = strtolower(trim((string) $method)); return in_array($method, ['pix','card','cash','payment_link','other'], true) ? $method : ($method !== '' ? 'other' : null); }
    private function nullableString(mixed $value): ?string { $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, 500); }
    private function jsonOrNull(mixed $value): ?string { return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE); }
}
