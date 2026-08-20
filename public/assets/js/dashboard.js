(() => {
    const labels = { new: 'Novo', received: 'Recebido', confirmed: 'Confirmado', preparing: 'Em preparo', ready: 'Pronto', out_for_delivery: 'Saiu para entrega', delivered: 'Entregue', cancelled: 'Cancelado' };
    const statusClass = (status) => status === 'delivered' ? 'success' : (status === 'cancelled' ? 'danger' : (['preparing','ready','out_for_delivery'].includes(status) ? 'warning' : ''));
    const text = (value) => window.SaaS.escape(value);
    async function load() {
        const error = document.querySelector('#dashboardError');
        try {
            error.hidden = true;
            const [dashboard, orders] = await Promise.all([window.SaaS.api('/api/v1/dashboard'), window.SaaS.api('/api/v1/orders?per_page=8')]);
            const data = dashboard.data || {};
            document.querySelector('#metricOrders').textContent = data.orders ?? 0;
            document.querySelector('#metricRevenue').textContent = window.SaaS.money(data.revenue);
            document.querySelector('#metricTicket').textContent = window.SaaS.money(data.average_ticket);
            document.querySelector('#metricCancellations').textContent = data.cancellations ?? 0;
            document.querySelector('#metricPreparation').textContent = data.preparation_minutes ?? 0;
            document.querySelector('#metricDelivery').textContent = data.delivery_minutes ?? 0;
            const statusGrid = document.querySelector('#statusGrid');
            statusGrid.innerHTML = Object.entries(labels).map(([key, label]) => `<div class="status-pill"><strong>${data.status?.[key] ?? 0}</strong><span>${label}</span></div>`).join('');
            const sourceRows = data.sources || [];
            document.querySelector('#sourcesTable').innerHTML = sourceRows.length ? sourceRows.map(row => `<tr><td>${text(row.source || 'Não informado')}</td><td>${row.orders}</td><td>${window.SaaS.money(row.revenue)}</td></tr>`).join('') : '<tr><td colspan="3" class="empty">Nenhum pedido hoje.</td></tr>';
            const orderRows = orders.data || [];
            document.querySelector('#ordersTable').innerHTML = orderRows.length ? orderRows.map(order => `<tr><td><strong>${text(order.order_number)}</strong></td><td>${text(order.source)}</td><td><span class="badge ${statusClass(order.status)}">${text(labels[order.status] || order.status)}</span></td><td>${window.SaaS.money(order.total)}</td><td>${new Date(order.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</td></tr>`).join('') : '<tr><td colspan="5" class="empty">Nenhum pedido recente.</td></tr>';
        } catch (e) {
            error.textContent = e.message;
            error.hidden = false;
        }
    }
    document.querySelector('#refreshDashboard')?.addEventListener('click', load);
    load();
})();
