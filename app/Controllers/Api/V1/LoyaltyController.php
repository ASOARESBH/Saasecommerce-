<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\Audit\AuditLogger;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\CustomerService;
use RuntimeException;

class LoyaltyController extends ApiController
{
    public function account(string $customerId): never
    {
        $this->authenticate(['loyalty:read'], true);
        ApiResponse::success((new CustomerService())->loyalty((int) $customerId));
    }

    public function adjust(string $customerId): never
    {
        $this->authenticate(['loyalty:manage'], true); $body = $this->body(); $points = (int) ($body['points'] ?? 0); $type = $points >= 0 ? 'credit' : 'debit'; $points = abs($points); if ($points === 0) throw new RuntimeException('Informe uma quantidade de pontos diferente de zero.', 422); $pdo = Database::getInstance(); $tenantId = TenantContext::requireId();
        $pdo->beginTransaction(); try {
            $account = $pdo->prepare('SELECT id FROM loyalty_accounts WHERE tenant_id = :tenant_id AND customer_id = :customer_id LIMIT 1'); $account->execute(['tenant_id' => $tenantId, 'customer_id' => $customerId]); $accountId = (int) $account->fetchColumn();
            if (!$accountId) { $pdo->prepare('INSERT INTO loyalty_accounts (tenant_id, customer_id, points_balance) VALUES (:tenant_id, :customer_id, 0)')->execute(['tenant_id' => $tenantId, 'customer_id' => $customerId]); $accountId = (int) $pdo->lastInsertId(); }
            $delta = $type === 'credit' ? $points : -$points; $pdo->prepare('UPDATE loyalty_accounts SET points_balance = points_balance + :delta WHERE id = :id AND tenant_id = :tenant_id AND points_balance + :check_delta >= 0')->execute(['delta' => $delta, 'check_delta' => $delta, 'id' => $accountId, 'tenant_id' => $tenantId]); if ((int) $pdo->query('SELECT ROW_COUNT()')->fetchColumn() !== 1) throw new RuntimeException('Saldo de pontos insuficiente ou conta inválida.', 422);
            $pdo->prepare('INSERT INTO loyalty_transactions (tenant_id, loyalty_account_id, type, points, reason) VALUES (:tenant_id, :account_id, :type, :points, :reason)')->execute(['tenant_id' => $tenantId, 'account_id' => $accountId, 'type' => $type, 'points' => $delta, 'reason' => $body['reason'] ?? null]); $pdo->commit(); AuditLogger::log('loyalty.adjusted', 'customer', (int) $customerId, ['points' => $delta]); ApiResponse::success((new CustomerService())->loyalty((int) $customerId));
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
