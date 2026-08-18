(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const usuarioActual = window.currentUserNombre || '';

    let paginaActual = 1;
    const porPagina = 50;
    let debounceTimer = null;

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

    let proximoNpSugerido = null;

    function sugerirProximoNp() {
        const campo = document.getElementById('prmNp');
        if (proximoNpSugerido === null || !campo || campo.value.trim()) return;
        campo.value = String(proximoNpSugerido);
    }

    function actualizarSugerenciaNpTrasCrear(npCreado) {
        if (!/^\d+$/.test(String(npCreado || ''))) return;
        const npNumerico = parseInt(npCreado, 10);
        if (proximoNpSugerido === null || npNumerico >= proximoNpSugerido) {
            proximoNpSugerido = npNumerico + 1;
        }
    }

    async function cargarCatalogos() {
        const response = await fetch(apiBaseUrl + 'prm/catalogos');
        if (!response.ok) return;
        const catalogos = await response.json();

        llenarDatalist('listaPrmClientes', catalogos.clientes || []);
        llenarDatalist('listaPrmCodigosMolde', catalogos.codigosMolde || []);

        if (catalogos.ultimoNp !== null && catalogos.ultimoNp !== undefined) {
            proximoNpSugerido = catalogos.ultimoNp + 1;
            sugerirProximoNp();
        }
    }

    function construirQueryFiltros(pagina) {
        const params = new URLSearchParams();
        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = document.getElementById('filtroPrmBuscar').value.trim();
        const cliente = document.getElementById('filtroPrmCliente').value.trim();
        const np = document.getElementById('filtroPrmNp').value.trim();
        const codigoMolde = document.getElementById('filtroPrmCodigoMolde').value.trim();
        const tipo = document.getElementById('filtroPrmTipo').value;
        const estado = document.getElementById('filtroPrmEstado').value;
        const fechaDesde = document.getElementById('filtroPrmFechaDesde').value;
        const fechaHasta = document.getElementById('filtroPrmFechaHasta').value;

        if (buscar) params.set('buscar', buscar);
        if (cliente) params.set('cliente', cliente);
        if (np) params.set('np', np);
        if (codigoMolde) params.set('codigoMolde', codigoMolde);
        if (tipo) params.set('tipo', tipo);
        if (estado) params.set('estado', estado);
        if (fechaDesde) params.set('fechaDesde', fechaDesde);
        if (fechaHasta) params.set('fechaHasta', fechaHasta);

        return params.toString();
    }

    async function cargarRegistros(pagina) {
        paginaActual = pagina || 1;
        const tbody = document.getElementById('tablaRegistrosBody');
        tbody.innerHTML = '<tr><td colspan="15" class="admin-empty">Cargando...</td></tr>';

        const query = construirQueryFiltros(paginaActual);
        const response = await fetch(apiBaseUrl + 'prm/registros?' + query);

        if (!response.ok) {
            tbody.innerHTML = '<tr><td colspan="15" class="admin-empty">No fue posible cargar los registros.</td></tr>';
            return;
        }

        const resultado = await response.json();
        document.getElementById('badgeCantidadRegistros').textContent = resultado.total + ' registros';

        if (!resultado.items.length) {
            tbody.innerHTML = '<tr><td colspan="15" class="admin-empty">Sin resultados.</td></tr>';
        } else {
            tbody.innerHTML = resultado.items.map(renderFila).join('');
        }

        renderPaginacion(resultado);
    }

    function renderFila(item) {
        return '<tr>' +
            '<td>' + formatearFecha(item.fecha) + '</td>' +
            '<td>' + escaparHtml(item.cliente) + '</td>' +
            '<td>' + escaparHtml(item.np) + '</td>' +
            '<td>' + escaparHtml(item.npOrigen) + '</td>' +
            '<td>' + escaparHtml(item.codigoMolde) + '</td>' +
            '<td>' + escaparHtml(item.tipo) + '</td>' +
            '<td>' + escaparHtml(item.diseno) + '</td>' +
            '<td>' + escaparHtml(item.desgajeInferior) + '</td>' +
            '<td>' + escaparHtml(item.desgajeSuperior) + '</td>' +
            '<td>' + escaparHtml(item.goma) + '</td>' +
            '<td>' + escaparHtml(item.nivelacion) + '</td>' +
            '<td>' + escaparHtml(item.acrilico) + '</td>' +
            '<td>' + escaparHtml(item.estado) + '</td>' +
            '<td>' + escaparHtml(item.observacion) + '</td>' +
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
        ['filtroPrmBuscar', 'filtroPrmCliente', 'filtroPrmNp', 'filtroPrmCodigoMolde'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', refiltrarConDebounce);
        });
        ['filtroPrmTipo', 'filtroPrmEstado', 'filtroPrmFechaDesde', 'filtroPrmFechaHasta'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () { cargarRegistros(1); });
        });

        document.getElementById('btnLimpiarFiltrosRegistro').addEventListener('click', function () {
            ['filtroPrmBuscar', 'filtroPrmCliente', 'filtroPrmNp', 'filtroPrmCodigoMolde', 'filtroPrmTipo', 'filtroPrmEstado', 'filtroPrmFechaDesde', 'filtroPrmFechaHasta'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            cargarRegistros(1);
        });
    }

    function leerFormulario(prefijo) {
        return {
            fecha: valor(prefijo + 'Fecha') || null,
            cliente: valor(prefijo + 'Cliente'),
            np: valor(prefijo + 'Np'),
            npOrigen: valor(prefijo + 'NpOrigen'),
            codigoMolde: valor(prefijo + 'CodigoMolde'),
            tipo: valor(prefijo + 'Tipo'),
            diseno: valor(prefijo + 'Diseno'),
            desgajeInferior: valor(prefijo + 'DesgajeInferior'),
            desgajeSuperior: valor(prefijo + 'DesgajeSuperior'),
            goma: valor(prefijo + 'Goma'),
            nivelacion: valor(prefijo + 'Nivelacion'),
            acrilico: valor(prefijo + 'Acrilico'),
            estado: valor(prefijo + 'Estado'),
            observacion: valor(prefijo + 'Observacion'),
            usuario: usuarioActual
        };
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
                const payload = leerFormulario('prm');

                const response = await fetch(apiBaseUrl + 'prm/registros', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible crear el registro.');

                const creado = JSON.parse(texto);
                mostrarAlerta('alertaRegistro', 'Registro NP ' + (creado.np || creado.id) + ' creado correctamente.', 'success');
                document.getElementById('formRegistro').reset();
                actualizarSugerenciaNpTrasCrear(creado.np);
                sugerirProximoNp();
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

            const id = document.getElementById('editarPrmId').value;
            const boton = document.getElementById('btnGuardarEdicionRegistro');
            boton.disabled = true;

            try {
                const payload = leerFormulario('editarPrm');

                const response = await fetch(apiBaseUrl + 'prm/registros/' + id, {
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
        const response = await fetch(apiBaseUrl + 'prm/registros/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el registro.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarPrmId').value = item.id;
        document.getElementById('modalEditarRegistroCodigo').textContent = item.np || ('#' + item.id);
        document.getElementById('editarPrmFecha').value = item.fecha ? item.fecha.substring(0, 10) : '';
        document.getElementById('editarPrmCliente').value = item.cliente || '';
        document.getElementById('editarPrmNp').value = item.np || '';
        document.getElementById('editarPrmNpOrigen').value = item.npOrigen || '';
        document.getElementById('editarPrmCodigoMolde').value = item.codigoMolde || '';
        document.getElementById('editarPrmTipo').value = item.tipo || '';
        document.getElementById('editarPrmDiseno').value = item.diseno || '';
        document.getElementById('editarPrmDesgajeInferior').value = item.desgajeInferior || '';
        document.getElementById('editarPrmDesgajeSuperior').value = item.desgajeSuperior || '';
        document.getElementById('editarPrmGoma').value = item.goma || '';
        document.getElementById('editarPrmNivelacion').value = item.nivelacion || '';
        document.getElementById('editarPrmAcrilico').value = item.acrilico || '';
        document.getElementById('editarPrmEstado').value = item.estado || '';
        document.getElementById('editarPrmObservacion').value = item.observacion || '';

        ocultarAlerta('alertaEditarRegistro');
        document.getElementById('modalEditarRegistro').classList.remove('hidden');
    }

    async function abrirHistorial(id, codigo) {
        document.getElementById('modalHistorialCodigo').textContent = codigo || '';
        const lista = document.getElementById('historialLista');
        lista.innerHTML = '<p>Cargando...</p>';
        document.getElementById('modalHistorial').classList.remove('hidden');

        const response = await fetch(apiBaseUrl + 'prm/registros/' + id + '/historial');
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
