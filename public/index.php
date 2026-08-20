<?php
/**
 * BASEAPP - Entry Point
 * Compativel com hospedagem compartilhada (Apache + PHP 8.x)
 */

// Carrega o bootstrap: paths, sessao, headers, autoload, env
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;
use App\Core\Auth;
use App\Middlewares\TenantMiddleware;

$uriAtual = strtok($_SERVER['REQUEST_URI'], '?');

// Rotas que nao precisam de autenticacao
$rotasPublicas = ['/login', '/logout', '/api/v1', '/webhooks'];

$ehPublica = false;
foreach ($rotasPublicas as $pub) {
    if ($uriAtual === $pub || strpos($uriAtual, $pub) === 0) {
        $ehPublica = true;
        break;
    }
}

// Carrega rotas
require_once BASE_PATH . '/routes/web.php';

// Multi-tenant (opcional): carrega o TenantContext a partir da sessao
// antes do controller ser chamado. Se o projeto for single-tenant,
// remova este bloco e o TenantMiddleware.
$apiComSessao = str_starts_with($uriAtual, '/api/v1') && Auth::check();
$webhookPublico = str_starts_with($uriAtual, '/webhooks');
if ((!$ehPublica || $apiComSessao) && $uriAtual !== '/selecionar-empresa' && !$webhookPublico && Auth::check()) {
    (new TenantMiddleware())->handle();
}

// Despacha a requisicao
Router::dispatch();
