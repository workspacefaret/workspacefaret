// Inspección de extintores (móvil, SIN login): brigadista -> lista por planta/área -> checklist del extintor elegido.
// Mismo archivo para ambas vistas: window.prExtintorId > 0 = checklist, 0 = lista.
// Usa solo los endpoints públicos de la API (publico/*): datos mínimos y registrar la inspección.
(function () {
    const PR = window.PrExt;
    const alerta = document.getElementById('formAlert');
    const URL_LISTA = '/modules/prevencion/extintores/inspeccion/';
    const CLAVE_BRIGADISTA = 'pr-extintores-brigadista';

    function brigadistaGuardado() {
        try { return localStorage.getItem(CLAVE_BRIGADISTA) || ''; } catch (e) { return ''; }
    }

    function guardarBrigadista(id) {
        try { localStorage.setItem(CLAVE_BRIGADISTA, String(id || '')); } catch (e) { /* sin storage */ }
    }

    function llenarBrigadistas(select, brigadistas, seleccionado) {
        select.innerHTML = '<option value="">Selecciona tu nombre</option>' + brigadistas.map(function (b) {
            return '<option value="' + b.id + '"' + (String(b.id) === String(seleccionado) ? ' selected' : '') + '>' + PR.escapeHtml(b.nombre) + '</option>';
        }).join('');
        if (!brigadistas.some(function (b) { return String(b.id) === String(seleccionado); })) select.value = '';
    }

    // ================= LISTA =================

    function iniciarLista() {
        const selectBrigadista = document.getElementById('selectBrigadista');
        const selectPlanta = document.getElementById('selectPlanta');
        const selectArea = document.getElementById('selectArea');
        const buscar = document.getElementById('buscarExtintor');
        const contenedor = document.getElementById('listaExtintores');
        const bloqueFiltros = document.getElementById('bloqueFiltros');
        let extintores = [];
        let filtro = 'PENDIENTES';
        let periodo = '';

        if (new URLSearchParams(window.location.search).get('ok')) {
            PR.mostrarAlerta(alerta, 'Inspección registrada correctamente.', 'success');
        }

        function dibujar() {
            const texto = buscar.value.trim().toLowerCase();
            const total = extintores.length;
            const hechos = extintores.filter(function (e) { return e.inspeccionado; }).length;

            document.getElementById('prProgreso').style.display = total ? 'block' : 'none';
            document.getElementById('prProgresoTexto').textContent = hechos + ' de ' + total + ' inspeccionados';
            document.getElementById('prProgresoPeriodo').textContent = PR.formatearPeriodo(periodo);
            document.getElementById('prProgresoRelleno').style.width = (total ? Math.round(hechos * 100 / total) : 0) + '%';

            const visibles = extintores.filter(function (e) {
                if (filtro === 'PENDIENTES' && e.inspeccionado) return false;
                if (!texto) return true;
                return (e.ubicacion + ' ' + e.numero + ' ' + e.tipo + ' ' + (e.areaNombre || '')).toLowerCase().includes(texto);
            });

            if (!visibles.length) {
                contenedor.innerHTML = '<p class="pr-muted">' + (total && filtro === 'PENDIENTES' && !texto
                    ? 'No quedan extintores pendientes este mes con estos filtros.'
                    : 'No hay extintores que coincidan.') + '</p>';
                return;
            }

            contenedor.innerHTML = visibles.map(function (e) {
                const area = e.areaNombre ? ' · ' + PR.escapeHtml(e.areaNombre) : '';
                return '<a class="pr-ext" href="' + URL_LISTA + '?e=' + e.id + '&b=' + encodeURIComponent(selectBrigadista.value) + '">' +
                    '<span class="pr-ext-num">' + e.numero + '</span>' +
                    '<span class="pr-ext-info"><strong>' + PR.escapeHtml(e.ubicacion) + '</strong>' +
                    '<small>' + PR.escapeHtml(PR.etiquetaTipo(e)) + area + '</small></span>' +
                    PR.badgeEstado(e.inspeccionado ? 'CONFORME' : 'PENDIENTE', e.inspeccionado ? 'Inspeccionado' : null) + '</a>';
            }).join('');
        }

        async function cargarExtintores() {
            if (!selectBrigadista.value) {
                extintores = [];
                bloqueFiltros.style.display = 'none';
                document.getElementById('prProgreso').style.display = 'none';
                contenedor.innerHTML = '<p class="pr-muted">Selecciona tu nombre para ver los extintores que te corresponden.</p>';
                return;
            }

            bloqueFiltros.style.display = '';
            contenedor.innerHTML = '<p class="pr-muted">Cargando extintores...</p>';
            const params = 'brigadistaId=' + encodeURIComponent(selectBrigadista.value) +
                (selectPlanta.value ? '&plantaId=' + encodeURIComponent(selectPlanta.value) : '') +
                (selectArea.value ? '&areaId=' + encodeURIComponent(selectArea.value) : '');
            try {
                extintores = await PR.api('publico/extintores?' + params);
                dibujar();
            } catch (error) {
                contenedor.innerHTML = '';
                PR.mostrarAlerta(alerta, error.message, 'error');
            }
        }

        // Las áreas disponibles dependen del brigadista (sus asignadas, o todas si no tiene restricción).
        async function cargarAreas() {
            selectArea.innerHTML = '<option value="">Todas mis áreas</option>';
            if (!selectBrigadista.value) return;
            try {
                const lista = await PR.api('publico/brigadistas/' + encodeURIComponent(selectBrigadista.value) + '/areas');
                selectArea.innerHTML = '<option value="">Todas mis áreas</option>' + lista.map(function (a) {
                    return '<option value="' + a.id + '">' + PR.escapeHtml(a.nombre) + '</option>';
                }).join('');
            } catch (error) {
                PR.mostrarAlerta(alerta, error.message, 'error');
            }
        }

        selectBrigadista.addEventListener('change', async function () {
            guardarBrigadista(selectBrigadista.value);
            await cargarAreas();
            cargarExtintores();
        });
        selectPlanta.addEventListener('change', function () {
            PR.guardarPlanta(selectPlanta.value);
            cargarExtintores();
        });
        selectArea.addEventListener('change', cargarExtintores);
        buscar.addEventListener('input', dibujar);
        document.getElementById('prFiltros').addEventListener('click', function (ev) {
            const chip = ev.target.closest('.pr-chip');
            if (!chip) return;
            filtro = chip.dataset.filtro;
            document.querySelectorAll('#prFiltros .pr-chip').forEach(function (c) { c.classList.toggle('is-active', c === chip); });
            dibujar();
        });

        PR.api('publico/catalogos').then(async function (catalogos) {
            periodo = catalogos.periodoActual;
            llenarBrigadistas(selectBrigadista, catalogos.brigadistas, brigadistaGuardado());
            selectPlanta.innerHTML = '<option value="">Todas las plantas</option>' + catalogos.plantas.map(function (p) {
                return '<option value="' + p.id + '">' + PR.escapeHtml(p.nombre) + '</option>';
            }).join('');
            const guardada = PR.plantaGuardada();
            if (catalogos.plantas.some(function (p) { return String(p.id) === guardada; })) selectPlanta.value = guardada;
            await cargarAreas();
            return cargarExtintores();
        }).catch(function (error) {
            contenedor.innerHTML = '';
            PR.mostrarAlerta(alerta, error.message, 'error');
        });
    }

    // ================= CHECKLIST =================

    function aplicaAlTipo(criterio, tipo) {
        if (!criterio.aplicaTipos) return true;
        return criterio.aplicaTipos.split(',').map(function (t) { return t.trim().toUpperCase(); }).includes(tipo.toUpperCase());
    }

    function opcionesAnio() {
        const actual = new Date().getFullYear();
        let html = '<option value="">Selecciona el año</option>';
        for (let a = actual; a >= actual - 12; a--) html += '<option value="' + a + '">' + a + '</option>';
        return html;
    }

    function dato(etiqueta, valor, extra) {
        return '<div class="pc-dato' + (extra || '') + '"><span>' + etiqueta + '</span><strong>' + valor + '</strong></div>';
    }

    // Se muestran siempre los 13 puntos. Los que no corresponden al tipo quedan fijos en "No aplica".
    function dibujarCriterio(c, tipo) {
        const aplica = aplicaAlTipo(c, tipo);
        const radios = [['SI', 'Sí', 'si'], ['NO', 'No', 'no'], ['NA', 'No aplica', 'na']].map(function (o) {
            const fijo = !aplica ? (o[0] === 'NA' ? ' checked disabled' : ' disabled') : '';
            return '<label class="pr-opcion"><input type="radio" name="c' + c.id + '" value="' + o[0] + '"' + fijo + '>' +
                '<span class="' + o[2] + '">' + o[1] + '</span></label>';
        }).join('');

        const nota = !aplica
            ? '<p class="pr-muted pr-noaplica">No aplica a un extintor ' + PR.escapeHtml(tipo) + ' (solo ' + PR.escapeHtml(c.aplicaTipos) + ').</p>'
            : '';

        return '<div class="pr-crit' + (aplica ? '' : ' is-na') + '" data-criterio="' + c.id + '" data-aplica="' + (aplica ? '1' : '0') +
            '" data-pide-anio="' + (c.pideAnio ? '1' : '0') + '">' +
            '<h3><span>' + c.orden + '</span>' + PR.escapeHtml(c.nombre) + '</h3>' +
            '<div class="pr-opciones">' + radios + '</div>' + nota +
            (c.pideAnio && aplica ? '<div class="field pr-extra pr-anio" hidden><label>Año</label><select>' + opcionesAnio() + '</select></div>' : '') +
            (aplica ? '<div class="field pr-extra pr-obs" hidden><label>¿Qué pasó? (obligatorio)</label>' +
            '<textarea rows="2" maxlength="500" placeholder="Describe el problema"></textarea></div>' : '') +
            '</div>';
    }

    function actualizarExtras(bloque) {
        const marcado = bloque.querySelector('input:checked');
        const valor = marcado ? marcado.value : '';
        const obs = bloque.querySelector('.pr-obs');
        const anio = bloque.querySelector('.pr-anio');
        if (obs) obs.hidden = valor !== 'NO';
        if (anio) anio.hidden = valor !== 'SI';
        bloque.classList.remove('is-error');
    }

    async function iniciarChecklist() {
        const form = document.getElementById('inspeccionForm');
        const lista = document.getElementById('listaCriterios');
        const selectBrigadista = document.getElementById('selectBrigadista');
        const params = new URLSearchParams(window.location.search);
        let extintor = null;
        let catalogos;

        try {
            catalogos = await PR.api('publico/catalogos');
        } catch (error) {
            PR.mostrarAlerta(alerta, error.message, 'error');
            return;
        }

        llenarBrigadistas(selectBrigadista, catalogos.brigadistas, params.get('b') || brigadistaGuardado());
        form.style.display = '';

        function dibujarExtintor() {
            document.getElementById('resumenExtintor').innerHTML =
                dato('Planta', PR.escapeHtml(extintor.plantaNombre)) +
                dato('N°', extintor.numero, '') +
                dato('Tipo', PR.escapeHtml(PR.etiquetaTipo(extintor))) +
                dato('Ubicación', PR.escapeHtml(extintor.ubicacion), ' pc-dato-full') +
                (extintor.areaNombre ? dato('Área', PR.escapeHtml(extintor.areaNombre), ' pc-dato-full') : '');

            const aviso = document.getElementById('avisoYaInspeccionado');
            if (extintor.inspeccionado) {
                aviso.textContent = 'Este extintor ya fue inspeccionado este mes. Si continúas, se registrará una nueva inspección.';
                aviso.style.display = 'block';
            } else {
                aviso.style.display = 'none';
            }

            lista.innerHTML = catalogos.criterios.map(function (c) { return dibujarCriterio(c, extintor.tipo); }).join('');
            document.getElementById('bloqueChecklist').style.display = '';
        }

        // Carga (o recarga) el extintor para el brigadista elegido: el servidor valida que sea de sus áreas.
        async function cargarExtintor() {
            PR.mostrarAlerta(alerta, '', '');
            document.getElementById('bloqueChecklist').style.display = 'none';
            extintor = null;
            if (!selectBrigadista.value) return;

            try {
                extintor = await PR.api('publico/extintores/' + window.prExtintorId + '?brigadistaId=' + encodeURIComponent(selectBrigadista.value));
                dibujarExtintor();
            } catch (error) {
                PR.mostrarAlerta(alerta, error.message, 'error');
            }
        }

        selectBrigadista.addEventListener('change', function () {
            guardarBrigadista(selectBrigadista.value);
            cargarExtintor();
        });

        lista.addEventListener('change', function (ev) {
            const bloque = ev.target.closest('.pr-crit');
            if (bloque) actualizarExtras(bloque);
        });

        // Al corregir el motivo, el bloque deja de marcarse en rojo.
        lista.addEventListener('input', function (ev) {
            const bloque = ev.target.closest('.pr-crit');
            if (bloque) bloque.classList.remove('is-error');
        });

        document.getElementById('btnTodoCumple').addEventListener('click', function () {
            lista.querySelectorAll('.pr-crit').forEach(function (bloque) {
                if (bloque.dataset.aplica !== '1') return;
                bloque.querySelector('input[value="SI"]').checked = true;
                actualizarExtras(bloque);
            });
        });

        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            PR.mostrarAlerta(alerta, '', '');

            if (!selectBrigadista.value || !extintor) {
                PR.mostrarAlerta(alerta, 'Selecciona el brigadista a cargo de la inspección.', 'error');
                return;
            }

            const resultados = [];
            let primerError = null;

            catalogos.criterios.forEach(function (c) {
                const bloque = lista.querySelector('.pr-crit[data-criterio="' + c.id + '"]');
                if (!bloque || bloque.dataset.aplica !== '1') {
                    resultados.push({ criterioId: c.id, resultado: 'NA', observacion: null, anio: null });
                    return;
                }

                const marcado = bloque.querySelector('input:checked');
                const observacion = bloque.querySelector('textarea').value.trim();
                const selectAnio = bloque.querySelector('.pr-anio select');
                const anio = selectAnio ? selectAnio.value : '';

                const invalido = !marcado
                    || (marcado.value === 'NO' && !observacion)
                    || (marcado.value === 'SI' && c.pideAnio && !anio);

                if (invalido) {
                    bloque.classList.add('is-error');
                    if (!primerError) primerError = bloque;
                }

                resultados.push({
                    criterioId: c.id,
                    resultado: marcado ? marcado.value : '',
                    observacion: marcado && marcado.value === 'NO' ? observacion : null,
                    anio: marcado && marcado.value === 'SI' && anio ? Number(anio) : null
                });
            });

            if (primerError) {
                PR.mostrarAlerta(alerta, 'Revisa los criterios marcados en rojo: falta responder, el motivo del «No» o el año.', 'error');
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            const boton = document.getElementById('btnEnviar');
            boton.disabled = true;

            try {
                await PR.enviarJson('POST', 'publico/inspecciones', {
                    extintorId: extintor.id,
                    brigadistaId: Number(selectBrigadista.value),
                    resultados: resultados
                });
                window.location.href = URL_LISTA + '?ok=1';
            } catch (error) {
                PR.mostrarAlerta(alerta, error.message, 'error');
                boton.disabled = false;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });

        cargarExtintor();
    }

    if (window.prExtintorId > 0) iniciarChecklist(); else iniciarLista();
})();
