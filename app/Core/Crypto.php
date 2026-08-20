<?php
namespace App\Core;

use RuntimeException;

class Crypto
{
    public static function encrypt(string $plain): string
    {
        $key = self::key();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) throw new RuntimeException('Não foi possível proteger o segredo.');
        return base64_encode($iv . hash_hmac('sha256', $iv . $cipher, $key, true) . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 48) throw new RuntimeException('Segredo cifrado inválido.');
        $key = self::key();
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        if (!hash_equals($mac, hash_hmac('sha256', $iv . $cipher, $key, true))) {
            throw new RuntimeException('Integridade do segredo inválida.');
        }
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) throw new RuntimeException('Não foi possível abrir o segredo.');
        return $plain;
    }

    private static function key(): string
    {
        $secret = (string) ($_ENV['APP_SECRET'] ?? '');
        if (strlen($secret) < 16) throw new RuntimeException('APP_SECRET precisa ter pelo menos 16 caracteres.');
        return hash('sha256', $secret, true);
    }
}
