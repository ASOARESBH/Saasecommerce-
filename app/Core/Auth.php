<?php
namespace App\Core;

class Auth {
    public static function login(string $email, string $password): bool {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT u.*, r.slug AS role_slug, r.name AS role_name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = :email AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user->password)) {
            return false;
        }

        // Atualiza ultimo login
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")
            ->execute(['id' => $user->id]);

        // Armazena usuario na sessao (sem a senha) e pre-carrega permissoes
        unset($user->password);
        $_SESSION['user']        = $user;
        $_SESSION['user_id']     = $user->id;
        $_SESSION['permissions'] = Permission::forRole((int) $user->role_id);

        // Superadmin nao precisa de tenant
        if ($user->role_slug === 'superadmin') {
            $_SESSION['tenant_id'] = null;
            return true;
        }

        // Multi-tenant (opcional): carrega os tenants do usuario, se houver
        $stmt2 = $pdo->prepare("
            SELECT ut.tenant_id, ut.role_id, r.slug AS role_slug, t.name, t.status
            FROM user_tenants ut
            JOIN tenants t ON t.id = ut.tenant_id
            JOIN roles r ON r.id = ut.role_id
            WHERE ut.user_id = :uid AND ut.active = 1 AND t.status = 'active'
        ");
        $stmt2->execute(['uid' => $user->id]);
        $tenants = $stmt2->fetchAll();

        $_SESSION['user_tenants'] = $tenants;

        if (count($tenants) === 1) {
            $_SESSION['tenant_id'] = $tenants[0]->tenant_id;
        }

        return true;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?object {
        return $_SESSION['user'] ?? null;
    }

    public static function tenantId(): ?int {
        return isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;
    }

    public static function isSuperAdmin(): bool {
        $user = self::user();
        return $user && $user->role_slug === 'superadmin';
    }

    public static function can(string $permission): bool {
        $user = self::user();
        if (!$user) return false;
        if ($user->role_slug === 'superadmin') return true;
        return in_array($permission, $_SESSION['permissions'] ?? [], true);
    }

    public static function userTenants(): array {
        return $_SESSION['user_tenants'] ?? [];
    }

    public static function setTenant(int $tenantId): void {
        $_SESSION['tenant_id'] = $tenantId;
    }
}
