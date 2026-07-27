(function () {
    var KEY = 'scroll:' + location.pathname + location.search;
    var MAX_ATTEMPTS = 20;
    var RETRY_MS = 100;
    var SAVE_DEBOUNCE_MS = 150;

    function restore() {
        var saved;
        try {
            saved = sessionStorage.getItem(KEY);
        } catch (e) {
            return;
        }
        if (saved === null) return;

        var y = parseInt(saved, 10);
        if (isNaN(y)) return;

        var attempts = 0;
        var apply = function () {
            window.scrollTo(0, y);
            attempts++;
        };
        apply();

        var interval = setInterval(function () {
            apply();
            if (attempts >= MAX_ATTEMPTS) {
                clearInterval(interval);
            }
        }, RETRY_MS);

        var stop = function () {
            clearInterval(interval);
            window.removeEventListener('wheel', stop);
            window.removeEventListener('touchmove', stop);
        };
        window.addEventListener('wheel', stop, { once: true });
        window.addEventListener('touchmove', stop, { once: true });
    }

    function save() {
        try {
            sessionStorage.setItem(KEY, String(window.scrollY));
        } catch (e) {}
    }

    var saveTimer;
    window.addEventListener('scroll', function () {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, SAVE_DEBOUNCE_MS);
    }, { passive: true });

    window.addEventListener('pagehide', save);

    restore();
})();
