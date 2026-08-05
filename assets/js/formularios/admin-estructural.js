document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const filtrosStorageKey = 'wsfaret-filtros-desarrollo-estructural';
    const esAdminTi = window.esAdminTi === true;
    const adminDeleteKey = window.API_ADMIN_DELETE_KEY || '';

    let solicitudes = [];
    let solicitudesFiltradas = [];
    let paginaActual = 1;
    const registrosPorPagina = 20;

    const tablaBody = document.getElementById('tablaSolicitudesBody');
    const badgeCantidad = document.getElementById('badgeCantidadSolicitudes');
    const paginacion = document.getElementById('adminPagination');

    const filtroCodigo = document.getElementById('filtroCodigo');
    const filtroCliente = document.getElementById('filtroCliente');
    const filtroEstado = document.getElementById('filtroEstado');
    const filtroPrioridad = document.getElementById('filtroPrioridad');
    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');

    document.getElementById('btnRefrescarSolicitudes')?.addEventListener('click', cargarSolicitudes);
    document.getElementById('btnLimpiarFiltros')?.addEventListener('click', limpiarFiltros);
    document.getElementById('btnExportarExcel')?.addEventListener('click', exportarExcel);

    [filtroCodigo, filtroCliente, filtroEstado, filtroPrioridad, filtroFechaDesde, filtroFechaHasta].forEach(control => {
        control?.addEventListener('input', aplicarFiltros);
        control?.addEventListener('change', aplicarFiltros);
    });

    cargarSolicitudes();
    setInterval(cargarSolicitudes, 5 * 60 * 1000);

    async function cargarSolicitudes() {
        mostrarCargando();

        try {
            const response = await fetch(`${apiBaseUrl}solicitudes-estructural`);
            if (!response.ok) throw new Error('No se pudieron cargar las solicitudes.');

            solicitudes = await response.json();

            cargarOpcionesFiltros();
            restaurarFiltros();
            aplicarFiltros();

        } catch (error) {
            tablaBody.innerHTML = `<tr><td colspan="13" class="admin-empty">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    function restaurarFiltros() {
        try {
            const guardado = JSON.parse(localStorage.getItem(filtrosStorageKey));
            if (!guardado) return;

            filtroCodigo.value = guardado.codigo || '';
            filtroCliente.value = guardado.cliente || '';
            filtroEstado.value = guardado.estado || '';
            filtroPrioridad.value = guardado.prioridad || '';
            filtroFechaDesde.value = guardado.fechaDesde || '';
            filtroFechaHasta.value = guardado.fechaHasta || '';
        } catch {
            // localStorage no disponible o dato corrupto: se ignora y se sigue sin filtros guardados.
        }
    }

    function guardarFiltros() {
        try {
            localStorage.setItem(filtrosStorageKey, JSON.stringify({
                codigo: filtroCodigo.value,
                cliente: filtroCliente.value,
                estado: filtroEstado.value,
                prioridad: filtroPrioridad.value,
                fechaDesde: filtroFechaDesde.value,
                fechaHasta: filtroFechaHasta.value
            }));
        } catch {
            // localStorage no disponible: se ignora, el filtrado sigue funcionando en memoria.
        }
    }

    function aplicarFiltros() {
        const codigo = normalizar(filtroCodigo.value);
        const cliente = normalizar(filtroCliente.value);
        const estado = filtroEstado.value;
        const prioridad = filtroPrioridad.value;
        const desde = filtroFechaDesde.value ? new Date(filtroFechaDesde.value) : null;
        const hasta = filtroFechaHasta.value ? new Date(filtroFechaHasta.value) : null;

        solicitudesFiltradas = solicitudes.filter(item => {
            const fechaRegistro = item.fechaRegistro ? new Date(item.fechaRegistro) : null;

            if (codigo && !normalizar(item.codigo).includes(codigo)) return false;
            if (cliente && !normalizar(item.clienteNombre).includes(cliente)) return false;
            if (estado && item.estado !== estado) return false;
            if (prioridad && item.prioridad !== prioridad) return false;
            if (desde && fechaRegistro && fechaRegistro < desde) return false;
            if (hasta && fechaRegistro && fechaRegistro > hasta) return false;

            return true;
        });

        guardarFiltros();
        paginaActual = 1;
        actualizarKpis();
        renderTabla();
        renderPaginacion();
    }

    function limpiarFiltros() {
        filtroCodigo.value = '';
        filtroCliente.value = '';
        filtroEstado.value = '';
        filtroPrioridad.value = '';
        filtroFechaDesde.value = '';
        filtroFechaHasta.value = '';
        aplicarFiltros();
    }

    function cargarOpcionesFiltros() {
        cargarSelectUnico(filtroEstado, solicitudes.map(x => x.estado), 'Todos');
        cargarSelectUnico(filtroPrioridad, solicitudes.map(x => x.prioridad), 'Todas');
    }

    function cargarSelectUnico(select, valores, textoInicial) {
        const valorActual = select.value;
        select.innerHTML = `<option value="">${textoInicial}</option>`;
        [...new Set(valores.filter(Boolean))].sort().forEach(valor => {
            select.innerHTML += `<option value="${escapeHtml(valor)}">${escapeHtml(mostrarEstado(valor))}</option>`;
        });
        select.value = valorActual;
    }

    function renderTabla() {
        if (!solicitudesFiltradas.length) {
            tablaBody.innerHTML = `<tr><td colspan="13" class="admin-empty">No hay solicitudes para mostrar.</td></tr>`;
            return;
        }

        const inicio = (paginaActual - 1) * registrosPorPagina;
        const pagina = solicitudesFiltradas.slice(inicio, inicio + registrosPorPagina);

        tablaBody.innerHTML = pagina.map(item => `
            <tr class="${claseSemaforo(item.estado)}">
                <td><strong>${escapeHtml(item.codigo)}</strong></td>
                <td><span class="admin-status">${escapeHtml(mostrarEstado(item.estado))}</span></td>
                <td><span class="admin-priority">${escapeHtml(item.prioridad || '-')}</span></td>
                <td>${formatearFecha(item.fechaRegistro)}</td>
                <td>${escapeHtml(item.solicitanteNombre)}</td>
                <td>${escapeHtml(item.operadorEdicion || '-')}</td>
                <td>${escapeHtml(item.clienteNombre)}${item.clienteNuevo ? ` <small>(${escapeHtml(item.clienteNuevo)})</small>` : ''}</td>
                <td>${escapeHtml(item.oc || '-')}</td>
                <td>${escapeHtml(item.producto)}</td>
                <td>${escapeHtml(item.cantidadMuestras)}</td>
                <td>${escapeHtml(item.destinoMuestras || '-')}</td>
                <td>${escapeHtml(item.referencia || '-')}</td>
                <td>
                    <div class="admin-row-actions">
                        <a class="admin-icon-btn" href="/modules/formularios/desarrollo/solicitud-estructural/admin/detalle.php?id=${item.id}" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a class="admin-icon-btn" href="${apiBaseUrl}solicitudes-estructural/${item.id}/pdf" target="_blank" title="PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                        ${item.cantidadAdjuntos > 0
                            ? `<span class="admin-icon-btn" title="${item.cantidadAdjuntos} adjunto(s)"><i class="bi bi-paperclip"></i></span>`
                            : ''}
                        ${esAdminTi ? `
                        <button class="admin-icon-btn admin-icon-btn-danger" type="button" data-eliminar-id="${item.id}" data-eliminar-codigo="${escapeHtml(item.codigo)}" title="Eliminar solicitud">
                            <i class="bi bi-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        if (esAdminTi) {
            tablaBody.querySelectorAll('button[data-eliminar-id]').forEach(btn => {
                btn.addEventListener('click', () => eliminarSolicitud(btn.dataset.eliminarId, btn.dataset.eliminarCodigo));
            });
        }
    }

    async function eliminarSolicitud(id, codigo) {
        const confirmado = confirm(
            `¿Eliminar definitivamente la solicitud ${codigo}?\n\nSe borrará la fila, su historial y sus adjuntos. Esta acción NO se puede deshacer.`
        );
        if (!confirmado) return;

        try {
            const response = await fetch(`${apiBaseUrl}solicitudes-estructural/${id}`, {
                method: 'DELETE',
                headers: { 'X-Admin-Key': adminDeleteKey }
            });

            if (!response.ok) {
                const mensaje = await response.text();
                throw new Error(mensaje || 'No se pudo eliminar la solicitud.');
            }

            solicitudes = solicitudes.filter(item => item.id !== Number(id));
            aplicarFiltros();
        } catch (error) {
            alert(error.message);
        }
    }

    function renderPaginacion() {
        const totalPaginas = Math.ceil(solicitudesFiltradas.length / registrosPorPagina);

        if (!paginacion || totalPaginas <= 1) {
            if (paginacion) paginacion.innerHTML = '';
            return;
        }

        paginacion.innerHTML = `
            <button ${paginaActual === 1 ? 'disabled' : ''} data-page="${paginaActual - 1}">Anterior</button>
            <span>Página ${paginaActual} de ${totalPaginas}</span>
            <button ${paginaActual === totalPaginas ? 'disabled' : ''} data-page="${paginaActual + 1}">Siguiente</button>
        `;

        paginacion.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                paginaActual = Number(btn.dataset.page);
                renderTabla();
                renderPaginacion();
            });
        });
    }

    async function exportarExcel() {
        if (!solicitudesFiltradas.length) {
            alert('No hay registros para exportar.');
            return;
        }

        const filas = solicitudesFiltradas.map(item => `
            <tr>
                <td>${escapeHtml(item.codigo)}</td>
                <td>${escapeHtml(item.estado)}</td>
                <td>${escapeHtml(item.prioridad || '')}</td>
                <td>${formatearFecha(item.fechaRegistro)}</td>
                <td>${escapeHtml(item.solicitanteNombre)}</td>
                <td>${escapeHtml(item.clienteNombre)}</td>
                <td>${escapeHtml(item.clienteNuevo || '')}</td>
                <td>${escapeHtml(item.oc || '')}</td>
                <td>${escapeHtml(item.producto)}</td>
                <td>${escapeHtml(item.solicitud)}</td>
                <td>${escapeHtml(item.cantidadMuestras)}</td>
                <td>${escapeHtml(item.destinoMuestras || '')}</td>
                <td>${escapeHtml(item.sustrato)}</td>
                <td>${escapeHtml(item.referencia || '')}</td>
                <td>${item.cantidadAdjuntos > 0 ? 'Sí' : 'No'}</td>
            </tr>
        `).join('');

        const html = `
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    table { border-collapse: collapse; font-family: Arial; font-size: 11px; }
                    th { background: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #334155; padding: 8px; }
                    td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }
                    tr:nth-child(even) td { background: #f8fafc; }
                    .title { font-size: 20px; font-weight: bold; color: #0f172a; }
                    .subtitle { color: #475569; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="15" class="title">Exportación Solicitudes Desarrollo Estructural</td></tr>
                    <tr><td colspan="15" class="subtitle">Workspace Faret - ${new Date().toLocaleString('es-CL')}</td></tr>
                    <tr></tr>
                    <tr>
                        <th>CÓDIGO</th>
                        <th>ESTADO</th>
                        <th>PRIORIDAD</th>
                        <th>FECHA CREACIÓN</th>
                        <th>SOLICITANTE</th>
                        <th>CLIENTE</th>
                        <th>CLIENTE NUEVO</th>
                        <th>OC</th>
                        <th>PRODUCTO</th>
                        <th>SOLICITUD</th>
                        <th>CANTIDAD MUESTRAS</th>
                        <th>DESTINO MUESTRAS</th>
                        <th>SUSTRATO</th>
                        <th>REFERENCIA</th>
                        <th>ARCHIVO ADJUNTO</th>
                    </tr>
                    ${filas}
                </table>
            </body>
            </html>
        `;

        const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = `solicitudes-desarrollo-estructural-${fechaArchivo()}.xls`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function actualizarKpis() {
        const haceUnaSemana = new Date();
        haceUnaSemana.setDate(haceUnaSemana.getDate() - 7);

        document.getElementById('kpiTotal').textContent = solicitudesFiltradas.length;
        document.getElementById('kpiConAdjunto').textContent = solicitudesFiltradas.filter(x => x.cantidadAdjuntos > 0).length;
        document.getElementById('kpiClienteNuevo').textContent = solicitudesFiltradas.filter(x => x.clienteNuevo).length;
        document.getElementById('kpiRecientes').textContent = solicitudesFiltradas.filter(x => x.fechaRegistro && new Date(x.fechaRegistro) >= haceUnaSemana).length;

        badgeCantidad.textContent = `${solicitudesFiltradas.length} registros`;
    }

    function mostrarCargando() {
        tablaBody.innerHTML = `<tr><td colspan="13" class="admin-empty">Cargando solicitudes...</td></tr>`;
    }

    function formatearFecha(valor) {
        if (!valor) return '';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return valor;

        return fecha.toLocaleString('es-CL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function fechaArchivo() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function normalizar(valor) {
        return String(valor || '').trim().toLowerCase();
    }

    function mostrarEstado(estado) {
        const normalizado = normalizar(estado);
        if (normalizado === 'en edición' || normalizado === 'en edicion') return 'En Proceso';
        return estado;
    }

    function claseSemaforo(estado) {
        switch (normalizar(estado)) {
            case 'en edición':
            case 'en edicion':
                return 'admin-row-edicion';
            case 'terminado':
                return 'admin-row-terminado';
            case 'rechazado':
                return 'admin-row-rechazado';
            case 'anulado':
                return 'admin-row-anulado';
            default:
                return '';
        }
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
