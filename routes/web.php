<?php
use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\PermissionMiddleware;
use App\Middlewares\SessionTimeoutMiddleware;

// ============================================================
// BASEAPP — Rotas
// Nenhuma rota de negocio aqui. Isto e apenas o esqueleto de
// autenticacao + exemplo de CRUD protegido por RBAC.
//
// Padrao de uso dos middlewares:
//   AuthMiddleware        -> exige usuario logado
//   CsrfMiddleware        -> gera/valida o token CSRF (GET gera, POST valida)
//   PermissionMiddleware  -> exige uma permissao especifica (RBAC via banco)
//   SessionTimeoutMiddleware -> derruba a sessao apos X segundos de inatividade
// ============================================================

Router::get('/', fn() => header('Location: /dashboard'));

// Autenticacao (rotas publicas, mas com CSRF)
Router::group(['middleware' => [CsrfMiddleware::class]], function () {
    Router::get('/login',  'AuthController@showLogin');
    Router::post('/login', 'AuthController@login');
});
Router::get('/logout', 'AuthController@logout');

// Rotas autenticadas
Router::group(['middleware' => [AuthMiddleware::class, SessionTimeoutMiddleware::class, CsrfMiddleware::class]], function () {

    // Multi-tenant (opcional) — remova este bloco se o projeto for single-tenant
    Router::get('/selecionar-empresa',  'AuthController@selectTenant');
    Router::post('/selecionar-empresa', 'AuthController@setTenant');

    // Dashboard (exemplo)
    Router::get('/dashboard', 'DashboardController@index');

    // Usuarios — exemplo de CRUD protegido por permissao (RBAC vindo do banco)
    Router::group(['prefix' => '/usuarios'], function () {
        Router::get('',               'UsuariosController@index',        [[PermissionMiddleware::class, 'manage_users']]);
        Router::get('/create',        'UsuariosController@create',       [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('',              'UsuariosController@store',        [[PermissionMiddleware::class, 'manage_users']]);
        Router::get('/{id}/edit',     'UsuariosController@edit',         [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('/{id}/update',  'UsuariosController@update',       [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('/{id}/toggle',  'UsuariosController@toggleStatus', [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('/{id}/remover', 'UsuariosController@remover',      [[PermissionMiddleware::class, 'manage_users']]);
    });
});
