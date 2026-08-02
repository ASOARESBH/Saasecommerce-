<?php
namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\TenantContext;
use App\Models\Tenant;

/**
 * Carrega o tenant ativo da sessao para o TenantContext antes do
 * Controller ser executado. So e necessario em projetos multi-tenant;
 * caso contrario, simplesmente nao registre este middleware nas rotas.
 */
class TenantMiddleware extends Middleware {
    public function handle(): void {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        // Superadmin: so precisa de contexto se estiver "entrando" em um tenant especifico
        if (Auth::isSuperAdmin()) {
            $tenantId = Auth::tenantId();
            if (!$tenantId) return;

            $tenant = (new Tenant())->findById($tenantId);
            if ($tenant) TenantContext::set($tenant);
            return;
        }

        // Se o usuario nao esta vinculado a nenhum tenant, o multi-tenant
        // simplesmente nao esta em uso neste projeto — segue sem contexto.
        $tenants = Auth::userTenants();
        if (empty($tenants)) {
            return;
        }

        $tenantId = Auth::tenantId();
        if (!$tenantId) {
            header('Location: /selecionar-empresa');
            exit;
        }

        $tenant = (new Tenant())->findById($tenantId);
        if (!$tenant || $tenant->status !== 'active') {
            Auth::logout();
            header('Location: /login?error=tenant_inativo');
            exit;
        }

        TenantContext::set($tenant);
    }
}
