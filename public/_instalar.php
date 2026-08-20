<?php
/**
 * Instalador inicial. Use somente uma vez em ambiente controlado e apague o
 * arquivo do servidor depois da instalação.
 */
define('BASE_PATH', dirname(__DIR__));
$envFile = BASE_PATH . '/.env';
if (!file_exists($envFile)) die('Arquivo .env não encontrado.');
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos(trim($line), '#') === 0 || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2); $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}
$appSecret = $_ENV['APP_SECRET'] ?? '';
if ($appSecret === '' || strlen($appSecret) < 16) die('Defina um APP_SECRET real com pelo menos 16 caracteres.');
if (!hash_equals($appSecret, $_GET['key'] ?? '')) { http_response_code(403); die('Acesso negado.'); }
try {
    $pdo = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';port=' . ($_ENV['DB_PORT'] ?? 3306) . ';dbname=' . $_ENV['DB_DATABASE'] . ';charset=utf8mb4', $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) { die('<h2 style="color:red">Erro de conexão: ' . htmlspecialchars($e->getMessage()) . '</h2>'); }

function executarArquivoSql(PDO $pdo, string $path): array {
    if (!file_exists($path)) return [['status' => 'error', 'msg' => "Arquivo não encontrado: {$path}"]];
    $sql = preg_replace('/^--.*$/m', '', file_get_contents($path));
    $statements = array_filter(array_map('trim', explode(';', (string) $sql)));
    $resultados = [];
    foreach ($statements as $stmt) {
        try { $pdo->exec($stmt); $resultados[] = ['status' => 'ok', 'msg' => htmlspecialchars(strlen($stmt) > 120 ? substr($stmt, 0, 120) . '…' : $stmt)]; }
        catch (Throwable $e) { $resultados[] = ['status' => 'error', 'msg' => htmlspecialchars($e->getMessage())]; }
    }
    return $resultados;
}

$resultados = [];
$migrations = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
$seeds = glob(BASE_PATH . '/database/seeds/*.sql') ?: [];
natcasesort($migrations); natcasesort($seeds);
foreach (array_merge($migrations, $seeds) as $file) $resultados = array_merge($resultados, executarArquivoSql($pdo, $file));
$ok = count(array_filter($resultados, fn($r) => $r['status'] === 'ok')); $erros = count($resultados, fn($r) => $r['status'] === 'error');
?><!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><title>SaaS E-commerce — Instalação</title><style>body{font-family:Arial;background:#f4f7fb;padding:30px}.box{max-width:1000px;margin:auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 20px #0001}table{width:100%;border-collapse:collapse;font-size:12px}td,th{border:1px solid #ddd;padding:8px;text-align:left}.ok{background:#e9f9ef}.error{background:#fff0f0}.badge{padding:8px 12px;border-radius:8px;background:#eaf2ff;display:inline-block;margin-right:8px}</style></head><body><main class="box"><h1>SaaS E-commerce — Instalação</h1><p><span class="badge">OK: <?= $ok ?></span><span class="badge">Erros: <?= $erros ?></span></p><table><thead><tr><th>Status</th><th>Comando</th></tr></thead><tbody><?php foreach ($resultados as $r): ?><tr class="<?= $r['status'] ?>"><td><?= strtoupper($r['status']) ?></td><td><code><?= $r['msg'] ?></code></td></tr><?php endforeach; ?></tbody></table><?php if ($erros === 0): ?><p><strong>Instalação concluída.</strong> Login inicial: <code>admin@example.com</code> / <code>Admin@123</code>. Troque a senha e remova <code>public/_instalar.php</code>.</p><?php endif; ?></main></body></html>
