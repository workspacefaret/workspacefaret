// Brigadistas, áreas y asignaciones de Inspección de Extintores (nivel "gestionar").
(function () {
    const PR = window.PrExt;
    const $ = function (id) { return document.getElementById(id); };

    let areas = [];
    let brigadistas = [];

    function alerta(id, mensaje, tipo) {
        const el = $(id);
        el.textContent = mensaje || '';
        el.className = 'admin-alert admin-alert-' + (tipo || 'error') + (mensaje ? '' : ' hidden');
    }

    function estado(activo) {
        return activo ? PR.badge('badge-success', 'Activo') : PR.badge('badge-danger', 'Inactivo');
    }

    // ---------- Listados ----------

    function iniciales(nombre) {
        const p = String(nombre).trim().split(/\s+/);
        return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
    }

    function nombresAreas(b) {
        return areas.filter(function (a) { return b.areaIds.includes(a.id); }).map(function (a) { return a.nombre; });
    }

    // Resumen superior y aviso de coherencia (brigadistas cuyas áreas no tienen extintores no verían ninguno).
    function dibujarResumen() {
        const activos = brigadistas.filter(function (b) { return b.activo; });
        const conAreas = activos.filter(function (b) { return b.areaIds.length; });
        const vacias = areas.filter(function (a) { return a.activo && !a.totalExtintores; });
        $('cfgKpiActivos').textContent = activos.length;
        $('cfgKpiConAreas').textContent = conAreas.length;
        $('cfgKpiSinAreas').textContent = activos.length - conAreas.length;
        $('cfgKpiAreasVacias').textContent = vacias.length;

        const sinVer = conAreas.filter(function (b) {
            return b.areaIds.every(function (id) {
                const a = areas.find(function (x) { return x.id === id; });
                return a && !a.totalExtintores;
            });
        });
        const banner = $('cfgBanner');
        if (sinVer.length) {
            banner.className = 'pr-banner pr-banner-warn';
            $('cfgBannerDetalle').textContent = sinVer.length + (sinVer.length === 1 ? ' brigadista tiene' : ' brigadistas tienen') +
                ' solo áreas sin extintores y no verá ninguno en el formulario: ' + sinVer.map(function (b) { return b.nombre; }).join(', ') +
                '. Asigna extintores a esas áreas desde la pestaña Extintores.';
        } else {
            banner.className = 'pr-banner';
            $('cfgBannerDetalle').textContent = 'Un brigadista sin áreas asignadas ve todos los extintores. Con una o más áreas, ve solo los extintores de esas áreas.';
        }
    }

    function dibujarBrigadistas() {
        if (!brigadistas.length) {
            $('tablaBrigadistas').innerHTML = '<tr><td colspan="4" class="admin-empty">No hay brigadistas.</td></tr>';
            return;
        }

        const texto = $('brigadistaBuscar').value.trim().toLowerCase();
        const lista = brigadistas.filter(function (b) {
            return !texto || (b.nombre + ' ' + nombresAreas(b).join(' ')).toLowerCase().includes(texto);
        });
        if (!lista.length) {
            $('tablaBrigadistas').innerHTML = '<tr><td colspan="4" class="admin-empty">No hay brigadistas con esa búsqueda.</td></tr>';
            return;
        }

        $('tablaBrigadistas').innerHTML = lista.map(function (b) {
            const nombres = nombresAreas(b);
            const detalle = nombres.length
                ? nombres.map(function (n) { return '<span class="pr-chip">' + PR.escapeHtml(n) + '</span>'; }).join('')
                : '<span class="pr-chip pr-chip-neutro">Todas (sin restricción)</span>';
            return '<tr' + (b.activo ? '' : ' class="pr-row-inactiva"') + '>' +
                '<td><div class="pr-persona"><span class="pr-avatar">' + PR.escapeHtml(iniciales(b.nombre)) + '</span>' + PR.escapeHtml(b.nombre) +
                (b.evaluable ? '' : ' <span class="pr-chip pr-chip-neutro" title="No entra en el cumplimiento mensual">No evaluado</span>') + '</div></td>' +
                '<td><div class="pr-chips">' + detalle + '</div></td>' +
                '<td>' + estado(b.activo) + '</td>' +
                '<td class="admin-table-actions"><button type="button" class="admin-icon-btn" data-editar-brigadista="' + b.id + '" title="Editar"><i class="bi bi-pencil"></i></button></td>' +
                '</tr>';
        }).join('');
    }

    function dibujarAreas() {
        if (!areas.length) {
            $('tablaAreas').innerHTML = '<tr><td colspan="4" class="admin-empty">No hay áreas.</td></tr>';
            return;
        }

        const max = Math.max.apply(null, areas.map(function (a) { return a.totalExtintores; }).concat(1));
        $('tablaAreas').innerHTML = areas.map(function (a) {
            const celdaExt = a.totalExtintores
                ? '<div class="pr-barra-fila"><div class="pr-barra"><i style="width:' + Math.round(a.totalExtintores / max * 100) + '%"></i></div><strong>' + a.totalExtintores + '</strong></div>'
                : '<span class="pr-chip pr-chip-alerta">Sin extintores</span>';
            return '<tr' + (a.activo ? '' : ' class="pr-row-inactiva"') + '>' +
                '<td>' + PR.escapeHtml(a.nombre) + '</td>' +
                '<td>' + celdaExt + '</td>' +
                '<td>' + estado(a.activo) + '</td>' +
                '<td class="admin-table-actions"><button type="button" class="admin-icon-btn" data-editar-area="' + a.id + '" title="Editar"><i class="bi bi-pencil"></i></button></td>' +
                '</tr>';
        }).join('');
    }

    async function cargar() {
        try {
            [areas, brigadistas] = await Promise.all([PR.api('areas?incluirInactivas=true'), PR.api('brigadistas?incluirInactivos=true')]);
        } catch (error) {
            alerta('alertaBrigadistas', error.message);
            return;
        }
        dibujarResumen();
        dibujarAreas();
        dibujarBrigadistas();
    }

    // ---------- Brigadista ----------

    function abrirBrigadista(b) {
        alerta('alertaModalBrigadista', '');
        $('tituloBrigadista').textContent = b ? 'Editar brigadista' : 'Nuevo brigadista';
        $('brigadistaId').value = b ? b.id : '';
        $('brigadistaNombre').value = b ? b.nombre : '';
        $('brigadistaActivo').checked = b ? b.activo : true;
        $('brigadistaEvaluable').checked = b ? b.evaluable : true;
        const asignadas = b ? b.areaIds : [];
        $('brigadistaAreas').innerHTML = areas.filter(function (a) { return a.activo || asignadas.includes(a.id); }).map(function (a) {
            return '<label class="pr-check" style="display:flex;align-items:center;gap:8px;font-weight:500;margin-bottom:6px;"><input type="checkbox" value="' + a.id + '"' + (asignadas.includes(a.id) ? ' checked' : '') + '> ' +
                PR.escapeHtml(a.nombre) + (a.activo ? '' : ' (inactiva)') + '</label>';
        }).join('');
        $('modalBrigadista').classList.remove('hidden');
    }

    async function guardarBrigadista(ev) {
        ev.preventDefault();
        alerta('alertaModalBrigadista', '');

        const id = $('brigadistaId').value;
        const areaIds = Array.from($('brigadistaAreas').querySelectorAll('input:checked')).map(function (c) { return Number(c.value); });
        const boton = $('guardarBrigadista');
        boton.disabled = true;
        try {
            await PR.enviarJson(id ? 'PUT' : 'POST', 'brigadistas' + (id ? '/' + id : ''), {
                nombre: $('brigadistaNombre').value.trim(),
                activo: $('brigadistaActivo').checked,
                evaluable: $('brigadistaEvaluable').checked,
                areaIds: areaIds
            });
            $('modalBrigadista').classList.add('hidden');
            PR.toast(id ? 'Brigadista actualizado correctamente.' : 'Brigadista creado correctamente.', 'success');
            await cargar();
        } catch (error) {
            alerta('alertaModalBrigadista', error.message);
            PR.toast('No se pudo guardar el brigadista.', 'error');
        } finally {
            boton.disabled = false;
        }
    }

    // ---------- Área ----------

    function abrirArea(a) {
        alerta('alertaModalArea', '');
        $('tituloArea').textContent = a ? 'Editar área' : 'Nueva área';
        $('areaId').value = a ? a.id : '';
        $('areaNombre').value = a ? a.nombre : '';
        $('areaActiva').checked = a ? a.activo : true;
        $('modalArea').classList.remove('hidden');
    }

    async function guardarArea(ev) {
        ev.preventDefault();
        alerta('alertaModalArea', '');

        const id = $('areaId').value;
        const boton = $('guardarArea');
        boton.disabled = true;
        try {
            await PR.enviarJson(id ? 'PUT' : 'POST', 'areas' + (id ? '/' + id : ''), {
                nombre: $('areaNombre').value.trim(),
                activo: $('areaActiva').checked
            });
            $('modalArea').classList.add('hidden');
            PR.toast(id ? 'Área actualizada correctamente.' : 'Área creada correctamente.', 'success');
            await cargar();
        } catch (error) {
            alerta('alertaModalArea', error.message);
            PR.toast('No se pudo guardar el área.', 'error');
        } finally {
            boton.disabled = false;
        }
    }

    $('brigadistaBuscar').addEventListener('input', dibujarBrigadistas);
    $('btnNuevoBrigadista').addEventListener('click', function () { abrirBrigadista(null); });
    $('btnNuevaArea').addEventListener('click', function () { abrirArea(null); });
    $('cerrarBrigadista').addEventListener('click', function () { $('modalBrigadista').classList.add('hidden'); });
    $('cerrarArea').addEventListener('click', function () { $('modalArea').classList.add('hidden'); });
    $('formBrigadista').addEventListener('submit', guardarBrigadista);
    $('formArea').addEventListener('submit', guardarArea);
    $('tablaBrigadistas').addEventListener('click', function (ev) {
        const boton = ev.target.closest('[data-editar-brigadista]');
        if (boton) abrirBrigadista(brigadistas.find(function (b) { return b.id === Number(boton.dataset.editarBrigadista); }));
    });
    $('tablaAreas').addEventListener('click', function (ev) {
        const boton = ev.target.closest('[data-editar-area]');
        if (boton) abrirArea(areas.find(function (a) { return a.id === Number(boton.dataset.editarArea); }));
    });

    cargar();
})();
