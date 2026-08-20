<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\Audit\AuditLogger;
use App\Core\Database;
use App\Core\TenantContext;
use RuntimeException;

class CatalogAdminController extends ApiController
{
    public function product(): never
    {
        $this->authenticate(['catalog:manage'], true);
        $body = $this->body(); $id = isset($body['id']) ? (int) $body['id'] : null;
        $name = trim((string) ($body['name'] ?? '')); if ($name === '') throw new RuntimeException('Nome do produto é obrigatório.', 422);
        $slug = $this->slug((string) ($body['slug'] ?? $name)); $pdo = Database::getInstance();
        if ($id) {
            $stmt = $pdo->prepare('UPDATE products SET category_id = :category_id, product_type = :product_type, name = :name, slug = :slug, sku = :sku, description = :description, image_url = :image_url, price = :price, cost = :cost, active = :active, featured = :featured, sort_order = :sort_order, metadata_json = :metadata WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute($this->productParams($body, $slug) + ['id' => $id, 'tenant_id' => TenantContext::requireId()]);
            AuditLogger::log('product.updated', 'product', $id, ['name' => $name]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO products (tenant_id, category_id, product_type, name, slug, sku, description, image_url, price, cost, active, featured, sort_order, metadata_json) VALUES (:tenant_id, :category_id, :product_type, :name, :slug, :sku, :description, :image_url, :price, :cost, :active, :featured, :sort_order, :metadata)');
            $params = $this->productParams($body, $slug) + ['tenant_id' => TenantContext::requireId()]; $stmt->execute($params); $id = (int) $pdo->lastInsertId();
            AuditLogger::log('product.created', 'product', $id, ['name' => $name]);
        }
        ApiResponse::success(['id' => $id], 201);
    }

    public function category(): never
    {
        $this->authenticate(['catalog:manage'], true); $body = $this->body(); $name = trim((string) ($body['name'] ?? '')); if ($name === '') throw new RuntimeException('Nome da categoria é obrigatório.', 422);
        $pdo = Database::getInstance(); $stmt = $pdo->prepare('INSERT INTO categories (tenant_id, name, slug, description, active, sort_order) VALUES (:tenant_id, :name, :slug, :description, :active, :sort_order)'); $stmt->execute(['tenant_id' => TenantContext::requireId(), 'name' => $name, 'slug' => $this->slug((string) ($body['slug'] ?? $name)), 'description' => $body['description'] ?? null, 'active' => !empty($body['active']) ? 1 : 0, 'sort_order' => (int) ($body['sort_order'] ?? 0)]); $id = (int) $pdo->lastInsertId(); AuditLogger::log('category.created', 'category', $id, ['name' => $name]); ApiResponse::success(['id' => $id], 201);
    }

    public function addon(): never
    {
        $this->authenticate(['catalog:manage'], true); $body = $this->body(); $name = trim((string) ($body['name'] ?? '')); if ($name === '') throw new RuntimeException('Nome do adicional é obrigatório.', 422);
        $pdo = Database::getInstance(); $stmt = $pdo->prepare('INSERT INTO addons (tenant_id, name, addon_type, description, price, cost, active, sort_order) VALUES (:tenant_id, :name, :addon_type, :description, :price, :cost, :active, :sort_order)'); $stmt->execute(['tenant_id' => TenantContext::requireId(), 'name' => $name, 'addon_type' => $body['addon_type'] ?? 'addon', 'description' => $body['description'] ?? null, 'price' => (float) ($body['price'] ?? 0), 'cost' => (float) ($body['cost'] ?? 0), 'active' => !empty($body['active']) ? 1 : 0, 'sort_order' => (int) ($body['sort_order'] ?? 0)]); $id = (int) $pdo->lastInsertId(); AuditLogger::log('addon.created', 'addon', $id, ['name' => $name]); ApiResponse::success(['id' => $id], 201);
    }

    public function area(): never
    {
        $this->authenticate(['delivery:manage'], true); $body = $this->body(); $name = trim((string) ($body['name'] ?? '')); if ($name === '') throw new RuntimeException('Nome da área é obrigatório.', 422);
        $pdo = Database::getInstance(); $stmt = $pdo->prepare('INSERT INTO delivery_areas (tenant_id, name, slug, city, state, postal_code, latitude, longitude, radius, delivery_fee, minimum_order, estimated_min, estimated_max, active) VALUES (:tenant_id, :name, :slug, :city, :state, :postal_code, :latitude, :longitude, :radius, :delivery_fee, :minimum_order, :estimated_min, :estimated_max, :active)'); $stmt->execute(['tenant_id' => TenantContext::requireId(), 'name' => $name, 'slug' => $this->slug((string) ($body['slug'] ?? $name)), 'city' => $body['city'] ?? null, 'state' => $body['state'] ?? null, 'postal_code' => $body['postal_code'] ?? $body['cep'] ?? null, 'latitude' => $body['latitude'] ?? null, 'longitude' => $body['longitude'] ?? null, 'radius' => $body['radius'] ?? null, 'delivery_fee' => (float) ($body['delivery_fee'] ?? 0), 'minimum_order' => (float) ($body['minimum_order'] ?? 0), 'estimated_min' => (int) ($body['estimated_min'] ?? 30), 'estimated_max' => (int) ($body['estimated_max'] ?? 60), 'active' => !empty($body['active']) ? 1 : 0]); $id = (int) $pdo->lastInsertId(); AuditLogger::log('delivery_area.created', 'delivery_area', $id, ['name' => $name]); ApiResponse::success(['id' => $id], 201);
    }

    public function ingredient(): never
    {
        $this->authenticate(['inventory:manage'], true); $body = $this->body(); $name = trim((string) ($body['name'] ?? '')); if ($name === '') throw new RuntimeException('Nome do insumo é obrigatório.', 422);
        $pdo = Database::getInstance(); $stmt = $pdo->prepare('INSERT INTO ingredients (tenant_id, name, sku, unit, minimum_stock, average_cost, current_stock, active) VALUES (:tenant_id, :name, :sku, :unit, :minimum_stock, :average_cost, :current_stock, :active)'); $stmt->execute(['tenant_id' => TenantContext::requireId(), 'name' => $name, 'sku' => $body['sku'] ?? null, 'unit' => $body['unit'] ?? 'un', 'minimum_stock' => (float) ($body['minimum_stock'] ?? 0), 'average_cost' => (float) ($body['average_cost'] ?? 0), 'current_stock' => (float) ($body['current_stock'] ?? 0), 'active' => !empty($body['active']) ? 1 : 0]); $id = (int) $pdo->lastInsertId(); AuditLogger::log('ingredient.created', 'ingredient', $id, ['name' => $name]); ApiResponse::success(['id' => $id], 201);
    }

    public function inventoryMovement(): never
    {
        $this->authenticate(['inventory:manage'], true); $body = $this->body(); $ingredientId = (int) ($body['ingredient_id'] ?? 0); $quantity = (float) ($body['quantity'] ?? 0); if ($ingredientId <= 0 || $quantity == 0) throw new RuntimeException('ingredient_id e quantity são obrigatórios.', 422);
        $pdo = Database::getInstance(); $pdo->beginTransaction(); try {
            $check = $pdo->prepare('SELECT id, current_stock FROM ingredients WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'); $check->execute(['id' => $ingredientId, 'tenant_id' => TenantContext::requireId()]); if (!$check->fetch()) throw new RuntimeException('Insumo não encontrado.', 404);
            $stmt = $pdo->prepare('INSERT INTO inventory_movements (tenant_id, ingredient_id, type, quantity, unit_cost, reference_type, reference_id, notes, user_id) VALUES (:tenant_id, :ingredient_id, :type, :quantity, :unit_cost, :reference_type, :reference_id, :notes, :user_id)'); $stmt->execute(['tenant_id' => TenantContext::requireId(), 'ingredient_id' => $ingredientId, 'type' => $body['type'] ?? 'adjustment', 'quantity' => $quantity, 'unit_cost' => (float) ($body['unit_cost'] ?? 0), 'reference_type' => $body['reference_type'] ?? null, 'reference_id' => $body['reference_id'] ?? null, 'notes' => $body['notes'] ?? null, 'user_id' => \App\Core\Auth::user()?->id]);
            $pdo->prepare('UPDATE ingredients SET current_stock = current_stock + :quantity WHERE id = :id AND tenant_id = :tenant_id')->execute(['quantity' => $quantity, 'id' => $ingredientId, 'tenant_id' => TenantContext::requireId()]); $pdo->commit(); AuditLogger::log('inventory.movement', 'ingredient', $ingredientId, ['quantity' => $quantity]); ApiResponse::success(['recorded' => true], 201);
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    private function productParams(array $body, string $slug): array
    {
        return ['category_id' => isset($body['category_id']) ? (int) $body['category_id'] : null, 'product_type' => $body['product_type'] ?? 'product', 'name' => trim((string) $body['name']), 'slug' => $slug, 'sku' => $body['sku'] ?? null, 'description' => $body['description'] ?? null, 'image_url' => $body['image_url'] ?? null, 'price' => (float) ($body['price'] ?? 0), 'cost' => (float) ($body['cost'] ?? 0), 'active' => !empty($body['active']) ? 1 : 0, 'featured' => !empty($body['featured']) ? 1 : 0, 'sort_order' => (int) ($body['sort_order'] ?? 0), 'metadata' => isset($body['metadata']) ? json_encode($body['metadata'], JSON_UNESCAPED_UNICODE) : null];
    }
    private function slug(string $text): string { $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtolower($text)) ?: strtolower($text); $text = preg_replace('/[^a-z0-9]+/', '-', $text); return trim($text, '-') ?: 'item-' . bin2hex(random_bytes(3)); }
}
