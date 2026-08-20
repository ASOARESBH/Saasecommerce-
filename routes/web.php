<?php
use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\PermissionMiddleware;
use App\Middlewares\SessionTimeoutMiddleware;

Router::get('/', fn() => header('Location: /dashboard'));

// Autenticação web.
Router::group(['middleware' => [CsrfMiddleware::class]], function () {
    Router::get('/login', 'AuthController@showLogin');
    Router::post('/login', 'AuthController@login');
});
Router::get('/logout', 'AuthController@logout');

// Painel web autenticado.
Router::group(['middleware' => [AuthMiddleware::class, SessionTimeoutMiddleware::class, CsrfMiddleware::class]], function () {
    Router::get('/selecionar-empresa', 'AuthController@selectTenant');
    Router::post('/selecionar-empresa', 'AuthController@setTenant');
    Router::get('/dashboard', 'DashboardController@index');
    Router::group(['prefix' => '/api'], function () {
        Router::get('/cnpj/{cnpj}', 'CnpjController@consultar');
    });
    Router::group(['prefix' => '/usuarios'], function () {
        Router::get('', 'UsuariosController@index', [[PermissionMiddleware::class, 'manage_users']]);
        Router::get('/create', 'UsuariosController@create', [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('', 'UsuariosController@store', [[PermissionMiddleware::class, 'manage_users']]);
        Router::get('/{id}/edit', 'UsuariosController@edit', [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('/{id}/update', 'UsuariosController@update', [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('/{id}/toggle', 'UsuariosController@toggleStatus', [[PermissionMiddleware::class, 'manage_users']]);
        Router::post('/{id}/remover', 'UsuariosController@remover', [[PermissionMiddleware::class, 'manage_users']]);
    });
});

// API REST versionada. Sites e canais usam API Key/Bearer + segredo; o painel
// também pode consumi-la via sessão, com o mesmo contrato.
Router::group(['prefix' => '/api/v1'], function () {
    Router::get('/menu', 'Api\\V1\\CatalogController@products');
    Router::get('/products', 'Api\\V1\\CatalogController@products');
    Router::get('/categories', 'Api\\V1\\CatalogController@categories');
    Router::get('/addons', 'Api\\V1\\CatalogController@addons');
    Router::get('/combos', 'Api\\V1\\CatalogController@combos');
    Router::get('/settings', 'Api\\V1\\CatalogController@settings');

    Router::get('/orders', 'Api\\V1\\OrdersController@index');
    Router::post('/orders', 'Api\\V1\\OrdersController@store');
    Router::get('/orders/{id}', 'Api\\V1\\OrdersController@show');
    Router::post('/orders/{id}/cancel', 'Api\\V1\\OrdersController@cancel');
    Router::post('/orders/{id}/status', 'Api\\V1\\OrdersController@status');

    Router::get('/customers', 'Api\\V1\\CustomersController@index');
    Router::post('/customers', 'Api\\V1\\CustomersController@store');
    Router::get('/customers/{id}', 'Api\\V1\\CustomersController@show');
    Router::get('/customers/{id}/loyalty', 'Api\\V1\\CustomersController@loyalty');

    Router::post('/coupons/validate', 'Api\\V1\\CouponsController@validate');
    Router::get('/delivery-areas', 'Api\\V1\\DeliveryController@areas');
    Router::post('/delivery/check', 'Api\\V1\\DeliveryController@check');

    Router::get('/dashboard', 'Api\\V1\\AnalyticsController@dashboard');
    Router::get('/reports/{type}', 'Api\\V1\\AnalyticsController@report');
    Router::get('/reports/{type}/export', 'Api\\V1\\ReportsController@export');
    Router::get('/operations/kitchen', 'Api\\V1\\OperationsController@kitchen');
    Router::get('/operations/dispatch', 'Api\\V1\\OperationsController@dispatch');
    Router::get('/operations/drivers', 'Api\\V1\\OperationsController@drivers');
    Router::post('/operations/deliveries/{id}/assign', 'Api\\V1\\OperationsController@assignDriver');
    Router::post('/operations/deliveries/{id}/status', 'Api\\V1\\OperationsController@deliveryStatus');
    Router::post('/operations/orders/{id}/payment', 'Api\\V1\\OperationsController@payment');
    Router::get('/operations/inventory', 'Api\\V1\\OperationsController@inventory');
    Router::get('/operations/finance', 'Api\\V1\\OperationsController@finance');
    Router::get('/loyalty/customers/{id}', 'Api\\V1\\LoyaltyController@account');
    Router::post('/loyalty/customers/{id}/adjust', 'Api\\V1\\LoyaltyController@adjust');
    Router::get('/marketing/campaigns', 'Api\\V1\\MarketingController@campaigns');
    Router::post('/marketing/campaigns', 'Api\\V1\\MarketingController@createCampaign');

    Router::get('/integrations/clients', 'Api\\V1\\IntegrationAdminController@clients');
    Router::post('/integrations/clients', 'Api\\V1\\IntegrationAdminController@createClient');
    Router::post('/integrations/clients/{id}/revoke', 'Api\\V1\\IntegrationAdminController@revokeClient');
    Router::get('/integrations/connections', 'Api\\V1\\IntegrationAdminController@connections');
    Router::post('/integrations/connections', 'Api\\V1\\IntegrationAdminController@storeConnection');

    Router::post('/admin/products', 'Api\\V1\\CatalogAdminController@product');
    Router::post('/admin/categories', 'Api\\V1\\CatalogAdminController@category');
    Router::post('/admin/addons', 'Api\\V1\\CatalogAdminController@addon');
    Router::post('/admin/delivery-areas', 'Api\\V1\\CatalogAdminController@area');
    Router::post('/admin/ingredients', 'Api\\V1\\CatalogAdminController@ingredient');
    Router::post('/admin/inventory/movements', 'Api\\V1\\CatalogAdminController@inventoryMovement');
    Router::post('/internal/outbox/process', 'Api\\V1\\WorkerController@processOutbox');
});

// Endpoint de entrada para qualquer provedor/canal. O tenant não é aceito no
// corpo: ele é resolvido pelo slug do caminho e a carga é validada por HMAC.
Router::post('/webhooks/{tenantSlug}/{provider}/{event}', 'Api\\V1\\WebhookController@receive');
