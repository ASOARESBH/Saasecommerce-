<?php
namespace App\Models;

use App\Core\Model;

class Role extends Model {
    protected string $table = 'roles';

    public function all(): array {
        return $this->pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
    }

    public function findById(int $id): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?object {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE slug = :slug LIMIT 1");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }
}
