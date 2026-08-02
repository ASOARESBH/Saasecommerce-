<?php
namespace App\Core;

/**
 * Contexto de multi-tenant (opcional).
 *
 * Se o seu projeto nao precisa de multi-tenant, basta nunca chamar
 * TenantContext::set() — o resto do framework (Model::tenantWhere /
 * tenantParam, TenantMiddleware) ja funciona corretamente sem tenant
 * definido. Para remover de vez, apague esta classe, o TenantMiddleware,
 * o Model de Tenant e as tabelas tenants/user_tenants da migration.
 */
class TenantContext {
    private static ?object $tenant   = null;
    private static ?int $tenantId    = null;
    private static ?array $settings  = null;

    public static function set(object $tenant): void {
        self::$tenant   = $tenant;
        self::$tenantId = (int) $tenant->id;
        self::$settings = null;
    }

    public static function clear(): void {
        self::$tenant   = null;
        self::$tenantId = null;
        self::$settings = null;
    }

    public static function get(): ?object { return self::$tenant; }
    public static function id(): ?int    { return self::$tenantId; }
    public static function name(): string { return self::$tenant->name ?? ''; }
    public static function isSet(): bool  { return self::$tenantId !== null; }

    /**
     * Le uma configuracao arbitraria armazenada em tenants.settings_json,
     * sem nenhuma chave de negocio pre-definida. Cada projeto decide o
     * que guardar ali (ex: cor da marca, limites, flags de feature).
     */
    public static function setting(string $key, mixed $default = null): mixed {
        if (!self::$tenant) return $default;

        if (self::$settings === null) {
            self::$settings = json_decode(self::$tenant->settings_json ?? '{}', true) ?: [];
        }

        return self::$settings[$key] ?? $default;
    }
}
