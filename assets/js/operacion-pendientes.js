document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const { contarPorCampo, renderDonut, renderRanking, renderLinea, setText } = window.DashboardCharts;
    const estadosPendientes = ['Recibido', 'En edición', 'Pendiente información'];
    const nombreUsuarioActual = (window.currentUserNombre || '').trim().toLowerCase();

    const COLOR_ESTADO = {
        'Terminado': '#22c55e',
        'Recibido': '#2563eb',
        'Anulado': '#94a3b8',
        'En edición': '#f59e0b',
        'Rechazado': '#ef4444'
    };

    const COLOR_RECORRIDO = {
        'FINALIZADO': '#22c55e',
        'EN_PROCESO': '#f59e0b'
    };

    const seccionAlertas = document.getElementById('seccionAlertas');
    const listaAlertas = document.getElementById('listaAlertas');

    const agregarAlerta = (texto, url, nivel) => {
        if (!listaAlertas || !seccionAlertas) return;

        const a = document.createElement('a');
        a.className = `home-alert home-alert-${nivel}`;
        a.href = url;
        a.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i><span>${texto}</span>`;

        listaAlertas.appendChild(a);
        seccionAlertas.style.display = '';
    };

    // ---------------- Desarrollo: KPIs + alertas + mini dashboard (mismo fetch para todo) ----------------
    if (window.homeVerDesarrollo) {
        const elPendientes = document.getElementById('pendDesarrolloPendientes');
        const elUrgentes = document.getElementById('pendDesarrolloUrgentes');
        const elMisPendientes = document.getElementById('misPendientesDesarrollo');
        const cardMisPendientes = document.getElementById('cardMisPendientesDesarrollo');

        Promise.all([
            fetch(`${apiBaseUrl}solicitudes`).then(r => r.ok ? r.json() : []).catch(() => []),
            fetch(`${apiBaseUrl}solicitudes-estructural`).then(r => r.ok ? r.json() : []).catch(() => [])
        ]).then(([grafico, estructural]) => {
            const pendientesGraficoLista = grafico.filter(x => estadosPendientes.includes(x.estado));
            const pendientesEstructuralLista = estructural.filter(x => estadosPendientes.includes(x.estado));
            const urgentes = grafico.filter(x => x.prioridad === 'URGENTE').length;

            if (elPendientes) elPendientes.textContent = pendientesGraficoLista.length + pendientesEstructuralLista.length;
            if (elUrgentes) elUrgentes.textContent = urgentes;

            if (elMisPendientes && cardMisPendientes && nombreUsuarioActual) {
                const esMio = x => (x.operadorEdicion || '').trim().toLowerCase() === nombreUsuarioActual
                    && estadosPendientes.includes(x.estado);

                const misSolicitudes = grafico.filter(esMio).length + estructural.filter(esMio).length;

                if (misSolicitudes > 0) {
                    elMisPendientes.textContent = misSolicitudes;
                    cardMisPendientes.style.display = '';
                    agregarAlerta(`Solicitudes Desarrollo asignadas a ti: ${misSolicitudes}`, '/modules/formularios/desarrollo/', 'info');
                }
            }

            if (urgentes > 0) {
                agregarAlerta(`Solicitudes urgentes sin atender: ${urgentes}`, '/modules/formularios/desarrollo/admin/?prioridad=URGENTE', 'critico');
            }

            renderDonut('homeChartDesarrolloEstados', contarPorCampo(grafico, 'estado'), { colores: COLOR_ESTADO });

            const pendientesPorSolicitante = contarPorCampo(
                pendientesGraficoLista.concat(pendientesEstructuralLista),
                'solicitanteNombre'
            );
            renderRanking('homeChartDesarrolloSolicitantes', pendientesPorSolicitante, { limite: 8 });
        }).catch(() => {
            if (elPendientes) elPendientes.textContent = '-';
            if (elUrgentes) elUrgentes.textContent = '-';
        });
    }

    // ---------------- Mejora Continua: aggregates ya calculados en PHP, solo se dibujan ----------------
    if (window.homeNcPorEstado && Object.keys(window.homeNcPorEstado).length) {
        renderDonut('homeChartNcEstado', window.homeNcPorEstado);
    }
    if (window.homeNcPorSeveridad && Object.keys(window.homeNcPorSeveridad).length) {
        renderDonut('homeChartNcSeveridad', window.homeNcPorSeveridad);
    }

    // ---------------- Guardias: aggregates ya calculados en PHP, solo se dibujan ----------------
    if (window.homeRecorridosPorEstado && Object.keys(window.homeRecorridosPorEstado).length) {
        renderDonut('homeChartRecorridosEstado', window.homeRecorridosPorEstado, { colores: COLOR_RECORRIDO });
    }
    if (window.homeRecorridosDias && window.homeRecorridosDias.length) {
        const etiquetas = window.homeRecorridosDias.map(f => new Date(`${f}T00:00:00`).toLocaleDateString('es-CL', { weekday: 'short' }));
        renderLinea('homeChartRecorridos7d', etiquetas, [
            { nombre: 'Recorridos', color: '#2563eb', valores: window.homeRecorridosValores || [] }
        ]);
    }

    // ---------------- Moldes: solo conteos (porPagina=1), sin traer listados completos ----------------
    const contarTotal = url => fetch(url).then(r => r.ok ? r.json() : { total: 0 }).then(d => d.total || 0).catch(() => 0);

    if (window.homeVerMoldesCpmPrm) {
        contarTotal(`${apiBaseUrl}cpm/perfiles?porPagina=1`).then(n => setText('homeMoldesPerfiles', n));
        Promise.all([
            contarTotal(`${apiBaseUrl}cpm/moldes?tipoMolde=REPETITIVO&porPagina=1`),
            contarTotal(`${apiBaseUrl}cpm/moldes?tipoMolde=NO_REPETITIVO&porPagina=1`)
        ]).then(([a, b]) => setText('homeMoldesCpm', a + b));
    }

    if (window.homeVerControlMoldes) {
        contarTotal(`${apiBaseUrl}com/registros?porPagina=1`).then(n => setText('homeMoldesControl', n));
    }

    if (window.homeVerStockMoldes) {
        contarTotal(`${apiBaseUrl}mre/registros?porPagina=1`).then(n => setText('homeMoldesStock', n));
    }
});
