<?php
namespace App\Models;

use App\Core\Model;

class User extends Model {
    protected string $table = 'users';

    public function findById(int $id): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /** Lista todos os usuarios globais, com o nome da role. */
    public function all(): array {
        $stmt = $this->pdo->query("
            SELECT u.id, u.name, u.email, u.status, u.last_login_at, u.created_at,
                   r.id AS role_id, r.slug AS role_slug, r.name AS role_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            ORDER BY u.name
        ");
        return $stmt->fetchAll();
    }

    /** Lista usuarios vinculados a um tenant (multi-tenant). */
    public function findByTenant(int $tenantId): array {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name, u.email, u.status, u.last_login_at,
                   ut.role_id, r.slug AS role_slug, r.name AS role_name, ut.active AS tenant_active
            FROM users u
            JOIN user_tenants ut ON ut.user_id = u.id
            JOIN roles r ON r.id = ut.role_id
            WHERE ut.tenant_id = :tid
            ORDER BY u.name
        ");
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (name, email, password, role_id, status, created_at)
            VALUES (:name, :email, :password, :role_id, :status, NOW())
        ");
        $stmt->execute([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id'  => $data['role_id'],
            'status'   => $data['status'] ?? 'active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->pdo->prepare("
            UPDATE users SET name = :name, role_id = :role_id, status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'    => $data['name'],
            'role_id' => $data['role_id'],
            'status'  => $data['status'],
            'id'      => $id,
        ]);
    }

    public function toggleStatus(int $id): bool {
        $stmt = $this->pdo->prepare("
            UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function attachToTenant(int $userId, int $tenantId, int $roleId): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_tenants (user_id, tenant_id, role_id, active)
            VALUES (:uid, :tid, :rid, 1)
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), active = 1
        ");
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId, 'rid' => $roleId]);
    }
}
