-- ============================================================
-- BASEAPP — Schema base do framework
-- MariaDB 10.6+ / MySQL 8.0+
-- Somente estrutura: autenticacao, RBAC, multi-tenant (opcional)
-- e auditoria. Nenhuma tabela de negocio.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- RBAC
-- ============================================================

CREATE TABLE IF NOT EXISTS `roles` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`       VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Ex: superadmin, admin, user',
    `name`       VARCHAR(100) NOT NULL,
    `is_system`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Impede exclusao pela aplicacao',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`       VARCHAR(100) NOT NULL UNIQUE COMMENT 'Ex: manage_users, view_dashboard',
    `name`       VARCHAR(150) NOT NULL,
    `group_name` VARCHAR(50)  NULL COMMENT 'Agrupamento livre para telas de administracao',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USUARIOS
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(255) NOT NULL,
    `email`          VARCHAR(255) NOT NULL UNIQUE,
    `password`       VARCHAR(255) NOT NULL,
    `role_id`        INT UNSIGNED NOT NULL,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `last_login_at`  DATETIME NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MULTI-TENANT (opcional — apague estas 2 tabelas + o
-- TenantMiddleware/TenantContext/Tenant model se nao precisar)
-- ============================================================

CREATE TABLE IF NOT EXISTS `tenants` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`           VARCHAR(255) NOT NULL,
    `slug`           VARCHAR(100) NOT NULL UNIQUE,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `settings_json`  JSON NULL COMMENT 'Configuracoes livres por tenant (ex: marca, limites)',
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_tenants` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `role_id`    INT UNSIGNED NOT NULL COMMENT 'Role do usuario dentro deste tenant',
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_tenant` (`user_id`, `tenant_id`),
    INDEX `idx_user_id`   (`user_id`),
    INDEX `idx_tenant_id` (`tenant_id`),
    CONSTRAINT `fk_ut_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_ut_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ut_role`   FOREIGN KEY (`role_id`)   REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- AUDITORIA
-- ============================================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`  INT UNSIGNED NULL,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(100) NOT NULL,
    `entity`     VARCHAR(100) NULL,
    `entity_id`  INT UNSIGNED NULL,
    `details`    JSON NULL,
    `ip`         VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_user`   (`user_id`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
