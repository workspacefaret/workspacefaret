// Helpers compartidos por las 3 pantallas de Pilas Cartulina Dimensionado
// (entrega, recepción y panel admin). Sin dependencias externas.
(function () {
    const apiBaseUrl = window.API_FORMULARIOS || 'https://api.faret.cl/formularios/api/';

    const ESTADOS_LABEL = {
        PENDIENTE_RECEPCION: 'Pendiente de recepción',
        RECIBIDO: 'Recibido',
        ANULADO: 'Anulado'
    };

    const ESTADOS_BADGE = {
        PENDIENTE_RECEPCION: 'badge-warning',
        RECIBIDO: 'badge-success',
        ANULADO: 'badge-danger'
    };

    function escapeHtml(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatearNumero(valor) {
        if (valor === null || valor === undefined || valor === '') return '-';
        const numero = Number(valor);
        if (!Number.isFinite(numero)) return String(valor);
        return numero.toLocaleString('es-CL', { maximumFractionDigits: 2 });
    }

    function formatearFechaHora(valor) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return String(valor);
        return fecha.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' })
            + ' ' + fecha.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
    }

    function formatearFecha(valor) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return String(valor);
        return fecha.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function etiquetaEstado(estado) {
        return ESTADOS_LABEL[estado] || estado || '-';
    }

    function claseBadgeEstado(estado) {
        return ESTADOS_BADGE[estado] || 'badge-primary';
    }

    // Formularios.Api responde los errores de validación como text/plain: leer siempre con text().
    async function leerError(response, mensajePorDefecto) {
        const texto = await response.text().catch(function () { return ''; });
        return texto || mensajePorDefecto;
    }

    // ---------- Catálogo de personas (sugerencias con "x" para eliminar) ----------
    // Un único catálogo para "Entregado por" y "Recibido por". Se carga una vez por página.
    const personas = {
        lista: null,
        cargando: null,

        async cargar() {
            if (this.lista) return this.lista;
            if (!this.cargando) {
                this.cargando = fetch(apiBaseUrl + 'pilas-cartulina/catalogos/personas')
                    .then(function (r) { return r.ok ? r.json() : []; })
                    .catch(function () { return []; })
                    .then(function (lista) { personas.lista = lista; return lista; });
            }
            return this.cargando;
        },

        async eliminar(id) {
            const response = await fetch(apiBaseUrl + 'pilas-cartulina/catalogos/personas/' + id, { method: 'DELETE' });
            if (!response.ok && response.status !== 404) {
                throw new Error(await leerError(response, 'No fue posible eliminar la sugerencia.'));
            }
            this.lista = (this.lista || []).filter(function (p) { return p.id !== id; });
        },

        // Vincula un input de texto con su contenedor .autocomplete-results.
        vincular(input, results) {
            const self = this;

            function render() {
                const texto = input.value.trim().toLowerCase();
                const coincidencias = (self.lista || [])
                    .filter(function (p) { return !texto || p.nombre.toLowerCase().includes(texto); })
                    .slice(0, 30);

                results.innerHTML = '';
                results.style.display = 'block';

                if (!coincidencias.length) {
                    const vacio = document.createElement('div');
                    vacio.className = 'autocomplete-item autocomplete-empty';
                    vacio.textContent = self.lista && self.lista.length
                        ? 'Sin coincidencias. Se usará el nombre escrito.'
                        : 'Escribe el nombre. Quedará guardado como sugerencia.';
                    results.appendChild(vacio);
                    return;
                }

                coincidencias.forEach(function (persona) {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item pc-item';
                    item.innerHTML = '<strong>' + escapeHtml(persona.nombre) + '</strong>'
                        + '<button type="button" class="pc-item-eliminar" title="Quitar sugerencia" aria-label="Quitar sugerencia"><i class="bi bi-x-lg"></i></button>';

                    item.addEventListener('click', function () {
                        input.value = persona.nombre;
                        results.style.display = 'none';
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    item.querySelector('.pc-item-eliminar').addEventListener('click', async function (event) {
                        event.stopPropagation();
                        try {
                            await self.eliminar(persona.id);
                            render();
                        } catch (error) {
                            alert(error.message);
                        }
                    });

                    results.appendChild(item);
                });
            }

            input.addEventListener('focus', async function () { await self.cargar(); render(); });
            input.addEventListener('input', function () { if (self.lista) render(); });

            document.addEventListener('click', function (event) {
                if (event.target !== input && !results.contains(event.target)) {
                    results.style.display = 'none';
                }
            });
        }
    };

    // ---------- Firma (canvas, mouse + touch) — mismo patrón que Desgaje ----------
    function crearFirma(canvas, btnLimpiar) {
        const ctx = canvas.getContext('2d');
        let dibujando = false;
        let tieneTrazo = false;

        function ajustar() {
            const ratio = window.devicePixelRatio || 1;
            const anchoCss = canvas.clientWidth || canvas.parentElement.clientWidth;
            const altoCss = 180;

            canvas.style.height = altoCss + 'px';
            canvas.width = anchoCss * ratio;
            canvas.height = altoCss * ratio;

            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0f172a';
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, anchoCss, altoCss);

            tieneTrazo = false;
        }

        function punto(event) {
            const rect = canvas.getBoundingClientRect();
            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        }

        canvas.addEventListener('pointerdown', function (event) {
            dibujando = true;
            tieneTrazo = true;
            const p = punto(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        });

        canvas.addEventListener('pointermove', function (event) {
            if (!dibujando) return;
            const p = punto(event);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        });

        ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (evento) {
            canvas.addEventListener(evento, function () { dibujando = false; });
        });

        if (btnLimpiar) btnLimpiar.addEventListener('click', ajustar);
        window.addEventListener('resize', ajustar);
        ajustar();

        return {
            tieneTrazo: function () { return tieneTrazo; },
            limpiar: ajustar,
            dataUrl: function () { return canvas.toDataURL('image/png'); }
        };
    }

    window.PilasCartulina = {
        apiBaseUrl: apiBaseUrl,
        escapeHtml: escapeHtml,
        formatearNumero: formatearNumero,
        formatearFecha: formatearFecha,
        formatearFechaHora: formatearFechaHora,
        etiquetaEstado: etiquetaEstado,
        claseBadgeEstado: claseBadgeEstado,
        leerError: leerError,
        personas: personas,
        crearFirma: crearFirma
    };
})();
