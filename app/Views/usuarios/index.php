<?php $csrf = $_SESSION['csrf_token'] ?? ''; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <a href="/usuarios/create" class="btn btn-primary btn-sm">+ Novo usuario</a>
</div>

<?php if (($_GET['sucesso'] ?? '') === 'criado'): ?>
    <div class="alert alert-success py-2">Usuario criado com sucesso.</div>
<?php elseif (($_GET['sucesso'] ?? '') === 'atualizado'): ?>
    <div class="alert alert-success py-2">Usuario atualizado com sucesso.</div>
<?php elseif (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger py-2">Nao foi possivel concluir a acao.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <table class="table table-hover mb-0 align-middle">
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Role</th>
                <th>Status</th>
                <th class="text-end">Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <?php
                $status = $u->status ?? ($u->tenant_active ? 'active' : 'inactive');
            ?>
            <tr>
                <td><?= htmlspecialchars($u->name) ?></td>
                <td><?= htmlspecialchars($u->email ?? '') ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($u->role_name) ?></span></td>
                <td>
                    <span class="badge bg-<?= $status === 'active' ? 'success' : 'secondary' ?>">
                        <?= $status === 'active' ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td class="text-end">
                    <a href="/usuarios/<?= (int) $u->id ?>/edit" class="btn btn-sm btn-outline-secondary">Editar</a>
                    <form method="POST" action="/usuarios/<?= (int) $u->id ?>/toggle" class="d-inline">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <?= $status === 'active' ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($usuarios)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Nenhum usuario cadastrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
