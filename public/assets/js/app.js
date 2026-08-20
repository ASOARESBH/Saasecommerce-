(() => {
    const toggle = document.querySelector('#menuToggle');
    const sidebar = document.querySelector('#sidebar');
    toggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));

    window.SaaS = {
        async api(path, options = {}) {
            const response = await fetch(path, {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...(options.headers || {}) },
                ...options,
            });
            const payload = await response.json().catch(() => ({ success: false, error: { message: 'Resposta inválida.' } }));
            if (!response.ok || payload.success === false) throw new Error(payload.error?.message || 'Não foi possível concluir a operação.');
            return payload;
        },
        money(value) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0)); },
        escape(value) { return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char])); },
    };
})();
