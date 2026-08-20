<?php
namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\TenantContext;
use App\Models\Tenant;

class TenantMiddleware extends Middleware
{
    public function handle(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        TenantContext::clear();
        $tenantId = Auth::tenantId();
        if (!$tenantId) {
            if (Auth::isSuperAdmin() || count(Auth::userTenants()) > 1) { header('Location: /selecionar-empresa'); exit; }
            http_response_code(403); echo 'Nenhuma empresa ativa foi vinculada a este usuário.'; exit;
        }
        $tenant = (new Tenant())->findById($tenantId);
        if (!$tenant || $tenant->status !== 'active') {
            Auth::logout(); header('Location: /login?error=tenant_inativo'); exit;
        }
        if (!Auth::isSuperAdmin()) {
            $allowed = false;
            foreach (Auth::userTenants() as $userTenant) if ((int) $userTenant->tenant_id === $tenantId && $userTenant->status === 'active') { $allowed = true; break; }
            if (!$allowed) { Auth::logout(); header('Location: /login?error=tenant_invalido'); exit; }
        }
        TenantContext::set($tenant);
    }
}
