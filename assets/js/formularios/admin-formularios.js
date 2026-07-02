document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';

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

    document.getElementById('btnRefrescarSolicitudes')?.addEventListener('click', cargarSolicitudes);
    document.getElementById('btnLimpiarFiltros')?.addEventListener('click', limpiarFiltros);
    document.getElementById('btnExportarExcel')?.addEventListener('click', exportarExcel);

    [filtroCodigo, filtroCliente, filtroEstado, filtroPrioridad].forEach(control => {
        control?.addEventListener('input', aplicarFiltros);
        control?.addEventListener('change', aplicarFiltros);
    });

    cargarSolicitudes();

    async function cargarSolicitudes() {
        mostrarCargando();

        try {
            const response = await fetch(`${apiBaseUrl}solicitudes`);
            if (!response.ok) throw new Error('No se pudieron cargar las solicitudes.');

            solicitudes = await response.json();
            solicitudesFiltradas = [...solicitudes];
            paginaActual = 1;

            cargarOpcionesFiltros();
            actualizarKpis();
            renderTabla();
            renderPaginacion();

        } catch (error) {
            tablaBody.innerHTML = `<tr><td colspan="9" class="admin-empty">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    function aplicarFiltros() {
        const codigo = normalizar(filtroCodigo.value);
        const cliente = normalizar(filtroCliente.value);
        const estado = filtroEstado.value;
        const prioridad = filtroPrioridad.value;

        solicitudesFiltradas = solicitudes.filter(item => {
            return (!codigo || normalizar(item.codigo).includes(codigo))
                && (!cliente || normalizar(item.clienteNombre).includes(cliente))
                && (!estado || item.estado === estado)
                && (!prioridad || item.prioridad === prioridad);
        });

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
        aplicarFiltros();
    }

    function renderTabla() {
        if (!solicitudesFiltradas.length) {
            tablaBody.innerHTML = `<tr><td colspan="9" class="admin-empty">No hay solicitudes para mostrar.</td></tr>`;
            return;
        }

        const inicio = (paginaActual - 1) * registrosPorPagina;
        const pagina = solicitudesFiltradas.slice(inicio, inicio + registrosPorPagina);

        tablaBody.innerHTML = pagina.map(item => `
            <tr>
                <td><strong>${escapeHtml(item.codigo)}</strong></td>
                <td><span class="admin-status">${escapeHtml(item.estado)}</span></td>
                <td><span class="admin-priority">${escapeHtml(item.prioridad)}</span></td>
                <td>${escapeHtml(item.solicitanteNombre)}</td>
                <td>${escapeHtml(item.clienteNombre)}</td>
                <td>${escapeHtml(item.producto)}</td>
                <td>${escapeHtml(item.tipoProceso)}</td>
                <td>${formatearFecha(item.fechaRegistro)}</td>
                <td>
                    <div class="admin-row-actions">
                        <a class="admin-icon-btn" href="/modules/formularios/desarrollo/admin/detalle.php?id=${item.id}" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a class="admin-icon-btn" href="${apiBaseUrl}solicitudes/${item.id}/pdf" target="_blank" title="PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');
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

        const detalles = await Promise.all(
            solicitudesFiltradas.map(async item => {
                try {
                    const response = await fetch(`${apiBaseUrl}solicitudes/${item.id}/detalle`);
                    return response.ok ? await response.json() : item;
                } catch {
                    return item;
                }
            })
        );

        const filas = detalles.map(item => `
            <tr>
                <td>${escapeHtml(item.estado)}</td>
                <td>${escapeHtml(item.prioridad)}</td>
                <td>${escapeHtml(item.operadorEdicion || '')}</td>
                <td>${escapeHtml(item.codigo)}</td>
                <td>${formatearFecha(item.fechaRegistro)}</td>
                <td>${escapeHtml(item.solicitanteNombre)}</td>
                <td>${escapeHtml(item.clienteNombre)}</td>
                <td>${escapeHtml(item.clienteNuevo || '')}</td>
                <td>${escapeHtml(item.producto)}</td>
                <td>${escapeHtml(item.sustrato || '')}</td>
                <td>${escapeHtml(item.tipoProceso)}</td>
                <td>${escapeHtml(item.perfil || '')}</td>
                <td>${escapeHtml(item.terminaciones || '')}</td>
                <td>${escapeHtml(item.solicitud || '')}</td>
                <td>${escapeHtml(item.cantidadMuestras || '')}</td>
                <td>${escapeHtml(item.emailSolicitante || '')}</td>
                <td>${escapeHtml(item.cantidadItems || '')}</td>
                <td>${escapeHtml(item.observacion || '')}</td>
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
                    <tr><td colspan="18" class="title">Exportación Solicitudes Desarrollo Gráfico</td></tr>
                    <tr><td colspan="18" class="subtitle">Workspace Faret - ${new Date().toLocaleString('es-CL')}</td></tr>
                    <tr></tr>
                    <tr>
                        <th>ESTADO</th>
                        <th>PRIORIDAD</th>
                        <th>OPERADOR EDICION</th>
                        <th>CÓDIGO</th>
                        <th>MARCA TEMPORAL</th>
                        <th>SOLICITANTE</th>
                        <th>CLIENTE</th>
                        <th>CLIENTE NUEVO</th>
                        <th>PRODUCTO</th>
                        <th>SUSTRATO</th>
                        <th>TIPO PROCESO</th>
                        <th>PERFIL</th>
                        <th>TERMINACIONES</th>
                        <th>SOLICITUD</th>
                        <th>CANTIDAD DE MUESTRAS</th>
                        <th>DIRECCIÓN DE CORREO ELECTRÓNICO</th>
                        <th>CANTIDAD DE ITEMS</th>
                        <th>OBSERVACIÓN</th>
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
        link.download = `solicitudes-desarrollo-grafico-${fechaArchivo()}.xls`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function cargarOpcionesFiltros() {
        cargarSelectUnico(filtroEstado, solicitudes.map(x => x.estado), 'Todos');
        cargarSelectUnico(filtroPrioridad, solicitudes.map(x => x.prioridad), 'Todas');
    }

    function cargarSelectUnico(select, valores, textoInicial) {
        const valorActual = select.value;
        select.innerHTML = `<option value="">${textoInicial}</option>`;
        [...new Set(valores.filter(Boolean))].sort().forEach(valor => {
            select.innerHTML += `<option value="${escapeHtml(valor)}">${escapeHtml(valor)}</option>`;
        });
        select.value = valorActual;
    }

    function actualizarKpis() {
        document.getElementById('kpiTotal').textContent = solicitudesFiltradas.length;
        document.getElementById('kpiRecibidas').textContent = solicitudesFiltradas.filter(x => x.estado === 'Recibido').length;
        document.getElementById('kpiEdicion').textContent = solicitudesFiltradas.filter(x => x.estado === 'En edición').length;
        document.getElementById('kpiUrgentes').textContent = solicitudesFiltradas.filter(x => x.prioridad === 'URGENTE').length;
        badgeCantidad.textContent = `${solicitudesFiltradas.length} registros`;
    }

    function mostrarCargando() {
        tablaBody.innerHTML = `<tr><td colspan="9" class="admin-empty">Cargando solicitudes...</td></tr>`;
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

    function escapeHtml(texto) {
        return String(texto ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
