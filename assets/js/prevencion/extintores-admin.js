// Panel de inspecciones de extintores: consolidado mensual por planta, KPIs, filtros y Excel.
(function () {
    const PR = window.PrExt;
    const $ = function (id) { return document.getElementById(id); };

    let criterios = [];
    let periodoActual = '';
    let filas = [];      // consolidado del periodo/planta consultados
    let visibles = [];   // filas tras los filtros del lado del cliente

    function alertaListado(mensaje) {
        const el = $('alertaListado');
        el.textContent = mensaje || '';
        el.className = 'admin-alert admin-alert-error' + (mensaje ? '' : ' hidden');
    }

    function esVencido(f) {
        const dias = PR.diasParaVencer(f.vencimiento);
        return dias !== null && dias < 0;
    }

    function hallazgos(f) {
        return f.resultados.filter(function (r) { return r.resultado === 'NO'; }).length;
    }

    function dibujarKpis() {
        const cuenta = function (fn) { return filas.filter(fn).length; };
        $('kpiTotal').textContent = filas.length;
        $('kpiPendientes').textContent = cuenta(function (f) { return PR.estadoDe(f) === 'PENDIENTE'; });
        $('kpiConformes').textContent = cuenta(function (f) { return PR.estadoDe(f) === 'CONFORME'; });
        $('kpiHallazgos').textContent = cuenta(function (f) { return PR.estadoDe(f) === 'CON_HALLAZGOS'; });
        $('kpiVencidos').textContent = cuenta(esVencido);
        $('kpiPorVencer').textContent = cuenta(function (f) {
            const d = PR.diasParaVencer(f.vencimiento);
            return d !== null && d >= 0 && d <= 60;
        });
    }

    // ---------- Dashboard (reutiliza DashboardCharts; depende solo de periodo y planta, no de los filtros de la tabla) ----------

    const COLORES_ESTADO = { 'Conformes': '#22c55e', 'Con hallazgos': '#ef4444', 'Pendientes': '#f59e0b' };
    const SIN_INFO = '<p class="pr-sin-info">Sin información</p>';

    function pintar(id, html) { $(id).innerHTML = html; }

    function esPorVencer(f) {
        const d = PR.diasParaVencer(f.vencimiento);
        return d !== null && d >= 0 && d <= 60;
    }

    function listaTarjetas(items) {
        return '<ul class="pr-lista">' + items.join('') + '</ul>';
    }

    function dibujarDashboard() {
        const total = filas.length;
        const estado = function (f) { return PR.estadoDe(f); };
        const conformes = filas.filter(function (f) { return estado(f) === 'CONFORME'; }).length;
        const conHallazgos = filas.filter(function (f) { return estado(f) === 'CON_HALLAZGOS'; }).length;
        const pendientes = filas.filter(function (f) { return estado(f) === 'PENDIENTE'; }).length;

        // Avance del mes
        const inspeccionados = conformes + conHallazgos;
        const pct = total ? Math.round((inspeccionados / total) * 100) : 0;
        $('avanceTexto').textContent = total
            ? inspeccionados + ' de ' + total + ' extintores inspeccionados en ' + PR.formatearPeriodo($('filtroPeriodo').value)
            : 'Sin información';
        $('avanceBarra').style.width = pct + '%';
        $('avancePct').textContent = total ? pct + '%' : '-';

        if (!total) {
            ['chartEstados', 'chartTipos', 'chartCriterios', 'chartPlantas', 'chartAreas', 'listaVencimientos', 'listaHallazgos']
                .forEach(function (id) { pintar(id, SIN_INFO); });
            return;
        }

        // Estado de los extintores
        window.DashboardCharts.renderDonut('chartEstados', { 'Conformes': conformes, 'Con hallazgos': conHallazgos, 'Pendientes': pendientes }, { colores: COLORES_ESTADO });

        // Por tipo
        window.DashboardCharts.renderDonut('chartTipos', window.DashboardCharts.contarPorCampo(filas, 'tipo'));

        // Criterios con más hallazgos (respuestas «No» del periodo)
        const porCriterio = {};
        filas.forEach(function (f) {
            f.resultados.forEach(function (r) {
                if (r.resultado !== 'NO') return;
                const c = criterios.find(function (x) { return x.id === r.criterioId; });
                const nombre = c ? c.orden + '. ' + c.nombre : 'Criterio ' + r.orden;
                porCriterio[nombre] = (porCriterio[nombre] || 0) + 1;
            });
        });
        if (Object.keys(porCriterio).length) window.DashboardCharts.renderRanking('chartCriterios', porCriterio, { limite: 6 });
        else pintar('chartCriterios', SIN_INFO);

        // Cumplimiento por planta (barra apilada: conformes / con hallazgos / pendientes)
        const plantas = {};
        filas.forEach(function (f) {
            const p = plantas[f.plantaNombre] || (plantas[f.plantaNombre] = { c: 0, h: 0, p: 0 });
            const e = estado(f);
            if (e === 'CONFORME') p.c++; else if (e === 'CON_HALLAZGOS') p.h++; else p.p++;
        });
        const ancho = function (n, t) { return (n / t * 100).toFixed(2) + '%'; };
        pintar('chartPlantas', '<div class="pr-plantas">' + Object.keys(plantas).map(function (nombre) {
            const p = plantas[nombre];
            const t = p.c + p.h + p.p;
            return '<div class="pr-planta">' +
                '<div class="pr-planta-cab"><span>' + PR.escapeHtml(nombre) + '</span><strong>' + (p.c + p.h) + ' / ' + t + '</strong></div>' +
                '<div class="pr-planta-barra" title="Conformes ' + p.c + ' · Con hallazgos ' + p.h + ' · Pendientes ' + p.p + '">' +
                (p.c ? '<i style="width:' + ancho(p.c, t) + ';background:#22c55e"></i>' : '') +
                (p.h ? '<i style="width:' + ancho(p.h, t) + ';background:#ef4444"></i>' : '') +
                (p.p ? '<i style="width:' + ancho(p.p, t) + ';background:#f59e0b"></i>' : '') +
                '</div></div>';
        }).join('') + '</div>' +
            '<div class="pr-leyenda"><span><i style="background:#22c55e"></i>Conformes</span><span><i style="background:#ef4444"></i>Con hallazgos</span><span><i style="background:#f59e0b"></i>Pendientes</span></div>');

        // Pendientes por área
        const pendPorArea = {};
        filas.filter(function (f) { return estado(f) === 'PENDIENTE'; }).forEach(function (f) {
            const a = f.areaNombre || 'Sin área';
            pendPorArea[a] = (pendPorArea[a] || 0) + 1;
        });
        if (Object.keys(pendPorArea).length) window.DashboardCharts.renderRanking('chartAreas', pendPorArea, { total: pendientes, limite: 8 });
        else pintar('chartAreas', SIN_INFO);

        // Vencidos y por vencer
        const venc = filas.filter(function (f) { return esVencido(f) || esPorVencer(f); })
            .sort(function (a, b) { return PR.diasParaVencer(a.vencimiento) - PR.diasParaVencer(b.vencimiento); });
        pintar('listaVencimientos', venc.length ? listaTarjetas(venc.slice(0, 8).map(function (f) {
            return '<li><div><strong>N° ' + f.numero + ' · ' + PR.escapeHtml(f.ubicacion) + '</strong>' +
                '<small>' + PR.escapeHtml(f.plantaNombre) + ' · ' + PR.escapeHtml(PR.etiquetaTipo(f)) + '</small></div>' +
                PR.badgeVencimiento(f.vencimiento) + '</li>';
        })) + (venc.length > 8 ? '<p class="pr-mas">y ' + (venc.length - 8) + ' más. Usa el filtro «Vencido» en la tabla.</p>' : '') : SIN_INFO);

        // Hallazgos
        const hall = filas.filter(function (f) { return estado(f) === 'CON_HALLAZGOS' && f.inspeccionId; });
        pintar('listaHallazgos', hall.length ? listaTarjetas(hall.slice(0, 8).map(function (f) {
            return '<li><a class="pr-lista-link" href="/modules/prevencion/extintores/admin/detalle.php?id=' + f.inspeccionId + '">' +
                '<div><strong>N° ' + f.numero + ' · ' + PR.escapeHtml(f.ubicacion) + '</strong>' +
                '<small>' + PR.escapeHtml(f.plantaNombre) + ' · ' + PR.escapeHtml(f.inspeccionUsuario || '-') + '</small></div>' +
                '<span class="badge badge-danger">' + hallazgos(f) + (hallazgos(f) === 1 ? ' hallazgo' : ' hallazgos') + '</span></a></li>';
        })) + (hall.length > 8 ? '<p class="pr-mas">y ' + (hall.length - 8) + ' más. Usa el filtro «Con hallazgos» en la tabla.</p>' : '') : SIN_INFO);
    }

    function filtrar() {
        const estado = $('filtroEstado').value;
        const tipo = $('filtroTipo').value;
        const texto = $('filtroBuscar').value.trim().toLowerCase();

        visibles = filas.filter(function (f) {
            if (estado === 'VENCIDO' ? !esVencido(f) : (estado && PR.estadoDe(f) !== estado)) return false;
            if (tipo && f.tipo !== tipo) return false;
            if (!texto) return true;
            return [f.ubicacion, f.numero, f.plantaNombre, f.areaNombre || '', f.inspeccionUsuario || ''].join(' ').toLowerCase().includes(texto);
        });
    }

    function dibujarTabla() {
        filtrar();
        $('badgeCantidad').textContent = visibles.length + (visibles.length === 1 ? ' extintor' : ' extintores');

        if (!visibles.length) {
            $('tablaBody').innerHTML = '<tr><td colspan="10" class="admin-empty">No hay extintores con esos filtros.</td></tr>';
            return;
        }

        $('tablaBody').innerHTML = visibles.map(function (f) {
            const n = hallazgos(f);
            const celdaHallazgos = f.inspeccionId ? (n ? '<strong>' + n + '</strong>' : '0') : '-';
            return '<tr' + (f.inspeccionId ? ' class="admin-row-clickable" data-id="' + f.inspeccionId + '"' : '') + '>' +
                '<td>' + PR.escapeHtml(f.plantaNombre) + '</td>' +
                '<td>' + f.numero + '</td>' +
                '<td>' + PR.escapeHtml(f.ubicacion) + '</td>' +
                '<td>' + PR.escapeHtml(f.areaNombre || '-') + '</td>' +
                '<td>' + PR.escapeHtml(PR.etiquetaTipo(f)) + '</td>' +
                '<td>' + PR.badgeVencimiento(f.vencimiento) + '</td>' +
                '<td>' + PR.badgeEstado(PR.estadoDe(f)) + '</td>' +
                '<td>' + celdaHallazgos + '</td>' +
                '<td>' + PR.escapeHtml(f.inspeccionUsuario || '-') + '</td>' +
                '<td>' + PR.escapeHtml(f.inspeccionFecha ? PR.formatearFechaHora(f.inspeccionFecha) : '-') + '</td></tr>';
        }).join('');
    }

    async function cargar() {
        alertaListado('');
        $('tablaBody').innerHTML = '<tr><td colspan="10" class="admin-empty">Cargando...</td></tr>';
        if (!$('filtroPeriodo').value) $('filtroPeriodo').value = periodoActual;
        const periodo = $('filtroPeriodo').value;
        const planta = $('filtroPlanta').value;

        try {
            filas = await PR.api('consolidado?periodo=' + encodeURIComponent(periodo) +
                (planta ? '&plantaId=' + encodeURIComponent(planta) : ''));
            dibujarKpis();
            dibujarDashboard();
            dibujarTabla();
        } catch (error) {
            filas = [];
            dibujarKpis();
            dibujarDashboard();
            dibujarTabla();
            alertaListado(error.message);
        }
    }

    // ---------- Excel (mismo estilo de exportación del resto del portal: HTML .xls) ----------

    function exportarExcel() {
        if (!visibles.length) { alertaListado('No hay extintores para exportar.'); return; }

        const fijas = ['Planta', 'N°', 'Ubicación', 'Área', 'Tipo', 'Vencimiento', 'Estado', 'Inspector', 'Fecha inspección'];
        const encabezados = fijas.concat(criterios.map(function (c) { return c.orden + '. ' + c.nombre; }), ['Observaciones']);

        const filasHtml = visibles.map(function (f) {
            const porCriterio = {};
            f.resultados.forEach(function (r) { porCriterio[r.criterioId] = r; });
            const observaciones = f.resultados.filter(function (r) { return r.observacion; })
                .map(function (r) { return r.orden + '. ' + r.observacion; }).join(' | ');

            const celdas = [
                f.plantaNombre, f.numero, f.ubicacion, f.areaNombre || '', PR.etiquetaTipo(f),
                f.vencimiento ? PR.formatearFecha(f.vencimiento) : '',
                (PR.ESTADOS[PR.estadoDe(f)] || {}).label || '',
                f.inspeccionUsuario || '', f.inspeccionFecha ? PR.formatearFechaHora(f.inspeccionFecha) : ''
            ].concat(criterios.map(function (c) {
                const r = porCriterio[c.id];
                if (!r) return '';
                return (PR.RESULTADOS[r.resultado] || { label: r.resultado }).label + (r.anio ? ' (' + r.anio + ')' : '');
            }), [observaciones]);

            return '<tr>' + celdas.map(function (v) { return '<td>' + PR.escapeHtml(v) + '</td>'; }).join('') + '</tr>';
        }).join('');

        const html = '<html><head><meta charset="UTF-8"><style>' +
            'table { border-collapse: collapse; font-family: Arial; font-size: 11px; }' +
            'th { background: #0f172a; color: #ffffff; font-weight: bold; border: 1px solid #334155; padding: 8px; }' +
            'td { border: 1px solid #cbd5e1; padding: 7px; vertical-align: top; }' +
            '.title { font-size: 20px; font-weight: bold; color: #0f172a; }' +
            '.subtitle { color: #475569; }' +
            '</style></head><body><table>' +
            '<tr><td colspan="' + encabezados.length + '" class="title">Inspección de extintores - ' + PR.escapeHtml(PR.formatearPeriodo($('filtroPeriodo').value)) + '</td></tr>' +
            '<tr><td colspan="' + encabezados.length + '" class="subtitle">Workspace Faret - ' + new Date().toLocaleString('es-CL') + '</td></tr>' +
            '<tr></tr><tr>' + encabezados.map(function (h) { return '<th>' + PR.escapeHtml(h.toUpperCase()) + '</th>'; }).join('') + '</tr>' +
            filasHtml + '</table></body></html>';

        const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'inspeccion-extintores-' + $('filtroPeriodo').value + '.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    // ---------- Inicio ----------

    $('tablaBody').addEventListener('click', function (ev) {
        const fila = ev.target.closest('tr[data-id]');
        if (fila) window.location.href = '/modules/prevencion/extintores/admin/detalle.php?id=' + fila.dataset.id;
    });
    // Los KPI de estado filtran la tabla y llevan hasta ella.
    function filtrarDesdeKpi(tarjeta) {
        $('filtroEstado').value = tarjeta.dataset.filtro;
        dibujarTabla();
        $('seccionTabla').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('.pr-kpi-click').forEach(function (t) {
        t.addEventListener('click', function () { filtrarDesdeKpi(t); });
        t.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); filtrarDesdeKpi(t); }
        });
    });
    $('filtroPeriodo').addEventListener('change', cargar);
    $('filtroPlanta').addEventListener('change', cargar);
    ['filtroEstado', 'filtroTipo'].forEach(function (id) { $(id).addEventListener('change', dibujarTabla); });
    $('filtroBuscar').addEventListener('input', dibujarTabla);
    $('btnExportarExcel').addEventListener('click', exportarExcel);
    $('btnLimpiarFiltros').addEventListener('click', function () {
        $('filtroEstado').value = '';
        $('filtroTipo').value = '';
        $('filtroBuscar').value = '';
        dibujarTabla();
    });

    PR.api('catalogos').then(function (catalogos) {
        criterios = catalogos.criterios;
        periodoActual = catalogos.periodoActual;
        $('filtroPeriodo').value = catalogos.periodoActual;
        PR.llenarSelectPlantas($('filtroPlanta'), catalogos.plantas, true);
        $('filtroTipo').innerHTML = '<option value="">Todos</option>' +
            catalogos.tipos.map(function (t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
        return cargar();
    }).catch(function (error) { alertaListado(error.message); });
})();
