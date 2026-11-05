// Gestión de hallazgos en el detalle de una inspección de extintor: bitácora de acciones correctivas y formulario para
// planificar la corrección, marcarla como corregida o cerrarla como conforme con observaciones.
// La inspección guardada nunca se edita: cada acción es un registro nuevo y el estado es el del último registro.
(function () {
    const PR = window.PrExt;
    const $ = function (id) { return document.getElementById(id); };

    const OPCIONES = [
        { tipo: 'PLANIFICADA', titulo: 'Planificar corrección', texto: 'Aún no se corrige: define una fecha.', icono: 'bi-calendar-check' },
        { tipo: 'CORREGIDO', titulo: 'Marcar como corregido', texto: 'Se corrigió: el equipo queda conforme.', icono: 'bi-wrench-adjustable' },
        { tipo: 'CONFORME_CON_OBS', titulo: 'Conforme con observaciones', texto: 'El hallazgo no afecta el funcionamiento.', icono: 'bi-clipboard-check' }
    ];

    const TEXTOS = {
        PLANIFICADA: { etiqueta: 'Corrección planificada', ayuda: '¿Qué se hará y quién se encargará?', fecha: 'Fecha comprometida' },
        CORREGIDO: { etiqueta: 'Acción tomada', ayuda: '¿Qué se hizo para corregir el hallazgo?', fecha: 'Fecha de la corrección' },
        CONFORME_CON_OBS: { etiqueta: 'Motivo y observaciones', ayuda: '¿Por qué el hallazgo no afecta el funcionamiento del equipo?', fecha: 'Fecha del cierre' }
    };

    let config = null;       // { inspeccion, puedeGestionar, usuario, alCambiar }
    let acciones = [];
    let tipoElegido = 'PLANIFICADA';

    function alerta(mensaje) {
        const el = $('alertaGestion');
        el.textContent = mensaje || '';
        el.className = 'admin-alert admin-alert-error' + (mensaje ? '' : ' hidden');
    }

    function ultima() { return acciones.length ? acciones[acciones.length - 1] : null; }
    function estaCerrado() { const u = ultima(); return !!u && u.tipo !== 'PLANIFICADA'; }

    // ---------- Contexto: criterios con hallazgo ----------

    function dibujarCriterios() {
        const fallidos = config.inspeccion.resultados.filter(function (r) { return r.resultado === 'NO'; });
        $('gestionCriterios').innerHTML = fallidos.map(function (r) {
            return '<li><span class="pr-criterio-num">' + r.orden + '</span><div><strong>' + PR.escapeHtml(r.criterio) + '</strong>' +
                '<small>' + PR.escapeHtml(r.observacion || 'Sin observación') + '</small></div></li>';
        }).join('');
    }

    // ---------- Bitácora ----------

    function item(clase, icono, titulo, meta, texto, extra) {
        return '<li class="pr-timeline-item pr-timeline-' + clase + '"><span class="pr-timeline-icono"><i class="bi ' + icono + '"></i></span>' +
            '<div class="pr-timeline-cuerpo"><div class="pr-timeline-titulo"><strong>' + titulo + '</strong>' + (extra || '') + '</div>' +
            '<small>' + meta + '</small>' + (texto ? '<p>' + PR.escapeHtml(texto) + '</p>' : '') + '</div></li>';
    }

    function dibujarBitacora() {
        const i = config.inspeccion;
        const n = i.resultados.filter(function (r) { return r.resultado === 'NO'; }).length;
        let html = item('alerta', 'bi-exclamation-triangle', 'Hallazgo detectado',
            PR.escapeHtml(PR.formatearFechaHora(i.fechaInspeccion)) + ' · ' + PR.escapeHtml(i.usuario),
            n + (n === 1 ? ' criterio en «No»' : ' criterios en «No»'));

        const ult = ultima();
        html += acciones.map(function (a) {
            const def = PR.ACCIONES[a.tipo] || { label: a.tipo, icono: 'bi-dot', clase: 'plan' };
            let detalle = '';
            let extra = '';
            if (a.tipo === 'PLANIFICADA') {
                detalle = 'Compromiso: ' + PR.formatearFecha(a.fechaCompromiso);
                const vencido = a === ult && PR.diasParaVencer(a.fechaCompromiso) < 0;
                if (vencido) extra = PR.badge('badge-danger', 'Plazo vencido');
            } else {
                detalle = 'Realizado el ' + PR.formatearFecha(a.fechaAccion);
            }
            return item(def.clase, def.icono, def.label,
                PR.escapeHtml(detalle) + ' · registrado ' + PR.escapeHtml(PR.formatearFechaHora(a.creadoEn)) + ' por ' + PR.escapeHtml(a.usuario),
                a.descripcion, extra);
        }).join('');

        $('gestionBitacora').innerHTML = html;
    }

    // ---------- Formulario / estado ----------

    function dibujarAccion() {
        const cont = $('gestionAccion');
        const u = ultima();

        if (estaCerrado()) {
            const def = PR.ACCIONES[u.tipo];
            cont.innerHTML = '<div class="pr-cierre"><i class="bi bi-patch-check-fill"></i><div><strong>Hallazgo cerrado</strong>' +
                '<p>' + PR.escapeHtml(def.label) + ' el ' + PR.escapeHtml(PR.formatearFecha(u.fechaAccion)) + ' por ' + PR.escapeHtml(u.usuario) + '. El equipo figura como conforme.</p>' +
                '<small>Si el problema reaparece, registra una nueva inspección.</small></div></div>';
            return;
        }

        if (!config.puedeGestionar) {
            cont.innerHTML = '<div class="pr-cierre pr-cierre-info"><i class="bi bi-info-circle-fill"></i><div><strong>Pendiente de gestión</strong>' +
                '<p>Solo los usuarios con nivel «Gestionar» pueden registrar acciones correctivas.</p></div></div>';
            return;
        }

        const t = TEXTOS[tipoElegido];
        const cierra = tipoElegido !== 'PLANIFICADA';
        const hoy = PR.hoyISO();
        const desde = String(config.inspeccion.fechaInspeccion).slice(0, 10);

        cont.innerHTML = '<form id="formGestion" class="pr-form-gestion" autocomplete="off" novalidate>' +
            '<h3 class="pr-subtitulo">Registrar acción</h3>' +
            '<div class="pr-opciones" role="radiogroup" aria-label="Tipo de acción">' + OPCIONES.map(function (o) {
                return '<button type="button" class="pr-opcion' + (o.tipo === tipoElegido ? ' is-active' : '') + '" role="radio" aria-checked="' + (o.tipo === tipoElegido) +
                    '" data-tipo="' + o.tipo + '"><i class="bi ' + o.icono + '"></i><strong>' + o.titulo + '</strong><small>' + o.texto + '</small></button>';
            }).join('') + '</div>' +
            '<div class="admin-form-field"><label for="gestDescripcion">' + t.etiqueta + '</label>' +
            '<textarea id="gestDescripcion" maxlength="1000" rows="4" placeholder="' + PR.escapeHtml(t.ayuda) + '" required></textarea></div>' +
            '<div class="admin-form-field"><label for="gestFecha">' + t.fecha + '</label>' +
            '<input type="date" id="gestFecha" value="' + hoy + '" ' + (cierra ? 'min="' + desde + '" max="' + hoy + '"' : 'min="' + hoy + '"') + ' required></div>' +
            (cierra ? '<p class="pr-aviso-cierre"><i class="bi bi-lock"></i>Al registrar, el hallazgo se cierra y el equipo pasa a «' +
                (tipoElegido === 'CORREGIDO' ? 'Conforme (corregido)' : 'Conforme c/ observaciones') + '». No se puede deshacer.</p>' : '') +
            '<div class="admin-form-actions"><button type="submit" class="admin-btn admin-btn-primary" id="gestGuardar"><i class="bi bi-check2-circle"></i>Registrar acción</button></div>' +
            '</form>';
    }

    function dibujarEstado() {
        const u = ultima();
        const estado = PR.estadoConGestion('CON_HALLAZGOS', u && u.tipo);
        $('badgeGestion').innerHTML = PR.badgeEstado(estado);
        config.alCambiar(u ? u.tipo : null);
    }

    function dibujarTodo() {
        dibujarEstado();
        dibujarBitacora();
        dibujarAccion();
    }

    // ---------- Eventos ----------

    async function registrar(ev) {
        ev.preventDefault();
        alerta('');
        const descripcion = $('gestDescripcion').value.trim();
        const fecha = $('gestFecha').value;
        if (!descripcion) { alerta('Escribe ' + (tipoElegido === 'PLANIFICADA' ? 'la corrección planificada.' : 'la acción tomada o el motivo.')); $('gestDescripcion').focus(); return; }
        if (!fecha) { alerta('Indica la fecha.'); $('gestFecha').focus(); return; }

        const boton = $('gestGuardar');
        boton.disabled = true;
        try {
            await PR.enviarJson('POST', 'inspecciones/' + config.inspeccion.id + '/acciones', {
                tipo: tipoElegido,
                descripcion: descripcion,
                fechaCompromiso: tipoElegido === 'PLANIFICADA' ? fecha : null,
                fechaAccion: tipoElegido === 'PLANIFICADA' ? null : fecha,
                usuario: config.usuario
            });
            acciones = await PR.api('inspecciones/' + config.inspeccion.id + '/acciones');
            tipoElegido = 'PLANIFICADA';
            dibujarTodo();
            PR.toast('Acción registrada correctamente.', 'success');
        } catch (error) {
            alerta(error.message);
            PR.toast('No se pudo registrar la acción.', 'error');
            boton.disabled = false;
        }
    }

    $('gestionAccion').addEventListener('click', function (ev) {
        const boton = ev.target.closest('[data-tipo]');
        if (!boton) return;
        const texto = $('gestDescripcion') ? $('gestDescripcion').value : '';
        tipoElegido = boton.dataset.tipo;
        alerta('');
        dibujarAccion();
        $('gestDescripcion').value = texto;   // al cambiar de opción no se pierde lo escrito
    });
    $('gestionAccion').addEventListener('submit', function (ev) {
        if (ev.target.id === 'formGestion') registrar(ev);
    });

    // inspeccion: detalle de la inspección (con resultados). Solo se muestra si tiene hallazgos.
    async function iniciar(opciones) {
        config = opciones;
        if (config.inspeccion.resultadoGeneral !== 'CON_HALLAZGOS') return;

        try {
            acciones = await PR.api('inspecciones/' + config.inspeccion.id + '/acciones');
        } catch (error) {
            $('seccionGestion').style.display = '';
            alerta(error.message);
            return;
        }
        dibujarCriterios();
        dibujarTodo();
        $('seccionGestion').style.display = '';
    }

    window.PrGestion = { iniciar: iniciar };
})();
