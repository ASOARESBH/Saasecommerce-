<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Usuarios cadastrados</div>
                <div class="fs-2 fw-bold"><?= (int) $totalUsuarios ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-body">
        <h2 class="h6">Bem-vindo, <?= htmlspecialchars($usuario->name ?? '') ?></h2>
        <p class="text-muted mb-0">
            Esta e a tela inicial de exemplo do BASEAPP. Substitua o conteudo de
            <code>app/Views/dashboard/index.php</code> pelas telas reais do seu projeto.
        </p>
    </div>
</div>
