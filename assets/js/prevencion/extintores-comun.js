// Helpers compartidos por las pantallas de Inspección de Extintores (Prevención de Riesgos):
// inspección, panel, detalle e inventario. Sin dependencias externas.
(function () {
    const apiBaseUrl = (window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/') + 'prevencion/extintores/';

    const ESTADOS = {
        PENDIENTE: { label: 'Pendiente', badge: 'badge-warning' },
        CONFORME: { label: 'Conforme', badge: 'badge-success' },
        CON_HALLAZGOS: { label: 'Con hallazgos', badge: 'badge-danger' }
    };

    const RESULTADOS = {
        SI: { label: 'Sí', badge: 'badge-success' },
        NO: { label: 'No', badge: 'badge-danger' },
        NA: { label: 'No aplica', badge: 'badge-primary' }
    };

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatearFecha(valor) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return String(valor);
        return fecha.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function formatearFechaHora(valor) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return String(valor);
        return formatearFecha(valor) + ' ' + fecha.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
    }

    // "2026-10" -> "octubre 2026"
    function formatearPeriodo(periodo) {
        const m = /^(\d{4})-(\d{2})$/.exec(periodo || '');
        if (!m) return periodo || '-';
        return new Date(Number(m[1]), Number(m[2]) - 1, 1).toLocaleDateString('es-CL', { month: 'long', year: 'numeric' });
    }

    // "PQS 10 kg" / "CO2 2 kg" / "K"
    function etiquetaTipo(extintor) {
        const kg = extintor.capacidadKg;
        return extintor.tipo + (kg ? ' ' + String(Number(kg)).replace('.', ',') + ' kg' : '');
    }

    // Estado de la inspección del periodo consultado.
    function estadoDe(extintor) {
        return extintor.inspeccionId ? extintor.inspeccionResultado : 'PENDIENTE';
    }

    // Días hasta el vencimiento (negativo = vencido). null si no hay fecha.
    function diasParaVencer(vencimiento) {
        if (!vencimiento) return null;
        const fecha = new Date(vencimiento);
        if (Number.isNaN(fecha.getTime())) return null;
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        return Math.round((fecha - hoy) / 86400000);
    }

    function badge(clase, texto) {
        return '<span class="badge ' + clase + '">' + escapeHtml(texto) + '</span>';
    }

    function badgeEstado(estado, etiqueta) {
        const e = ESTADOS[estado] || { label: estado || '-', badge: 'badge-primary' };
        return badge(e.badge, etiqueta || e.label);
    }

    function badgeResultado(resultado) {
        const r = RESULTADOS[resultado] || { label: resultado || '-', badge: 'badge-primary' };
        return badge(r.badge, r.label);
    }

    function badgeVencimiento(vencimiento) {
        const dias = diasParaVencer(vencimiento);
        if (dias === null) return '<span class="pr-muted">Sin fecha</span>';
        const texto = formatearFecha(vencimiento);
        if (dias < 0) return badge('badge-danger', 'Vencido ' + texto);
        if (dias <= 60) return badge('badge-warning', 'Vence ' + texto);
        return escapeHtml(texto);
    }

    // Formularios.Api responde los errores de validación como text/plain: leer siempre con text().
    async function leerError(response, mensajePorDefecto) {
        const texto = await response.text().catch(function () { return ''; });
        return texto || mensajePorDefecto;
    }

    // Pantallas de administración (window.PR_ADMIN): pasan por api.php, que valida sesión y permiso en el servidor y firma la
    // llamada a la API. El formulario público llama directo a los endpoints publico/*.
    function urlDe(ruta) {
        if (!window.PR_ADMIN) return apiBaseUrl + ruta;
        const partes = String(ruta).split('?');
        return '/modules/prevencion/extintores/api.php?r=' + encodeURIComponent(partes[0]) + (partes[1] ? '&' + partes[1] : '');
    }

    async function api(ruta, opciones) {
        const response = await fetch(urlDe(ruta), opciones);
        if (!response.ok) throw new Error(await leerError(response, 'No se pudo completar la operación (' + response.status + ').'));
        return response.status === 204 ? null : response.json();
    }

    function enviarJson(metodo, ruta, cuerpo) {
        return api(ruta, {
            method: metodo,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(cuerpo)
        });
    }

    // Última planta usada (comodidad por usuario; si el storage falla no pasa nada).
    function plantaGuardada() {
        try { return localStorage.getItem('pr-extintores-planta') || ''; } catch (e) { return ''; }
    }

    function guardarPlanta(id) {
        try { localStorage.setItem('pr-extintores-planta', String(id || '')); } catch (e) { /* sin storage */ }
    }

    function llenarSelectPlantas(select, plantas, opcionTodas) {
        select.innerHTML = (opcionTodas ? '<option value="">Todas las plantas</option>' : '') +
            plantas.map(function (p) { return '<option value="' + p.id + '">' + escapeHtml(p.nombre) + '</option>'; }).join('');
    }

    // Alerta del formulario móvil (.form-alert + success/error).
    function mostrarAlerta(el, mensaje, tipo) {
        el.textContent = mensaje || '';
        el.className = 'form-alert' + (tipo ? ' ' + tipo : '');
        el.style.display = mensaje ? 'block' : 'none';
    }

    window.PrExt = {
        api, enviarJson, escapeHtml, formatearFecha, formatearFechaHora, formatearPeriodo, etiquetaTipo,
        estadoDe, diasParaVencer, badge, badgeEstado, badgeResultado, badgeVencimiento, leerError,
        plantaGuardada, guardarPlanta, llenarSelectPlantas, mostrarAlerta, ESTADOS, RESULTADOS
    };
})();
