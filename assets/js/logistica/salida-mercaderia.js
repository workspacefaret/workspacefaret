(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const usuarioActual = window.currentUserNombre || '';
    const puedeEliminar = window.puedeEliminarSalidaMercaderia === true;
    const adminDeleteKey = window.API_ADMIN_DELETE_KEY || '';

    let paginaActual = 1;
    const porPagina = 50;
    let debounceTimer = null;

    const UNIDADES_LABEL = {
        UND: 'Unidad', CAJA: 'Caja', PALLET: 'Pallet', KG: 'Kilogramo',
        LT: 'Litro', ROLLO: 'Rollo', SACO: 'Saco', BULTO: 'Bulto', M: 'Metro'
    };

    const EMPRESAS_LABEL = {
        FARET: 'Faret', INNPACK: 'Innpack', PHARPACK: 'Pharpack', LENIAN: 'Lenian'
    };

    function etiquetaUnidad(unidad) {
        return UNIDADES_LABEL[unidad] || unidad || '-';
    }

    function etiquetaEmpresa(empresa) {
        return EMPRESAS_LABEL[empresa] || empresa || '-';
    }

    function mostrarAlerta(elId, mensaje, tipo) {
        const el = document.getElementById(elId);
        el.textContent = mensaje;
        el.className = 'admin-alert admin-alert-' + tipo;
        el.classList.remove('hidden');
        if (tipo === 'success') {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function ocultarAlerta(elId) {
        document.getElementById(elId).classList.add('hidden');
    }

    function escaparHtml(valor) {
        const div = document.createElement('div');
        div.textContent = valor === null || valor === undefined ? '' : String(valor);
        return div.innerHTML;
    }

    function formatearFecha(valor) {
        if (!valor) return '-';
        return valor.substring(0, 10).split('-').reverse().join('-');
    }

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() || null : null;
    }

    function construirQueryFiltros(pagina) {
        const params = new URLSearchParams();
        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = valor('filtroSmBuscar');
        const empresa = valor('filtroSmEmpresa');
        const unidadMedida = valor('filtroSmUnidadMedida');
        const fechaDesde = valor('filtroSmFechaDesde');
        const fechaHasta = valor('filtroSmFechaHasta');
        const incluirAnulados = document.getElementById('filtroSmIncluirAnulados').value;

        if (buscar) params.set('buscar', buscar);
        if (empresa) params.set('empresa', empresa);
        if (unidadMedida) params.set('unidadMedida', unidadMedida);
        if (fechaDesde) params.set('fechaDesde', fechaDesde);
        if (fechaHasta) params.set('fechaHasta', fechaHasta);
        params.set('incluirAnulados', incluirAnulados);

        return params.toString();
    }

    async function cargarRegistros(pagina) {
        paginaActual = pagina || 1;
        const tbody = document.getElementById('tablaRegistrosBody');
        tbody.innerHTML = '<tr><td colspan="12" class="admin-empty">Cargando...</td></tr>';

        const query = construirQueryFiltros(paginaActual);
        const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros?' + query);

        if (!response.ok) {
            tbody.innerHTML = '<tr><td colspan="12" class="admin-empty">No fue posible cargar los registros.</td></tr>';
            return;
        }

        const resultado = await response.json();
        document.getElementById('badgeCantidadRegistros').textContent = resultado.total + ' registros';

        if (!resultado.items.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="admin-empty">Sin resultados.</td></tr>';
        } else {
            tbody.innerHTML = resultado.items.map(renderFila).join('');
        }

        renderPaginacion(resultado);
    }

    function renderFila(item) {
        const claseFila = item.anulado ? ' class="admin-row-anulado"' : '';

        const botonEliminar = puedeEliminar
            ? '<button type="button" class="admin-icon-btn" data-eliminar="' + item.id + '" data-eliminar-folio="' + escaparHtml(item.folio) + '" title="Eliminar"><i class="bi bi-trash"></i></button>'
            : '';

        const tituloAnular = item.anulado ? 'Reactivar' : 'Anular';
        const iconoAnular = item.anulado ? 'bi-arrow-counterclockwise' : 'bi-x-circle';

        return '<tr' + claseFila + '>' +
            '<td>' + escaparHtml(item.folio) + '</td>' +
            '<td>' + escaparHtml(etiquetaEmpresa(item.empresa)) + '</td>' +
            '<td>' + formatearFecha(item.fecha) + '</td>' +
            '<td>' + escaparHtml(item.tipoMercaderia) + '</td>' +
            '<td>' + escaparHtml(item.cantidad) + '</td>' +
            '<td>' + escaparHtml(etiquetaUnidad(item.unidadMedida)) + '</td>' +
            '<td>' + escaparHtml(item.retiradoPor) + '</td>' +
            '<td>' + escaparHtml(item.rutRetira) + '</td>' +
            '<td>' + escaparHtml(item.patente) + '</td>' +
            '<td>' + escaparHtml(item.autorizadoPor) + '</td>' +
            '<td>' + escaparHtml(item.observaciones) + '</td>' +
            '<td class="admin-table-actions">' +
                '<div class="admin-row-actions">' +
                    '<a class="admin-icon-btn" href="' + apiBaseUrl + 'salida-mercaderia/registros/' + item.id + '/pdf" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>' +
                    '<button type="button" class="admin-icon-btn" data-editar="' + item.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="admin-icon-btn" data-toggle-anulado="' + item.id + '" title="' + tituloAnular + '"><i class="bi ' + iconoAnular + '"></i></button>' +
                    botonEliminar +
                '</div>' +
            '</td>' +
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
            boton.addEventListener('click', function () {
                cargarRegistros(parseInt(boton.dataset.page, 10));
            });
        });
    }

    function refiltrarConDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { cargarRegistros(1); }, 350);
    }

    async function imprimirRegistros() {
        const boton = document.getElementById('btnImprimirRegistros');
        boton.disabled = true;

        try {
            const items = await obtenerTodosLosRegistrosFiltrados();

            const encabezados = ['Folio', 'Empresa', 'Fecha', 'Tipo de mercadería', 'Cantidad', 'Unidad', 'Retirado por', 'RUT', 'Patente', 'Autorizado por', 'Observaciones', 'Registrado por'];

            const filas = items.map(function (item) {
                return [
                    item.folio, etiquetaEmpresa(item.empresa), formatearFecha(item.fecha), item.tipoMercaderia, item.cantidad,
                    etiquetaUnidad(item.unidadMedida), item.retiradoPor, item.rutRetira, item.patente,
                    item.autorizadoPor, item.observaciones, item.usuarioRegistro
                ];
            });

            window.PlanificacionPrint.imprimir({
                tituloModulo: 'Salida de Mercadería',
                subtitulo: 'Listado de salidas de mercadería registradas',
                encabezados: encabezados,
                filas: filas,
                filtrosTexto: window.PlanificacionPrint.resumenFiltros([
                    ['Buscar', valor('filtroSmBuscar')],
                    ['Empresa', etiquetaEmpresa(valor('filtroSmEmpresa'))],
                    ['Unidad', etiquetaUnidad(valor('filtroSmUnidadMedida'))],
                    ['Fecha desde', valor('filtroSmFechaDesde')],
                    ['Fecha hasta', valor('filtroSmFechaHasta')]
                ])
            });
        } catch (error) {
            alert(error.message);
        } finally {
            boton.disabled = false;
        }
    }

    async function obtenerTodosLosRegistrosFiltrados() {
        const params = new URLSearchParams(construirQueryFiltros(1));
        params.set('porPagina', '100000');

        const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros?' + params.toString());
        if (!response.ok) throw new Error('No fue posible obtener los registros.');
        const resultado = await response.json();
        return resultado.items;
    }

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

            const filas = items.map(function (item) {
                return '<tr>' +
                    '<td>' + escaparHtml(item.folio) + '</td>' +
                    '<td>' + escaparHtml(etiquetaEmpresa(item.empresa)) + '</td>' +
                    '<td>' + formatearFecha(item.fecha) + '</td>' +
                    '<td>' + escaparHtml(item.tipoMercaderia) + '</td>' +
                    '<td>' + escaparHtml(item.cantidad) + '</td>' +
                    '<td>' + escaparHtml(etiquetaUnidad(item.unidadMedida)) + '</td>' +
                    '<td>' + escaparHtml(item.retiradoPor) + '</td>' +
                    '<td>' + escaparHtml(item.rutRetira) + '</td>' +
                    '<td>' + escaparHtml(item.patente) + '</td>' +
                    '<td>' + escaparHtml(item.autorizadoPor) + '</td>' +
                    '<td>' + escaparHtml(item.observaciones) + '</td>' +
                    '<td>' + escaparHtml(item.usuarioRegistro) + '</td>' +
                    '<td>' + (item.anulado ? 'ANULADO' : 'Activo') + '</td>' +
                '</tr>';
            }).join('');

            const html = '<html><head><meta charset="UTF-8"><style>' +
                'table { border-collapse: collapse; font-family: Arial; font-size: 11px; }' +
                'th { background: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #334155; padding: 8px; }' +
                'td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }' +
                'tr:nth-child(even) td { background: #f8fafc; }' +
                '.title { font-size: 20px; font-weight: bold; color: #0f172a; }' +
                '.subtitle { color: #475569; }' +
                '</style></head><body><table>' +
                '<tr><td colspan="13" class="title">Exportación Salida de Mercadería</td></tr>' +
                '<tr><td colspan="13" class="subtitle">Workspace Faret - ' + new Date().toLocaleString('es-CL') + '</td></tr>' +
                '<tr></tr>' +
                '<tr>' +
                    '<th>FOLIO</th><th>EMPRESA</th><th>FECHA</th><th>TIPO DE MERCADERÍA</th><th>CANTIDAD</th><th>UNIDAD</th>' +
                    '<th>RETIRADO POR</th><th>RUT</th><th>PATENTE</th><th>AUTORIZADO POR</th><th>OBSERVACIONES</th><th>REGISTRADO POR</th><th>ESTADO</th>' +
                '</tr>' +
                filas +
                '</table></body></html>';

            const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = 'salida-mercaderia-' + fechaArchivo() + '.xls';
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

    function inicializarFiltros() {
        document.getElementById('filtroSmBuscar').addEventListener('input', refiltrarConDebounce);
        ['filtroSmEmpresa', 'filtroSmUnidadMedida', 'filtroSmFechaDesde', 'filtroSmFechaHasta', 'filtroSmIncluirAnulados'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () { cargarRegistros(1); });
        });

        document.getElementById('btnLimpiarFiltrosRegistro').addEventListener('click', function () {
            ['filtroSmBuscar', 'filtroSmEmpresa', 'filtroSmUnidadMedida', 'filtroSmFechaDesde', 'filtroSmFechaHasta'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            document.getElementById('filtroSmIncluirAnulados').value = 'false';
            cargarRegistros(1);
        });
    }

    function leerFormularioCrear() {
        return {
            usuario: usuarioActual,
            empresa: valor('smEmpresa'),
            fecha: valor('smFecha'),
            tipoMercaderia: valor('smTipoMercaderia'),
            cantidad: Number(valor('smCantidad')),
            unidadMedida: valor('smUnidadMedida'),
            retiradoPor: valor('smRetiradoPor'),
            rutRetira: valor('smRutRetira'),
            patente: valor('smPatente'),
            autorizadoPor: valor('smAutorizadoPor'),
            observaciones: valor('smObservaciones')
        };
    }

    function leerFormularioEditar() {
        return {
            usuario: usuarioActual,
            empresa: valor('editarSmEmpresa'),
            fecha: valor('editarSmFecha'),
            tipoMercaderia: valor('editarSmTipoMercaderia'),
            cantidad: Number(valor('editarSmCantidad')),
            unidadMedida: valor('editarSmUnidadMedida'),
            retiradoPor: valor('editarSmRetiradoPor'),
            rutRetira: valor('editarSmRutRetira'),
            patente: valor('editarSmPatente'),
            autorizadoPor: valor('editarSmAutorizadoPor'),
            observaciones: valor('editarSmObservaciones'),
            anulado: document.getElementById('editarSmAnulado').value === '1'
        };
    }

    function inicializarFormularioCrear() {
        document.getElementById('formRegistro').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaRegistro');

            const boton = document.getElementById('btnGuardarRegistro');
            boton.disabled = true;

            try {
                const payload = leerFormularioCrear();

                const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible crear el registro.');

                const creado = JSON.parse(texto);
                mostrarAlerta('alertaRegistro', 'Registro ' + creado.folio + ' creado correctamente.', 'success');
                document.getElementById('formRegistro').reset();
                cargarRegistros(1);
            } catch (error) {
                mostrarAlerta('alertaRegistro', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    function inicializarTablaAcciones() {
        document.getElementById('tablaRegistrosBody').addEventListener('click', async function (evento) {
            const botonEditar = evento.target.closest('[data-editar]');
            if (botonEditar) {
                await abrirModalEditar(parseInt(botonEditar.dataset.editar, 10));
                return;
            }

            const botonToggle = evento.target.closest('[data-toggle-anulado]');
            if (botonToggle) {
                await toggleAnulado(parseInt(botonToggle.dataset.toggleAnulado, 10), botonToggle);
                return;
            }

            const botonEliminar = evento.target.closest('[data-eliminar]');
            if (botonEliminar) {
                await eliminarRegistro(parseInt(botonEliminar.dataset.eliminar, 10), botonEliminar.dataset.eliminarFolio, botonEliminar);
            }
        });

        document.getElementById('btnCerrarModalEditarRegistro').addEventListener('click', function () {
            document.getElementById('modalEditarRegistro').classList.add('hidden');
        });

        document.getElementById('formEditarRegistro').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaEditarRegistro');

            const id = document.getElementById('editarSmId').value;
            const boton = document.getElementById('btnGuardarEdicionRegistro');
            boton.disabled = true;

            try {
                const payload = leerFormularioEditar();

                const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible guardar los cambios.');

                document.getElementById('modalEditarRegistro').classList.add('hidden');
                cargarRegistros(paginaActual);
            } catch (error) {
                mostrarAlerta('alertaEditarRegistro', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    async function abrirModalEditar(id) {
        const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el registro.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarSmId').value = item.id;
        document.getElementById('modalEditarRegistroFolio').textContent = item.folio;
        document.getElementById('editarSmEmpresa').value = item.empresa || '';
        document.getElementById('editarSmFecha').value = item.fecha ? item.fecha.substring(0, 10) : '';
        document.getElementById('editarSmTipoMercaderia').value = item.tipoMercaderia || '';
        document.getElementById('editarSmCantidad').value = item.cantidad;
        document.getElementById('editarSmUnidadMedida').value = item.unidadMedida || '';
        document.getElementById('editarSmRetiradoPor').value = item.retiradoPor || '';
        document.getElementById('editarSmRutRetira').value = item.rutRetira || '';
        document.getElementById('editarSmPatente').value = item.patente || '';
        document.getElementById('editarSmAutorizadoPor').value = item.autorizadoPor || '';
        document.getElementById('editarSmObservaciones').value = item.observaciones || '';
        document.getElementById('editarSmAnulado').value = item.anulado ? '1' : '0';

        ocultarAlerta('alertaEditarRegistro');
        document.getElementById('modalEditarRegistro').classList.remove('hidden');
    }

    async function toggleAnulado(id, boton) {
        boton.disabled = true;
        try {
            const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros/' + id);
            if (!response.ok) throw new Error('No fue posible cargar el registro.');
            const item = await response.json();

            const payload = {
                usuario: usuarioActual,
                empresa: item.empresa,
                fecha: item.fecha,
                tipoMercaderia: item.tipoMercaderia,
                cantidad: item.cantidad,
                unidadMedida: item.unidadMedida,
                retiradoPor: item.retiradoPor,
                rutRetira: item.rutRetira,
                patente: item.patente,
                autorizadoPor: item.autorizadoPor,
                observaciones: item.observaciones,
                anulado: !item.anulado
            };

            const putResponse = await fetch(apiBaseUrl + 'salida-mercaderia/registros/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const texto = await putResponse.text();
            if (!putResponse.ok) throw new Error(texto || 'No fue posible cambiar el estado.');

            cargarRegistros(paginaActual);
        } catch (error) {
            alert(error.message);
            boton.disabled = false;
        }
    }

    async function eliminarRegistro(id, folio, boton) {
        if (!confirm('¿Eliminar definitivamente el registro ' + (folio || '') + '? Esta acción no se puede deshacer.')) {
            return;
        }

        boton.disabled = true;
        try {
            const response = await fetch(apiBaseUrl + 'salida-mercaderia/registros/' + id, {
                method: 'DELETE',
                headers: { 'X-Admin-Key': adminDeleteKey }
            });

            if (!response.ok) {
                const mensaje = await response.text().catch(function () { return ''; });
                throw new Error(mensaje || 'No fue posible eliminar el registro.');
            }

            cargarRegistros(paginaActual);
        } catch (error) {
            alert(error.message);
            boton.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        inicializarFiltros();
        inicializarFormularioCrear();
        inicializarTablaAcciones();
        cargarRegistros(1);

        document.getElementById('btnImprimirRegistros').addEventListener('click', imprimirRegistros);
        document.getElementById('btnExportarExcel').addEventListener('click', exportarExcel);
    });
})();
