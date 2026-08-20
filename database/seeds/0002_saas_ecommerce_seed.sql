-- Seed genérica do SaaS E-commerce.
-- Não contém regras específicas de uma marca ou canal.

INSERT IGNORE INTO roles (slug, name, is_system) VALUES
('tenant_admin', 'Administrador da loja', 1),
('manager', 'Gerente', 1),
('attendant', 'Atendente', 1),
('kitchen', 'Cozinha', 1),
('dispatch', 'Expedição', 1),
('driver', 'Entregador', 1),
('finance', 'Financeiro', 1),
('inventory', 'Estoque', 1),
('viewer', 'Consulta', 1);

INSERT IGNORE INTO permissions (slug, name, group_name) VALUES
('view_dashboard', 'Visualizar dashboard', 'dashboard'),
('manage_catalog', 'Gerenciar catálogo', 'catalog'),
('manage_orders', 'Gerenciar pedidos', 'orders'),
('manage_customers', 'Gerenciar clientes', 'customers'),
('manage_delivery', 'Gerenciar entrega', 'delivery'),
('manage_drivers', 'Gerenciar entregadores', 'delivery'),
('manage_payments', 'Gerenciar pagamentos', 'finance'),
('manage_coupons', 'Gerenciar cupons', 'marketing'),
('manage_loyalty', 'Gerenciar fidelidade', 'marketing'),
('manage_marketing', 'Gerenciar marketing', 'marketing'),
('manage_inventory', 'Gerenciar estoque', 'inventory'),
('manage_finance', 'Gerenciar financeiro', 'finance'),
('view_reports', 'Visualizar relatórios', 'reports'),
('manage_integrations', 'Gerenciar integrações', 'integrations'),
('manage_users', 'Gerenciar usuários', 'admin'),
('manage_tenant', 'Gerenciar empresa', 'admin');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'tenant_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN (
    'view_dashboard','manage_catalog','manage_orders','manage_customers','manage_delivery',
    'manage_drivers','manage_payments','manage_coupons','manage_loyalty','manage_marketing',
    'manage_inventory','manage_finance','view_reports','manage_integrations'
) WHERE r.slug = 'manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','manage_orders','manage_customers','manage_coupons') WHERE r.slug = 'attendant';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','manage_orders') WHERE r.slug = 'kitchen';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','manage_orders','manage_delivery','manage_drivers') WHERE r.slug = 'dispatch';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','manage_orders','manage_delivery') WHERE r.slug = 'driver';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','manage_payments','manage_finance','view_reports') WHERE r.slug = 'finance';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','manage_inventory','view_reports') WHERE r.slug = 'inventory';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','view_reports') WHERE r.slug = 'viewer';

INSERT IGNORE INTO tenants (name, slug, status, settings_json)
VALUES ('Minha Loja', 'minha-loja', 'active', JSON_OBJECT(
    'brand', JSON_OBJECT('name', 'Minha Loja', 'primary_color', '#2563eb'),
    'currency', 'BRL',
    'timezone', 'America/Sao_Paulo',
    'order_statuses', JSON_ARRAY('new','received','confirmed','preparing','ready','out_for_delivery','delivered','cancelled')
));

INSERT IGNORE INTO user_tenants (user_id, tenant_id, role_id)
SELECT u.id, t.id, r.id
FROM users u CROSS JOIN tenants t CROSS JOIN roles r
WHERE u.email = 'admin@example.com' AND t.slug = 'minha-loja' AND r.slug = 'tenant_admin';

-- O primeiro cliente de API é criado pelo fluxo seguro de administração.
-- Segredos não são distribuídos em SQL.
