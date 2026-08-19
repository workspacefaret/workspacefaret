(function () {
    const { setText, contarPorCampo, renderDonut, renderColumnas, renderRanking, renderLinea } = window.DashboardCharts;

    // Colores semánticos para campos con significado conocido (no inventan datos, solo estilo)
    const COLOR_ESTADO = {
        'Terminado': '#22c55e',
        'Recibido': '#2563eb',
        'Anulado': '#94a3b8',
        'En edición': '#f59e0b',
        'Rechazado': '#ef4444'
    };

    const COLOR_PRIORIDAD = {
        'URGENTE': '#ef4444',
        'ALTA': '#f59e0b',
        'MEDIA': '#2563eb',
        'BAJA': '#22c55e'
    };

    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // ---------------- Evolución temporal Gráfico vs Estructural (por mes) ----------------
    function agruparPorMes(lista, campoFecha) {
        const mapa = {};
        lista.forEach(item => {
            const fecha = item[campoFecha];
            if (!fecha) return;
            const d = new Date(fecha);
            if (isNaN(d)) return;
            const clave = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            mapa[clave] = (mapa[clave] || 0) + 1;
        });
        return mapa;
    }

    function formatearMes(clave) {
        const [anio, mes] = clave.split('-');
        return `${MESES[parseInt(mes, 10) - 1]} ${anio.slice(2)}`;
    }

    function renderEvolucionTemporal(id, solicitudesGrafico, solicitudesEstructural) {
        const seccion = document.getElementById('seccionEvolucion');

        const porMesGrafico = agruparPorMes(solicitudesGrafico, 'fechaRegistro');
        const porMesEstructural = agruparPorMes(solicitudesEstructural, 'fechaRegistro');
        const claves = Array.from(new Set([...Object.keys(porMesGrafico), ...Object.keys(porMesEstructural)])).sort();

        const dibujado = renderLinea(id, claves.map(formatearMes), [
            { nombre: 'Desarrollo Gráfico', color: '#2563eb', valores: claves.map(c => porMesGrafico[c] || 0) },
            { nombre: 'Desarrollo Estructural', color: '#06b6d4', valores: claves.map(c => porMesEstructural[c] || 0) }
        ]);

        if (!dibujado && seccion) seccion.style.display = 'none';
    }

    // Coordina el gráfico combinado: se renderiza recién cuando ambos dashboards ya cargaron sus datos
    const cargaDashboard = { grafico: null, estructural: null };

    function registrarCarga(clave, solicitudes) {
        cargaDashboard[clave] = solicitudes;
        if (cargaDashboard.grafico && cargaDashboard.estructural) {
            renderEvolucionTemporal('chartEvolucion', cargaDashboard.grafico, cargaDashboard.estructural);
        }
    }

    // Selector Gráfico/Estructural: solo alterna qué panel se ve, ambos datasets ya se cargan igual que antes
    function inicializarTabsDashboard() {
        const botones = Array.from(document.querySelectorAll('[data-dev-tab]'));
        botones.forEach(boton => {
            boton.addEventListener('click', () => {
                const clave = boton.dataset.devTab;
                botones.forEach(b => b.classList.toggle('is-active', b === boton));
                document.querySelectorAll('[data-dev-panel]').forEach(panel => {
                    panel.classList.toggle('is-active', panel.dataset.devPanel === clave);
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', inicializarTabsDashboard);

    window.DesarrolloDashboardUtils = {
        setText, contarPorCampo, renderDonut, renderColumnas, renderRanking, registrarCarga,
        COLOR_ESTADO, COLOR_PRIORIDAD
    };
})();
