<?php
namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;
use RuntimeException;

class DeliveryService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::getInstance(); }

    public function areas(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM delivery_areas WHERE tenant_id = :tenant_id AND active = 1 ORDER BY name');
        $stmt->execute(['tenant_id' => TenantContext::requireId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function check(array $address, float $subtotal): array
    {
        $tenantId = TenantContext::requireId();
        $postalCode = preg_replace('/\D+/', '', (string) ($address['postal_code'] ?? $address['cep'] ?? ''));
        $city = trim((string) ($address['city'] ?? $address['cidade'] ?? ''));
        $neighborhood = trim((string) ($address['neighborhood'] ?? $address['bairro'] ?? ''));
        $params = ['tenant_id' => $tenantId, 'postal_code_value' => $postalCode, 'postal_code_nonempty' => $postalCode, 'city_value' => $city, 'neighborhood_value' => $neighborhood, 'city_name' => $city];
        $stmt = $this->pdo->prepare("SELECT * FROM delivery_areas WHERE tenant_id = :tenant_id AND active = 1 AND (
            (postal_code IS NOT NULL AND REPLACE(REPLACE(postal_code, '-', ''), ' ', '') = :postal_code_value AND :postal_code_nonempty <> '')
            OR (city IS NOT NULL AND LOWER(city) = LOWER(:city_value) AND (name = :neighborhood_value OR name = :city_name))
        ) ORDER BY CASE WHEN postal_code IS NOT NULL AND postal_code <> '' THEN 0 ELSE 1 END, delivery_fee LIMIT 1");
        $stmt->execute($params);
        $area = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$area) throw new RuntimeException('O endereço está fora das áreas de entrega disponíveis.', 422);
        if ($subtotal < (float) $area['minimum_order']) throw new RuntimeException('O pedido mínimo para esta área é de R$ ' . number_format((float) $area['minimum_order'], 2, ',', '.'), 422);
        return [
            'available' => true,
            'area' => $area,
            'delivery_fee' => (float) $area['delivery_fee'],
            'minimum_order' => (float) $area['minimum_order'],
            'estimated_min' => (int) $area['estimated_min'],
            'estimated_max' => (int) $area['estimated_max'],
        ];
    }
}
