(function () {
    function crearCelda(doc, texto, esEncabezado) {
        const celda = doc.createElement(esEncabezado ? 'th' : 'td');
        celda.textContent = (texto === null || texto === undefined || texto === '') ? '-' : String(texto);
        return celda;
    }

    function imprimir(opciones) {
        const tituloModulo = opciones.tituloModulo || 'Reporte';
        const subtitulo = opciones.subtitulo || '';
        const encabezados = opciones.encabezados || [];
        const filas = opciones.filas || [];
        const filtrosTexto = opciones.filtrosTexto || 'Sin filtros aplicados — se imprimen todos los registros.';

        const ventana = window.open('', '_blank');
        if (!ventana) {
            alert('El navegador bloqueó la ventana de impresión. Habilita las ventanas emergentes para este sitio e inténtalo de nuevo.');
            return;
        }

        const doc = ventana.document;
        doc.title = tituloModulo;

        const estilo = doc.createElement('style');
        estilo.textContent = [
            '@page { size: landscape; margin: 12mm; }',
            '* { box-sizing: border-box; }',
            'body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 0; padding: 24px; }',
            '.print-header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }',
            '.print-header h1 { font-size: 18px; margin: 0 0 4px; }',
            '.print-header p { font-size: 12px; margin: 0; color: #475569; }',
            '.print-meta { font-size: 11px; color: #475569; text-align: right; }',
            '.print-meta p { margin: 0 0 2px; }',
            'table { width: 100%; border-collapse: collapse; font-size: 10px; }',
            'th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }',
            'th { background: #0f172a; color: #fff; }',
            'tbody tr:nth-child(even) td { background: #f1f5f9; }',
            '.print-footer { margin-top: 12px; font-size: 11px; color: #475569; text-align: right; }'
        ].join('\n');
        doc.head.appendChild(estilo);

        const header = doc.createElement('div');
        header.className = 'print-header';

        const bloqueTitulo = doc.createElement('div');
        const h1 = doc.createElement('h1');
        h1.textContent = 'Workspace Faret — ' + tituloModulo;
        const pSub = doc.createElement('p');
        pSub.textContent = subtitulo;
        bloqueTitulo.appendChild(h1);
        bloqueTitulo.appendChild(pSub);

        const meta = doc.createElement('div');
        meta.className = 'print-meta';
        const pFecha = doc.createElement('p');
        pFecha.textContent = 'Impreso: ' + new Date().toLocaleString('es-CL');
        const pFiltros = doc.createElement('p');
        pFiltros.textContent = filtrosTexto;
        meta.appendChild(pFecha);
        meta.appendChild(pFiltros);

        header.appendChild(bloqueTitulo);
        header.appendChild(meta);
        doc.body.appendChild(header);

        const tabla = doc.createElement('table');
        const thead = doc.createElement('thead');
        const trEncabezados = doc.createElement('tr');
        encabezados.forEach(function (texto) {
            trEncabezados.appendChild(crearCelda(doc, texto, true));
        });
        thead.appendChild(trEncabezados);
        tabla.appendChild(thead);

        const tbody = doc.createElement('tbody');
        filas.forEach(function (fila) {
            const tr = doc.createElement('tr');
            fila.forEach(function (valor) {
                tr.appendChild(crearCelda(doc, valor, false));
            });
            tbody.appendChild(tr);
        });
        tabla.appendChild(tbody);
        doc.body.appendChild(tabla);

        const footer = doc.createElement('div');
        footer.className = 'print-footer';
        footer.textContent = 'Total: ' + filas.length + ' registro' + (filas.length === 1 ? '' : 's');
        doc.body.appendChild(footer);

        ventana.focus();
        setTimeout(function () {
            ventana.print();
        }, 250);
    }

    function resumenFiltros(pares) {
        const activos = pares
            .filter(function (par) { return par[1]; })
            .map(function (par) { return par[0] + ': ' + par[1]; });
        return activos.length
            ? ('Filtros aplicados — ' + activos.join(' | '))
            : 'Sin filtros aplicados — se imprimen todos los registros.';
    }

    window.PlanificacionPrint = { imprimir: imprimir, resumenFiltros: resumenFiltros };
})();
