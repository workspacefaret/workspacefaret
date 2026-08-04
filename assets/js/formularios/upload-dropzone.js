document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.upload-card').forEach(card => {

        const input = card.querySelector('input[type=file]');

        if (!input) {
            return;
        }

        card.addEventListener('dragover', event => {
            event.preventDefault();
            card.classList.add('is-dragover');
        });

        card.addEventListener('dragleave', () => {
            card.classList.remove('is-dragover');
        });

        card.addEventListener('drop', event => {
            event.preventDefault();
            card.classList.remove('is-dragover');

            const archivos = event.dataTransfer?.files;

            if (!archivos || !archivos.length) {
                return;
            }

            input.files = archivos;
            input.dispatchEvent(new Event('change'));
        });
    });

    ['dragover', 'drop'].forEach(evento => {
        window.addEventListener(evento, event => {
            if (!event.target.closest('.upload-card')) {
                event.preventDefault();
            }
        });
    });
});
