<?php
namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Middleware;

/**
 * Restringe uma rota a usuarios com role 'superadmin' (acesso total,
 * independente de tenant). Util para paineis administrativos globais.
 */
class SuperAdminMiddleware extends Middleware {
    public function handle(): void {
        if (!Auth::check() || !Auth::isSuperAdmin()) {
            http_response_code(403);
            exit('Acesso restrito ao administrador do sistema.');
        }
    }
}
