<?php
namespace App\Core;

/**
 * RBAC orientado a banco de dados (roles / permissions / role_permissions).
 *
 * Nao ha nenhuma permissao de negocio pre-cadastrada aqui — todas as
 * permissoes disponiveis ficam na tabela `permissions` e sao atribuidas
 * a cada `role` via `role_permissions` (ver database/migrations e
 * database/seeds). Isso permite adicionar/remover permissoes por
 * projeto sem alterar codigo PHP.
 *
 * A unica regra especial embutida no framework: a role com slug
 * 'superadmin' sempre tem acesso a tudo (curinga), assim como no Auth.
 */
class Permission {
    private static array $cache = [];

    /**
     * Retorna a lista de slugs de permissao associados a uma role.
     */
    public static function forRole(int $roleId): array {
        if (isset(self::$cache[$roleId])) {
            return self::$cache[$roleId];
        }

        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT p.slug
            FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
        ");
        $stmt->execute(['role_id' => $roleId]);

        return self::$cache[$roleId] = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'slug');
    }

    public static function can(?string $roleSlug, ?int $roleId, string $permission): bool {
        if ($roleSlug === 'superadmin') {
            return true;
        }
        if (!$roleId) {
            return false;
        }
        return in_array($permission, self::forRole($roleId), true);
    }
}
