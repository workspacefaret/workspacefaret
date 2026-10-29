(function () {
    const PC = window.PilasCartulina;
    const apiBaseUrl = PC.apiBaseUrl;
    const usuarioActual = window.currentUserNombre || '';
    const puedeGestionar = window.pilasPuedeGestionar === true;
    const adminDeleteKey = window.API_ADMIN_DELETE_KEY || '';

    let paginaActual = 1;
    const porPagina = 50;
    let debounceTimer = null;

    const ESTADOS_VALIDOS = ['PENDIENTE_RECEPCION', 'RECIBIDO', 'ANULADO'];

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() || null : null;
    }

    function mostrarAlerta(elId, mensaje, tipo) {
        const el = document.getElementById(elId);
        if (!el) return;
        el.textContent = mensaje;
        el.className = 'admin-alert admin-alert-' + tipo;
        el.classList.remove('hidden');
    }

    function ocultarAlerta(elId) {
        const el = document.getElementById(elId);
        if (el) el.classList.add('hidden');
    }

    function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function cerrarModal(id) { document.getElementById(id).classList.add('hidden'); }

    function construirQueryFiltros(pagina) {
        const params = new URLSearchParams();
        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = valor('filtroBuscar');
        const np = valor('filtroNp');
        const estado = valor('filtroEstado');
        const fechaDesde = valor('filtroFechaDesde');
        const fechaHasta = valor('filtroFechaHasta');

        if (buscar) params.set('buscar', buscar);
        if (np) params.set('np', np);
        if (estado) params.set('estado', estado);
        if (fechaDesde) params.set('fechaDesde', fechaDesde);
        if (fechaHasta) params.set('fechaHasta', fechaHasta);

        return params.toString();
    }

    async function cargarRegistros(pagina) {
        paginaActual = pagina || 1;
        const tbody = document.getElementById('tablaRegistrosBody');
        tbody.innerHTML = '<tr><td colspan="14" class="admin-empty">Cargando...</td></tr>';
        ocultarAlerta('alertaListado');

        try {
            const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros?' + construirQueryFiltros(paginaActual));
            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible cargar los registros.'));

            const resultado = await response.json();

            document.getElementById('badgeCantidadRegistros').textContent = resultado.total + ' registros';
            document.getElementById('kpiPendientes').textContent = resultado.totalPendientes;
            document.getElementById('kpiRecibidos').textContent = resultado.totalRecibidos;
            document.getElementById('kpiAnulados').textContent = resultado.totalAnulados;
            document.getElementById('kpiTotal').textContent = resultado.totalPendientes + resultado.totalRecibidos + resultado.totalAnulados;

            tbody.innerHTML = resultado.items.length
                ? resultado.items.map(renderFila).join('')
                : '<tr><td colspan="14" class="admin-empty">Sin resultados.</td></tr>';

            renderPaginacion(resultado);
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="14" class="admin-empty">No fue posible cargar los registros.</td></tr>';
            mostrarAlerta('alertaListado', error.message, 'error');
        }
    }

    function renderFila(item) {
        const claseFila = item.estado === 'ANULADO' ? ' class="admin-row-anulado"' : '';
        const urlDetalle = '/modules/formularios/pilas-cartulina/recepcion/?id=' + item.id;
        const tituloDetalle = item.estado === 'PENDIENTE_RECEPCION' ? 'Recibir / ver' : 'Ver';

        let acciones =
            '<a class="admin-icon-btn" href="' + urlDetalle + '" title="' + tituloDetalle + '"><i class="bi bi-eye"></i></a>' +
            '<a class="admin-icon-btn" href="' + apiBaseUrl + 'pilas-cartulina/registros/' + item.id + '/pdf" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>' +
            '<button type="button" class="admin-icon-btn" data-historial="' + item.id + '" data-folio="' + PC.escapeHtml(item.folio) + '" title="Historial"><i class="bi bi-clock-history"></i></button>';

        if (puedeGestionar) {
            acciones +=
                '<button type="button" class="admin-icon-btn" data-editar="' + item.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                (item.estado !== 'ANULADO'
                    ? '<button type="button" class="admin-icon-btn" data-anular="' + item.id + '" data-folio="' + PC.escapeHtml(item.folio) + '" title="Anular"><i class="bi bi-x-circle"></i></button>'
                    : '') +
                '<button type="button" class="admin-icon-btn admin-icon-btn-danger" data-eliminar="' + item.id + '" data-folio="' + PC.escapeHtml(item.folio) + '" title="Eliminar"><i class="bi bi-trash"></i></button>';
        }

        return '<tr' + claseFila + '>' +
            '<td><strong>' + PC.escapeHtml(item.folio) + '</strong></td>' +
            '<td>' + PC.formatearFechaHora(item.fechaEntrega) + '</td>' +
            '<td>' + PC.escapeHtml(item.np) + '</td>' +
            '<td>' + PC.escapeHtml(item.cliente || '-') + '</td>' +
            '<td>' + PC.escapeHtml(item.cartulina || '-') + '</td>' +
            '<td>' + PC.escapeHtml(item.formato || '-') + '</td>' +
            '<td>' + PC.formatearNumero(item.cantidadSolicitada) + '</td>' +
            '<td>' + PC.formatearNumero(item.cantidadEntregada) + '</td>' +
            '<td>' + PC.escapeHtml(item.numeroPalets) + '</td>' +
            '<td>' + PC.escapeHtml(item.entregadoPor) + '</td>' +
            '<td>' + PC.escapeHtml(item.recibidoPor || '-') + '</td>' +
            '<td>' + PC.formatearFechaHora(item.fechaRecepcion) + '</td>' +
            '<td><span class="badge ' + PC.claseBadgeEstado(item.estado) + '">' + PC.escapeHtml(PC.etiquetaEstado(item.estado)) + '</span></td>' +
            '<td class="admin-table-actions"><div class="admin-row-actions">' + acciones + '</div></td>' +
        '</tr>';
    }

    function renderPaginacion(resultado) {
        const contenedor = document.getElementById('paginacionRegistros');
        const totalPaginas = Math.max(1, Math.ceil(resultado.total / resultado.porPagina));

        contenedor.innerHTML =
            '<button ' + (resultado.pagina <= 1 ? 'disabled' : '') + ' data-page="' + (resultado.pagina - 1) + '">Anterior</button>' +
            '<span>Página ' + resultado.pagina + ' de ' + totalPaginas + '</span>' +
            '<button ' + (resultado.pagina >= totalPaginas ? 'disabled' : '') + ' data-page="' + (resultado.pagina + 1) + '">Siguiente</button>';

        contenedor.querySelectorAll('button[data-page]').forEach(function (boton) {
            boton.addEventListener('click', function () { cargarRegistros(parseInt(boton.dataset.page, 10)); });
        });
    }

    function refiltrarConDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { cargarRegistros(1); }, 350);
    }

    async function obtenerTodosLosRegistrosFiltrados() {
        const params = new URLSearchParams(construirQueryFiltros(1));
        params.set('porPagina', '500');

        const items = [];
        let pagina = 1;

        // La API limita a 500 por página: se recorre hasta agotar.
        while (true) {
            params.set('pagina', String(pagina));
            const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros?' + params.toString());
            if (!response.ok) throw new Error('No fue posible obtener los registros.');
            const resultado = await response.json();
            items.push.apply(items, resultado.items);
            if (items.length >= resultado.total || !resultado.items.length) break;
            pagina++;
        }

        return items;
    }

    const COLUMNAS_EXPORT = [
        ['Folio', function (i) { return i.folio; }],
        ['Fecha entrega', function (i) { return PC.formatearFechaHora(i.fechaEntrega); }],
        ['NP', function (i) { return i.np; }],
        ['Empresa', function (i) { return i.empresa; }],
        ['OT', function (i) { return i.ordenTrabajo; }],
        ['Cliente', function (i) { return i.cliente; }],
        ['Cartulina', function (i) { return i.cartulina; }],
        ['Formato', function (i) { return i.formato; }],
        ['Proceso FPS', function (i) { return i.procesoFps; }],
        ['Pliegos FPS', function (i) { return i.pliegosFps; }],
        ['Productos', function (i) { return i.productos; }],
        ['Cantidad solicitada', function (i) { return i.cantidadSolicitada; }],
        ['Cantidad entregada', function (i) { return i.cantidadEntregada; }],
        ['N° palets', function (i) { return i.numeroPalets; }],
        ['Entregado por', function (i) { return i.entregadoPor; }],
        ['Observaciones entrega', function (i) { return i.observacionesEntrega; }],
        ['Recibido por', function (i) { return i.recibidoPor; }],
        ['Fecha recepción', function (i) { return PC.formatearFechaHora(i.fechaRecepcion); }],
        ['Observaciones recepción', function (i) { return i.observacionesRecepcion; }],
        ['Estado', function (i) { return PC.etiquetaEstado(i.estado); }],
        ['Motivo anulación', function (i) { return i.motivoAnulacion; }],
        ['Registrado por', function (i) { return i.usuarioRegistro; }],
        ['Última edición', function (i) { return i.usuarioEdicion; }]
    ];

    function fechaArchivo() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    async function exportarExcel() {
        const boton = document.getElementById('btnExportarExcel');
        boton.disabled = true;

        try {
            const items = await obtenerTodosLosRegistrosFiltrados();
            if (!items.length) {
                alert('No hay registros para exportar.');
                return;
            }

            const encabezados = COLUMNAS_EXPORT.map(function (c) { return '<th>' + PC.escapeHtml(c[0].toUpperCase()) + '</th>'; }).join('');
            const filas = items.map(function (item) {
                return '<tr>' + COLUMNAS_EXPORT.map(function (c) {
                    const v = c[1](item);
                    return '<td>' + PC.escapeHtml(v === null || v === undefined ? '' : v) + '</td>';
                }).join('') + '</tr>';
            }).join('');

            const html = '<html><head><meta charset="UTF-8"><style>' +
                'table { border-collapse: collapse; font-family: Arial; font-size: 11px; }' +
                'th { background: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #334155; padding: 8px; }' +
                'td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }' +
                'tr:nth-child(even) td { background: #f8fafc; }' +
                '.title { font-size: 20px; font-weight: bold; color: #0f172a; }' +
                '.subtitle { color: #475569; }' +
                '</style></head><body><table>' +
                '<tr><td colspan="' + COLUMNAS_EXPORT.length + '" class="title">Exportación Pilas Cartulina Dimensionado</td></tr>' +
                '<tr><td colspan="' + COLUMNAS_EXPORT.length + '" class="subtitle">Workspace Faret - ' + new Date().toLocaleString('es-CL') + '</td></tr>' +
                '<tr></tr>' +
                '<tr>' + encabezados + '</tr>' +
                filas +
                '</table></body></html>';

            const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'pilas-cartulina-' + fechaArchivo() + '.xls';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } catch (error) {
            alert(error.message);
        } finally {
            boton.disabled = false;
        }
    }

    async function imprimirRegistros() {
        const boton = document.getElementById('btnImprimirRegistros');
        boton.disabled = true;

        try {
            const items = await obtenerTodosLosRegistrosFiltrados();
            const columnas = COLUMNAS_EXPORT.filter(function (c) {
                return ['Folio', 'Fecha entrega', 'NP', 'Cliente', 'Cartulina', 'Formato', 'Cantidad solicitada', 'Cantidad entregada', 'N° palets', 'Entregado por', 'Recibido por', 'Fecha recepción', 'Estado'].includes(c[0]);
            });

            window.PlanificacionPrint.imprimir({
                tituloModulo: 'Pilas Cartulina Dimensionado',
                subtitulo: 'Listado de traspasos de pilas',
                encabezados: columnas.map(function (c) { return c[0]; }),
                filas: items.map(function (item) { return columnas.map(function (c) { return c[1](item); }); }),
                filtrosTexto: window.PlanificacionPrint.resumenFiltros([
                    ['Buscar', valor('filtroBuscar')],
                    ['NP', valor('filtroNp')],
                    ['Estado', PC.etiquetaEstado(valor('filtroEstado'))],
                    ['Entrega desde', valor('filtroFechaDesde')],
                    ['Entrega hasta', valor('filtroFechaHasta')]
                ])
            });
        } catch (error) {
            alert(error.message);
        } finally {
            boton.disabled = false;
        }
    }

    // ---------------- Historial ----------------
    async function abrirHistorial(id, folio) {
        document.getElementById('modalHistorialFolio').textContent = folio;
        const contenedor = document.getElementById('historialContenido');
        contenedor.innerHTML = '<p class="admin-empty-box">Cargando...</p>';
        abrirModal('modalHistorial');

        try {
            const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros/' + id + '/historial');
            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible cargar el historial.'));

            const historial = await response.json();
            if (!historial.length) {
                contenedor.innerHTML = '<p class="admin-empty-box">Sin movimientos.</p>';
                return;
            }

            contenedor.innerHTML = historial.map(function (h) {
                return '<div class="admin-history-item">' +
                    '<div class="admin-history-top"><strong>' + PC.escapeHtml(etiquetaAccion(h.accion)) + '</strong><span>' + PC.formatearFechaHora(h.creadoEn) + '</span></div>' +
                    '<small>Usuario: ' + PC.escapeHtml(h.usuario || '-') + '</small>' +
                    '<p>' + PC.escapeHtml(resumirDetalle(h)) + '</p>' +
                '</div>';
            }).join('');
        } catch (error) {
            contenedor.innerHTML = '<p class="admin-empty-box">' + PC.escapeHtml(error.message) + '</p>';
        }
    }

    function etiquetaAccion(accion) {
        return { CREAR: 'Entrega registrada', RECIBIR: 'Recepción firmada', EDITAR: 'Edición', ANULAR: 'Anulación' }[accion] || accion;
    }

    const CAMPOS_LEGIBLES = {
        np: 'NP', cliente: 'Cliente', cartulina: 'Cartulina', formato: 'Formato', procesoFps: 'Proceso FPS', pliegosFps: 'Pliegos FPS',
        productos: 'Productos', cantidadSolicitada: 'Cantidad solicitada', cantidadEntregada: 'Cantidad entregada', numeroPalets: 'N° palets',
        entregadoPor: 'Entregado por', recibidoPor: 'Recibido por', observacionesEntrega: 'Obs. entrega', observacionesRecepcion: 'Obs. recepción',
        fechaEntrega: 'Fecha entrega', empresa: 'Empresa', ordenTrabajo: 'OT'
    };

    function resumirDetalle(h) {
        let detalle;
        try { detalle = JSON.parse(h.detalle || '{}'); } catch (e) { return h.detalle || ''; }

        if (h.accion === 'CREAR' && detalle.despues) {
            const d = detalle.despues;
            return 'NP ' + d.np + ' · solicitada ' + PC.formatearNumero(d.cantidadSolicitada) + ' · entregada ' + PC.formatearNumero(d.cantidadEntregada) + ' · palets ' + d.numeroPalets + ' · entrega ' + d.entregadoPor + (d.recibidoPor ? ' → ' + d.recibidoPor : '');
        }

        if (h.accion === 'RECIBIR') {
            return 'Recibido por ' + (detalle.recibidoPor || '-') + (detalle.observacionesRecepcion ? ' · ' + detalle.observacionesRecepcion : '');
        }

        if (h.accion === 'ANULAR') {
            return 'Motivo: ' + (detalle.motivo || '-') + ' (estado anterior: ' + PC.etiquetaEstado(detalle.estadoAnterior) + ')';
        }

        if (h.accion === 'EDITAR' && detalle.antes && detalle.despues) {
            const cambios = [];
            Object.keys(CAMPOS_LEGIBLES).forEach(function (campo) {
                const a = detalle.antes[campo];
                const b = detalle.despues[campo];
                if (String(a === null || a === undefined ? '' : a) !== String(b === null || b === undefined ? '' : b)) {
                    cambios.push(CAMPOS_LEGIBLES[campo] + ': "' + (a === null || a === undefined ? '' : a) + '" → "' + (b === null || b === undefined ? '' : b) + '"');
                }
            });
            return cambios.length ? cambios.join(' · ') : 'Sin cambios en los campos editables.';
        }

        return h.detalle || '';
    }

    // ---------------- Editar / Anular / Eliminar (solo gestionar) ----------------
    async function abrirModalEditar(id) {
        const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el registro.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarId').value = item.id;
        document.getElementById('modalEditarFolio').textContent = item.folio;
        document.getElementById('editarNp').value = item.np || '';
        document.getElementById('editarFechaEntrega').value = item.fechaEntrega ? item.fechaEntrega.substring(0, 16) : '';
        document.getElementById('editarCliente').value = item.cliente || '';
        document.getElementById('editarFormato').value = item.formato || '';
        document.getElementById('editarCartulina').value = item.cartulina || '';
        document.getElementById('editarCantidadSolicitada').value = item.cantidadSolicitada;
        document.getElementById('editarCantidadEntregada').value = item.cantidadEntregada;
        document.getElementById('editarNumeroPalets').value = item.numeroPalets;
        document.getElementById('editarEntregadoPor').value = item.entregadoPor || '';
        document.getElementById('editarRecibidoPor').value = item.recibidoPor || '';
        document.getElementById('editarProductos').value = item.productos || '';
        document.getElementById('editarObservacionesEntrega').value = item.observacionesEntrega || '';
        document.getElementById('editarObservacionesRecepcion').value = item.observacionesRecepcion || '';

        // Campos FPS que no se muestran pero deben conservarse.
        document.getElementById('formEditar').dataset.empresa = item.empresa || '';
        document.getElementById('formEditar').dataset.ordenTrabajo = item.ordenTrabajo || '';
        document.getElementById('formEditar').dataset.procesoFps = item.procesoFps || '';
        document.getElementById('formEditar').dataset.pliegosFps = item.pliegosFps === null || item.pliegosFps === undefined ? '' : item.pliegosFps;

        ocultarAlerta('alertaEditar');
        abrirModal('modalEditar');
    }

    async function guardarEdicion(evento) {
        evento.preventDefault();
        ocultarAlerta('alertaEditar');

        const form = document.getElementById('formEditar');
        const id = document.getElementById('editarId').value;
        const boton = document.getElementById('btnGuardarEdicion');
        boton.disabled = true;

        try {
            const payload = {
                usuario: usuarioActual,
                np: valor('editarNp'),
                empresa: form.dataset.empresa || null,
                ordenTrabajo: form.dataset.ordenTrabajo ? Number(form.dataset.ordenTrabajo) : null,
                cliente: valor('editarCliente'),
                cartulina: valor('editarCartulina'),
                formato: valor('editarFormato'),
                procesoFps: form.dataset.procesoFps || null,
                pliegosFps: form.dataset.pliegosFps !== '' ? Number(form.dataset.pliegosFps) : null,
                productos: valor('editarProductos'),
                cantidadSolicitada: Number(valor('editarCantidadSolicitada')),
                cantidadEntregada: Number(valor('editarCantidadEntregada') || 0),
                numeroPalets: Number(valor('editarNumeroPalets') || 0),
                entregadoPor: valor('editarEntregadoPor'),
                recibidoPor: valor('editarRecibidoPor'),
                observacionesEntrega: valor('editarObservacionesEntrega'),
                observacionesRecepcion: valor('editarObservacionesRecepcion'),
                fechaEntrega: valor('editarFechaEntrega')
            };

            const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible guardar los cambios.'));

            cerrarModal('modalEditar');
            cargarRegistros(paginaActual);
        } catch (error) {
            mostrarAlerta('alertaEditar', error.message, 'error');
        } finally {
            boton.disabled = false;
        }
    }

    function abrirModalAnular(id, folio) {
        document.getElementById('anularId').value = id;
        document.getElementById('modalAnularFolio').textContent = folio;
        document.getElementById('anularMotivo').value = '';
        ocultarAlerta('alertaAnular');
        abrirModal('modalAnular');
    }

    async function confirmarAnular(evento) {
        evento.preventDefault();
        ocultarAlerta('alertaAnular');

        const id = document.getElementById('anularId').value;
        const boton = document.getElementById('btnConfirmarAnular');
        boton.disabled = true;

        try {
            const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros/' + id + '/anular', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ motivo: valor('anularMotivo'), usuario: usuarioActual })
            });

            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible anular el traspaso.'));

            cerrarModal('modalAnular');
            cargarRegistros(paginaActual);
        } catch (error) {
            mostrarAlerta('alertaAnular', error.message, 'error');
        } finally {
            boton.disabled = false;
        }
    }

    async function eliminarRegistro(id, folio, boton) {
        if (!confirm('¿Eliminar definitivamente el traspaso ' + (folio || '') + '? Se borra también su historial y firma. Esta acción no se puede deshacer.')) {
            return;
        }

        boton.disabled = true;
        try {
            const response = await fetch(apiBaseUrl + 'pilas-cartulina/registros/' + id, {
                method: 'DELETE',
                headers: { 'X-Admin-Key': adminDeleteKey }
            });

            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible eliminar el traspaso.'));

            cargarRegistros(paginaActual);
        } catch (error) {
            alert(error.message);
            boton.disabled = false;
        }
    }

    // ---------------- Init ----------------
    document.addEventListener('DOMContentLoaded', function () {
        if (window.pilasEstadoInicial && ESTADOS_VALIDOS.includes(window.pilasEstadoInicial)) {
            document.getElementById('filtroEstado').value = window.pilasEstadoInicial;
        }

        document.getElementById('filtroBuscar').addEventListener('input', refiltrarConDebounce);
        document.getElementById('filtroNp').addEventListener('input', refiltrarConDebounce);
        ['filtroEstado', 'filtroFechaDesde', 'filtroFechaHasta'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () { cargarRegistros(1); });
        });

        document.getElementById('btnLimpiarFiltros').addEventListener('click', function () {
            ['filtroBuscar', 'filtroNp', 'filtroEstado', 'filtroFechaDesde', 'filtroFechaHasta'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            cargarRegistros(1);
        });

        document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);
        document.getElementById('btnImprimirRegistros').addEventListener('click', imprimirRegistros);
        document.getElementById('btnCerrarModalHistorial').addEventListener('click', function () { cerrarModal('modalHistorial'); });

        if (puedeGestionar) {
            document.getElementById('btnCerrarModalEditar').addEventListener('click', function () { cerrarModal('modalEditar'); });
            document.getElementById('formEditar').addEventListener('submit', guardarEdicion);
            document.getElementById('btnCerrarModalAnular').addEventListener('click', function () { cerrarModal('modalAnular'); });
            document.getElementById('formAnular').addEventListener('submit', confirmarAnular);
        }

        document.getElementById('tablaRegistrosBody').addEventListener('click', async function (evento) {
            const btnHistorial = evento.target.closest('[data-historial]');
            if (btnHistorial) {
                await abrirHistorial(parseInt(btnHistorial.dataset.historial, 10), btnHistorial.dataset.folio);
                return;
            }

            if (!puedeGestionar) return;

            const btnEditar = evento.target.closest('[data-editar]');
            if (btnEditar) {
                await abrirModalEditar(parseInt(btnEditar.dataset.editar, 10));
                return;
            }

            const btnAnular = evento.target.closest('[data-anular]');
            if (btnAnular) {
                abrirModalAnular(parseInt(btnAnular.dataset.anular, 10), btnAnular.dataset.folio);
                return;
            }

            const btnEliminar = evento.target.closest('[data-eliminar]');
            if (btnEliminar) {
                await eliminarRegistro(parseInt(btnEliminar.dataset.eliminar, 10), btnEliminar.dataset.folio, btnEliminar);
            }
        });

        cargarRegistros(1);
    });
})();
