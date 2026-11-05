// Cumplimiento mensual de brigadistas (pestaña «Brigadistas y áreas», nivel "gestionar"):
// extintores asignados vs. inspeccionados por cada brigadista en el periodo, con el detalle de lo que le falta.
(function () {
    const PR = window.PrExt;
    const $ = function (id) { return document.getElementById(id); };

    // Orden de la tabla: primero lo que requiere atención.
    const ESTADOS = {
        INCUMPLE: { orden: 0, html: PR.badge('badge-danger', 'No cumple'), barra: 'pr-barra-mala' },
        EN_CURSO: { orden: 1, html: PR.badge('badge-warning', 'En curso'), barra: 'pr-barra-media' },
        CUMPLE: { orden: 2, html: PR.badge('badge-success', 'Cumple'), barra: '' },
        SIN_AREAS: { orden: 3, html: '<span class="pr-chip pr-chip-neutro">Sin áreas asignadas</span>', barra: '' },
        SIN_EXTINTORES: { orden: 4, html: '<span class="pr-chip pr-chip-neutro">Áreas sin extintores</span>', barra: '' },
        NO_EVALUADO: { orden: 5, html: '<span class="pr-chip pr-chip-neutro">No evaluado</span>', barra: '' }
    };
    const MEDIBLES = ['CUMPLE', 'EN_CURSO', 'INCUMPLE'];

    let datos = null;                 // respuesta de brigadistas/cumplimiento
    const abiertos = new Set();       // brigadistas con el detalle desplegado

    function alerta(mensaje) {
        const el = $('alertaCumplimiento');
        el.textContent = mensaje || '';
        el.className = 'admin-alert admin-alert-error' + (mensaje ? '' : ' hidden');
    }

    function ordenados() {
        return datos.brigadistas.slice().sort(function (a, b) {
            return (ESTADOS[a.estado].orden - ESTADOS[b.estado].orden) || a.nombre.localeCompare(b.nombre, 'es');
        });
    }

    function dibujarResumen() {
        const medibles = datos.brigadistas.filter(function (b) { return MEDIBLES.includes(b.estado); });
        const cuenta = function (e) { return medibles.filter(function (b) { return b.estado === e; }).length; };
        const asignados = medibles.reduce(function (t, b) { return t + b.asignados; }, 0);
        const realizados = medibles.reduce(function (t, b) { return t + Math.min(b.realizados, b.asignados); }, 0);

        $('cumplKpiEvaluados').textContent = medibles.length;
        $('cumplKpiCumplen').textContent = cuenta('CUMPLE');

        // Mes cerrado: lo que falta es incumplimiento; mes en curso: todavía hay plazo.
        const cerrado = datos.periodoCerrado;
        $('cumplKpiPendientesCard').style.setProperty('--kpi-color', cerrado ? '#ef4444' : '#f59e0b');
        $('cumplKpiPendientesIcono').className = 'bi ' + (cerrado ? 'bi-exclamation-octagon' : 'bi-hourglass-split');
        $('cumplKpiPendientesTitulo').textContent = cerrado ? 'No cumplen' : 'En curso';
        $('cumplKpiPendientes').textContent = cerrado ? cuenta('INCUMPLE') : cuenta('EN_CURSO');
        $('cumplKpiPendientesDetalle').textContent = cerrado ? 'Mes cerrado con extintores sin inspeccionar' : 'Aún les quedan extintores por inspeccionar';

        $('cumplKpiGlobal').textContent = asignados ? Math.round(realizados / asignados * 100) + '%' : '-';
        $('cumplKpiGlobalDetalle').textContent = realizados + ' de ' + asignados + ' extintores inspeccionados';

        // Avisos que explican por qué alguien no aparece medido.
        const sinAreas = datos.brigadistas.filter(function (b) { return b.estado === 'SIN_AREAS'; });
        const aviso = $('cumplAviso');
        let titulo = '';
        let detalle = '';
        if (!cerrado && medibles.length) {
            titulo = 'El mes sigue en curso';
            detalle = 'Los extintores pendientes todavía no cuentan como incumplimiento: se evalúa al cerrar el mes.';
        }
        if (sinAreas.length) {
            titulo = titulo || 'Hay brigadistas sin medir';
            detalle += (detalle ? ' ' : '') + sinAreas.length + (sinAreas.length === 1 ? ' brigadista activo no tiene' : ' brigadistas activos no tienen') +
                ' áreas asignadas, por eso no se puede calcular su cumplimiento: ' + sinAreas.map(function (b) { return b.nombre; }).join(', ') +
                '. Asígnales áreas o márcalos como «No evaluado».';
        }
        aviso.classList.toggle('hidden', !titulo);
        $('cumplAvisoTitulo').textContent = titulo;
        $('cumplAvisoDetalle').textContent = detalle;
    }

    function celdaAvance(b) {
        if (!MEDIBLES.includes(b.estado)) return '<span class="pr-muted">-</span>';
        return '<div class="pr-barra-fila"><div class="pr-barra ' + ESTADOS[b.estado].barra + '"><i style="width:' + b.porcentaje + '%"></i></div>' +
            '<strong>' + b.realizados + ' / ' + b.asignados + '</strong></div>';
    }

    function detallePendientes(b) {
        if (!b.extintoresPendientes.length) {
            return '<p class="pr-sin-info pr-sin-info-compacto"><i class="bi bi-check-circle"></i> No tiene extintores pendientes en este periodo.</p>';
        }
        return '<ul class="pr-lista pr-pendientes">' + b.extintoresPendientes.map(function (e) {
            const otro = e.inspeccionadoPor
                ? '<span class="pr-chip pr-chip-neutro" title="Otra persona ya lo inspeccionó este mes (área compartida)">Inspeccionado por ' + PR.escapeHtml(e.inspeccionadoPor) + '</span>'
                : '';
            return '<li><div><strong>N° ' + e.numero + ' · ' + PR.escapeHtml(e.ubicacion) + '</strong>' +
                '<small>' + PR.escapeHtml(e.plantaNombre) + ' · ' + PR.escapeHtml(e.areaNombre || 'Sin área') + '</small></div>' + otro + '</li>';
        }).join('') + '</ul>';
    }

    function dibujarTabla() {
        if (!datos.brigadistas.length) {
            $('tablaCumplimiento').innerHTML = '<tr><td colspan="5" class="admin-empty">No hay brigadistas activos.</td></tr>';
            return;
        }

        $('tablaCumplimiento').innerHTML = ordenados().map(function (b) {
            const medible = MEDIBLES.includes(b.estado);
            const abierto = abiertos.has(b.brigadistaId);
            const fila = '<tr class="' + (medible ? 'admin-row-clickable' : '') + (abierto ? ' is-open' : '') + '"' +
                (medible ? ' data-brigadista="' + b.brigadistaId + '" tabindex="0"' : '') + '>' +
                '<td><div class="pr-persona"><span class="pr-avatar">' + PR.escapeHtml(iniciales(b.nombre)) + '</span>' + PR.escapeHtml(b.nombre) + '</div></td>' +
                '<td>' + celdaAvance(b) + '</td>' +
                '<td>' + (medible ? (b.pendientes ? '<strong>' + b.pendientes + '</strong>' : '0') : '<span class="pr-muted">-</span>') + '</td>' +
                '<td>' + (medible ? '<strong>' + b.porcentaje + '%</strong>' : '<span class="pr-muted">-</span>') + '</td>' +
                '<td>' + ESTADOS[b.estado].html + (medible ? ' <i class="bi ' + (abierto ? 'bi-chevron-up' : 'bi-chevron-down') + ' pr-chevron"></i>' : '') + '</td></tr>';
            const detalle = abierto
                ? '<tr class="pr-fila-detalle"><td colspan="5">' + detallePendientes(b) + '</td></tr>'
                : '';
            return fila + detalle;
        }).join('');
    }

    function iniciales(nombre) {
        const p = String(nombre).trim().split(/\s+/);
        return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
    }

    async function cargar() {
        alerta('');
        $('tablaCumplimiento').innerHTML = '<tr><td colspan="5" class="admin-empty">Cargando...</td></tr>';
        try {
            datos = await PR.api('brigadistas/cumplimiento?periodo=' + encodeURIComponent($('cumplPeriodo').value));
        } catch (error) {
            datos = { brigadistas: [], periodoCerrado: false };
            alerta(error.message);
        }
        dibujarResumen();
        dibujarTabla();
    }

    // ---------- Excel (mismo estilo de exportación del resto del portal: HTML .xls) ----------

    function exportarExcel() {
        if (!datos || !datos.brigadistas.length) { alerta('No hay datos para exportar.'); return; }

        const etiqueta = { INCUMPLE: 'No cumple', EN_CURSO: 'En curso', CUMPLE: 'Cumple', SIN_AREAS: 'Sin áreas asignadas', SIN_EXTINTORES: 'Áreas sin extintores', NO_EVALUADO: 'No evaluado' };
        const encabezados = ['Brigadista', 'Estado', 'Asignados', 'Inspeccionados', 'Pendientes', 'Cumplimiento', 'Extintores pendientes'];
        const filas = ordenados().map(function (b) {
            const medible = MEDIBLES.includes(b.estado);
            const celdas = [
                b.nombre, etiqueta[b.estado], medible ? b.asignados : '', medible ? b.realizados : '', medible ? b.pendientes : '',
                medible ? b.porcentaje + '%' : '',
                b.extintoresPendientes.map(function (e) { return 'N° ' + e.numero + ' ' + e.ubicacion + ' (' + e.plantaNombre + ')'; }).join(' | ')
            ];
            return '<tr>' + celdas.map(function (v) { return '<td>' + PR.escapeHtml(v) + '</td>'; }).join('') + '</tr>';
        }).join('');

        const periodo = $('cumplPeriodo').value;
        const html = '<html><head><meta charset="UTF-8"><style>' +
            'table { border-collapse: collapse; font-family: Arial; font-size: 11px; }' +
            'th { background: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #334155; padding: 8px; }' +
            'td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }' +
            '.title { font-size: 20px; font-weight: bold; color: #0f172a; }' +
            '.subtitle { color: #475569; }' +
            '</style></head><body><table>' +
            '<tr><td colspan="' + encabezados.length + '" class="title">Cumplimiento de brigadistas - ' + PR.escapeHtml(PR.formatearPeriodo(periodo)) + '</td></tr>' +
            '<tr><td colspan="' + encabezados.length + '" class="subtitle">Workspace Faret - ' + new Date().toLocaleString('es-CL') +
            (datos.periodoCerrado ? '' : ' - Mes en curso (los pendientes aún no son incumplimiento)') + '</td></tr>' +
            '<tr></tr><tr>' + encabezados.map(function (h) { return '<th>' + PR.escapeHtml(h.toUpperCase()) + '</th>'; }).join('') + '</tr>' +
            filas + '</table></body></html>';

        const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'cumplimiento-brigadistas-' + periodo + '.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function alternarDetalle(fila) {
        const id = Number(fila.dataset.brigadista);
        if (abiertos.has(id)) abiertos.delete(id); else abiertos.add(id);
        dibujarTabla();
    }

    $('tablaCumplimiento').addEventListener('click', function (ev) {
        const fila = ev.target.closest('tr[data-brigadista]');
        if (fila) alternarDetalle(fila);
    });
    $('tablaCumplimiento').addEventListener('keydown', function (ev) {
        const fila = ev.target.closest('tr[data-brigadista]');
        if (fila && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); alternarDetalle(fila); }
    });
    $('cumplPeriodo').addEventListener('change', function () {
        if ($('cumplPeriodo').value) { abiertos.clear(); cargar(); }
    });
    $('btnExportarCumplimiento').addEventListener('click', exportarExcel);

    PR.api('catalogos').then(function (catalogos) {
        $('cumplPeriodo').value = catalogos.periodoActual;
        return cargar();
    }).catch(function (error) { alerta(error.message); });
})();
