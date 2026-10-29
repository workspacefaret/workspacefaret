// Inventario de extintores (nivel "gestionar"): alta, edición y baja. El N° lo asigna la API.
(function () {
    const PR = window.PrExt;
    const $ = function (id) { return document.getElementById(id); };

    let plantas = [];
    let areas = [];
    let extintores = [];

    function opcionesAreas(seleccionada) {
        return '<option value="">Sin área</option>' + areas.map(function (a) {
            return '<option value="' + a.id + '"' + (a.id === seleccionada ? ' selected' : '') + '>' + PR.escapeHtml(a.nombre) + '</option>';
        }).join('');
    }

    function alerta(id, mensaje, tipo) {
        const el = $(id);
        el.textContent = mensaje || '';
        el.className = 'admin-alert admin-alert-' + (tipo || 'error') + (mensaje ? '' : ' hidden');
    }

    function visibles() {
        const estado = $('filtroEstado').value;
        const texto = $('filtroBuscar').value.trim().toLowerCase();
        const area = $('filtroArea').value;
        return extintores.filter(function (e) {
            if (estado === 'ACTIVOS' && !e.activo) return false;
            if (estado === 'BAJA' && e.activo) return false;
            if (area === 'SIN' && e.areaId) return false;
            if (area && area !== 'SIN' && String(e.areaId) !== area) return false;
            if (!texto) return true;
            return (e.ubicacion + ' ' + e.numero + ' ' + e.tipo).toLowerCase().includes(texto);
        });
    }

    function dibujar() {
        const lista = visibles();
        $('badgeCantidad').textContent = lista.length + (lista.length === 1 ? ' extintor' : ' extintores');

        if (!lista.length) {
            $('tablaBody').innerHTML = '<tr><td colspan="8" class="admin-empty">No hay extintores con esos filtros.</td></tr>';
            return;
        }

        $('tablaBody').innerHTML = lista.map(function (e) {
            return '<tr>' +
                '<td>' + PR.escapeHtml(e.plantaNombre) + '</td>' +
                '<td>' + e.numero + '</td>' +
                '<td>' + PR.escapeHtml(e.ubicacion) + '</td>' +
                '<td>' + PR.escapeHtml(PR.etiquetaTipo(e)) + '</td>' +
                '<td><select data-area-de="' + e.id + '" aria-label="Área del extintor ' + e.numero + '">' + opcionesAreas(e.areaId) + '</select></td>' +
                '<td>' + PR.badgeVencimiento(e.vencimiento) + '</td>' +
                '<td>' + (e.activo ? PR.badge('badge-success', 'Activo') : PR.badge('badge-danger', 'De baja')) + '</td>' +
                '<td class="admin-table-actions"><button type="button" class="admin-icon-btn" data-editar="' + e.id + '" title="Editar"><i class="bi bi-pencil"></i></button></td>' +
                '</tr>';
        }).join('');
    }

    async function cargar() {
        alerta('alertaListado', '');
        const planta = $('filtroPlanta').value;
        try {
            extintores = await PR.api('?incluirInactivos=true' + (planta ? '&plantaId=' + encodeURIComponent(planta) : ''));
        } catch (error) {
            extintores = [];
            alerta('alertaListado', error.message);
        }
        dibujar();
    }

    // ---------- Modal ----------

    function abrirModal() { $('modalExtintor').classList.remove('hidden'); }
    function cerrarModal() { $('modalExtintor').classList.add('hidden'); }

    // Sugerencias de ubicación de la planta elegida (menos escritura, mismos nombres).
    function actualizarSugerencias() {
        const plantaId = Number($('formPlanta').value);
        const nombres = Array.from(new Set(extintores.filter(function (e) { return e.plantaId === plantaId; }).map(function (e) { return e.ubicacion; })));
        $('listaUbicaciones').innerHTML = nombres.map(function (n) { return '<option value="' + PR.escapeHtml(n) + '">'; }).join('');
    }

    function abrirNuevo() {
        alerta('alertaModal', '');
        $('modalTitulo').textContent = 'Nuevo extintor';
        $('extintorId').value = '';
        $('formPlanta').disabled = false;
        $('formPlanta').value = $('filtroPlanta').value || (plantas[0] && plantas[0].id);
        $('formTipo').value = 'PQS';
        $('formUbicacion').value = '';
        $('formCapacidad').value = '';
        $('formVencimiento').value = '';
        $('formArea').innerHTML = opcionesAreas(null);
        $('formActivo').checked = true;
        actualizarSugerencias();
        abrirModal();
    }

    function abrirEdicion(id) {
        const e = extintores.find(function (x) { return x.id === id; });
        if (!e) return;
        alerta('alertaModal', '');
        $('modalTitulo').textContent = 'Editar extintor N° ' + e.numero;
        $('extintorId').value = e.id;
        $('formPlanta').value = e.plantaId;
        $('formPlanta').disabled = true; // la planta no se cambia: se da de baja y se crea en la otra
        $('formTipo').value = e.tipo;
        $('formUbicacion').value = e.ubicacion;
        $('formCapacidad').value = e.capacidadKg || '';
        $('formVencimiento').value = e.vencimiento ? String(e.vencimiento).substring(0, 10) : '';
        $('formArea').innerHTML = opcionesAreas(e.areaId);
        $('formActivo').checked = e.activo;
        actualizarSugerencias();
        abrirModal();
    }

    async function guardar(ev) {
        ev.preventDefault();
        alerta('alertaModal', '');

        const id = $('extintorId').value;
        const existente = id ? extintores.find(function (x) { return x.id === Number(id); }) : null;
        const cuerpo = {
            plantaId: Number($('formPlanta').value),
            ubicacion: $('formUbicacion').value.trim(),
            zona: existente ? existente.zona : null,
            tipo: $('formTipo').value,
            capacidadKg: $('formCapacidad').value ? Number($('formCapacidad').value) : null,
            vencimiento: $('formVencimiento').value || null,
            areaId: $('formArea').value ? Number($('formArea').value) : null,
            activo: $('formActivo').checked
        };

        const boton = $('btnGuardar');
        boton.disabled = true;
        try {
            await PR.enviarJson(id ? 'PUT' : 'POST', id || '', cuerpo);
            cerrarModal();
            await cargar();
        } catch (error) {
            alerta('alertaModal', error.message);
        } finally {
            boton.disabled = false;
        }
    }

    $('btnNuevo').addEventListener('click', abrirNuevo);
    $('btnCerrarModal').addEventListener('click', cerrarModal);
    $('formPlanta').addEventListener('change', actualizarSugerencias);
    $('formExtintor').addEventListener('submit', guardar);
    $('filtroPlanta').addEventListener('change', cargar);
    $('filtroEstado').addEventListener('change', dibujar);
    $('filtroArea').addEventListener('change', dibujar);
    $('filtroBuscar').addEventListener('input', dibujar);
    // Asignación rápida de área: guarda al cambiar el selector de la fila.
    $('tablaBody').addEventListener('change', async function (ev) {
        const select = ev.target.closest('[data-area-de]');
        if (!select) return;

        const e = extintores.find(function (x) { return x.id === Number(select.dataset.areaDe); });
        if (!e) return;

        alerta('alertaListado', '');
        select.disabled = true;
        try {
            await PR.enviarJson('PUT', String(e.id), {
                plantaId: e.plantaId, ubicacion: e.ubicacion, zona: e.zona, tipo: e.tipo, capacidadKg: e.capacidadKg,
                vencimiento: e.vencimiento ? String(e.vencimiento).substring(0, 10) : null,
                areaId: select.value ? Number(select.value) : null, activo: e.activo
            });
            const nueva = select.value ? Number(select.value) : null;
            e.areaId = nueva;
            const area = areas.find(function (a) { return a.id === nueva; });
            e.areaNombre = area ? area.nombre : null;
        } catch (error) {
            select.value = e.areaId ? String(e.areaId) : '';
            alerta('alertaListado', error.message);
        } finally {
            select.disabled = false;
        }
    });
    $('tablaBody').addEventListener('click', function (ev) {
        const boton = ev.target.closest('[data-editar]');
        if (boton) abrirEdicion(Number(boton.dataset.editar));
    });

    Promise.all([PR.api('catalogos'), PR.api('areas')]).then(function (res) {
        const catalogos = res[0];
        areas = res[1];
        $('filtroArea').innerHTML = '<option value="">Todas las áreas</option><option value="SIN">Sin área asignada</option>' +
            areas.map(function (a) { return '<option value="' + a.id + '">' + PR.escapeHtml(a.nombre) + '</option>'; }).join('');
        plantas = catalogos.plantas;
        PR.llenarSelectPlantas($('filtroPlanta'), plantas, true);
        PR.llenarSelectPlantas($('formPlanta'), plantas, false);
        $('formTipo').innerHTML = catalogos.tipos.map(function (t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
        return cargar();
    }).catch(function (error) { alerta('alertaListado', error.message); });
})();
