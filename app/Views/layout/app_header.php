<?php
use App\Core\Auth;
use App\Core\TenantContext;
$usuarioLogado = Auth::user();
$uriAtual = strtok($_SERVER['REQUEST_URI'], '?');
$tenantAtual = TenantContext::get();
$menu = [
    ['/dashboard', 'Visão geral', 'view_dashboard'],
    ['/dashboard#orders', 'Pedidos', 'manage_orders'],
    ['/dashboard#catalog', 'Catálogo', 'manage_catalog'],
    ['/dashboard#customers', 'Clientes', 'manage_customers'],
    ['/dashboard#delivery', 'Entrega', 'manage_delivery'],
    ['/dashboard#reports', 'Relatórios', 'view_reports'],
    ['/dashboard#integrations', 'Integrações', 'manage_integrations'],
    ['/usuarios', 'Usuários', 'manage_users'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? ($_ENV['APP_NAME'] ?? 'SaaS E-commerce')) ?></title>
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><span class="brand-mark">S</span><span><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'SaaS E-commerce') ?></span></div>
        <?php if ($tenantAtual): ?><div class="tenant-chip"><span>Empresa ativa</span><strong><?= htmlspecialchars($tenantAtual->name) ?></strong></div><?php endif; ?>
        <nav class="main-nav" aria-label="Navegação principal">
            <?php foreach ($menu as [$href, $label, $permission]): if (Auth::can($permission)): ?>
                <a class="nav-link <?= $uriAtual === strtok($href, '#') ? 'active' : '' ?>" href="<?= htmlspecialchars($href) ?>"><span><?= htmlspecialchars($label) ?></span></a>
            <?php endif; endforeach; ?>
        </nav>
        <div class="sidebar-footer"><a class="nav-link" href="/logout">Sair da conta</a></div>
    </aside>
    <div class="app-content">
        <header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Abrir menu">☰</button><div><span class="eyebrow">Painel operacional</span><h1><?= htmlspecialchars($title ?? '') ?></h1></div><div class="user-block"><span><?= htmlspecialchars($usuarioLogado->name ?? '') ?></span><small><?= htmlspecialchars($tenantAtual->name ?? 'Selecione uma empresa') ?></small></div></header>
        <main class="page-content">
