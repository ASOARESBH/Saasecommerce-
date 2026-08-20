<?php
namespace App\Core;

use RuntimeException;

/**
 * Contexto de execução da empresa/tenant atual.
 *
 * O domínio e-commerce nunca deve usar um tenant implícito. Controllers e
 * Services devem chamar requireId() antes de consultar ou gravar dados.
 */
class TenantContext
{
    private static ?object $tenant = null;
    private static ?int $tenantId = null;
    private static ?array $settings = null;

    public static function set(object $tenant): void
    {
        self::$tenant = $tenant;
        self::$tenantId = (int) $tenant->id;
        self::$settings = null;
    }

    public static function clear(): void
    {
        self::$tenant = null;
        self::$tenantId = null;
        self::$settings = null;
    }

    public static function get(): ?object { return self::$tenant; }
    public static function id(): ?int { return self::$tenantId; }
    public static function name(): string { return self::$tenant->name ?? ''; }
    public static function isSet(): bool { return self::$tenantId !== null; }

    public static function requireId(): int
    {
        if (self::$tenantId === null) {
            throw new RuntimeException('Tenant não definido para esta operação.', 422);
        }
        return self::$tenantId;
    }

    public static function setting(string $key, mixed $default = null): mixed
    {
        if (!self::$tenant) return $default;
        if (self::$settings === null) {
            self::$settings = json_decode(self::$tenant->settings_json ?? '{}', true) ?: [];
        }
        return self::$settings[$key] ?? $default;
    }

    public static function settings(): array
    {
        if (!self::$tenant) return [];
        if (self::$settings === null) {
            self::$settings = json_decode(self::$tenant->settings_json ?? '{}', true) ?: [];
        }
        return self::$settings;
    }
}
