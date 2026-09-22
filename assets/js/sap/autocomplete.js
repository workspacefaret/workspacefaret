(function () {
    var DEBOUNCE_MS = 300;
    var MIN_CHARS = 3;
    var MAX_RECIENTES = 10;

    function claveRecientes(tipo) {
        return 'sap-recientes-' + tipo;
    }

    function leerRecientes(tipo) {
        try {
            var crudo = localStorage.getItem(claveRecientes(tipo));
            return crudo ? JSON.parse(crudo) : [];
        } catch (e) {
            return [];
        }
    }

    // Expuesto para que cada página llame esto inline cuando una ficha (cliente/proveedor/
    // artículo) cargó con éxito — así "recientes" refleja toda forma de llegar a una ficha
    // (autocomplete, búsqueda completa + clic, o escribir el código exacto), no solo el widget.
    window.SapAutocomplete = window.SapAutocomplete || {};

    window.SapAutocomplete.registrarReciente = function (tipo, valor, titulo, subtitulo) {
        if (!valor) {
            return;
        }

        try {
            var recientes = leerRecientes(tipo).filter(function (r) {
                return r.valor !== valor;
            });

            recientes.unshift({ valor: valor, titulo: titulo || valor, subtitulo: subtitulo || '' });
            localStorage.setItem(claveRecientes(tipo), JSON.stringify(recientes.slice(0, MAX_RECIENTES)));
        } catch (e) {
            // localStorage no disponible (modo privado, etc.): no-op, no es crítico.
        }
    };

    function crearLista(input) {
        var lista = document.createElement('div');
        lista.className = 'sap-autocomplete-list';
        lista.setAttribute('role', 'listbox');
        lista.id = input.id + '-sugerencias';
        lista.hidden = true;
        // .sap-search tiene overflow:hidden (recorta el botón dentro del pill), así que el
        // dropdown se ancla con position:fixed en <body> en vez de ser hijo del formulario,
        // para no quedar recortado por ese overflow de ningún ancestro.
        document.body.appendChild(lista);
        return lista;
    }

    function posicionar(input, lista) {
        var ancla = input.closest('.sap-search') || input;
        var r = ancla.getBoundingClientRect();
        lista.style.left = r.left + 'px';
        lista.style.top = (r.bottom + 6) + 'px';
        lista.style.width = r.width + 'px';
    }

    function iniciar(input) {
        var tipo = input.getAttribute('data-sap-autocomplete');
        var targetParam = input.getAttribute('data-sap-target');
        var lista = crearLista(input);
        var timer = null;
        var controlador = null;
        var items = [];
        var activo = -1;

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', lista.id);
        input.setAttribute('autocomplete', 'off');

        function empresaActual() {
            return new URLSearchParams(window.location.search).get('empresa') || 'FARET';
        }

        function ocultar() {
            lista.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            activo = -1;
        }

        function irA(valor) {
            var item = items[activo] || { valor: valor, titulo: valor, subtitulo: '' };

            window.SapAutocomplete.registrarReciente(tipo, item.valor, item.titulo, item.subtitulo);

            var params = new URLSearchParams(window.location.search);
            params.set('empresa', empresaActual());
            params.set(targetParam, item.valor);
            window.location.href = window.location.pathname + '?' + params.toString();
        }

        function pintar(nuevosItems, titulo) {
            items = nuevosItems;
            activo = -1;
            lista.innerHTML = '';

            if (titulo) {
                var encabezado = document.createElement('div');
                encabezado.className = 'sap-autocomplete-titulo';
                encabezado.textContent = titulo;
                lista.appendChild(encabezado);
            }

            if (items.length === 0) {
                ocultar();
                return;
            }

            items.forEach(function (item, indice) {
                var fila = document.createElement('div');
                fila.className = 'sap-autocomplete-item';
                fila.id = lista.id + '-opcion-' + indice;
                fila.setAttribute('role', 'option');
                fila.innerHTML =
                    '<span class="sap-autocomplete-item-titulo"></span>' +
                    (item.subtitulo ? '<span class="sap-autocomplete-item-sub"></span>' : '');
                fila.querySelector('.sap-autocomplete-item-titulo').textContent = item.titulo;

                if (item.subtitulo) {
                    fila.querySelector('.sap-autocomplete-item-sub').textContent = item.subtitulo;
                }

                fila.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    activo = indice;
                    irA(item.valor);
                });

                lista.appendChild(fila);
            });

            posicionar(input, lista);
            lista.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function marcarActivo(indice) {
            var opciones = lista.querySelectorAll('.sap-autocomplete-item');
            opciones.forEach(function (op) {
                op.classList.remove('is-active');
            });

            if (indice >= 0 && opciones[indice]) {
                opciones[indice].classList.add('is-active');
                input.setAttribute('aria-activedescendant', opciones[indice].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function mostrarRecientes() {
            if (input.value.trim() !== '') {
                return;
            }

            pintar(leerRecientes(tipo), 'Tus recientes');
        }

        function buscar(texto) {
            if (controlador) {
                controlador.abort();
            }

            controlador = new AbortController();

            fetch('/modules/sap/buscar.php?tipo=' + encodeURIComponent(tipo) + '&texto=' + encodeURIComponent(texto) + '&empresa=' + encodeURIComponent(empresaActual()), {
                signal: controlador.signal,
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    pintar((json && json.data) || []);
                })
                .catch(function (e) {
                    if (e.name !== 'AbortError') {
                        ocultar();
                    }
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var texto = input.value.trim();

            if (texto.length < MIN_CHARS) {
                texto === '' ? mostrarRecientes() : ocultar();
                return;
            }

            timer = setTimeout(function () { buscar(texto); }, DEBOUNCE_MS);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim() === '') {
                mostrarRecientes();
            }
        });

        input.addEventListener('keydown', function (e) {
            if (lista.hidden || items.length === 0) {
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activo = Math.min(activo + 1, items.length - 1);
                marcarActivo(activo);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activo = Math.max(activo - 1, 0);
                marcarActivo(activo);
            } else if (e.key === 'Enter' && activo >= 0) {
                e.preventDefault();
                irA(items[activo].valor);
            } else if (e.key === 'Escape') {
                ocultar();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target !== input && !lista.contains(e.target)) {
                ocultar();
            }
        });

        // Reposicionar (no cerrar) en scroll/resize: al usar capture:true en window, esto
        // también dispara con el scroll INTERNO de la propia lista (overflow-y:auto) — cerrarla
        // ahí era el bug real ("el scroll desaparece"): la lista se cerraba sola apenas el
        // usuario intentaba hacer scroll dentro de ella para ver más resultados.
        function reposicionarSiAbierta() {
            if (!lista.hidden) {
                posicionar(input, lista);
            }
        }

        window.addEventListener('scroll', reposicionarSiAbierta, true);
        window.addEventListener('resize', reposicionarSiAbierta);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sap-autocomplete]').forEach(iniciar);
    });
})();
