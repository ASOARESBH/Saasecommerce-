<div id="dashboardError" class="alert error" hidden></div>
<section class="grid grid-4" aria-label="Indicadores do dia">
    <article class="card metric"><span class="metric-label">Pedidos hoje</span><strong class="metric-value" id="metricOrders">—</strong><span class="metric-note">Todos os canais conectados</span></article>
    <article class="card metric"><span class="metric-label">Faturamento</span><strong class="metric-value" id="metricRevenue">—</strong><span class="metric-note">Pedidos registrados hoje</span></article>
    <article class="card metric"><span class="metric-label">Ticket médio</span><strong class="metric-value" id="metricTicket">—</strong><span class="metric-note">Valor médio por pedido</span></article>
    <article class="card metric"><span class="metric-label">Cancelamentos</span><strong class="metric-value" id="metricCancellations">—</strong><span class="metric-note">Acompanhe a saúde da operação</span></article>
</section>
<section class="card" style="margin-top:18px"><div class="section-head"><div><h2>Operação em tempo real</h2><p>Fluxo do dia por etapa operacional</p></div><button class="btn btn-ghost" id="refreshDashboard">Atualizar</button></div><div class="status-grid" id="statusGrid"><div class="status-pill"><strong>—</strong><span>Carregando</span></div></div></section>
<section class="grid grid-2" style="margin-top:18px">
    <article class="card" id="orders"><div class="section-head"><div><h2>Pedidos recentes</h2><p>Use os status para coordenar atendimento, cozinha e expedição.</p></div><a class="btn btn-primary" href="#orders">Ver pedidos</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Pedido</th><th>Origem</th><th>Status</th><th>Total</th><th>Horário</th></tr></thead><tbody id="ordersTable"><tr><td colspan="5" class="empty">Carregando pedidos…</td></tr></tbody></table></div></article>
    <article class="card"><div class="section-head"><div><h2>Canais de venda</h2><p>Distribuição por origem e campanha</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Origem</th><th>Pedidos</th><th>Receita</th></tr></thead><tbody id="sourcesTable"><tr><td colspan="3" class="empty">Carregando fontes…</td></tr></tbody></table></div></article>
</section>
<section class="grid grid-3" style="margin-top:18px" id="catalog">
    <article class="card"><div class="section-head"><div><h2>Catálogo</h2><p>Produtos, tamanhos, adicionais e combos.</p></div></div><a class="btn btn-ghost" href="#catalog">Gerenciar catálogo</a></article>
    <article class="card" id="customers"><div class="section-head"><div><h2>Clientes e CRM</h2><p>Recorrência, ticket e fidelidade em um único tenant.</p></div></div><a class="btn btn-ghost" href="#customers">Abrir clientes</a></article>
    <article class="card" id="integrations"><div class="section-head"><div><h2>Integrações</h2><p>Conecte sites, marketplaces, gateways e canais.</p></div></div><a class="btn btn-ghost" href="#integrations">Configurar canais</a></article>
</section>
<section class="card" style="margin-top:18px" id="delivery"><div class="section-head"><div><h2>Tempo operacional</h2><p>Indicadores para cozinha e delivery</p></div></div><div class="grid grid-2"><div><span class="metric-label">Tempo médio de preparo</span><div class="metric-value"><span id="metricPreparation">—</span> min</div></div><div><span class="metric-label">Tempo médio de entrega</span><div class="metric-value"><span id="metricDelivery">—</span> min</div></div></div></section>
<script src="/assets/js/dashboard.js" defer></script>
