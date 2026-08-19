document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const { setText, contarPorCampo, renderDonut, renderColumnas, renderRanking, registrarCarga, COLOR_ESTADO, COLOR_PRIORIDAD } = window.DesarrolloDashboardUtils;

    cargarResumenDesarrollo();

    async function cargarResumenDesarrollo() {
        try {
            const response = await fetch(`${apiBaseUrl}solicitudes`);
            if (!response.ok) throw new Error('No se pudieron cargar las estadísticas.');

            const solicitudes = await response.json();
            const total = solicitudes.length;

            setText('dashTotalSolicitudes', total);
            setText('dashRecibidas', contarPorCampo(solicitudes, 'estado')['Recibido'] || 0);
            setText('dashUrgentes', contarPorCampo(solicitudes, 'prioridad')['URGENTE'] || 0);
            setText('dashTerminadas', contarPorCampo(solicitudes, 'estado')['Terminado'] || 0);

            renderDonut('chartEstados', contarPorCampo(solicitudes, 'estado'), { colores: COLOR_ESTADO });
            renderDonut('chartPrioridades', contarPorCampo(solicitudes, 'prioridad'), { colores: COLOR_PRIORIDAD });
            renderColumnas('chartProcesos', contarPorCampo(solicitudes, 'tipoProceso'));
            renderRanking('chartSolicitantes', contarPorCampo(solicitudes, 'solicitanteNombre'), { total, limite: 8 });

            registrarCarga('grafico', solicitudes);

        } catch (error) {
            ['chartEstados', 'chartPrioridades', 'chartProcesos', 'chartSolicitantes'].forEach(id => {
                setText(id, error.message);
            });
            registrarCarga('grafico', []);
        }
    }
});
