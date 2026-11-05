// Sidebar plegable: submódulos tipo acordeón (vertical), barra de íconos con submenú flotante (horizontal)
// y panel deslizante en móvil. Mejora el HTML existente de includes/sidebar.php sin cambiar sus links ni permisos.
(function () {
    'use strict';

    var sidebar = document.querySelector('.sidebar');
    var menu = sidebar && sidebar.querySelector('.menu');
    if (!menu) return;

    var root = document.documentElement;
    var K_MINI = 'ws-sidebar';          // 'mini' | 'full'
    var K_GRUPOS = 'ws-sidebar-grupos'; // { "<href del módulo>": true|false }
    var movil = window.matchMedia('(max-width: 900px)');

    function leer(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function guardar(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } }

    var estadoGrupos = {};
    try { estadoGrupos = JSON.parse(leer(K_GRUPOS) || '{}') || {}; } catch (e) { estadoGrupos = {}; }

    // Texto de cada link dentro de <span class="menu-label"> (permite ocultarlo en la barra de íconos).
    Array.prototype.forEach.call(menu.querySelectorAll('a'), function (a) {
        var texto = a.textContent.replace(/\s+/g, ' ').trim();
        a.setAttribute('data-label', texto);
        Array.prototype.forEach.call(Array.prototype.slice.call(a.childNodes), function (n) {
            if (n.nodeType === 3 && n.textContent.trim()) {
                var s = document.createElement('span');
                s.className = 'menu-label';
                s.textContent = n.textContent.replace(/\s+/g, ' ').trim();
                a.replaceChild(s, n);
            } else if (n.nodeType === 3) {
                a.removeChild(n);
            }
        });
    });

    // Página actual: el link interno cuyo href es el prefijo más largo de la ruta.
    var ruta = window.location.pathname;
    var actual = null;
    Array.prototype.forEach.call(menu.querySelectorAll('a[href^="/"]'), function (a) {
        var h = a.getAttribute('href');
        if (a.target === '_blank') return;
        if (ruta.indexOf(h) === 0 && (!actual || h.length > actual.getAttribute('href').length)) actual = a;
    });
    if (actual) actual.classList.add('active');

    // Cada módulo con submenú pasa a ser un grupo: [link + flecha] y el submenú plegable.
    var grupos = [];
    Array.prototype.forEach.call(menu.querySelectorAll('.menu-submenu'), function (sub) {
        var padre = sub.previousElementSibling;
        if (!padre || padre.tagName !== 'A') return;

        var grupo = document.createElement('div');
        grupo.className = 'menu-group';
        var cabecera = document.createElement('div');
        cabecera.className = 'menu-group-head';
        var envoltorio = document.createElement('div');
        envoltorio.className = 'menu-sub-wrap';
        envoltorio.setAttribute('data-label', padre.getAttribute('data-label'));

        menu.insertBefore(grupo, padre);
        cabecera.appendChild(padre);
        envoltorio.appendChild(sub);
        grupo.appendChild(cabecera);
        grupo.appendChild(envoltorio);

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'menu-toggle';
        boton.innerHTML = '<i class="bi bi-chevron-down"></i>';
        cabecera.appendChild(boton);

        var id = padre.getAttribute('href');
        var contieneActual = !!(actual && grupo.contains(actual));
        var abierto = contieneActual || estadoGrupos[id] === true;
        grupo.classList.toggle('abierto', abierto);
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        boton.setAttribute('aria-label', 'Mostrar u ocultar submódulos de ' + padre.getAttribute('data-label'));

        boton.addEventListener('click', function () {
            var ahora = !grupo.classList.contains('abierto');
            grupo.classList.toggle('abierto', ahora);
            boton.setAttribute('aria-expanded', ahora ? 'true' : 'false');
            estadoGrupos[id] = ahora;
            guardar(K_GRUPOS, JSON.stringify(estadoGrupos));
        });

        // Barra de íconos: el submenú flota a la derecha, a la altura de su módulo.
        grupo.addEventListener('mouseenter', function () { colocarFlotante(grupo, cabecera, envoltorio); });
        grupo.addEventListener('focusin', function () { colocarFlotante(grupo, cabecera, envoltorio); });
        grupos.push(grupo);
    });

    function esMini() { return root.getAttribute('data-sidebar') === 'mini' && !movil.matches; }

    function colocarFlotante(grupo, cabecera, envoltorio) {
        if (!esMini()) return;
        var top = cabecera.getBoundingClientRect().top;
        var alto = envoltorio.scrollHeight + 16;
        envoltorio.style.setProperty('--fly-top', Math.max(8, Math.min(top, window.innerHeight - alto - 8)) + 'px');
    }

    // Tooltips solo en la barra de íconos (allí no se ve el texto).
    function sincronizarTitulos() {
        Array.prototype.forEach.call(menu.querySelectorAll('.menu > a, .menu-group-head > a'), function (a) {
            if (esMini()) a.setAttribute('title', a.getAttribute('data-label'));
            else a.removeAttribute('title');
        });
    }

    function aplicarMini(mini) {
        if (mini) root.setAttribute('data-sidebar', 'mini'); else root.removeAttribute('data-sidebar');
        sincronizarTitulos();
    }

    aplicarMini(leer(K_MINI) === 'mini');
    movil.addEventListener('change', function () { root.classList.remove('sb-open'); sincronizarTitulos(); });

    // Móvil: fondo oscuro que cierra el panel.
    var fondo = document.createElement('div');
    fondo.className = 'sb-backdrop';
    document.body.appendChild(fondo);
    function cerrarPanel() { root.classList.remove('sb-open'); }
    fondo.addEventListener('click', cerrarPanel);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarPanel(); });
    sidebar.addEventListener('click', function (e) {
        if (movil.matches && e.target.closest('a[href]')) cerrarPanel();
    });

    // Sin sesión no hay botón para volver atrás: se ignora el modo íconos guardado.
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('sidebarToggle')) aplicarMini(false);
    });

    // El botón vive en el topbar (se carga después de este script): delegación de eventos.
    document.addEventListener('click', function (e) {
        var b = e.target.closest('#sidebarToggle');
        if (!b) return;
        if (movil.matches) {
            root.classList.toggle('sb-open');
        } else {
            var mini = !esMini();
            aplicarMini(mini);
            guardar(K_MINI, mini ? 'mini' : 'full');
        }
    });
})();
