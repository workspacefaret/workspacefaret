document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const { setText, contarPorCampo, renderDonut, renderRanking, registrarCarga } = window.DesarrolloDashboardUtils;

    cargarResumenEstructural();

    async function cargarResumenEstructural() {
        try {
            const response = await fetch(`${apiBaseUrl}solicitudes-estructural`);
            if (!response.ok) throw new Error('No se pudieron cargar las estadísticas.');

            const solicitudes = await response.json();
            const total = solicitudes.length;

            const haceUnaSemana = new Date();
            haceUnaSemana.setDate(haceUnaSemana.getDate() - 7);

            setText('dashEstructuralTotal', total);
            setText('dashEstructuralAdjunto', solicitudes.filter(x => x.cantidadAdjuntos > 0).length);
            setText('dashEstructuralClienteNuevo', solicitudes.filter(x => x.clienteNuevo).length);
            setText('dashEstructuralRecientes', solicitudes.filter(x => x.fechaRegistro && new Date(x.fechaRegistro) >= haceUnaSemana).length);

            renderRanking('chartEstructuralProducto', contarPorCampo(solicitudes, 'producto'), { total, limite: 8 });
            renderRanking('chartEstructuralSustrato', contarPorCampo(solicitudes, 'sustrato'), { total, limite: 8 });
            renderDonut('chartEstructuralDestino', contarPorCampo(solicitudes, 'destinoMuestras'));
            renderRanking('chartEstructuralSolicitantes', contarPorCampo(solicitudes, 'solicitanteNombre'), { total, limite: 8 });

            registrarCarga('estructural', solicitudes);

        } catch (error) {
            ['chartEstructuralProducto', 'chartEstructuralSustrato', 'chartEstructuralDestino', 'chartEstructuralSolicitantes'].forEach(id => {
                setText(id, error.message);
            });
            registrarCarga('estructural', []);
        }
    }
});
