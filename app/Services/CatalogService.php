<?php
namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;
use RuntimeException;

class CatalogService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    private function tenantId(): int { return TenantContext::requireId(); }

    public function products(array $filters = []): array
    {
        [$page, $perPage, $offset] = $filters['pagination'] ?? [1, 25, 0];
        $tenantId = $this->tenantId();
        $where = ['p.tenant_id = :tenant_id', 'p.active = 1'];
        $params = ['tenant_id' => $tenantId];
        if (!empty($filters['category'])) { $where[] = 'c.slug = :category'; $params['category'] = $filters['category']; }
        if (!empty($filters['search'])) { $where[] = '(p.name LIKE :search OR p.description LIKE :search)'; $params['search'] = '%' . $filters['search'] . '%'; }
        if (!empty($filters['featured'])) $where[] = 'p.featured = 1';
        $sqlWhere = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE {$sqlWhere}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE {$sqlWhere} ORDER BY p.sort_order, p.name LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['sizes'] = $this->sizes((int) $item['id']);
            $item['addons'] = $this->addonsForProduct((int) $item['id']);
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int) ceil($total / max(1, $perPage))];
    }

    public function categories(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, description, sort_order FROM categories WHERE tenant_id = :tenant_id AND active = 1 ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenantId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addons(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, addon_type, description, price, sort_order FROM addons WHERE tenant_id = :tenant_id AND active = 1 ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenantId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function combos(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug, description, image_url, price, valid_from, valid_until, sort_order FROM combos WHERE tenant_id = :tenant_id AND active = 1 AND (valid_from IS NULL OR valid_from <= NOW()) AND (valid_until IS NULL OR valid_until >= NOW()) ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenantId()]);
        $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($combos as &$combo) {
            $items = $this->pdo->prepare('SELECT ci.product_id, ci.quantity, p.name FROM combo_items ci JOIN products p ON p.id = ci.product_id AND p.tenant_id = ci.tenant_id WHERE ci.tenant_id = :tenant_id AND ci.combo_id = :combo_id ORDER BY p.name');
            $items->execute(['tenant_id' => $this->tenantId(), 'combo_id' => $combo['id']]);
            $combo['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        }
        return $combos;
    }

    public function resolveLine(array $line): array
    {
        $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
        $comboId = isset($line['combo_id']) ? (int) $line['combo_id'] : null;
        $quantity = max(0.001, (float) ($line['quantity'] ?? 1));
        if (!$productId && !$comboId) throw new RuntimeException('Cada item precisa de product_id ou combo_id.', 422);

        if ($productId) {
            $stmt = $this->pdo->prepare('SELECT id, name, price, cost FROM products WHERE id = :id AND tenant_id = :tenant_id AND active = 1 LIMIT 1');
            $stmt->execute(['id' => $productId, 'tenant_id' => $this->tenantId()]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) throw new RuntimeException('Produto indisponível.', 422);
            $unitPrice = (float) $product['price'];
            $sizeId = isset($line['size_id']) ? (int) $line['size_id'] : null;
            if ($sizeId) {
                $size = $this->pdo->prepare('SELECT id, name, price, cost FROM product_sizes WHERE id = :id AND tenant_id = :tenant_id AND product_id = :product_id AND active = 1 LIMIT 1');
                $size->execute(['id' => $sizeId, 'tenant_id' => $this->tenantId(), 'product_id' => $productId]);
                $sizeRow = $size->fetch(PDO::FETCH_ASSOC);
                if (!$sizeRow) throw new RuntimeException('Tamanho inválido para o produto.', 422);
                $unitPrice = (float) $sizeRow['price'];
            }
            $resolved = ['product_id' => $productId, 'combo_id' => null, 'product_name' => $product['name'], 'quantity' => $quantity, 'unit_price' => $unitPrice, 'total_price' => $unitPrice * $quantity, 'notes' => (string) ($line['notes'] ?? ''), 'addons' => []];
            foreach ((array) ($line['addons'] ?? []) as $addonLine) {
                $addonId = (int) ($addonLine['addon_id'] ?? 0);
                $addonStmt = $this->pdo->prepare('SELECT id, name, price FROM addons WHERE id = :id AND tenant_id = :tenant_id AND active = 1 LIMIT 1');
                $addonStmt->execute(['id' => $addonId, 'tenant_id' => $this->tenantId()]);
                $addon = $addonStmt->fetch(PDO::FETCH_ASSOC);
                if (!$addon) throw new RuntimeException('Adicional inválido.', 422);
                $addonQty = max(0.001, (float) ($addonLine['quantity'] ?? 1));
                $resolved['addons'][] = ['addon_id' => $addon['id'], 'addon_name' => $addon['name'], 'quantity' => $addonQty, 'unit_price' => (float) $addon['price'], 'total_price' => (float) $addon['price'] * $addonQty];
                $resolved['total_price'] += (float) $addon['price'] * $addonQty * $quantity;
            }
            return $resolved;
        }

        $stmt = $this->pdo->prepare('SELECT id, name, price FROM combos WHERE id = :id AND tenant_id = :tenant_id AND active = 1 LIMIT 1');
        $stmt->execute(['id' => $comboId, 'tenant_id' => $this->tenantId()]);
        $combo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$combo) throw new RuntimeException('Combo indisponível.', 422);
        return ['product_id' => null, 'combo_id' => (int) $combo['id'], 'product_name' => $combo['name'], 'quantity' => $quantity, 'unit_price' => (float) $combo['price'], 'total_price' => (float) $combo['price'] * $quantity, 'notes' => (string) ($line['notes'] ?? ''), 'addons' => []];
    }

    private function sizes(int $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, code, price, cost, sort_order FROM product_sizes WHERE tenant_id = :tenant_id AND product_id = :product_id AND active = 1 ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenantId(), 'product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function addonsForProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id, a.name, a.addon_type, a.price, pa.required FROM product_addons pa JOIN addons a ON a.id = pa.addon_id AND a.tenant_id = pa.tenant_id WHERE pa.tenant_id = :tenant_id AND pa.product_id = :product_id AND a.active = 1 ORDER BY a.sort_order, a.name');
        $stmt->execute(['tenant_id' => $this->tenantId(), 'product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
