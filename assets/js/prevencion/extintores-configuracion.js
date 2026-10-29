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

    function dibujarBrigadistas() {
        if (!brigadistas.length) {
            $('tablaBrigadistas').innerHTML = '<tr><td colspan="4" class="admin-empty">No hay brigadistas.</td></tr>';
            return;
        }

        $('tablaBrigadistas').innerHTML = brigadistas.map(function (b) {
            const nombres = areas.filter(function (a) { return b.areaIds.includes(a.id); }).map(function (a) { return a.nombre; });
            const detalle = nombres.length
                ? PR.escapeHtml(nombres.join(' · '))
                : '<span class="pr-muted">Todas (sin restricción)</span>';
            return '<tr>' +
                '<td>' + PR.escapeHtml(b.nombre) + '</td>' +
                '<td>' + detalle + '</td>' +
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

        $('tablaAreas').innerHTML = areas.map(function (a) {
            return '<tr>' +
                '<td>' + PR.escapeHtml(a.nombre) + '</td>' +
                '<td>' + a.totalExtintores + '</td>' +
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
                areaIds: areaIds
            });
            $('modalBrigadista').classList.add('hidden');
            await cargar();
        } catch (error) {
            alerta('alertaModalBrigadista', error.message);
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
            await cargar();
        } catch (error) {
            alerta('alertaModalArea', error.message);
        } finally {
            boton.disabled = false;
        }
    }

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
