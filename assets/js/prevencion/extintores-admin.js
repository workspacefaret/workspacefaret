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
            dibujarTabla();
        } catch (error) {
            filas = [];
            dibujarKpis();
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
