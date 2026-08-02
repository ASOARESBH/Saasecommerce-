<?php
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<h1 class="h4 mb-4 text-center"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Base App') ?></h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (($_GET['error'] ?? '') === 'sessao_expirada'): ?>
    <div class="alert alert-warning py-2">Sua sessao expirou. Faca login novamente.</div>
<?php endif; ?>

<form method="POST" action="/login">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus>
    </div>

    <div class="mb-3">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary w-100">Entrar</button>
</form>
