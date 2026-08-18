(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const usuarioActual = window.currentUserNombre || '';

    let paginaActual = 1;
    const porPagina = 50;
    let debounceTimer = null;

    const CAMPOS = [
        'IngresoLayout', 'NombreOperador', 'Np', 'Cliente', 'Rubro', 'Solicitud', 'FechaEntrega',
        'Formato', 'Tiraje', 'Status', 'NpAntigua', 'NpOrigen', 'CodigoMolde',
        'Sustrato', 'Gramaje', 'Emplacado', 'Laminado',
        'Troquelado', 'Pinza', 'Separacion', 'Extras',
        'Comentarios',
        'LayoutEstado', 'MoldeEstado'
    ];
    const CAMPOS_FECHA = ['IngresoLayout', 'FechaEntrega'];

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

    function llenarDatalist(idDatalist, valores) {
        const datalist = document.getElementById(idDatalist);
        if (!datalist) return;
        datalist.innerHTML = valores.map(function (v) {
            return '<option value="' + escaparHtml(v) + '"></option>';
        }).join('');
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

    async function cargarCatalogos() {
        const response = await fetch(apiBaseUrl + 'com/catalogos');
        if (!response.ok) return;
        const catalogos = await response.json();

        llenarDatalist('listaComClientes', catalogos.clientes || []);
        llenarDatalist('listaComOperadores', catalogos.operadores || []);
        llenarDatalist('listaComCodigosMolde', catalogos.codigosMolde || []);
    }

    function construirQueryFiltros(pagina) {
        const params = new URLSearchParams();
        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = document.getElementById('filtroComBuscar').value.trim();
        const cliente = document.getElementById('filtroComCliente').value.trim();
        const np = document.getElementById('filtroComNp').value.trim();
        const operador = document.getElementById('filtroComOperador').value.trim();
        const codigoMolde = document.getElementById('filtroComCodigoMolde').value.trim();
        const status = document.getElementById('filtroComStatus').value;
        const fechaDesde = document.getElementById('filtroComFechaDesde').value;
        const fechaHasta = document.getElementById('filtroComFechaHasta').value;

        if (buscar) params.set('buscar', buscar);
        if (cliente) params.set('cliente', cliente);
        if (np) params.set('np', np);
        if (operador) params.set('nombreOperador', operador);
        if (codigoMolde) params.set('codigoMolde', codigoMolde);
        if (status) params.set('status', status);
        if (fechaDesde) params.set('fechaDesde', fechaDesde);
        if (fechaHasta) params.set('fechaHasta', fechaHasta);

        return params.toString();
    }

    async function cargarRegistros(pagina) {
        paginaActual = pagina || 1;
        const tbody = document.getElementById('tablaRegistrosBody');
        tbody.innerHTML = '<tr><td colspan="10" class="admin-empty">Cargando...</td></tr>';

        const query = construirQueryFiltros(paginaActual);
        const response = await fetch(apiBaseUrl + 'com/registros?' + query);

        if (!response.ok) {
            tbody.innerHTML = '<tr><td colspan="10" class="admin-empty">No fue posible cargar los registros.</td></tr>';
            return;
        }

        const resultado = await response.json();
        document.getElementById('badgeCantidadRegistros').textContent = resultado.total + ' registros';

        if (!resultado.items.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="admin-empty">Sin resultados.</td></tr>';
        } else {
            tbody.innerHTML = resultado.items.map(renderFila).join('');
        }

        renderPaginacion(resultado);
    }

    function renderFila(item) {
        return '<tr>' +
            '<td>' + escaparHtml(item.np) + '</td>' +
            '<td>' + escaparHtml(item.cliente) + '</td>' +
            '<td>' + escaparHtml(item.nombreOperador) + '</td>' +
            '<td>' + escaparHtml(item.status) + '</td>' +
            '<td>' + formatearFecha(item.fechaEntrega) + '</td>' +
            '<td>' + escaparHtml(item.codigoMolde) + '</td>' +
            '<td>' + escaparHtml(item.troquelado) + '</td>' +
            '<td>' + escaparHtml(item.layoutEstado) + '</td>' +
            '<td>' + escaparHtml(item.moldeEstado) + '</td>' +
            '<td class="admin-table-actions">' +
                '<div class="admin-row-actions">' +
                    '<button type="button" class="admin-icon-btn" data-editar="' + item.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="admin-icon-btn" data-historial="' + item.id + '" data-historial-codigo="' + escaparHtml(item.np) + '" title="Historial"><i class="bi bi-clock-history"></i></button>' +
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

    function inicializarFiltros() {
        ['filtroComBuscar', 'filtroComCliente', 'filtroComNp', 'filtroComOperador', 'filtroComCodigoMolde'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', refiltrarConDebounce);
        });
        ['filtroComStatus', 'filtroComFechaDesde', 'filtroComFechaHasta'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () { cargarRegistros(1); });
        });

        document.getElementById('btnLimpiarFiltrosRegistro').addEventListener('click', function () {
            ['filtroComBuscar', 'filtroComCliente', 'filtroComNp', 'filtroComOperador', 'filtroComCodigoMolde', 'filtroComStatus', 'filtroComFechaDesde', 'filtroComFechaHasta'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            cargarRegistros(1);
        });
    }

    function leerFormulario(prefijo) {
        const payload = { usuario: usuarioActual };
        CAMPOS.forEach(function (campo) {
            const clave = campo.charAt(0).toLowerCase() + campo.slice(1);
            payload[clave] = valor(prefijo + campo);
        });
        return payload;
    }

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() || null : null;
    }

    function inicializarFormularioCrear() {
        document.getElementById('formRegistro').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaRegistro');

            const boton = document.getElementById('btnGuardarRegistro');
            boton.disabled = true;

            try {
                const payload = leerFormulario('com');

                const response = await fetch(apiBaseUrl + 'com/registros', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible crear el registro.');

                const creado = JSON.parse(texto);
                mostrarAlerta('alertaRegistro', 'Registro NP ' + (creado.np || creado.id) + ' creado correctamente.', 'success');
                document.getElementById('formRegistro').reset();
                cargarRegistros(1);
            } catch (error) {
                mostrarAlerta('alertaRegistro', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    function inicializarEdicion() {
        document.getElementById('tablaRegistrosBody').addEventListener('click', async function (evento) {
            const botonEditar = evento.target.closest('[data-editar]');
            if (botonEditar) {
                await abrirModalEditar(parseInt(botonEditar.dataset.editar, 10));
                return;
            }

            const botonHistorial = evento.target.closest('[data-historial]');
            if (botonHistorial) {
                await abrirHistorial(botonHistorial.dataset.historial, botonHistorial.dataset.historialCodigo);
            }
        });

        document.getElementById('btnCerrarModalEditarRegistro').addEventListener('click', function () {
            document.getElementById('modalEditarRegistro').classList.add('hidden');
        });

        document.getElementById('formEditarRegistro').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaEditarRegistro');

            const id = document.getElementById('editarComId').value;
            const boton = document.getElementById('btnGuardarEdicionRegistro');
            boton.disabled = true;

            try {
                const payload = leerFormulario('editarCom');

                const response = await fetch(apiBaseUrl + 'com/registros/' + id, {
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
        const response = await fetch(apiBaseUrl + 'com/registros/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el registro.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarComId').value = item.id;
        document.getElementById('modalEditarRegistroCodigo').textContent = item.np || ('#' + item.id);

        CAMPOS.forEach(function (campo) {
            const clave = campo.charAt(0).toLowerCase() + campo.slice(1);
            const el = document.getElementById('editarCom' + campo);
            if (!el) return;
            if (CAMPOS_FECHA.indexOf(campo) !== -1) {
                el.value = item[clave] ? item[clave].substring(0, 10) : '';
            } else {
                el.value = item[clave] || '';
            }
        });

        ocultarAlerta('alertaEditarRegistro');
        document.getElementById('modalEditarRegistro').classList.remove('hidden');
    }

    async function abrirHistorial(id, codigo) {
        document.getElementById('modalHistorialCodigo').textContent = codigo || '';
        const lista = document.getElementById('historialLista');
        lista.innerHTML = '<p>Cargando...</p>';
        document.getElementById('modalHistorial').classList.remove('hidden');

        const response = await fetch(apiBaseUrl + 'com/registros/' + id + '/historial');
        if (!response.ok) {
            lista.innerHTML = '<p>No fue posible cargar el historial.</p>';
            return;
        }

        const historial = await response.json();
        if (!historial.length) {
            lista.innerHTML = '<div class="admin-empty-box">Sin cambios registrados.</div>';
            return;
        }

        lista.innerHTML = historial.map(function (h) {
            const fecha = new Date(h.fechaHora).toLocaleString('es-CL');
            let diff = '';
            if (h.accion === 'EDITAR' && h.valoresAnteriores && h.valoresNuevos) {
                diff = renderDiff(JSON.parse(h.valoresAnteriores), JSON.parse(h.valoresNuevos));
            }
            return '<div class="admin-history-item">' +
                '<div class="admin-history-top">' +
                    '<strong>' + escaparHtml(h.accion) + '</strong>' +
                    '<span>' + fecha + '</span>' +
                '</div>' +
                '<p>Usuario: ' + escaparHtml(h.usuario || '-') + '</p>' +
                diff +
            '</div>';
        }).join('');
    }

    function renderDiff(anterior, nuevo) {
        const cambios = Object.keys(nuevo).filter(function (clave) {
            return String(anterior[clave] || '') !== String(nuevo[clave] || '');
        });

        if (!cambios.length) return '';

        return '<div class="admin-history-note">' + cambios.map(function (clave) {
            return '<div>' + escaparHtml(clave) + ': "' + escaparHtml(anterior[clave]) + '" &rarr; "' + escaparHtml(nuevo[clave]) + '"</div>';
        }).join('') + '</div>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        cargarCatalogos();
        inicializarFiltros();
        inicializarFormularioCrear();
        inicializarEdicion();
        cargarRegistros(1);

        document.getElementById('btnCerrarModalHistorial').addEventListener('click', function () {
            document.getElementById('modalHistorial').classList.add('hidden');
        });
    });
})();
