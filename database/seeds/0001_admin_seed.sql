-- ============================================================
-- BASEAPP — Dados iniciais (roles, permissions e superadmin)
-- Nenhuma permissao de negocio: apenas o minimo para o painel
-- de usuarios de exemplo funcionar. Adicione as permissoes do
-- seu projeto aqui (ou via uma tela de administracao).
-- ============================================================

-- Roles
INSERT IGNORE INTO `roles` (`slug`, `name`, `is_system`) VALUES
('superadmin', 'Super Administrador', 1),
('admin',      'Administrador',       0),
('user',       'Usuario',             0);

-- Permissions
INSERT IGNORE INTO `permissions` (`slug`, `name`, `group_name`) VALUES
('view_dashboard', 'Ver dashboard',        'geral'),
('manage_users',   'Gerenciar usuarios',   'administracao'),
('manage_roles',   'Gerenciar roles',      'administracao');

-- Mapeamento role -> permissions (superadmin nao precisa: tem acesso total via wildcard no Auth/Permission)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.slug = 'admin' AND p.slug IN ('view_dashboard', 'manage_users', 'manage_roles');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.slug = 'user' AND p.slug IN ('view_dashboard');

-- Usuario super administrador inicial
-- E-mail: admin@example.com | Senha: Admin@123
-- IMPORTANTE: troque essa senha assim que fizer o primeiro login.
INSERT INTO `users` (`name`, `email`, `password`, `role_id`, `status`, `created_at`, `updated_at`)
SELECT 'Administrador', 'admin@example.com',
       '$2b$10$vWAdPPDyhPW0u/UeSpdf1eLsXidEiwemycK/I9x7I1DcVjCLSTJii',
       r.id, 'active', NOW(), NOW()
FROM `roles` r WHERE r.slug = 'superadmin'
ON DUPLICATE KEY UPDATE
    name       = VALUES(name),
    password   = VALUES(password),
    role_id    = VALUES(role_id),
    status     = VALUES(status),
    updated_at = NOW();
