(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const usuarioActual = window.currentUserNombre || '';

    const TABS = [
        { categoria: 'TROQUEL', prefijo: 'treq', label: 'Troquel General' },
        { categoria: 'FARMACEUTICO', prefijo: 'farm', label: 'Farmacéutico' },
        { categoria: 'INDUSTRIAL', prefijo: 'indu', label: 'Industrial' },
        { categoria: 'CORRUGADO', prefijo: 'corr', label: 'Corrugado' },
        { categoria: '', prefijo: 'todo', label: 'Todos', consolidado: true }
    ];

    const TAB_PANELS = {
        treq: ['panelTreq', 'panelTreqTabla'],
        farm: ['panelFarm', 'panelFarmTabla'],
        indu: ['panelIndu', 'panelInduTabla'],
        corr: ['panelCorr', 'panelCorrTabla'],
        todo: ['panelTodoTabla']
    };

    function etiquetaCategoria(categoria) {
        const tab = TABS.find(function (t) { return t.categoria === categoria; });
        return tab ? tab.label : categoria;
    }

    const paginaActualPorTab = {};
    const porPagina = 50;
    const debounceTimers = {};

    function capitalizar(texto) {
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

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

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() || null : null;
    }

    async function cargarCatalogos() {
        const response = await fetch(apiBaseUrl + 'mre/catalogos');
        if (!response.ok) return;
        const catalogos = await response.json();

        llenarDatalist('listaMreClientes', catalogos.clientes || []);
    }

    function construirQueryFiltros(tab, pagina) {
        const params = new URLSearchParams();
        const p = capitalizar(tab.prefijo);

        const categoriaFiltro = tab.consolidado ? valor('filtro' + p + 'Categoria') : tab.categoria;
        if (categoriaFiltro) params.set('categoria', categoriaFiltro);

        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = valor('filtro' + p + 'Buscar');
        const cliente = valor('filtro' + p + 'Cliente');
        const rack = valor('filtro' + p + 'Rack');
        const perfil = valor('filtro' + p + 'Perfil');

        if (buscar) params.set('buscar', buscar);
        if (cliente) params.set('cliente', cliente);
        if (rack) params.set('rack', rack);
        if (perfil) params.set('perfil', perfil);

        return params.toString();
    }

    async function cargarRegistros(tab, pagina) {
        const p = capitalizar(tab.prefijo);
        paginaActualPorTab[tab.prefijo] = pagina || 1;

        const columnas = tab.consolidado ? 7 : 6;
        const tbody = document.getElementById('tabla' + p + 'Body');
        tbody.innerHTML = '<tr><td colspan="' + columnas + '" class="admin-empty">Cargando...</td></tr>';

        const query = construirQueryFiltros(tab, paginaActualPorTab[tab.prefijo]);
        const response = await fetch(apiBaseUrl + 'mre/registros?' + query);

        if (!response.ok) {
            tbody.innerHTML = '<tr><td colspan="' + columnas + '" class="admin-empty">No fue posible cargar los registros.</td></tr>';
            return;
        }

        const resultado = await response.json();
        document.getElementById('badgeCantidad' + p).textContent = resultado.total + ' registros';

        if (!resultado.items.length) {
            tbody.innerHTML = '<tr><td colspan="' + columnas + '" class="admin-empty">Sin resultados.</td></tr>';
        } else {
            tbody.innerHTML = resultado.items.map(tab.consolidado ? renderFilaConsolidada : renderFila).join('');
        }

        renderPaginacion('paginacion' + p, resultado, function (pagina2) { cargarRegistros(tab, pagina2); });
    }

    function accionesHtml(item) {
        return '<div class="admin-row-actions">' +
                    '<button type="button" class="admin-icon-btn" data-editar="' + item.id + '" data-categoria="' + escaparHtml(item.categoria) + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="admin-icon-btn" data-historial="' + item.id + '" data-historial-codigo="' + escaparHtml(item.npMolde) + '" title="Historial"><i class="bi bi-clock-history"></i></button>' +
                '</div>';
    }

    function renderFila(item) {
        return '<tr>' +
            '<td>' + escaparHtml(item.npMolde) + '</td>' +
            '<td>' + escaparHtml(item.cliente) + '</td>' +
            '<td>' + escaparHtml(item.rack) + '</td>' +
            '<td>' + escaparHtml(item.perfil) + '</td>' +
            '<td>' + escaparHtml(item.observaciones) + '</td>' +
            '<td class="admin-table-actions">' + accionesHtml(item) + '</td>' +
        '</tr>';
    }

    function renderFilaConsolidada(item) {
        return '<tr>' +
            '<td>' + escaparHtml(etiquetaCategoria(item.categoria)) + '</td>' +
            '<td>' + escaparHtml(item.npMolde) + '</td>' +
            '<td>' + escaparHtml(item.cliente) + '</td>' +
            '<td>' + escaparHtml(item.rack) + '</td>' +
            '<td>' + escaparHtml(item.perfil) + '</td>' +
            '<td>' + escaparHtml(item.observaciones) + '</td>' +
            '<td class="admin-table-actions">' + accionesHtml(item) + '</td>' +
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

    function refiltrarConDebounce(tab) {
        clearTimeout(debounceTimers[tab.prefijo]);
        debounceTimers[tab.prefijo] = setTimeout(function () { cargarRegistros(tab, 1); }, 350);
    }

    function inicializarFiltros(tab) {
        const p = capitalizar(tab.prefijo);
        ['Buscar', 'Cliente', 'Rack', 'Perfil'].forEach(function (campo) {
            document.getElementById('filtro' + p + campo).addEventListener('input', function () { refiltrarConDebounce(tab); });
        });

        if (tab.consolidado) {
            document.getElementById('filtro' + p + 'Categoria').addEventListener('change', function () { cargarRegistros(tab, 1); });
        }

        document.getElementById('btnLimpiarFiltros' + p).addEventListener('click', function () {
            ['Buscar', 'Cliente', 'Rack', 'Perfil'].forEach(function (campo) {
                document.getElementById('filtro' + p + campo).value = '';
            });
            if (tab.consolidado) document.getElementById('filtro' + p + 'Categoria').value = '';
            cargarRegistros(tab, 1);
        });
    }

    function leerFormulario(tab, prefijoCampos) {
        return {
            categoria: tab.categoria,
            npMolde: valor(prefijoCampos + 'NpMolde'),
            cliente: valor(prefijoCampos + 'Cliente'),
            rack: valor(prefijoCampos + 'Rack'),
            perfil: valor(prefijoCampos + 'Perfil'),
            observaciones: valor(prefijoCampos + 'Observaciones'),
            usuario: usuarioActual
        };
    }

    function inicializarFormularioCrear(tab) {
        const p = capitalizar(tab.prefijo);

        document.getElementById('form' + p).addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alerta' + p);

            const boton = document.getElementById('btnGuardar' + p);
            boton.disabled = true;

            try {
                const payload = leerFormulario(tab, tab.prefijo);

                const response = await fetch(apiBaseUrl + 'mre/registros', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible crear el registro.');

                const creado = JSON.parse(texto);
                mostrarAlerta('alerta' + p, 'Registro ' + (creado.npMolde || creado.id) + ' creado correctamente.', 'success');
                document.getElementById('form' + p).reset();
                cargarRegistros(tab, 1);
            } catch (error) {
                mostrarAlerta('alerta' + p, error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    function inicializarTablaAcciones(tab) {
        const p = capitalizar(tab.prefijo);

        document.getElementById('tabla' + p + 'Body').addEventListener('click', async function (evento) {
            const botonEditar = evento.target.closest('[data-editar]');
            if (botonEditar) {
                await abrirModalEditar(parseInt(botonEditar.dataset.editar, 10), tab);
                return;
            }

            const botonHistorial = evento.target.closest('[data-historial]');
            if (botonHistorial) {
                await abrirHistorial(botonHistorial.dataset.historial, botonHistorial.dataset.historialCodigo);
            }
        });
    }

    async function abrirModalEditar(id, tab) {
        const response = await fetch(apiBaseUrl + 'mre/registros/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el registro.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarMreId').value = item.id;
        document.getElementById('editarMreCategoria').value = item.categoria;
        document.getElementById('modalEditarRegistroCodigo').textContent = item.npMolde || ('#' + item.id);
        document.getElementById('modalEditarRegistroCategoria').textContent = item.categoria;
        document.getElementById('editarMreNpMolde').value = item.npMolde || '';
        document.getElementById('editarMreCliente').value = item.cliente || '';
        document.getElementById('editarMreRack').value = item.rack || '';
        document.getElementById('editarMrePerfil').value = item.perfil || '';
        document.getElementById('editarMreObservaciones').value = item.observaciones || '';

        ocultarAlerta('alertaEditarRegistro');
        document.getElementById('modalEditarRegistro').classList.remove('hidden');

        window.__mreTabModalEditar = tab;
    }

    function inicializarEdicion() {
        document.getElementById('btnCerrarModalEditarRegistro').addEventListener('click', function () {
            document.getElementById('modalEditarRegistro').classList.add('hidden');
        });

        document.getElementById('formEditarRegistro').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            ocultarAlerta('alertaEditarRegistro');

            const id = document.getElementById('editarMreId').value;
            const categoria = document.getElementById('editarMreCategoria').value;
            const boton = document.getElementById('btnGuardarEdicionRegistro');
            boton.disabled = true;

            try {
                const payload = leerFormulario({ categoria: categoria }, 'editarMre');

                const response = await fetch(apiBaseUrl + 'mre/registros/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible guardar los cambios.');

                document.getElementById('modalEditarRegistro').classList.add('hidden');

                const tab = window.__mreTabModalEditar;
                if (tab) cargarRegistros(tab, paginaActualPorTab[tab.prefijo] || 1);
            } catch (error) {
                mostrarAlerta('alertaEditarRegistro', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    async function abrirHistorial(id, codigo) {
        document.getElementById('modalHistorialCodigo').textContent = codigo || '';
        const lista = document.getElementById('historialLista');
        lista.innerHTML = '<p>Cargando...</p>';
        document.getElementById('modalHistorial').classList.remove('hidden');

        const response = await fetch(apiBaseUrl + 'mre/registros/' + id + '/historial');
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

    async function imprimir(tab) {
        const p = capitalizar(tab.prefijo);
        const boton = document.getElementById('btnImprimir' + p);
        boton.disabled = true;

        try {
            const params = new URLSearchParams(construirQueryFiltros(tab, 1));
            params.set('porPagina', '100000');

            const response = await fetch(apiBaseUrl + 'mre/registros?' + params.toString());
            if (!response.ok) throw new Error('No fue posible obtener los registros para imprimir.');
            const resultado = await response.json();

            const filas = resultado.items.map(function (item) {
                return tab.consolidado
                    ? [etiquetaCategoria(item.categoria), item.npMolde, item.cliente, item.rack, item.perfil, item.observaciones]
                    : [item.npMolde, item.cliente, item.rack, item.perfil, item.observaciones];
            });

            window.PlanificacionPrint.imprimir({
                tituloModulo: 'Stock de Moldes — ' + tab.label,
                subtitulo: 'Inventario de moldes por rack (PLA-MOL-MRE)',
                encabezados: tab.consolidado
                    ? ['Categoría', 'NP / Molde', 'Cliente', 'Rack', 'Perfil', 'Observaciones']
                    : ['NP / Molde', 'Cliente', 'Rack', 'Perfil', 'Observaciones'],
                filas: filas,
                filtrosTexto: window.PlanificacionPrint.resumenFiltros([
                    ['Buscar', valor('filtro' + p + 'Buscar')],
                    tab.consolidado ? ['Categoría', etiquetaCategoria(valor('filtro' + p + 'Categoria'))] : null,
                    ['Cliente', valor('filtro' + p + 'Cliente')],
                    ['Rack', valor('filtro' + p + 'Rack')],
                    ['Perfil', valor('filtro' + p + 'Perfil')]
                ].filter(Boolean))
            });
        } catch (error) {
            alert(error.message);
        } finally {
            boton.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        inicializarTabs();
        cargarCatalogos();
        inicializarEdicion();

        document.getElementById('btnCerrarModalHistorial').addEventListener('click', function () {
            document.getElementById('modalHistorial').classList.add('hidden');
        });

        TABS.forEach(function (tab) {
            inicializarFiltros(tab);
            if (!tab.consolidado) inicializarFormularioCrear(tab);
            inicializarTablaAcciones(tab);
            cargarRegistros(tab, 1);

            const p = capitalizar(tab.prefijo);
            document.getElementById('btnImprimir' + p).addEventListener('click', function () {
                imprimir(tab);
            });
        });
    });
})();
