<?php
namespace App\Models;

use App\Core\Model;

/**
 * Representa uma organizacao/empresa/workspace no modo multi-tenant.
 * Se o projeto nao precisa de multi-tenant, este Model (e a tabela
 * tenants/user_tenants) pode ser removido sem afetar o restante do
 * framework.
 */
class Tenant extends Model {
    protected string $table = 'tenants';

    public function findById(int $id): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM tenants WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function all(): array {
        return $this->pdo->query("SELECT * FROM tenants ORDER BY name")->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO tenants (name, slug, status, settings_json, created_at)
            VALUES (:name, :slug, :status, :settings_json, NOW())
        ");
        $stmt->execute([
            'name'          => $data['name'],
            'slug'          => $data['slug'],
            'status'        => $data['status'] ?? 'active',
            'settings_json' => $data['settings_json'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
