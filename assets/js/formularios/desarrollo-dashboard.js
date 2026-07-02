document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';

    cargarResumenDesarrollo();

    async function cargarResumenDesarrollo() {
        try {
            const response = await fetch(`${apiBaseUrl}solicitudes`);
            if (!response.ok) throw new Error('No se pudieron cargar las estadísticas.');

            const solicitudes = await response.json();

            setText('dashTotalSolicitudes', solicitudes.length);
            setText('dashRecibidas', contarPorCampo(solicitudes, 'estado')['Recibido'] || 0);
            setText('dashUrgentes', contarPorCampo(solicitudes, 'prioridad')['URGENTE'] || 0);
            setText('dashTerminadas', contarPorCampo(solicitudes, 'estado')['Terminado'] || 0);

            renderChart('chartEstados', contarPorCampo(solicitudes, 'estado'));
            renderChart('chartPrioridades', contarPorCampo(solicitudes, 'prioridad'));
            renderChart('chartProcesos', contarPorCampo(solicitudes, 'tipoProceso'));
            renderChart('chartSolicitantes', contarPorCampo(solicitudes, 'solicitanteNombre'));

        } catch (error) {
            ['chartEstados', 'chartPrioridades', 'chartProcesos', 'chartSolicitantes'].forEach(id => {
                setText(id, error.message);
            });
        }
    }

    function contarPorCampo(lista, campo) {
        return lista.reduce((acc, item) => {
            const valor = item[campo] || 'Sin dato';
            acc[valor] = (acc[valor] || 0) + 1;
            return acc;
        }, {});
    }

    function renderChart(id, datos) {
        const contenedor = document.getElementById(id);
        if (!contenedor) return;

        const entries = Object.entries(datos).sort((a, b) => b[1] - a[1]);
        const max = Math.max(...entries.map(x => x[1]), 1);

        contenedor.innerHTML = entries.map(([label, value]) => {
            const width = Math.round((value / max) * 100);

            return `
                <div class="chart-row">
                    <div class="chart-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
                    <div class="chart-bar">
                        <div class="chart-fill" style="width:${width}%"></div>
                    </div>
                    <div class="chart-value">${value}</div>
                </div>
            `;
        }).join('');
    }

    function setText(id, valor) {
        const elemento = document.getElementById(id);
        if (elemento) elemento.textContent = valor;
    }

    function escapeHtml(texto) {
        return String(texto ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
