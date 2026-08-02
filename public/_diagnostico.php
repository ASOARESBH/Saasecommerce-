<?php
/**
 * BASEAPP — Diagnostico para hospedagem compartilhada.
 * Acesso: https://seu-dominio.com/_diagnostico.php?key=SEU_APP_SECRET
 *
 * IMPORTANTE: apague este arquivo do servidor depois de usar.
 */

$base = dirname(__DIR__);

$envFile = $base . '/.env';
if (!file_exists($envFile)) {
    die('Arquivo .env nao encontrado.');
}
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') === false) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

$appSecret = $_ENV['APP_SECRET'] ?? '';
if ($appSecret === '' || !hash_equals($appSecret, $_GET['key'] ?? '')) {
    http_response_code(403);
    die('Acesso negado. Use ?key=SEU_APP_SECRET (o valor de APP_SECRET no .env).');
}

require_once $base . '/app/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnostico BASEAPP</h1>";

echo "<h2>1. Ambiente PHP</h2>";
echo "Versao do PHP: " . phpversion() . "<br>";
echo "SAPI: " . php_sapi_name() . "<br>";
echo (version_compare(PHP_VERSION, '8.1.0', '>=')
    ? "<span style='color:green'>OK — atende ao minimo (PHP 8.1+)</span>"
    : "<span style='color:red'>ATENCAO — este projeto requer PHP 8.1 ou superior</span>") . "<br>";

echo "<h2>2. Extensoes necessarias</h2>";
$exts = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo', 'openssl'];
foreach ($exts as $ext) {
    echo "Extensao <b>{$ext}</b>: " . (extension_loaded($ext) ? "<span style='color:green'>OK</span>" : "<span style='color:red'>FALTANDO</span>") . "<br>";
}

echo "<h2>3. Permissoes de diretorio</h2>";
$dirs = [$base . '/storage', $base . '/storage/logs', $base . '/storage/sessions', $base . '/storage/uploads'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        echo "Diretorio <b>" . basename($dir) . "</b>: <span style='color:red'>NAO EXISTE</span><br>";
    } else {
        echo "Diretorio <b>" . basename($dir) . "</b>: " . (is_writable($dir) ? "<span style='color:green'>GRAVAVEL</span>" : "<span style='color:red'>SEM PERMISSAO DE ESCRITA (ajuste para 755)</span>") . "<br>";
    }
}

echo "<h2>4. Conexao com o banco de dados</h2>";
try {
    $pdo = \App\Core\Database::getInstance();
    echo "<span style='color:green'>Conexao OK.</span><br>";
    $tabelasEsperadas = ['roles', 'permissions', 'role_permissions', 'users', 'tenants', 'user_tenants', 'audit_logs'];
    foreach ($tabelasEsperadas as $t) {
        $existe = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetchColumn();
        echo "Tabela <b>{$t}</b>: " . ($existe ? "<span style='color:green'>OK</span>" : "<span style='color:red'>NAO ENCONTRADA (rode o instalador ou importe a migration)</span>") . "<br>";
    }
} catch (\Exception $e) {
    echo "<span style='color:red'>Erro de conexao: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h2>5. Variaveis de ambiente</h2>";
echo "APP_ENV: " . htmlspecialchars($_ENV['APP_ENV'] ?? 'nao definido') . "<br>";
echo "APP_DEBUG: " . htmlspecialchars($_ENV['APP_DEBUG'] ?? 'nao definido') . "<br>";
echo "DB_HOST: " . htmlspecialchars($_ENV['DB_HOST'] ?? 'nao definido') . "<br>";
echo "DB_DATABASE: " . htmlspecialchars($_ENV['DB_DATABASE'] ?? 'nao definido') . "<br>";

echo "<hr><p>Se tudo acima estiver OK e mesmo assim aparecer erro 500 no site, verifique <code>storage/logs/php_errors.log</code> e <code>storage/logs/app.log</code>.</p>";
echo "<p style='color:red'><strong>Apague este arquivo (public/_diagnostico.php) do servidor depois de usar.</strong></p>";
