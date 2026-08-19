(function () {
    function crear(idContenedor) {
        const contenedor = document.getElementById(idContenedor);
        if (!contenedor) return null;

        const toggle = contenedor.querySelector('.ms-dropdown-toggle');
        const panel = contenedor.querySelector('.ms-dropdown-panel');
        const opciones = Array.from(contenedor.querySelectorAll('.ms-dropdown-option'));
        const etiquetaBase = toggle.dataset.msLabel || 'Todos';
        let onChange = null;

        function seleccionadas() {
            return opciones.filter(function (o) { return o.classList.contains('is-selected'); });
        }

        function actualizarToggle() {
            const sel = seleccionadas();
            toggle.textContent = sel.length
                ? sel.map(function (o) { return o.dataset.msTexto; }).join(', ')
                : etiquetaBase;
            toggle.classList.toggle('is-filtered', sel.length > 0);
        }

        function cerrarOtros() {
            document.querySelectorAll('.ms-dropdown-panel').forEach(function (p) {
                if (p !== panel) p.classList.add('hidden');
            });
        }

        toggle.addEventListener('click', function (evento) {
            evento.stopPropagation();
            cerrarOtros();
            panel.classList.toggle('hidden');
        });

        panel.addEventListener('click', function (evento) {
            evento.stopPropagation();
        });

        document.addEventListener('click', function () {
            panel.classList.add('hidden');
        });

        opciones.forEach(function (opcion) {
            opcion.addEventListener('click', function () {
                opcion.classList.toggle('is-selected');
                actualizarToggle();
                if (onChange) onChange();
            });
        });

        actualizarToggle();

        return {
            getValores: function () {
                return seleccionadas().map(function (o) { return o.dataset.msValor; });
            },
            limpiar: function () {
                opciones.forEach(function (o) { o.classList.remove('is-selected'); });
                actualizarToggle();
            },
            onChange: function (callback) {
                onChange = callback;
            }
        };
    }

    window.MultiSelectFiltro = { crear: crear };
})();
