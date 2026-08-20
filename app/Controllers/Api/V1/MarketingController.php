<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiResponse;
use App\Core\Audit\AuditLogger;
use App\Core\Database;
use App\Core\TenantContext;
use RuntimeException;

class MarketingController extends ApiController
{
    public function campaigns(): never
    {
        $this->authenticate(['marketing:read'], true); $stmt = Database::getInstance()->prepare('SELECT * FROM marketing_campaigns WHERE tenant_id = :tenant_id ORDER BY created_at DESC'); $stmt->execute(['tenant_id' => TenantContext::requireId()]); ApiResponse::success($stmt->fetchAll());
    }

    public function createCampaign(): never
    {
        $this->authenticate(['marketing:manage'], true); $body = $this->body(); $name = trim((string) ($body['name'] ?? '')); if ($name === '') throw new RuntimeException('Nome da campanha é obrigatório.', 422); $pdo = Database::getInstance(); $stmt = $pdo->prepare('INSERT INTO marketing_campaigns (tenant_id, name, channel, source, medium, campaign, content, term, status, starts_at, ends_at) VALUES (:tenant_id, :name, :channel, :source, :medium, :campaign, :content, :term, :status, :starts_at, :ends_at)'); $stmt->execute(['tenant_id' => TenantContext::requireId(), 'name' => $name, 'channel' => $body['channel'] ?? null, 'source' => $body['source'] ?? null, 'medium' => $body['medium'] ?? null, 'campaign' => $body['campaign'] ?? null, 'content' => $body['content'] ?? null, 'term' => $body['term'] ?? null, 'status' => $body['status'] ?? 'draft', 'starts_at' => $body['starts_at'] ?? null, 'ends_at' => $body['ends_at'] ?? null]); $id = (int) $pdo->lastInsertId(); AuditLogger::log('marketing_campaign.created', 'campaign', $id, ['name' => $name]); ApiResponse::success(['id' => $id], 201);
    }
}
