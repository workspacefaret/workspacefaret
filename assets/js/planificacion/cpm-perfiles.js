(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const usuarioActual = window.currentUserNombre || '';

    const TAB_PANELS = {
        perfiles: ['panelPerfiles', 'panelPerfilesTabla'],
        moldes: ['panelMoldes', 'panelMoldesTabla'],
        'moldes-nr': ['panelMoldesNr', 'panelMoldesNrTabla']
    };

    function inicializarTabs() {
        document.querySelectorAll('.admin-tab').forEach(function (boton) {
            boton.addEventListener('click', function () {
                activarTab(boton.dataset.tab);
            });
        });
    }

    function activarTab(tab) {
        document.querySelectorAll('.admin-tab').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.tab === tab);
        });

        Object.keys(TAB_PANELS).forEach(function (clave) {
            const activo = clave === tab;
            TAB_PANELS[clave].forEach(function (idPanel) {
                const panel = document.getElementById(idPanel);
                if (panel) panel.classList.toggle('is-active', activo);
            });
        });
    }

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

    // ---------------- Sugerencias de Operador (localStorage, texto libre, solo desplegable nativo) ----------------

    const OPERADORES_SUGERIDOS_DEFECTO = ['Heber Ibarra', 'Sergio Herrera', 'Ricardo Muñoz'];
    const CLAVE_OPERADORES_PERFILES = 'cpm_sugerencias_operador_perfiles';

    function obtenerSugerenciasOperador(clave) {
        try {
            const guardado = localStorage.getItem(clave);
            if (guardado) return JSON.parse(guardado);
        } catch (error) {
            // localStorage no disponible o dato corrupto: se reinicia con los valores por defecto.
        }
        localStorage.setItem(clave, JSON.stringify(OPERADORES_SUGERIDOS_DEFECTO));
        return OPERADORES_SUGERIDOS_DEFECTO.slice();
    }

    function poblarDatalistOperador(idDatalist, clave) {
        llenarDatalist(idDatalist, obtenerSugerenciasOperador(clave));
    }

    function agregarSugerenciaOperador(clave, nombre, idDatalist) {
        if (!nombre) return;
        const lista = obtenerSugerenciasOperador(clave);
        const yaExiste = lista.some(function (n) { return n.toLowerCase() === nombre.toLowerCase(); });
        if (!yaExiste) {
            lista.push(nombre);
            localStorage.setItem(clave, JSON.stringify(lista));
        }
        if (idDatalist) poblarDatalistOperador(idDatalist, clave);
    }

    function formatearFecha(valor) {
        if (!valor) return '-';
        return valor.substring(0, 10).split('-').reverse().join('-');
    }

    async function cargarCatalogos() {
        const response = await fetch(apiBaseUrl + 'cpm/catalogos');
        if (!response.ok) return;
        const catalogos = await response.json();

        llenarDatalist('listaPerfilRubros', catalogos.perfilesRubros || []);
    }

    function construirQueryFiltros(pagina) {
        const params = new URLSearchParams();
        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = document.getElementById('filtroPerfilBuscar').value.trim();
        const cliente = document.getElementById('filtroPerfilCliente').value.trim();
        const rubro = document.getElementById('filtroPerfilRubro').value.trim();
        const operador = document.getElementById('filtroPerfilOperador').value.trim();
        const fechaDesde = document.getElementById('filtroPerfilFechaDesde').value;
        const fechaHasta = document.getElementById('filtroPerfilFechaHasta').value;

        if (buscar) params.set('buscar', buscar);
        if (cliente) params.set('cliente', cliente);
        if (rubro) params.set('rubro', rubro);
        if (operador) params.set('operador', operador);
        if (fechaDesde) params.set('fechaDesde', fechaDesde);
        if (fechaHasta) params.set('fechaHasta', fechaHasta);

        return params.toString();
    }

    async function cargarPerfiles(pagina) {
        paginaActual = pagina || 1;
        const tbody = document.getElementById('tablaPerfilesBody');
        tbody.innerHTML = '<tr><td colspan="9" class="admin-empty">Cargando...</td></tr>';

        const query = construirQueryFiltros(paginaActual);
        const response = await fetch(apiBaseUrl + 'cpm/perfiles?' + query);

        if (!response.ok) {
            tbody.innerHTML = '<tr><td colspan="9" class="admin-empty">No fue posible cargar los perfiles.</td></tr>';
            return;
        }

        const resultado = await response.json();
        document.getElementById('badgeCantidadPerfiles').textContent = resultado.total + ' registros';

        if (!resultado.items.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="admin-empty">Sin resultados.</td></tr>';
        } else {
            tbody.innerHTML = resultado.items.map(renderFilaPerfil).join('');
        }

        renderPaginacion('paginacionPerfiles', resultado, cargarPerfiles);
    }

    function renderFilaPerfil(item) {
        return '<tr>' +
            '<td>' + escaparHtml(item.numeroPerfil) + '</td>' +
            '<td>' + escaparHtml(item.cliente) + '</td>' +
            '<td>' + escaparHtml(item.medidas) + '</td>' +
            '<td>' + escaparHtml(item.descripcion) + '</td>' +
            '<td>' + escaparHtml(item.numeroDesarrollo) + '</td>' +
            '<td>' + formatearFecha(item.fecha) + '</td>' +
            '<td>' + escaparHtml(item.rubro) + '</td>' +
            '<td>' + escaparHtml(item.operador) + '</td>' +
            '<td class="admin-table-actions">' +
                '<div class="admin-row-actions">' +
                    '<button type="button" class="admin-icon-btn" data-editar-perfil="' + item.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="admin-icon-btn" data-historial-perfil="' + item.id + '" data-historial-codigo="' + escaparHtml(item.numeroPerfil) + '" title="Historial"><i class="bi bi-clock-history"></i></button>' +
                '</div>' +
            '</td>' +
        '</tr>';
    }

    function renderPaginacion(idContenedor, resultado, callback) {
        const contenedor = document.getElementById(idContenedor);
        const totalPaginas = Math.max(1, Math.ceil(resultado.total / resultado.porPagina));

        contenedor.innerHTML =
            '<button ' + (resultado.pagina <= 1 ? 'disabled' : '') + ' data-page="' + (resultado.pagina - 1) + '">Anterior</button>' +
            '<span>Página ' + resultado.pagina + ' de ' + totalPaginas + '</span>' +
            '<button ' + (resultado.pagina >= totalPaginas ? 'disabled' : '') + ' data-page="' + (resultado.pagina + 1) + '">Siguiente</button>';

        contenedor.querySelectorAll('button[data-page]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                callback(parseInt(boton.dataset.page, 10));
            });
        });
    }

    function refiltrarConDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { cargarPerfiles(1); }, 350);
    }

    async function imprimirPerfiles() {
        const boton = document.getElementById('btnImprimirPerfiles');
        boton.disabled = true;

        try {
            const params = new URLSearchParams(construirQueryFiltros(1));
            params.set('porPagina', '100000');

            const response = await fetch(apiBaseUrl + 'cpm/perfiles?' + params.toString());
            if (!response.ok) throw new Error('No fue posible obtener los perfiles para imprimir.');
            const resultado = await response.json();

            const filas = resultado.items.map(function (item) {
                return [
                    item.numeroPerfil, item.cliente, item.medidas, item.descripcion,
                    item.numeroDesarrollo, formatearFecha(item.fecha), item.rubro, item.operador
                ];
            });

            window.PlanificacionPrint.imprimir({
                tituloModulo: 'Perfiles y Moldes — Perfiles',
                subtitulo: 'Listado de perfiles (Correlativos Perfiles y Moldes)',
                encabezados: ['N° Perfil', 'Cliente', 'Medidas', 'Descripción', 'N° Desarrollo', 'Fecha', 'Rubro', 'Operador'],
                filas: filas,
                filtrosTexto: window.PlanificacionPrint.resumenFiltros([
                    ['Buscar', document.getElementById('filtroPerfilBuscar').value.trim()],
                    ['Cliente', document.getElementById('filtroPerfilCliente').value.trim()],
                    ['Rubro', document.getElementById('filtroPerfilRubro').value.trim()],
                    ['Operador', document.getElementById('filtroPerfilOperador').value.trim()],
                    ['Fecha desde', document.getElementById('filtroPerfilFechaDesde').value],
                    ['Fecha hasta', document.getElementById('filtroPerfilFechaHasta').value]
                ])
            });
        } catch (error) {
            alert(error.message);
        } finally {
            boton.disabled = false;
        }
    }

    function inicializarFiltrosPerfil() {
        ['filtroPerfilBuscar', 'filtroPerfilCliente', 'filtroPerfilRubro', 'filtroPerfilOperador'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', refiltrarConDebounce);
        });
        ['filtroPerfilFechaDesde', 'filtroPerfilFechaHasta'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', function () { cargarPerfiles(1); });
        });

        document.getElementById('btnLimpiarFiltrosPerfil').addEventListener('click', function () {
            ['filtroPerfilBuscar', 'filtroPerfilCliente', 'filtroPerfilRubro', 'filtroPerfilOperador', 'filtroPerfilFechaDesde', 'filtroPerfilFechaHasta'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            cargarPerfiles(1);
        });
    }

    function leerFormularioPerfil(prefijo) {
        return {
            cliente: valor(prefijo + 'Cliente'),
            medidas: valor(prefijo + 'Medidas'),
            descripcion: valor(prefijo + 'Descripcion'),
            numeroDesarrollo: valor(prefijo + 'NumeroDesarrollo'),
            fecha: valor(prefijo + 'Fecha') || null,
            rubro: valor(prefijo + 'Rubro'),
            operador: valor(prefijo + 'Operador'),
            numeroCaja: valor(prefijo + 'NumeroCaja'),
            unidadesPorCaja: valor(prefijo + 'UnidadesPorCaja'),
            status: valor(prefijo + 'Status'),
            estado: valor(prefijo + 'Estado'),
            perfilNuevo: valor(prefijo + 'PerfilNuevo'),
            usuario: usuarioActual
        };
    }

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() || null : null;
    }

    function inicializarFormularioCrear() {
        document.getElementById('formPerfil').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaPerfil');

            const boton = document.getElementById('btnGuardarPerfil');
            boton.disabled = true;

            try {
                const payload = leerFormularioPerfil('perfil');

                const response = await fetch(apiBaseUrl + 'cpm/perfiles', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible crear el perfil.');

                const creado = JSON.parse(texto);
                mostrarAlerta('alertaPerfil', 'Perfil ' + creado.numeroPerfil + ' creado correctamente.', 'success');
                agregarSugerenciaOperador(CLAVE_OPERADORES_PERFILES, payload.operador, 'listaPerfilOperadores');
                document.getElementById('formPerfil').reset();
                cargarPerfiles(1);
            } catch (error) {
                mostrarAlerta('alertaPerfil', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    function inicializarEdicionPerfil() {
        document.getElementById('tablaPerfilesBody').addEventListener('click', async function (evento) {
            const botonEditar = evento.target.closest('[data-editar-perfil]');
            if (botonEditar) {
                await abrirModalEditarPerfil(parseInt(botonEditar.dataset.editarPerfil, 10));
                return;
            }

            const botonHistorial = evento.target.closest('[data-historial-perfil]');
            if (botonHistorial) {
                await abrirHistorial('perfiles', botonHistorial.dataset.historialPerfil, botonHistorial.dataset.historialCodigo);
            }
        });

        document.getElementById('btnCerrarModalEditarPerfil').addEventListener('click', function () {
            document.getElementById('modalEditarPerfil').classList.add('hidden');
        });

        document.getElementById('formEditarPerfil').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaEditarPerfil');

            const id = document.getElementById('editarPerfilId').value;
            const boton = document.getElementById('btnGuardarEdicionPerfil');
            boton.disabled = true;

            try {
                const payload = leerFormularioPerfil('editarPerfil');

                const response = await fetch(apiBaseUrl + 'cpm/perfiles/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible guardar los cambios.');

                document.getElementById('modalEditarPerfil').classList.add('hidden');
                cargarPerfiles(paginaActual);
            } catch (error) {
                mostrarAlerta('alertaEditarPerfil', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    async function abrirModalEditarPerfil(id) {
        const response = await fetch(apiBaseUrl + 'cpm/perfiles/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el perfil.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarPerfilId').value = item.id;
        document.getElementById('modalEditarPerfilCodigo').textContent = item.numeroPerfil;
        document.getElementById('editarPerfilCliente').value = item.cliente || '';
        document.getElementById('editarPerfilMedidas').value = item.medidas || '';
        document.getElementById('editarPerfilDescripcion').value = item.descripcion || '';
        document.getElementById('editarPerfilNumeroDesarrollo').value = item.numeroDesarrollo || '';
        document.getElementById('editarPerfilFecha').value = item.fecha ? item.fecha.substring(0, 10) : '';
        document.getElementById('editarPerfilRubro').value = item.rubro || '';
        document.getElementById('editarPerfilOperador').value = item.operador || '';
        document.getElementById('editarPerfilNumeroCaja').value = item.numeroCaja || '';
        document.getElementById('editarPerfilUnidadesPorCaja').value = item.unidadesPorCaja || '';
        document.getElementById('editarPerfilStatus').value = item.status || '';
        document.getElementById('editarPerfilEstado').value = item.estado || '';
        document.getElementById('editarPerfilPerfilNuevo').value = item.perfilNuevo || '';

        ocultarAlerta('alertaEditarPerfil');
        document.getElementById('modalEditarPerfil').classList.remove('hidden');
    }

    async function abrirHistorial(entidadRuta, id, codigo) {
        document.getElementById('modalHistorialCodigo').textContent = codigo || '';
        const lista = document.getElementById('historialLista');
        lista.innerHTML = '<p>Cargando...</p>';
        document.getElementById('modalHistorial').classList.remove('hidden');

        const response = await fetch(apiBaseUrl + 'cpm/' + entidadRuta + '/' + id + '/historial');
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
        inicializarTabs();
        cargarCatalogos();
        poblarDatalistOperador('listaPerfilOperadores', CLAVE_OPERADORES_PERFILES);
        inicializarFiltrosPerfil();
        inicializarFormularioCrear();
        inicializarEdicionPerfil();
        cargarPerfiles(1);

        document.getElementById('btnImprimirPerfiles').addEventListener('click', imprimirPerfiles);

        document.getElementById('btnCerrarModalHistorial').addEventListener('click', function () {
            document.getElementById('modalHistorial').classList.add('hidden');
        });
    });

    window.CpmPerfiles = {
        abrirHistorial: abrirHistorial,
        escaparHtml: escaparHtml,
        formatearFecha: formatearFecha,
        renderPaginacion: renderPaginacion,
        mostrarAlerta: mostrarAlerta,
        ocultarAlerta: ocultarAlerta,
        llenarDatalist: llenarDatalist,
        poblarDatalistOperador: poblarDatalistOperador,
        agregarSugerenciaOperador: agregarSugerenciaOperador
    };
})();
