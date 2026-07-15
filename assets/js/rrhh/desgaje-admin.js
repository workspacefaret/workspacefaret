document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';

    let catalogos = { tipos: [], talleres: [], operadores: [] };
    let paginaActual = 1;
    let totalPaginas = 1;
    const porPagina = 20;
    let filtroDebounce = null;

    const tablaBody = document.getElementById('tablaRegistrosBody');
    const badgeCantidad = document.getElementById('badgeCantidadRegistros');
    const paginacion = document.getElementById('adminPagination');

    const filtroFechaDesde = document.getElementById('filtroFechaDesde');
    const filtroFechaHasta = document.getElementById('filtroFechaHasta');
    const filtroTaller = document.getElementById('filtroTaller');
    const filtroOperador = document.getElementById('filtroOperador');
    const filtroCliente = document.getElementById('filtroCliente');
    const filtroNp = document.getElementById('filtroNp');
    const filtroTipo = document.getElementById('filtroTipo');
    const filtroEstado = document.getElementById('filtroEstado');

    document.getElementById('btnRefrescarRegistros')?.addEventListener('click', () => cargarRegistros());
    document.getElementById('btnLimpiarFiltros')?.addEventListener('click', limpiarFiltros);
    document.getElementById('btnExportarExcel')?.addEventListener('click', exportarExcel);

    [filtroTaller, filtroOperador, filtroTipo, filtroEstado, filtroFechaDesde, filtroFechaHasta].forEach(control => {
        control?.addEventListener('change', () => { paginaActual = 1; cargarRegistros(); });
    });

    [filtroCliente, filtroNp].forEach(control => {
        control?.addEventListener('input', () => {
            clearTimeout(filtroDebounce);
            filtroDebounce = setTimeout(() => { paginaActual = 1; cargarRegistros(); }, 350);
        });
    });

    inicializar();

    async function inicializar() {
        await cargarCatalogos();
        await cargarPendientes();
        await cargarRegistros();
    }

    async function cargarCatalogos() {
        try {
            const response = await fetch(`${apiBaseUrl}desgaje/catalogos`);
            if (!response.ok) throw new Error('No se pudieron cargar los catálogos.');

            catalogos = await response.json();

            cargarSelect(filtroTaller, catalogos.talleres, 'Todos');
            cargarSelect(filtroOperador, catalogos.operadores, 'Todos');
            cargarSelect(filtroTipo, catalogos.tipos, 'Todos');

        } catch (error) {
            console.error(error);
        }
    }

    function cargarSelect(select, items, textoInicial) {
        if (!select) return;
        select.innerHTML = `<option value="">${textoInicial}</option>`;
        (items || []).filter(i => i.activo).forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.nombre;
            select.appendChild(option);
        });
    }

    async function cargarPendientes() {
        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros?estado=FIRMADO&porPagina=1`);
            if (!response.ok) throw new Error('No se pudo cargar el pendiente de validar.');

            const data = await response.json();
            document.getElementById('kpiPendientes').textContent = data.total;

        } catch (error) {
            console.error(error);
        }
    }

    function construirQuery(paginaOverride, porPaginaOverride) {
        const params = new URLSearchParams();

        if (filtroFechaDesde.value) params.set('fechaDesde', filtroFechaDesde.value);
        if (filtroFechaHasta.value) params.set('fechaHasta', filtroFechaHasta.value);
        if (filtroTaller.value) params.set('tallerId', filtroTaller.value);
        if (filtroOperador.value) params.set('operadorId', filtroOperador.value);
        if (filtroCliente.value.trim()) params.set('cliente', filtroCliente.value.trim());
        if (filtroNp.value.trim()) params.set('np', filtroNp.value.trim());
        if (filtroTipo.value) params.set('tipoDesgajeId', filtroTipo.value);
        if (filtroEstado.value) params.set('estado', filtroEstado.value);

        params.set('pagina', paginaOverride || paginaActual);
        params.set('porPagina', porPaginaOverride || porPagina);

        return params.toString();
    }

    async function cargarRegistros() {
        mostrarCargando();

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros?${construirQuery()}`);
            if (!response.ok) throw new Error('No se pudieron cargar los registros.');

            const data = await response.json();

            totalPaginas = Math.max(1, Math.ceil(data.total / porPagina));
            actualizarKpis(data);
            renderTabla(data.items);
            renderPaginacion();

        } catch (error) {
            tablaBody.innerHTML = `<tr><td colspan="9" class="admin-empty">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    function limpiarFiltros() {
        filtroFechaDesde.value = '';
        filtroFechaHasta.value = '';
        filtroTaller.value = '';
        filtroOperador.value = '';
        filtroCliente.value = '';
        filtroNp.value = '';
        filtroTipo.value = '';
        filtroEstado.value = '';
        paginaActual = 1;
        cargarRegistros();
    }

    function renderTabla(items) {
        if (!items || !items.length) {
            tablaBody.innerHTML = `<tr><td colspan="9" class="admin-empty">No hay registros para mostrar.</td></tr>`;
            return;
        }

        tablaBody.innerHTML = items.map(item => `
            <tr>
                <td><strong>${escapeHtml(item.codigo)}</strong></td>
                <td>${formatearFecha(item.fechaRegistro, true)}</td>
                <td>${escapeHtml(item.tallerNombreSnapshot)}</td>
                <td>${escapeHtml(item.operadorNombreSnapshot)}</td>
                <td>${badgeEstado(item.estado)}</td>
                <td>${item.cantidadTrabajos}</td>
                <td>${item.cantidadEstuchesTotal}</td>
                <td>${formatearMoneda(item.valorTotal)}</td>
                <td>
                    <div class="admin-row-actions">
                        <a class="admin-icon-btn" href="/modules/rrhh/desgaje/admin/detalle.php?id=${item.id}" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                        ${item.pdfRuta ? `
                        <a class="admin-icon-btn" href="${apiBaseUrl}desgaje/registros/${item.id}/pdf" target="_blank" title="PDF">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderPaginacion() {
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
                cargarRegistros();
            });
        });
    }

    function actualizarKpis(data) {
        document.getElementById('kpiTotal').textContent = data.total;
        document.getElementById('kpiEstuches').textContent = data.totales?.cantidadEstuches ?? 0;
        document.getElementById('kpiValor').textContent = formatearMoneda(data.totales?.valorTotal ?? 0);
        badgeCantidad.textContent = `${data.total} registros`;
    }

    async function exportarExcel() {
        let items = [];

        try {
            const response = await fetch(`${apiBaseUrl}desgaje/registros?${construirQuery(1, 5000)}`);
            if (!response.ok) throw new Error('No se pudieron cargar los registros para exportar.');

            const data = await response.json();
            items = data.items || [];

        } catch (error) {
            alert(error.message);
            return;
        }

        if (!items.length) {
            alert('No hay registros para exportar.');
            return;
        }

        const filas = items.map(item => `
            <tr>
                <td>${escapeHtml(item.codigo)}</td>
                <td>${formatearFecha(item.fechaRegistro, true)}</td>
                <td>${escapeHtml(item.tallerNombreSnapshot)}</td>
                <td>${escapeHtml(item.operadorNombreSnapshot)}</td>
                <td>${escapeHtml(item.estado)}</td>
                <td>${item.cantidadTrabajos}</td>
                <td>${item.cantidadEstuchesTotal}</td>
                <td>${item.valorTotal}</td>
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
                    <tr><td colspan="8" class="title">Exportación Registros de Desgaje</td></tr>
                    <tr><td colspan="8" class="subtitle">Workspace Faret - ${new Date().toLocaleString('es-CL')}</td></tr>
                    <tr></tr>
                    <tr>
                        <th>CÓDIGO</th>
                        <th>FECHA</th>
                        <th>TALLER</th>
                        <th>OPERADOR</th>
                        <th>ESTADO</th>
                        <th>TRABAJOS</th>
                        <th>ESTUCHES</th>
                        <th>VALOR</th>
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
        link.download = `registros-desgaje-${fechaArchivo()}.xls`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    }

    function mostrarCargando() {
        tablaBody.innerHTML = `<tr><td colspan="9" class="admin-empty">Cargando registros...</td></tr>`;
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
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatearMoneda(valor) {
        const numero = Number(valor || 0);
        return `$${numero.toLocaleString('es-CL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function fechaArchivo() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
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
