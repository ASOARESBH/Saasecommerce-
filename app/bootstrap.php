<?php
/**
 * BASEAPP - Bootstrap
 * ORDEM OBRIGATORIA: ini_set -> sessao -> headers -> autoload -> env
 *
 * Este arquivo nao deve conter nenhuma regra de negocio. Ele so prepara
 * o ambiente (paths, sessao, headers de seguranca, autoload e .env)
 * para que o Router possa despachar a requisicao.
 */

// Define constantes de diretorio absolutas baseadas no local do arquivo
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

// Configuracoes de erro (antes de tudo)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');
error_reporting(E_ALL);

// ─── SESSAO: deve ser configurada ANTES de qualquer header() ─────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

$sessionPath = STORAGE_PATH . '/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ─────────────────────────────────────────────────────────────────────────────

// Headers de seguranca (APOS session_start)
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Carrega o autoloader customizado (nao depende do composer no servidor)
require_once APP_PATH . '/autoload.php';

// Funcao simples para ler .env sem dependencia externa
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;

            $name  = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove aspas se houver
            if (preg_match('/^"(.*)"$/s', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/s", $value, $m)) {
                $value = $m[1];
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name]    = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Carrega variaveis de ambiente
loadEnv(BASE_PATH . '/.env');

// Configura timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');

// Se debug estiver ativo, mostra erros
if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
    ini_set('display_errors', 1);
}
