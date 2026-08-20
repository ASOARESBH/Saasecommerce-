<?php
namespace App\Core;

use RuntimeException;

class Auth
{
    public static function login(string $email, string $password): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email AND u.status = "active" LIMIT 1');
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user->password)) return false;
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user->id]);
        unset($user->password);
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['tenant_id'] = null;
        $_SESSION['permissions'] = Permission::forRole((int) $user->role_id);
        $_SESSION['user_tenants'] = [];

        $stmt2 = $pdo->prepare('SELECT ut.tenant_id, ut.role_id, r.slug AS role_slug, r.name AS role_name, t.name, t.slug, t.status FROM user_tenants ut JOIN tenants t ON t.id = ut.tenant_id JOIN roles r ON r.id = ut.role_id WHERE ut.user_id = :uid AND ut.active = 1 AND t.status = "active" ORDER BY t.name');
        $stmt2->execute(['uid' => $user->id]);
        $_SESSION['user_tenants'] = $stmt2->fetchAll();
        if (count($_SESSION['user_tenants']) === 1) self::setTenant((int) $_SESSION['user_tenants'][0]->tenant_id);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool { return isset($_SESSION['user_id']); }
    public static function user(): ?object { return $_SESSION['user'] ?? null; }
    public static function tenantId(): ?int { return isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null; }
    public static function isSuperAdmin(): bool { return (bool) (self::user()?->role_slug === 'superadmin'); }
    public static function can(string $permission): bool
    {
        if (!self::check()) return false;
        if (self::isSuperAdmin()) return true;
        return in_array($permission, $_SESSION['permissions'] ?? [], true);
    }
    public static function userTenants(): array { return $_SESSION['user_tenants'] ?? []; }

    public static function setTenant(int $tenantId): void
    {
        $pdo = Database::getInstance();
        if (self::isSuperAdmin()) {
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = :id AND status = 'active' LIMIT 1");
            $stmt->execute(['id' => $tenantId]);
        } else {
            $stmt = $pdo->prepare("SELECT t.*, ut.role_id, r.slug AS tenant_role_slug, r.name AS tenant_role_name FROM user_tenants ut JOIN tenants t ON t.id = ut.tenant_id JOIN roles r ON r.id = ut.role_id WHERE ut.user_id = :user_id AND ut.tenant_id = :tenant_id AND ut.active = 1 AND t.status = 'active' LIMIT 1");
            $stmt->execute(['user_id' => $_SESSION['user_id'] ?? 0, 'tenant_id' => $tenantId]);
        }
        $tenant = $stmt->fetch();
        if (!$tenant) throw new RuntimeException('Tenant inválido para o usuário.', 403);
        $_SESSION['tenant_id'] = $tenantId;
        if (!self::isSuperAdmin()) {
            $roleId = (int) ($tenant->role_id ?? 0);
            $_SESSION['permissions'] = $roleId ? Permission::forRole($roleId) : [];
            $_SESSION['tenant_role_slug'] = $tenant->tenant_role_slug ?? null;
        }
    }
}
