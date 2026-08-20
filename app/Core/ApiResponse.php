<?php
namespace App\Core;

class ApiResponse
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, int $status = 200, array $meta = []): never
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta !== []) $payload['meta'] = $meta;
        self::json($payload, $status);
    }

    public static function error(string $message, int $status = 400, string $code = 'BAD_REQUEST', array $details = []): never
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) $error['details'] = $details;
        self::json(['success' => false, 'error' => $error], $status);
    }
}
