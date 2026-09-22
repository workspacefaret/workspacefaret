(function () {
    var CLAVE = 'portal-sap-empresa';

    try {
        var params = new URLSearchParams(window.location.search);

        if (!params.has('empresa')) {
            var guardada = localStorage.getItem(CLAVE);

            if (guardada) {
                params.set('empresa', guardada);
                window.location.replace(window.location.pathname + '?' + params.toString());
                return;
            }
        }
    } catch (e) {
        // localStorage no disponible (modo privado, etc.): se usa el valor por defecto del servidor.
    }

    document.addEventListener('DOMContentLoaded', function () {
        var selector = document.getElementById('selectorEmpresa');

        if (!selector) {
            return;
        }

        selector.addEventListener('change', function () {
            try {
                localStorage.setItem(CLAVE, selector.value);
            } catch (e) {
                // no-op
            }

            var params = new URLSearchParams(window.location.search);
            params.set('empresa', selector.value);
            window.location.href = window.location.pathname + '?' + params.toString();
        });
    });
})();
