// Modal "Novedades de la versión": se muestra una sola vez por usuario y versión (localStorage) al abrir Operación.
// Los datos llegan filtrados por permisos desde PHP (config/novedades_version.php) en window.wsNovedades.
(function () {
    'use strict';

    var datos = window.wsNovedades;
    if (!datos || !datos.id || !datos.slides || !datos.slides.length) return;

    var clave = 'ws_novedades_visto_' + datos.userId;

    function leer() { try { return localStorage.getItem(clave); } catch (e) { return null; } }
    function marcarVisto() { try { localStorage.setItem(clave, datos.id); } catch (e) { /* sin almacenamiento: se mostrará de nuevo */ } }
    if (leer() === datos.id) return;

    function el(tag, clase, texto) {
        var n = document.createElement(tag);
        if (clase) n.className = clase;
        if (texto !== undefined) n.textContent = texto;
        return n;
    }

    var overlay = el('div', 'nov-overlay');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Novedades de la versión');

    var card = el('div', 'nov-card');
    var head = el('div', 'nov-head');
    head.appendChild(el('span', 'nov-badge', 'Novedades · ' + datos.id));
    head.appendChild(el('h2', null, '¡Workspace Faret se actualizó!'));
    head.appendChild(el('p', null, 'Esto es lo nuevo, en un minuto.'));
    var btnX = el('button', 'nov-x', '×');
    btnX.type = 'button';
    btnX.setAttribute('aria-label', 'Cerrar');
    head.appendChild(btnX);

    var viewport = el('div', 'nov-viewport');
    var track = el('div', 'nov-track');
    datos.slides.forEach(function (s) {
        var sec = el('section', 'nov-slide');
        var top = el('div', 'nov-slide-top');
        var ico = el('div', 'nov-ico');
        ico.appendChild(el('i', 'bi ' + (s.icono || 'bi-stars')));
        top.appendChild(ico);
        top.appendChild(el('h3', null, s.titulo));
        sec.appendChild(top);
        var ul = el('ul', 'nov-list');
        s.puntos.forEach(function (t) { ul.appendChild(el('li', null, t)); });
        sec.appendChild(ul);
        track.appendChild(sec);
    });
    viewport.appendChild(track);

    var foot = el('div', 'nov-foot');
    var dotsBox = el('div', 'nov-dots');
    var dots = datos.slides.map(function (_, i) {
        var d = el('button', 'nov-dot');
        d.type = 'button';
        d.setAttribute('aria-label', 'Ir a la ventana ' + (i + 1));
        d.addEventListener('click', function () { ir(i); });
        dotsBox.appendChild(d);
        return d;
    });
    var nav = el('div', 'nov-nav');
    var prev = el('button', 'nov-btn', 'Anterior');
    var next = el('button', 'nov-btn primario', 'Siguiente');
    prev.type = next.type = 'button';
    nav.appendChild(prev);
    nav.appendChild(next);
    foot.appendChild(dotsBox);
    foot.appendChild(nav);

    card.appendChild(head);
    card.appendChild(viewport);
    card.appendChild(foot);
    overlay.appendChild(card);

    var i = 0;
    var ultimo = datos.slides.length - 1;

    function ir(n) {
        i = Math.max(0, Math.min(ultimo, n));
        track.style.transform = 'translateX(-' + (i * 100) + '%)';
        dots.forEach(function (d, k) { d.classList.toggle('activo', k === i); });
        prev.style.visibility = i === 0 ? 'hidden' : 'visible';
        next.textContent = i === ultimo ? 'Entendido' : 'Siguiente';
        track.children[i].scrollTop = 0;
    }

    function teclas(e) {
        if (e.key === 'Escape') cerrar();
        else if (e.key === 'ArrowRight') ir(i + 1);
        else if (e.key === 'ArrowLeft') ir(i - 1);
    }

    function cerrar() {
        document.removeEventListener('keydown', teclas);
        overlay.remove();
        marcarVisto();
    }

    btnX.addEventListener('click', cerrar);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) cerrar(); });
    prev.addEventListener('click', function () { ir(i - 1); });
    next.addEventListener('click', function () { if (i === ultimo) cerrar(); else ir(i + 1); });
    document.addEventListener('keydown', teclas);

    document.body.appendChild(overlay);
    ir(0);
    next.focus();
})();
