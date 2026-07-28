document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const estadosPendientes = ['Recibido', 'En edición', 'Pendiente información'];
    const nombreUsuarioActual = (window.currentUserNombre || '').trim().toLowerCase();

    const elPendientes = document.getElementById('pendDesarrolloPendientes');
    const elUrgentes = document.getElementById('pendDesarrolloUrgentes');
    const elMisPendientes = document.getElementById('misPendientesDesarrollo');
    const cardMisPendientes = document.getElementById('cardMisPendientesDesarrollo');
    const seccionAlertas = document.getElementById('seccionAlertas');
    const listaAlertas = document.getElementById('listaAlertas');

    const agregarAlerta = (texto, url) => {
        if (!listaAlertas || !seccionAlertas) return;

        const li = document.createElement('li');
        li.style.cssText = 'display:flex; align-items:center; gap:10px; padding:12px 16px; border-left:3px solid #dc2626; background:rgba(220,38,38,.08); border-radius:8px;';
        li.innerHTML = `<i class="bi bi-exclamation-circle-fill" style="color:#dc2626;"></i>
            <a href="${url}" style="color:inherit; text-decoration:none; font-weight:500;">${texto}</a>`;

        listaAlertas.appendChild(li);
        seccionAlertas.style.display = '';
    };

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

        if (elMisPendientes && cardMisPendientes && nombreUsuarioActual) {
            const esMio = x => (x.operadorEdicion || '').trim().toLowerCase() === nombreUsuarioActual
                && estadosPendientes.includes(x.estado);

            const misSolicitudes = grafico.filter(esMio).length + estructural.filter(esMio).length;

            if (misSolicitudes > 0) {
                elMisPendientes.textContent = misSolicitudes;
                cardMisPendientes.style.display = '';
                agregarAlerta(`Solicitudes Desarrollo asignadas a ti: ${misSolicitudes}`, '/modules/formularios/desarrollo/');
            }
        }

        if (urgentes > 0) {
            agregarAlerta(`Solicitudes urgentes sin atender: ${urgentes}`, '/modules/formularios/desarrollo/admin/?prioridad=URGENTE');
        }
    }).catch(() => {
        if (elPendientes) elPendientes.textContent = '-';
        if (elUrgentes) elUrgentes.textContent = '-';
    });
});
