<?php
namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;
use RuntimeException;

class CustomerService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::getInstance(); }

    public function upsert(array $data): array
    {
        $tenantId = TenantContext::requireId();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Nome do cliente é obrigatório.', 422);
        $phone = $this->normalizePhone($data['phone'] ?? $data['whatsapp'] ?? null);
        $email = filter_var($data['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
        $existing = null;
        if ($phone) {
            $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id AND phone = :phone LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId, 'phone' => $phone]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$existing && $email) {
            $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id AND email = :email LIMIT 1');
            $stmt->execute(['tenant_id' => $tenantId, 'email' => $email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE customers SET name = :name, phone = COALESCE(:phone, phone), whatsapp = COALESCE(:whatsapp, whatsapp), email = COALESCE(:email, email), document = COALESCE(:document, document), notes = COALESCE(:notes, notes), consent_json = COALESCE(:consent_json, consent_json), source = COALESCE(:source, source) WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute([
                'name' => $name, 'phone' => $phone, 'whatsapp' => $phone, 'email' => $email,
                'document' => $this->nullableString($data['document'] ?? null), 'notes' => $this->nullableString($data['notes'] ?? null),
                'consent_json' => $this->jsonOrNull($data['consent'] ?? null), 'source' => $this->nullableString($data['source'] ?? null),
                'id' => $existing['id'], 'tenant_id' => $tenantId,
            ]);
            $customerId = (int) $existing['id'];
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO customers (tenant_id, name, phone, whatsapp, email, document, notes, consent_json, source) VALUES (:tenant_id, :name, :phone, :whatsapp, :email, :document, :notes, :consent_json, :source)');
            $stmt->execute([
                'tenant_id' => $tenantId, 'name' => $name, 'phone' => $phone, 'whatsapp' => $phone, 'email' => $email,
                'document' => $this->nullableString($data['document'] ?? null), 'notes' => $this->nullableString($data['notes'] ?? null),
                'consent_json' => $this->jsonOrNull($data['consent'] ?? null), 'source' => $this->nullableString($data['source'] ?? null),
            ]);
            $customerId = (int) $this->pdo->lastInsertId();
        }

        if (isset($data['address']) && is_array($data['address'])) $this->saveAddress($customerId, $data['address']);
        return $this->find($customerId) ?? throw new RuntimeException('Cliente não encontrado após gravação.', 500);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => TenantContext::requireId()]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) return null;
        $addresses = $this->pdo->prepare('SELECT * FROM customer_addresses WHERE tenant_id = :tenant_id AND customer_id = :customer_id ORDER BY is_default DESC, id DESC');
        $addresses->execute(['tenant_id' => TenantContext::requireId(), 'customer_id' => $id]);
        $customer['addresses'] = $addresses->fetchAll(PDO::FETCH_ASSOC);
        $orders = $this->pdo->prepare('SELECT id, order_number, status, total, source, created_at FROM orders WHERE tenant_id = :tenant_id AND customer_id = :customer_id ORDER BY created_at DESC LIMIT 25');
        $orders->execute(['tenant_id' => TenantContext::requireId(), 'customer_id' => $id]);
        $customer['history'] = $orders->fetchAll(PDO::FETCH_ASSOC);
        return $customer;
    }

    public function list(array $filters = []): array
    {
        [$page, $perPage, $offset] = $filters['pagination'] ?? [1, 25, 0];
        $tenantId = TenantContext::requireId();
        $where = ['tenant_id = :tenant_id']; $params = ['tenant_id' => $tenantId];
        if (!empty($filters['search'])) { $where[] = '(name LIKE :search OR phone LIKE :search OR email LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
        $sqlWhere = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM customers WHERE {$sqlWhere}"); $count->execute($params); $total = (int) $count->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE {$sqlWhere} ORDER BY last_order_at DESC, name LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int) ceil($total / max(1, $perPage))];
    }

    public function loyalty(int $customerId): array
    {
        $stmt = $this->pdo->prepare('SELECT la.*, c.name FROM loyalty_accounts la JOIN customers c ON c.id = la.customer_id AND c.tenant_id = la.tenant_id WHERE la.tenant_id = :tenant_id AND la.customer_id = :customer_id LIMIT 1');
        $stmt->execute(['tenant_id' => TenantContext::requireId(), 'customer_id' => $customerId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['customer_id' => $customerId, 'points_balance' => 0, 'level_slug' => null, 'transactions' => []];
        if (!empty($account['id'])) {
            $tx = $this->pdo->prepare('SELECT type, points, reason, created_at FROM loyalty_transactions WHERE tenant_id = :tenant_id AND loyalty_account_id = :account_id ORDER BY created_at DESC LIMIT 50');
            $tx->execute(['tenant_id' => TenantContext::requireId(), 'account_id' => $account['id']]);
            $account['transactions'] = $tx->fetchAll(PDO::FETCH_ASSOC);
        }
        return $account;
    }

    private function saveAddress(int $customerId, array $address): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO customer_addresses (tenant_id, customer_id, label, postal_code, street, number, complement, neighborhood, city, state, reference_note, latitude, longitude, is_default) VALUES (:tenant_id, :customer_id, :label, :postal_code, :street, :number, :complement, :neighborhood, :city, :state, :reference_note, :latitude, :longitude, :is_default)');
        $stmt->execute([
            'tenant_id' => TenantContext::requireId(), 'customer_id' => $customerId, 'label' => $this->nullableString($address['label'] ?? null),
            'postal_code' => $this->nullableString($address['postal_code'] ?? $address['cep'] ?? null), 'street' => trim((string) ($address['street'] ?? $address['logradouro'] ?? '')),
            'number' => $this->nullableString($address['number'] ?? $address['numero'] ?? null), 'complement' => $this->nullableString($address['complement'] ?? null),
            'neighborhood' => $this->nullableString($address['neighborhood'] ?? $address['bairro'] ?? null), 'city' => $this->nullableString($address['city'] ?? $address['cidade'] ?? null),
            'state' => $this->nullableString($address['state'] ?? $address['estado'] ?? null), 'reference_note' => $this->nullableString($address['reference_note'] ?? null),
            'latitude' => $address['latitude'] ?? null, 'longitude' => $address['longitude'] ?? null, 'is_default' => !empty($address['is_default']) ? 1 : 0,
        ]);
    }

    private function normalizePhone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        return $digits !== '' ? substr($digits, 0, 20) : null;
    }

    private function nullableString(mixed $value): ?string { $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, 500); }
    private function jsonOrNull(mixed $value): ?string { return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE); }
}
