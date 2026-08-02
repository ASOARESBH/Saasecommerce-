<?php
$csrf     = $_SESSION['csrf_token'] ?? '';
$editando = $usuario !== null;
$action   = $editando ? "/usuarios/{$usuario->id}/update" : '/usuarios';
?>

<div class="card shadow-sm" style="max-width: 480px;">
    <div class="card-body">
        <form method="POST" action="<?= $action ?>">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($usuario->name ?? '') ?>" required>
            </div>

            <?php if (!$editando): ?>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role_id" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r->id ?>"
                            <?= (($usuario->role_id ?? null) == $r->id) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($editando): ?>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active"   <?= ($usuario->status ?? '') === 'active'   ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= ($usuario->status ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="/usuarios" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
