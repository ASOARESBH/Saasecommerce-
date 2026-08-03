<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Audit\AuditLogger;
use App\Services\CnpjService;

/**
 * Endpoint centralizado para consulta de CNPJ.
 *
 * GET /api/cnpj/{cnpj}
 *
 * Responsabilidades deste controller (thin controller):
 *  1. Validar o CNPJ recebido na rota.
 *  2. Aplicar rate-limit por IP (janela deslizante de 1 minuto).
 *  3. Verificar e servir o cache em banco (TTL configuravel via .env).
 *  4. Delegar a consulta ao CnpjService (que gerencia fallback entre provedores).
 *  5. Persistir o resultado no cache.
 *  6. Registrar auditoria (quem consultou, quando, qual provedor respondeu).
 *  7. Retornar JSON padronizado.
 */
class CnpjController extends Controller {

    // Limites configurados via .env com valores-padrao seguros
    private int   $rateLimitMax;   // Maximo de requisicoes por janela
    private int   $rateLimitSecs;  // Tamanho da janela em segundos
    private int   $cacheTtlHours;  // Tempo de vida do cache em horas

    public function __construct() {
        $this->rateLimitMax  = (int) ($_ENV['CNPJ_RATE_LIMIT_MAX']   ?? 10);
        $this->rateLimitSecs = (int) ($_ENV['CNPJ_RATE_LIMIT_SECS']  ?? 60);
        $this->cacheTtlHours = (int) ($_ENV['CNPJ_CACHE_TTL_HOURS']  ?? 24);
    }

    /**
     * GET /api/cnpj/{cnpj}
     */
    public function consultar(string $cnpj): void {
        // 1. Sanitizacao e validacao basica
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        if (strlen($cnpj) !== 14 || !$this->validarDigitosCnpj($cnpj)) {
            $this->json([
                'success' => false,
                'code'    => 'CNPJ_INVALIDO',
                'message' => 'CNPJ invalido. Verifique os digitos e tente novamente.'
            ], 422);
        }

        $ip = $this->resolverIp();

        // 2. Rate-limit
        if (!$this->verificarRateLimit($ip)) {
            Logger::warning("Rate-limit atingido para IP {$ip} ao consultar CNPJ {$cnpj}");
            $this->json([
                'success'     => false,
                'code'        => 'RATE_LIMIT',
                'message'     => "Limite de {$this->rateLimitMax} consultas por {$this->rateLimitSecs}s atingido. Aguarde e tente novamente.",
                'retry_after' => $this->rateLimitSecs
            ], 429);
        }

        // 3. Cache
        $cached = $this->buscarCache($cnpj);
        if ($cached) {
            Logger::info("CNPJ {$cnpj} servido do cache (provedor original: {$cached['provider']})");
            $this->json([
                'success'  => true,
                'source'   => 'cache',
                'provider' => $cached['provider'],
                'data'     => $cached['payload']
            ]);
        }

        // 4. Consulta via Service (com fallback automatico entre provedores)
        try {
            $service  = new CnpjService();
            $resultado = $service->consultar($cnpj);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'code' => 'CNPJ_INVALIDO', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'code' => 'PROVEDOR_INDISPONIVEL', 'message' => $e->getMessage()], 503);
        }

        // 5. Persistir no cache
        $this->salvarCache($cnpj, $resultado['provider'], $resultado['payload'], $resultado['raw_response']);

        // 6. Auditoria
        AuditLogger::log(
            action:   'cnpj_consulta',
            entity:   'cnpj',
            entityId: null,
            details:  [
                'cnpj'     => $cnpj,
                'provider' => $resultado['provider'],
                'ip'       => $ip
            ]
        );

        // 7. Resposta
        $this->json([
            'success'  => true,
            'source'   => 'api',
            'provider' => $resultado['provider'],
            'data'     => $resultado['payload']
        ]);
    }

    // =========================================================================
    // RATE-LIMIT — janela deslizante por IP
    // =========================================================================

    private function verificarRateLimit(string $ip): bool {
        $pdo         = Database::getInstance();
        $windowStart = date('Y-m-d H:i:s', floor(time() / $this->rateLimitSecs) * $this->rateLimitSecs);

        // Upsert: insere ou incrementa o contador da janela atual
        $stmt = $pdo->prepare("
            INSERT INTO cnpj_rate_limit (ip, window_start, hit_count)
            VALUES (:ip, :ws, 1)
            ON DUPLICATE KEY UPDATE hit_count = hit_count + 1
        ");
        $stmt->execute([':ip' => $ip, ':ws' => $windowStart]);

        // Leitura do contador atual
        $stmt = $pdo->prepare("
            SELECT hit_count FROM cnpj_rate_limit
            WHERE ip = :ip AND window_start = :ws
        ");
        $stmt->execute([':ip' => $ip, ':ws' => $windowStart]);
        $row = $stmt->fetch();

        return !$row || (int) $row->hit_count <= $this->rateLimitMax;
    }

    // =========================================================================
    // CACHE
    // =========================================================================

    private function buscarCache(string $cnpj): ?array {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT provider, payload
            FROM cnpj_cache
            WHERE cnpj = :cnpj AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':cnpj' => $cnpj]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'provider' => $row->provider,
            'payload'  => json_decode($row->payload, true)
        ];
    }

    private function salvarCache(string $cnpj, string $provider, array $payload, array $raw): void {
        $pdo       = Database::getInstance();
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->cacheTtlHours} hours"));

        $stmt = $pdo->prepare("
            INSERT INTO cnpj_cache (cnpj, provider, payload, raw_response, http_status, expires_at)
            VALUES (:cnpj, :provider, :payload, :raw, 200, :expires)
            ON DUPLICATE KEY UPDATE
                provider     = VALUES(provider),
                payload      = VALUES(payload),
                raw_response = VALUES(raw_response),
                http_status  = 200,
                expires_at   = VALUES(expires_at),
                updated_at   = NOW()
        ");
        $stmt->execute([
            ':cnpj'     => $cnpj,
            ':provider' => $provider,
            ':payload'  => json_encode($payload,  JSON_UNESCAPED_UNICODE),
            ':raw'      => json_encode($raw,       JSON_UNESCAPED_UNICODE),
            ':expires'  => $expiresAt
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Resolve o IP real do cliente, respeitando proxies reversos comuns
     * (Cloudflare, nginx, cPanel/LiteSpeed).
     */
    private function resolverIp(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                // X-Forwarded-For pode conter lista separada por virgula
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Validacao matematica dos digitos verificadores do CNPJ.
     * Rejeita CNPJs com todos os digitos iguais (ex: 00000000000000).
     */
    private function validarDigitosCnpj(string $cnpj): bool {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $calcDigito = function (string $cnpj, int $tamanho): int {
            $soma = 0;
            $pos  = $tamanho - 7;
            for ($i = $tamanho; $i >= 1; $i--) {
                $soma += (int) $cnpj[$tamanho - $i] * $pos--;
                if ($pos < 2) {
                    $pos = 9;
                }
            }
            $resto = $soma % 11;
            return $resto < 2 ? 0 : 11 - $resto;
        };

        $d1 = $calcDigito($cnpj, 12);
        $d2 = $calcDigito($cnpj, 13);

        return (int) $cnpj[12] === $d1 && (int) $cnpj[13] === $d2;
    }
}
