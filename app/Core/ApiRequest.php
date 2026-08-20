<?php
namespace App\Core;

class ApiRequest
{
    private static ?array $json = null;
    private static ?string $raw = null;

    public static function body(): array
    {
        if (self::$json !== null) return self::$json;
        $raw = self::rawBody();
        if ($raw === '') return self::$json = [];
        $data = json_decode($raw, true);
        return self::$json = is_array($data) ? $data : [];
    }

    public static function rawBody(): string
    {
        if (self::$raw === null) self::$raw = file_get_contents('php://input') ?: '';
        return self::$raw;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return self::body()[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function string(string $key, ?string $default = null, int $maxLength = 10000): ?string
    {
        $value = self::input($key, $default);
        if ($value === null) return null;
        if (!is_scalar($value)) return $default;
        $value = trim((string) $value);
        return mb_substr($value, 0, $maxLength);
    }

    public static function int(string $key, ?int $default = null): ?int
    {
        $value = self::input($key, $default);
        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function float(string $key, ?float $default = null): ?float
    {
        $value = self::input($key, $default);
        return filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function array(string $key, array $default = []): array
    {
        $value = self::input($key, $default);
        return is_array($value) ? $value : $default;
    }

    public static function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') $key = 'CONTENT_TYPE';
        return isset($_SERVER[$key]) ? trim((string) $_SERVER[$key]) : $default;
    }

    public static function bearerToken(): ?string
    {
        $header = self::header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $match)) return trim($match[1]);
        return null;
    }

    public static function pagination(): array
    {
        $page = max(1, self::int('page', 1) ?? 1);
        $perPage = min(100, max(1, self::int('per_page', 25) ?? 25));
        return [$page, $perPage, ($page - 1) * $perPage];
    }

    public static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}
