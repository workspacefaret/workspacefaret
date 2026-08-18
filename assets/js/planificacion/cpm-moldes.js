(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';
    const usuarioActual = window.currentUserNombre || '';

    const TABS = [
        { tipo: 'REPETITIVO', prefijo: 'molde', tieneProductoPerfil: true, colspan: 10 },
        { tipo: 'NO_REPETITIVO', prefijo: 'moldeNr', tieneProductoPerfil: false, colspan: 8 }
    ];

    const CLAVE_OPERADORES_MOLDES = 'cpm_sugerencias_operador_moldes';

    const paginaActualPorTab = {};
    const porPagina = 50;
    const debounceTimers = {};

    function esperarUtilidadesPerfiles() {
        return window.CpmPerfiles || {};
    }

    async function cargarCatalogos() {
        const response = await fetch(apiBaseUrl + 'cpm/catalogos');
        if (!response.ok) return;
        const catalogos = await response.json();

        const util = esperarUtilidadesPerfiles();
        util.llenarDatalist('listaMoldeRubros', catalogos.moldesRubros || []);
    }

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() || null : null;
    }

    function construirQueryFiltros(tab, pagina) {
        const params = new URLSearchParams();
        params.set('tipoMolde', tab.tipo);
        params.set('pagina', pagina);
        params.set('porPagina', porPagina);

        const buscar = valor('filtro' + capitalizar(tab.prefijo) + 'Buscar');
        const cliente = valor('filtro' + capitalizar(tab.prefijo) + 'Cliente');
        const rubro = valor('filtro' + capitalizar(tab.prefijo) + 'Rubro');
        const operador = valor('filtro' + capitalizar(tab.prefijo) + 'Operador');
        const fechaDesde = document.getElementById('filtro' + capitalizar(tab.prefijo) + 'FechaDesde').value;
        const fechaHasta = document.getElementById('filtro' + capitalizar(tab.prefijo) + 'FechaHasta').value;
        const incluirObsoletos = document.getElementById('filtro' + capitalizar(tab.prefijo) + 'Obsoletos').checked;

        if (buscar) params.set('buscar', buscar);
        if (cliente) params.set('cliente', cliente);
        if (rubro) params.set('rubro', rubro);
        if (operador) params.set('operador', operador);
        if (fechaDesde) params.set('fechaDesde', fechaDesde);
        if (fechaHasta) params.set('fechaHasta', fechaHasta);
        if (incluirObsoletos) params.set('incluirObsoletos', 'true');

        return params.toString();
    }

    function capitalizar(texto) {
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    async function cargarMoldes(tab, pagina) {
        const util = esperarUtilidadesPerfiles();
        paginaActualPorTab[tab.tipo] = pagina || 1;

        const idTabla = 'tabla' + capitalizar(tab.prefijo) + 'Body';
        const tbody = document.getElementById(idTabla);
        tbody.innerHTML = '<tr><td colspan="' + tab.colspan + '" class="admin-empty">Cargando...</td></tr>';

        const query = construirQueryFiltros(tab, paginaActualPorTab[tab.tipo]);
        const response = await fetch(apiBaseUrl + 'cpm/moldes?' + query);

        if (!response.ok) {
            tbody.innerHTML = '<tr><td colspan="' + tab.colspan + '" class="admin-empty">No fue posible cargar los moldes.</td></tr>';
            return;
        }

        const resultado = await response.json();
        document.getElementById('badgeCantidad' + capitalizar(tab.prefijo)).textContent = resultado.total + ' registros';

        if (!resultado.items.length) {
            tbody.innerHTML = '<tr><td colspan="' + tab.colspan + '" class="admin-empty">Sin resultados.</td></tr>';
        } else {
            tbody.innerHTML = resultado.items.map(function (item) { return renderFilaMolde(item, tab); }).join('');
        }

        util.renderPaginacion('paginacion' + capitalizar(tab.prefijo), resultado, function (pagina2) { cargarMoldes(tab, pagina2); });
    }

    function renderFilaMolde(item, tab) {
        const util = esperarUtilidadesPerfiles();
        const esc = util.escaparHtml;
        const fmtFecha = util.formatearFecha;

        let fila = '<tr' + (item.esObsoleto ? ' class="admin-row-anulado"' : '') + '>' +
            '<td>' + esc(item.codigo) + '</td>' +
            '<td>' + esc(item.cliente) + '</td>' +
            '<td>' + esc(item.rubro) + '</td>';

        if (tab.tieneProductoPerfil) {
            fila += '<td>' + esc(item.producto) + '</td>';
        }

        fila += '<td>' + esc(item.npPrimeraEntrada) + '</td>';

        if (tab.tieneProductoPerfil) {
            fila += '<td>' + esc(item.perfil) + '</td>';
        }

        fila += '<td>' + fmtFecha(item.fechaIngreso) + '</td>' +
            '<td>' + esc(item.operador) + '</td>' +
            '<td>' + esc(item.comentarios) + '</td>' +
            '<td class="admin-table-actions">' +
                '<div class="admin-row-actions">' +
                    '<button type="button" class="admin-icon-btn" data-editar-molde="' + item.id + '" title="Editar"><i class="bi bi-pencil"></i></button>' +
                    '<button type="button" class="admin-icon-btn" data-historial-molde="' + item.id + '" data-historial-codigo="' + esc(item.codigo) + '" title="Historial"><i class="bi bi-clock-history"></i></button>' +
                '</div>' +
            '</td>' +
        '</tr>';

        return fila;
    }

    function refiltrarConDebounce(tab) {
        clearTimeout(debounceTimers[tab.tipo]);
        debounceTimers[tab.tipo] = setTimeout(function () { cargarMoldes(tab, 1); }, 350);
    }

    function inicializarFiltros(tab) {
        const p = capitalizar(tab.prefijo);
        ['Buscar', 'Cliente', 'Rubro', 'Operador'].forEach(function (campo) {
            document.getElementById('filtro' + p + campo).addEventListener('input', function () { refiltrarConDebounce(tab); });
        });
        ['FechaDesde', 'FechaHasta'].forEach(function (campo) {
            document.getElementById('filtro' + p + campo).addEventListener('change', function () { cargarMoldes(tab, 1); });
        });
        document.getElementById('filtro' + p + 'Obsoletos').addEventListener('change', function () { cargarMoldes(tab, 1); });

        document.getElementById('btnLimpiarFiltros' + p).addEventListener('click', function () {
            ['Buscar', 'Cliente', 'Rubro', 'Operador', 'FechaDesde', 'FechaHasta'].forEach(function (campo) {
                document.getElementById('filtro' + p + campo).value = '';
            });
            document.getElementById('filtro' + p + 'Obsoletos').checked = false;
            cargarMoldes(tab, 1);
        });
    }

    function leerFormularioMolde(tab, prefijoCampos) {
        return {
            tipoMolde: tab.tipo,
            cliente: valor(prefijoCampos + 'Cliente'),
            rubro: valor(prefijoCampos + 'Rubro'),
            producto: valor(prefijoCampos + 'Producto'),
            npPrimeraEntrada: valor(prefijoCampos + 'Np'),
            perfil: valor(prefijoCampos + 'Perfil'),
            fechaIngreso: valor(prefijoCampos + 'Ingreso'),
            // Listo/Estado y Entrega ya no se capturan en el formulario: si el id existe (input oculto
            // del modal de edición) se preserva el valor histórico; si no existe (formularios "Nuevo"), queda null.
            listoEstado: valor(prefijoCampos + 'ListoEstado'),
            fechaEntrega: valor(prefijoCampos + 'Entrega'),
            operador: valor(prefijoCampos + 'Operador'),
            comentarios: valor(prefijoCampos + 'Comentarios'),
            usuario: usuarioActual
        };
    }

    function inicializarFormularioCrear(tab) {
        const util = esperarUtilidadesPerfiles();
        const p = capitalizar(tab.prefijo);

        document.getElementById('form' + p).addEventListener('submit', async function (evento) {
            evento.preventDefault();
            util.ocultarAlerta('alerta' + p);

            const boton = document.getElementById('btnGuardar' + p);
            boton.disabled = true;

            try {
                const payload = leerFormularioMolde(tab, tab.prefijo);

                const response = await fetch(apiBaseUrl + 'cpm/moldes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible crear el molde.');

                const creado = JSON.parse(texto);
                util.mostrarAlerta('alerta' + p, 'Molde ' + creado.codigo + ' creado correctamente.', 'success');
                util.agregarSugerenciaOperador(CLAVE_OPERADORES_MOLDES, payload.operador, 'listaMoldeOperadores');
                document.getElementById('form' + p).reset();
                cargarMoldes(tab, 1);
            } catch (error) {
                util.mostrarAlerta('alerta' + p, error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    function inicializarTablaAcciones(tab) {
        const idTabla = 'tabla' + capitalizar(tab.prefijo) + 'Body';

        document.getElementById(idTabla).addEventListener('click', async function (evento) {
            const util = esperarUtilidadesPerfiles();

            const botonEditar = evento.target.closest('[data-editar-molde]');
            if (botonEditar) {
                await abrirModalEditarMolde(parseInt(botonEditar.dataset.editarMolde, 10), tab);
                return;
            }

            const botonHistorial = evento.target.closest('[data-historial-molde]');
            if (botonHistorial) {
                await util.abrirHistorial('moldes', botonHistorial.dataset.historialMolde, botonHistorial.dataset.historialCodigo);
            }
        });
    }

    async function abrirModalEditarMolde(id, tab) {
        const util = esperarUtilidadesPerfiles();
        const response = await fetch(apiBaseUrl + 'cpm/moldes/' + id);
        if (!response.ok) {
            alert('No fue posible cargar el molde.');
            return;
        }
        const item = await response.json();

        document.getElementById('editarMoldeId').value = item.id;
        document.getElementById('editarMoldeTipo').value = item.tipoMolde;
        document.getElementById('modalEditarMoldeCodigo').textContent = item.codigo;
        document.getElementById('editarMoldeCliente').value = item.cliente || '';
        document.getElementById('editarMoldeRubro').value = item.rubro || '';
        document.getElementById('editarMoldeProducto').value = item.producto || '';
        document.getElementById('editarMoldeNp').value = item.npPrimeraEntrada || '';
        document.getElementById('editarMoldePerfil').value = item.perfil || '';
        document.getElementById('editarMoldeIngreso').value = item.fechaIngreso ? item.fechaIngreso.substring(0, 10) : '';
        document.getElementById('editarMoldeListoEstado').value = item.listoEstado || '';
        document.getElementById('editarMoldeEntrega').value = item.fechaEntrega ? item.fechaEntrega.substring(0, 10) : '';
        document.getElementById('editarMoldeOperador').value = item.operador || '';
        document.getElementById('editarMoldeComentarios').value = item.comentarios || '';

        const esRepetitivo = item.tipoMolde === 'REPETITIVO';
        document.getElementById('editarMoldeProductoWrap').style.display = esRepetitivo ? '' : 'none';
        document.getElementById('editarMoldePerfilWrap').style.display = esRepetitivo ? '' : 'none';

        util.ocultarAlerta('alertaEditarMolde');
        document.getElementById('modalEditarMolde').classList.remove('hidden');

        window.__cpmTabModalMolde = tab;
    }

    function inicializarEdicionMolde() {
        const util = esperarUtilidadesPerfiles();

        document.getElementById('btnCerrarModalEditarMolde').addEventListener('click', function () {
            document.getElementById('modalEditarMolde').classList.add('hidden');
        });

        document.getElementById('formEditarMolde').addEventListener('submit', async function (evento) {
            evento.preventDefault();
            util.ocultarAlerta('alertaEditarMolde');

            const id = document.getElementById('editarMoldeId').value;
            const tipoMolde = document.getElementById('editarMoldeTipo').value;
            const boton = document.getElementById('btnGuardarEdicionMolde');
            boton.disabled = true;

            try {
                const payload = leerFormularioMolde({ tipo: tipoMolde }, 'editarMolde');

                const response = await fetch(apiBaseUrl + 'cpm/moldes/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const texto = await response.text();
                if (!response.ok) throw new Error(texto || 'No fue posible guardar los cambios.');

                document.getElementById('modalEditarMolde').classList.add('hidden');

                const tab = window.__cpmTabModalMolde;
                if (tab) cargarMoldes(tab, paginaActualPorTab[tab.tipo] || 1);
            } catch (error) {
                util.mostrarAlerta('alertaEditarMolde', error.message, 'error');
            } finally {
                boton.disabled = false;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        cargarCatalogos();
        inicializarEdicionMolde();

        const util = esperarUtilidadesPerfiles();
        util.poblarDatalistOperador('listaMoldeOperadores', CLAVE_OPERADORES_MOLDES);

        TABS.forEach(function (tab) {
            inicializarFiltros(tab);
            inicializarFormularioCrear(tab);
            inicializarTablaAcciones(tab);
            cargarMoldes(tab, 1);
        });
    });
})();
