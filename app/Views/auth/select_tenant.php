<?php $csrf = $_SESSION['csrf_token'] ?? ''; ?>
<h1 class="h4 mb-4 text-center">Selecione a organizacao</h1>

<form method="POST" action="/selecionar-empresa">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <div class="list-group mb-3">
        <?php foreach ($tenants as $t): ?>
            <label class="list-group-item d-flex align-items-center gap-2">
                <input type="radio" name="tenant_id" value="<?= (int) $t->tenant_id ?>" class="form-check-input mt-0" required>
                <?= htmlspecialchars($t->name) ?>
            </label>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100">Continuar</button>
</form>
