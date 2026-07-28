document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';

    const overlay = document.getElementById('modalComparacionFps');
    const input = document.getElementById('inputNpComparacion');
    const columnaDesgaje = document.getElementById('columnaDesgaje');
    const columnaFps = document.getElementById('columnaFps');

    let desgajeController = null;
    let fpsController = null;

    document.getElementById('btnCompararFps')?.addEventListener('click', abrirModal);
    document.getElementById('btnCerrarModalFps')?.addEventListener('click', cerrarModal);
    document.getElementById('btnBuscarComparacion')?.addEventListener('click', buscar);

    overlay?.addEventListener('click', (evento) => {
        if (evento.target === overlay) cerrarModal();
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && !overlay.classList.contains('hidden')) cerrarModal();
    });

    input?.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') buscar();
    });

    function abrirModal() {
        overlay.classList.remove('hidden');
        input.focus();
    }

    function cerrarModal() {
        overlay.classList.add('hidden');
    }

    function buscar() {
        const np = input.value.trim();

        if (!np) {
            input.focus();
            return;
        }

        buscarDesgaje(np);
        buscarFps(np);
    }

    async function buscarDesgaje(np) {
        if (desgajeController) desgajeController.abort();
        desgajeController = new AbortController();

        columnaDesgaje.className = 'admin-empty-box';
        columnaDesgaje.textContent = 'Buscando...';

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros?np=${encodeURIComponent(np)}&porPagina=5`, {
                signal: desgajeController.signal
            });
            if (!response.ok) throw new Error('No fue posible buscar el registro de Desgaje.');

            const data = await response.json();
            const items = data.items || [];

            if (items.length === 0) {
                columnaDesgaje.textContent = 'No hay un registro de Desgaje asociado a esta NP.';
                return;
            }

            const response2 = await fetch(`${apiBaseUrl}desgaje/registros/${items[0].id}`, {
                signal: desgajeController.signal
            });
            if (!response2.ok) throw new Error('No fue posible cargar el registro de Desgaje.');

            const registro = await response2.json();
            renderDesgaje(registro, data.total);

        } catch (error) {
            if (error.name === 'AbortError') return;
            columnaDesgaje.className = 'admin-empty-box';
            columnaDesgaje.textContent = error.message;
        }
    }

    async function buscarFps(np) {
        if (fpsController) fpsController.abort();
        fpsController = new AbortController();

        columnaFps.className = 'admin-empty-box';
        columnaFps.textContent = 'Consultando FPS...';

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/comparacion-fps?np=${encodeURIComponent(np)}`, {
                signal: fpsController.signal
            });
            if (!response.ok) throw new Error('No fue posible consultar la información de FPS.');

            const data = await response.json();

            if (!data.ok) {
                columnaFps.className = 'admin-empty-box';
                columnaFps.textContent = data.mensaje || 'No fue posible consultar la información de producción en este momento.';
                return;
            }

            if (!data.registros || data.registros.length === 0) {
                columnaFps.className = 'admin-empty-box';
                columnaFps.textContent = data.mensaje || 'No se encontraron procesos en FPS para esta NP.';
                return;
            }

            renderFps(data.registros);

        } catch (error) {
            if (error.name === 'AbortError') return;
            columnaFps.className = 'admin-empty-box';
            columnaFps.textContent = error.message;
        }
    }

    function renderDesgaje(registro, totalCoincidencias) {
        const estuchesTotal = (registro.detalles || []).reduce((a, d) => a + d.cantidadEstuches, 0);
        const valorTotal = (registro.detalles || []).reduce((a, d) => a + d.valorCalculado, 0);

        const nota = totalCoincidencias > 1
            ? `<p class="admin-empty-box">Mostrando el más reciente de ${totalCoincidencias} registros con esta NP.</p>`
            : '';

        columnaDesgaje.className = '';
        columnaDesgaje.innerHTML = `
            <div class="admin-modal-column-header">
                <strong>${escapeHtml(registro.codigo)}</strong>
                ${badgeEstado(registro.estado)}
            </div>
            ${nota}
            <table class="admin-modal-mini-table">
                <tr><th>Fecha</th><td>${formatearFecha(registro.fechaRegistro, true)}</td></tr>
                <tr><th>Operador</th><td>${escapeHtml(registro.operadorNombreSnapshot)}</td></tr>
                <tr><th>Taller</th><td>${escapeHtml(registro.tallerNombreSnapshot)}</td></tr>
                <tr><th>Trabajos</th><td>${(registro.detalles || []).length}</td></tr>
                <tr><th>Estuches totales</th><td>${estuchesTotal.toLocaleString('es-CL')}</td></tr>
                <tr><th>Valor total</th><td>${formatearMoneda(valorTotal)}</td></tr>
            </table>

            <table class="admin-modal-mini-table" style="margin-top:14px;">
                <thead>
                    <tr><th>NP</th><th>Pliegos</th><th>Moldes</th><th>Estuches</th></tr>
                </thead>
                <tbody>
                    ${(registro.detalles || []).map(d => `
                        <tr>
                            <td>${escapeHtml(d.np)}</td>
                            <td>${d.cantidadPliegos}</td>
                            <td>${d.numeroMoldes}</td>
                            <td>${d.cantidadEstuches}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderFps(registros) {
        const cliente = registros[0].cliente || '-';
        const np = registros[0].np || '-';

        columnaFps.className = '';
        columnaFps.innerHTML = `
            <div class="admin-modal-column-header">
                <strong>${escapeHtml(cliente)}</strong>
                <span class="badge badge-primary">NP ${escapeHtml(np)}</span>
            </div>
            ${registros.map(proceso => `
                <div class="admin-modal-proceso">
                    <div class="admin-modal-proceso-header">
                        <span title="${escapeHtml(proceso.descripcion || '')}">${escapeHtml(proceso.nombreProceso || '-')}</span>
                        ${proceso.esProcesoDesgaje
                            ? '<span class="badge badge-success">Proceso de Desgaje</span>'
                            : '<span class="badge badge-primary">Otro proceso</span>'}
                    </div>
                    <table class="admin-modal-mini-table">
                        <tr><th>OF</th><td>${escapeHtml(proceso.of ?? '-')}</td></tr>
                        <tr><th>Comprometida</th><td>${formatearNumero(proceso.cantidadComprometida)}</td></tr>
                        <tr><th>Realizada</th><td>${formatearNumero(proceso.cantidadRealizada)}</td></tr>
                        <tr><th>Pendiente</th><td>${formatearNumero(proceso.cantidadPendiente)}</td></tr>
                        <tr><th>Avance</th><td>${formatearNumero(proceso.porcentajeAvance)}%</td></tr>
                    </table>
                </div>
            `).join('')}
        `;
    }

    function badgeEstado(estado) {
        const clases = {
            BORRADOR: 'badge',
            FIRMADO: 'badge badge-warning',
            VALIDADO: 'badge badge-success',
            ANULADO: 'badge badge-danger'
        };
        const textos = {
            BORRADOR: 'Borrador',
            FIRMADO: 'Firmado',
            VALIDADO: 'Validado',
            ANULADO: 'Anulado'
        };
        return `<span class="${clases[estado] || 'badge'}">${escapeHtml(textos[estado] || estado)}</span>`;
    }

    function formatearFecha(valor, soloFecha = false) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return valor;

        if (soloFecha) {
            return fecha.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        return fecha.toLocaleString('es-CL', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    }

    function formatearMoneda(valor) {
        const numero = Number(valor || 0);
        return `$${numero.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatearNumero(valor) {
        if (valor === null || valor === undefined) return '-';
        return Number(valor).toLocaleString('es-CL');
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
