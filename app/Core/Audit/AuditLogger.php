<?php
namespace App\Core\Audit;

use App\Core\Database;
use App\Core\Auth;
use App\Core\TenantContext;

/**
 * Log de auditoria generico: registra "quem fez o que" sem nenhuma
 * regra de negocio embutida. Chame AuditLogger::log() a partir dos
 * seus Controllers/Services sempre que uma acao relevante ocorrer
 * (ex: criar usuario, alterar permissao, excluir registro).
 */
class AuditLogger {
    public static function log(string $action, string $entity, ?int $entityId = null, array $details = []): void {
        try {
            $pdo  = Database::getInstance();
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, details, ip, user_agent, created_at)
                VALUES (:tenant_id, :user_id, :action, :entity, :entity_id, :details, :ip, :user_agent, NOW())
            ");
            $stmt->execute([
                'tenant_id'  => TenantContext::id(),
                'user_id'    => Auth::user()?->id,
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'details'    => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Falha silenciosa no log de auditoria para nao interromper o fluxo principal
            error_log('[AuditLogger] ' . $e->getMessage());
        }
    }
}
