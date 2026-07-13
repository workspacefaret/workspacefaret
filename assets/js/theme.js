(function () {
    var STORAGE_KEY = 'workspace-theme';
    var toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    function applyIcon(theme) {
        var icon = toggle.querySelector('i');
        if (!icon) return;
        icon.className = theme === 'light' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }

    applyIcon(document.documentElement.getAttribute('data-theme') || 'dark');

    toggle.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {}
        applyIcon(next);
    });
})();
