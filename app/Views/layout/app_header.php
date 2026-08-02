<?php
use App\Core\Auth;
$usuarioLogado = Auth::user();
$uriAtual      = strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? ($_ENV['APP_NAME'] ?? 'Base App')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <nav class="sidebar p-3" style="width: 240px;">
        <a href="/dashboard" class="d-block text-white text-decoration-none fw-bold fs-5 mb-4">
            <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Base App') ?>
        </a>
        <ul class="nav nav-pills flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link <?= $uriAtual === '/dashboard' ? 'active' : '' ?>" href="/dashboard">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= str_starts_with($uriAtual, '/usuarios') ? 'active' : '' ?>" href="/usuarios">Usuarios</a>
            </li>
        </ul>
    </nav>

    <div class="flex-grow-1">
        <header class="d-flex justify-content-between align-items-center bg-white border-bottom px-4 py-2">
            <h1 class="h5 mb-0"><?= htmlspecialchars($title ?? '') ?></h1>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small"><?= htmlspecialchars($usuarioLogado->name ?? '') ?></span>
                <a href="/logout" class="btn btn-sm btn-outline-secondary">Sair</a>
            </div>
        </header>
        <main class="p-4">
