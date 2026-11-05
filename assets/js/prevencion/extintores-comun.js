// Helpers compartidos por las pantallas de Inspección de Extintores (Prevención de Riesgos):
// inspección, panel, detalle e inventario. Sin dependencias externas.
(function () {
    const apiBaseUrl = (window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/') + 'prevencion/extintores/';

    const ESTADOS = {
        PENDIENTE: { label: 'Pendiente', badge: 'badge-warning' },
        CONFORME: { label: 'Conforme', badge: 'badge-success' },
        CON_HALLAZGOS: { label: 'Con hallazgos', badge: 'badge-danger' },
        // Estados derivados de la gestión del hallazgo (ver estadoConGestion).
        EN_PLANIFICACION: { label: 'En planificación', badge: 'badge-primary' },
        CONFORME_CORREGIDO: { label: 'Conforme (corregido)', badge: 'badge-success' },
        CONFORME_CON_OBS: { label: 'Conforme c/ observaciones', badge: 'badge-success' }
    };

    // Registros de la bitácora de gestión de un hallazgo (columna tipo de pr_acciones_correctivas).
    const ACCIONES = {
        PLANIFICADA: { label: 'Corrección planificada', icono: 'bi-calendar-check', clase: 'plan' },
        CORREGIDO: { label: 'Hallazgo corregido', icono: 'bi-wrench-adjustable', clase: 'ok' },
        CONFORME_CON_OBS: { label: 'Cerrado conforme con observaciones', icono: 'bi-clipboard-check', clase: 'ok' }
    };

    const ESTADO_POR_GESTION = {
        PLANIFICADA: 'EN_PLANIFICACION',
        CORREGIDO: 'CONFORME_CORREGIDO',
        CONFORME_CON_OBS: 'CONFORME_CON_OBS'
    };

    // Resultado de la inspección + último registro de gestión -> estado que se muestra.
    // Solo un hallazgo (CON_HALLAZGOS) cambia de estado con la gestión; la inspección guardada no se modifica.
    function estadoConGestion(resultado, gestionTipo) {
        return resultado === 'CON_HALLAZGOS' ? (ESTADO_POR_GESTION[gestionTipo] || 'CON_HALLAZGOS') : resultado;
    }

    // true si el estado es de un equipo conforme (inspección limpia, corregido o cerrado con observaciones).
    function esConforme(estado) {
        return estado === 'CONFORME' || estado === 'CONFORME_CORREGIDO' || estado === 'CONFORME_CON_OBS';
    }

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
        return extintor.inspeccionId ? estadoConGestion(extintor.inspeccionResultado, extintor.gestionTipo) : 'PENDIENTE';
    }

    // Fecha local de hoy como YYYY-MM-DD (para <input type="date">; toISOString daría la fecha UTC).
    function hoyISO() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // Días enteros transcurridos desde una fecha hasta hoy.
    function diasDesde(valor) {
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return null;
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        fecha.setHours(0, 0, 0, 0);
        return Math.round((hoy - fecha) / 86400000);
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
        const headers = { 'Content-Type': 'application/json' };
        // IIS rechaza PUT hacia PHP (405): en el admin se envía como POST con override, que api.php convierte de vuelta.
        if (window.PR_ADMIN && metodo === 'PUT') {
            metodo = 'POST';
            headers['X-HTTP-Method-Override'] = 'PUT';
        }
        return api(ruta, {
            method: metodo,
            headers: headers,
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

    // Aviso flotante de éxito/error (se cierra solo; los de error duran más). tipo: 'success' | 'error'.
    function toast(mensaje, tipo) {
        let caja = document.getElementById('prToasts');
        if (!caja) {
            caja = document.createElement('div');
            caja.id = 'prToasts';
            caja.className = 'pr-toasts';
            caja.setAttribute('aria-live', 'polite');
            document.body.appendChild(caja);
        }
        const t = document.createElement('div');
        t.className = 'pr-toast pr-toast-' + (tipo === 'success' ? 'success' : 'error');
        t.setAttribute('role', tipo === 'success' ? 'status' : 'alert');
        t.innerHTML = '<i class="bi ' + (tipo === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill') + '"></i>' +
            '<span>' + escapeHtml(mensaje) + '</span>';
        caja.appendChild(t);
        const cerrar = function () {
            t.classList.add('pr-toast-salir');
            setTimeout(function () { t.remove(); }, 250);
        };
        t.addEventListener('click', cerrar);
        setTimeout(cerrar, tipo === 'success' ? 4000 : 8000);
    }

    // ---------- Paginación en el navegador ----------
    // Las pantallas de Extintores traen la lista completa (filtros, KPIs y Excel trabajan sobre ella): aquí solo se recorta lo que se
    // dibuja. contenedor = donde va la barra; alCambiar = vuelve a dibujar la tabla; ancla = elemento al que volver al cambiar de página.
    const FILAS_POR_PAGINA = [25, 50, 100];

    function filasGuardadas() {
        try {
            const n = Number(localStorage.getItem('pr-extintores-filas'));
            return FILAS_POR_PAGINA.includes(n) ? n : FILAS_POR_PAGINA[0];
        } catch (e) { return FILAS_POR_PAGINA[0]; }
    }

    function crearPaginador(contenedor, alCambiar, ancla) {
        let pagina = 1;
        let porPagina = filasGuardadas();

        function dibujarBarra(total, desde, hasta, totalPaginas) {
            const resumen = '<span class="pr-pag-resumen">Mostrando <strong>' + desde + '–' + hasta + '</strong> de <strong>' + total + '</strong></span>';
            if (total <= FILAS_POR_PAGINA[0]) { contenedor.innerHTML = resumen; return; }

            contenedor.innerHTML = resumen + '<div class="pr-pag-controles">' +
                '<label class="pr-pag-filas">Filas <select data-pag-filas aria-label="Filas por página">' +
                FILAS_POR_PAGINA.map(function (n) { return '<option value="' + n + '"' + (n === porPagina ? ' selected' : '') + '>' + n + '</option>'; }).join('') +
                '</select></label>' +
                '<button type="button" data-pag="-1"' + (pagina === 1 ? ' disabled' : '') + '><i class="bi bi-chevron-left"></i>Anterior</button>' +
                '<span class="pr-pag-estado">Página ' + pagina + ' de ' + totalPaginas + '</span>' +
                '<button type="button" data-pag="1"' + (pagina === totalPaginas ? ' disabled' : '') + '>Siguiente<i class="bi bi-chevron-right"></i></button>' +
                '</div>';
        }

        // Devuelve solo las filas de la página actual y dibuja la barra. Si la lista se acorta, la página se ajusta sola.
        function cortar(lista) {
            const total = lista.length;
            if (!total) { contenedor.innerHTML = ''; return []; }
            const totalPaginas = Math.ceil(total / porPagina);
            pagina = Math.min(Math.max(pagina, 1), totalPaginas);
            const desde = (pagina - 1) * porPagina;
            const hasta = Math.min(desde + porPagina, total);
            dibujarBarra(total, desde + 1, hasta, totalPaginas);
            return lista.slice(desde, hasta);
        }

        contenedor.addEventListener('click', function (ev) {
            const boton = ev.target.closest('[data-pag]');
            if (!boton || boton.disabled) return;
            pagina += Number(boton.dataset.pag);
            alCambiar();
            if (ancla) ancla.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        contenedor.addEventListener('change', function (ev) {
            if (!ev.target.matches('[data-pag-filas]')) return;
            porPagina = Number(ev.target.value);
            try { localStorage.setItem('pr-extintores-filas', String(porPagina)); } catch (e) { /* sin storage */ }
            pagina = 1;
            alCambiar();
        });

        return { cortar: cortar, reiniciar: function () { pagina = 1; } };
    }

    window.PrExt = {
        toast, api, enviarJson, escapeHtml, formatearFecha, formatearFechaHora, formatearPeriodo, etiquetaTipo,
        estadoDe, estadoConGestion, esConforme, hoyISO, diasDesde, ACCIONES, diasParaVencer, badge, badgeEstado, badgeResultado, badgeVencimiento, leerError,
        plantaGuardada, guardarPlanta, llenarSelectPlantas, mostrarAlerta, crearPaginador, ESTADOS, RESULTADOS
    };
})();
