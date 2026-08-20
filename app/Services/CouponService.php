<?php
namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use PDO;
use RuntimeException;

class CouponService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Database::getInstance(); }

    public function validate(string $code, float $subtotal, ?int $customerId = null, float $deliveryFee = 0): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') throw new RuntimeException('Informe um cupom.', 422);
        $stmt = $this->pdo->prepare('SELECT * FROM coupons WHERE tenant_id = :tenant_id AND code = :code AND active = 1 LIMIT 1');
        $stmt->execute(['tenant_id' => TenantContext::requireId(), 'code' => $code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) throw new RuntimeException('Cupom inválido ou indisponível.', 422);
        $now = time();
        if ($coupon['valid_from'] && strtotime($coupon['valid_from']) > $now) throw new RuntimeException('Cupom ainda não está vigente.', 422);
        if ($coupon['valid_until'] && strtotime($coupon['valid_until']) < $now) throw new RuntimeException('Cupom expirado.', 422);
        if ($coupon['usage_limit'] !== null && (int) $coupon['usage_count'] >= (int) $coupon['usage_limit']) throw new RuntimeException('Limite de uso do cupom atingido.', 422);
        if ((float) $coupon['minimum_order'] > $subtotal) throw new RuntimeException('O pedido mínimo para este cupom é de R$ ' . number_format((float) $coupon['minimum_order'], 2, ',', '.'), 422);
        if ($coupon['customer_id'] !== null && (int) $coupon['customer_id'] !== $customerId) throw new RuntimeException('Cupom restrito a outro cliente.', 422);
        $this->validateSchedule($coupon);
        $discount = match ($coupon['discount_type']) {
            'percentage' => min($subtotal, $subtotal * ((float) $coupon['value'] / 100)),
            'free_delivery' => min($deliveryFee, (float) $coupon['value'] > 0 ? (float) $coupon['value'] : $deliveryFee),
            default => min($subtotal, (float) $coupon['value']),
        };
        return ['valid' => true, 'coupon_id' => (int) $coupon['id'], 'code' => $coupon['code'], 'discount_type' => $coupon['discount_type'], 'discount' => round($discount, 2), 'coupon' => $coupon];
    }

    public function incrementUsage(int $couponId): void
    {
        $stmt = $this->pdo->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $couponId, 'tenant_id' => TenantContext::requireId()]);
    }

    private function validateSchedule(array $coupon): void
    {
        $days = json_decode((string) ($coupon['days_json'] ?? 'null'), true);
        $day = (int) date('N');
        if (is_array($days) && $days !== [] && !in_array($day, array_map('intval', $days), true)) throw new RuntimeException('Cupom indisponível neste dia.', 422);
        $time = date('H:i:s');
        if ($coupon['starts_at'] && $coupon['ends_at'] && ($time < $coupon['starts_at'] || $time > $coupon['ends_at'])) throw new RuntimeException('Cupom indisponível neste horário.', 422);
    }
}
