(function () {
    // Paleta estadística general (se reutiliza cíclicamente cuando no hay color semántico definido)
    const PALETA = ['#2563eb', '#06b6d4', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6'];

    function escapeHtml(texto) {
        return String(texto ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatearPct(valor, total) {
        return total ? ((valor / total) * 100).toLocaleString('es-CL', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : '0,0';
    }

    function contarPorCampo(lista, campo) {
        return lista.reduce((acc, item) => {
            const valor = item[campo] || 'Sin dato';
            acc[valor] = (acc[valor] || 0) + 1;
            return acc;
        }, {});
    }

    // ---------------- Donut (SVG, sin dependencias) ----------------
    // opciones.colores -> mapa { etiqueta: color } para colores semánticos (ej. estados/prioridades)
    function renderDonut(id, datos, opciones = {}) {
        const contenedor = document.getElementById(id);
        if (!contenedor) return;

        const entries = Object.entries(datos).sort((a, b) => b[1] - a[1]);
        const total = entries.reduce((s, [, v]) => s + v, 0);
        const colorMapa = opciones.colores || {};

        const radio = 46, grosor = 18, cx = 60, cy = 60;
        const circunferencia = 2 * Math.PI * radio;
        let acumulado = 0;

        const segmentos = entries.map(([label, value], i) => {
            const frac = total ? value / total : 0;
            const largo = frac * circunferencia;
            const color = colorMapa[label] || PALETA[i % PALETA.length];
            const pct = formatearPct(value, total);
            const dasharray = `${largo} ${circunferencia - largo}`;
            const dashoffset = -acumulado;
            acumulado += largo;
            return { label, value, pct, color, dasharray, dashoffset };
        });

        const svgSegmentos = segmentos.map(s => `
            <circle cx="${cx}" cy="${cy}" r="${radio}" fill="none" stroke="${s.color}" stroke-width="${grosor}"
                stroke-dasharray="${s.dasharray}" stroke-dashoffset="${s.dashoffset}" transform="rotate(-90 ${cx} ${cy})">
                <title>${escapeHtml(s.label)}: ${s.value} (${s.pct}%)</title>
            </circle>
        `).join('');

        const leyenda = segmentos.map(s => `
            <li>
                <span class="donut-dot" style="background:${s.color}"></span>
                <span class="donut-label">${escapeHtml(s.label)}</span>
                <span class="donut-value">${s.value}<small>${s.pct}%</small></span>
            </li>
        `).join('');

        contenedor.innerHTML = `
            <div class="donut-wrap">
                <div class="donut-chart">
                    <svg viewBox="0 0 120 120">${svgSegmentos}</svg>
                    <div class="donut-center"><strong>${total}</strong><span>Total</span></div>
                </div>
                <ul class="donut-legend">${leyenda}</ul>
            </div>
        `;
    }

    // ---------------- Columnas verticales ----------------
    function renderColumnas(id, datos) {
        const contenedor = document.getElementById(id);
        if (!contenedor) return;

        const entries = Object.entries(datos).sort((a, b) => b[1] - a[1]);
        const max = Math.max(...entries.map(([, v]) => v), 1);

        contenedor.innerHTML = `<div class="col-chart">${entries.map(([label, value], i) => {
            const alto = Math.round((value / max) * 100);
            const color = PALETA[i % PALETA.length];
            return `
                <div class="col-item" title="${escapeHtml(label)}: ${value}">
                    <span class="col-value">${value}</span>
                    <div class="col-bar" style="height:${alto}%; background:${color};"></div>
                    <span class="col-label">${escapeHtml(label)}</span>
                </div>
            `;
        }).join('')}</div>`;
    }

    // ---------------- Ranking horizontal ----------------
    // opciones.total -> muestra porcentaje junto al valor
    // opciones.limite -> corta en Top N + "Ver todos (N)" para el resto (sin perder datos)
    function renderRanking(id, datos, opciones = {}) {
        const contenedor = document.getElementById(id);
        if (!contenedor) return;

        const total = opciones.total || 0;
        const limite = opciones.limite || 0;

        const entries = Object.entries(datos).sort((a, b) => b[1] - a[1]);
        const max = Math.max(...entries.map(x => x[1]), 1);

        const fila = ([label, value], i) => {
            const width = Math.round((value / max) * 100);
            const color = PALETA[i % PALETA.length];
            const pct = total ? ` <span class="chart-value-pct">${formatearPct(value, total)}%</span>` : '';

            return `
                <div class="chart-row">
                    <div class="chart-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div>
                    <div class="chart-bar">
                        <div class="chart-fill" style="width:${width}%; background:${color};"></div>
                    </div>
                    <div class="chart-value">${value}${pct}</div>
                </div>
            `;
        };

        const visibles = limite ? entries.slice(0, limite) : entries;
        const resto = limite ? entries.slice(limite) : [];

        let html = visibles.map(fila).join('');

        if (resto.length) {
            html += `
                <details class="dev-ver-todos">
                    <summary>Ver todos (${entries.length})</summary>
                    ${resto.map((e, i) => fila(e, visibles.length + i)).join('')}
                </details>
            `;
        }

        contenedor.innerHTML = html;
    }

    // ---------------- Línea / área (SVG, multi-serie) ----------------
    // claves: etiquetas del eje X (ya formateadas por el llamador)
    // series: [{ nombre, color, valores }], valores alineado con claves
    // Devuelve true si dibujó algo (false si no hay suficientes puntos) para que el llamador decida ocultar la sección
    function renderLinea(id, claves, series) {
        const contenedor = document.getElementById(id);
        if (!contenedor) return false;

        if (claves.length < 2 || !series.length) {
            contenedor.innerHTML = '';
            return false;
        }

        const max = Math.max(...series.flatMap(s => s.valores), 1);
        const ancho = 900, alto = 200, margen = 20;
        const pasoX = (ancho - margen * 2) / (claves.length - 1);
        const y = valor => alto - margen - (valor / max) * (alto - margen * 2);
        const puntos = valores => valores.map((v, i) => `${margen + i * pasoX},${y(v)}`).join(' ');
        const circulos = (valores, color, nombre) => valores.map((v, i) => `
            <circle cx="${margen + i * pasoX}" cy="${y(v)}" r="3.5" fill="${color}">
                <title>${escapeHtml(nombre)} · ${escapeHtml(claves[i])}: ${v}</title>
            </circle>
        `).join('');

        const svg = `
            <svg viewBox="0 0 ${ancho} ${alto}" preserveAspectRatio="none">
                ${series.map(s => `<polyline points="${puntos(s.valores)}" fill="none" stroke="${s.color}" stroke-width="2.5" />`).join('')}
                ${series.map(s => circulos(s.valores, s.color, s.nombre)).join('')}
            </svg>
        `;
        const eje = `<div class="evolucion-eje">${claves.map(c => `<span>${escapeHtml(c)}</span>`).join('')}</div>`;
        const leyenda = series.length > 1
            ? `<div class="evolucion-legend">${series.map(s => `<span><i style="background:${s.color}"></i>${escapeHtml(s.nombre)}</span>`).join('')}</div>`
            : '';

        contenedor.innerHTML = `<div class="evolucion-chart">${svg}${eje}</div>${leyenda}`;
        return true;
    }

    function setText(id, valor) {
        const elemento = document.getElementById(id);
        if (elemento) elemento.textContent = valor;
    }

    window.DashboardCharts = {
        PALETA, escapeHtml, formatearPct, contarPorCampo,
        renderDonut, renderColumnas, renderRanking, renderLinea, setText
    };
})();
