document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const estadosPendientes = ['Recibido', 'En edición', 'Pendiente información'];

    const elPendientes = document.getElementById('pendDesarrolloPendientes');
    const elUrgentes = document.getElementById('pendDesarrolloUrgentes');

    if (!elPendientes && !elUrgentes) return;

    Promise.all([
        fetch(`${apiBaseUrl}solicitudes`).then(r => r.ok ? r.json() : []).catch(() => []),
        fetch(`${apiBaseUrl}solicitudes-estructural`).then(r => r.ok ? r.json() : []).catch(() => [])
    ]).then(([grafico, estructural]) => {
        const pendientesGrafico = grafico.filter(x => estadosPendientes.includes(x.estado)).length;
        const pendientesEstructural = estructural.filter(x => estadosPendientes.includes(x.estado)).length;
        const urgentes = grafico.filter(x => x.prioridad === 'URGENTE').length;

        if (elPendientes) elPendientes.textContent = pendientesGrafico + pendientesEstructural;
        if (elUrgentes) elUrgentes.textContent = urgentes;
    }).catch(() => {
        if (elPendientes) elPendientes.textContent = '-';
        if (elUrgentes) elUrgentes.textContent = '-';
    });
});
