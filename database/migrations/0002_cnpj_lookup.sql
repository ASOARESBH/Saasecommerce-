-- ============================================================
-- CNPJ Lookup — Tabelas de cache e rate-limit
-- Depende de: 0001_core_schema.sql (audit_logs)
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- CACHE DE CONSULTAS CNPJ
-- Evita repetir chamadas externas para o mesmo CNPJ.
-- TTL padrão: 24h (controlado em .env via CNPJ_CACHE_TTL_HOURS).
-- ============================================================
CREATE TABLE IF NOT EXISTS `cnpj_cache` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cnpj`         CHAR(14)     NOT NULL COMMENT 'Somente digitos, 14 chars',
    `provider`     VARCHAR(50)  NOT NULL COMMENT 'brasilapi | receitaws | cnpja',
    `payload`      JSON         NOT NULL COMMENT 'Resposta normalizada do provedor',
    `raw_response` JSON         NULL     COMMENT 'Payload bruto original (debug/auditoria)',
    `http_status`  SMALLINT     NOT NULL DEFAULT 200,
    `expires_at`   DATETIME     NOT NULL,
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cnpj` (`cnpj`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Cache de respostas de consulta de CNPJ com TTL configuravel';

-- ============================================================
-- RATE-LIMIT CENTRALIZADO POR IP
-- Janela deslizante de 1 minuto (configuravel via .env).
-- ============================================================
CREATE TABLE IF NOT EXISTS `cnpj_rate_limit` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip`           VARCHAR(45)  NOT NULL,
    `window_start` DATETIME     NOT NULL COMMENT 'Inicio da janela de 1 minuto',
    `hit_count`    SMALLINT     NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ip_window` (`ip`, `window_start`),
    INDEX `idx_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Rate-limit por IP para o endpoint /api/cnpj — janela deslizante de 1 minuto';

-- ============================================================
-- PERMISSAO RBAC (opcional — remova se o endpoint for publico)
-- ============================================================
INSERT IGNORE INTO `permissions` (`slug`, `name`, `group_name`)
VALUES ('consultar_cnpj', 'Consultar CNPJ', 'Integrações');
