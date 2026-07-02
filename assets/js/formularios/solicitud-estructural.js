document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = document.getElementById('apiBaseUrl').value;

    const form = document.getElementById('solicitudEstructuralForm');
    const formAlert = document.getElementById('formAlert');

    const solicitanteSelect = document.getElementById('solicitanteId');

    const clienteSearchInput = document.getElementById('clienteSearch');
    const clienteIdInput = document.getElementById('clienteId');
    const clienteNuevoInput = document.getElementById('clienteNuevo');
    const clientesResults = document.getElementById('clientesResults');

    const cantidadMuestrasInput = document.getElementById('cantidadMuestras');
    const cantidadMuestrasError = document.getElementById('cantidadMuestrasError');

    const referenciaOtrosField = document.getElementById('referenciaOtrosField');
    const referenciaOtrosTexto = document.getElementById('referenciaOtrosTexto');

    const btnEnviar = document.getElementById('btnEnviarSolicitud');
    const adjuntosInput = document.getElementById('adjuntos');
    const adjuntosPreview = document.getElementById('adjuntosPreview');

    let solicitantes = [];
    let clienteSeleccionado = null;
    let clienteTimer = null;

    cargarSolicitantes();
    actualizarVisibilidadOtros();

    adjuntosInput?.addEventListener('change', () => {
        const archivos = Array.from(adjuntosInput.files);

        if (!archivos.length) {
            adjuntosPreview.innerHTML = '<span class="muted">No hay archivos seleccionados</span>';
            return;
        }

        adjuntosPreview.innerHTML = '';

        archivos.forEach(file => {
            const item = document.createElement('div');
            item.className = 'adjunto-item';
            item.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
            adjuntosPreview.appendChild(item);
        });
    });

    async function cargarSolicitantes() {
        try {
            const response = await fetch(`${apiBaseUrl}catalogos/solicitantes`);

            if (!response.ok) {
                throw new Error('No fue posible cargar los solicitantes.');
            }

            solicitantes = await response.json();

            solicitanteSelect.innerHTML = '';

            const optionDefault = document.createElement('option');
            optionDefault.value = '';
            optionDefault.textContent = 'Seleccione solicitante';
            solicitanteSelect.appendChild(optionDefault);

            solicitantes.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.nombre;
                solicitanteSelect.appendChild(option);
            });

        } catch (error) {
            mostrarAlerta('error', error.message);
        }
    }

    clienteSearchInput.addEventListener('focus', () => {
        mostrarOpcionNuevoCliente();
    });

    clienteSearchInput.addEventListener('input', () => {
        const search = clienteSearchInput.value.trim();

        clienteIdInput.value = '';
        clienteSeleccionado = null;

        clearTimeout(clienteTimer);

        if (search.length < 3) {
            mostrarOpcionNuevoCliente(search);
            return;
        }

        clienteTimer = setTimeout(() => buscarClientes(search), 300);
    });

    async function buscarClientes(search) {
        try {
            clientesResults.innerHTML = '';
            clientesResults.style.display = 'block';

            agregarOpcionNuevoCliente(search);

            const cargando = document.createElement('div');
            cargando.className = 'autocomplete-item autocomplete-loading';
            cargando.textContent = 'Buscando clientes...';
            clientesResults.appendChild(cargando);

            const response = await fetch(`${apiBaseUrl}catalogos/clientes?search=${encodeURIComponent(search)}`);

            if (!response.ok) {
                throw new Error('No fue posible buscar clientes.');
            }

            const clientes = await response.json();

            cargando.remove();

            if (!clientes || clientes.length === 0) {
                const item = document.createElement('div');
                item.className = 'autocomplete-item autocomplete-empty';
                item.textContent = 'Sin clientes encontrados';
                clientesResults.appendChild(item);
                return;
            }

            clientes.slice(0, 30).forEach(cliente => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.innerHTML = `
                    <strong>${escapeHtml(cliente.nombre)}</strong>
                    <small>Cliente existente</small>
                `;

                item.addEventListener('click', () => {
                    clienteSeleccionado = cliente;
                    clienteIdInput.value = cliente.id;
                    clienteSearchInput.value = cliente.nombre;
                    clienteNuevoInput.value = '';
                    ocultarClientes();
                });

                clientesResults.appendChild(item);
            });

        } catch (error) {
            clientesResults.innerHTML = '';
            agregarOpcionNuevoCliente(search);

            const item = document.createElement('div');
            item.className = 'autocomplete-item autocomplete-empty';
            item.textContent = error.message;
            clientesResults.appendChild(item);
        }
    }

    function mostrarOpcionNuevoCliente(texto = '') {
        clientesResults.innerHTML = '';
        clientesResults.style.display = 'block';
        agregarOpcionNuevoCliente(texto);
    }

    function agregarOpcionNuevoCliente(texto = '') {
        const nombre = texto.trim();

        const item = document.createElement('div');
        item.className = 'autocomplete-item autocomplete-new';
        item.innerHTML = `
            <div class="new-client-icon">+</div>
            <div>
                <strong>Nuevo cliente</strong>
                <small>${nombre ? `Usar "${escapeHtml(nombre)}" como cliente nuevo` : 'Crear cliente manualmente'}</small>
            </div>
        `;

        item.addEventListener('click', () => {
            clienteSeleccionado = null;
            clienteIdInput.value = '';
            clienteNuevoInput.value = nombre;
            clienteSearchInput.value = nombre || 'CLIENTE NUEVO';
            clienteNuevoInput.focus();
            ocultarClientes();
        });

        clientesResults.appendChild(item);
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.autocomplete-field')) {
            ocultarClientes();
        }
    });

    function ocultarClientes() {
        clientesResults.style.display = 'none';
        clientesResults.innerHTML = '';
    }

    document.querySelectorAll('input[name="referencia"]').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarVisibilidadOtros);
    });

    function actualizarVisibilidadOtros() {
        const otrosCheckbox = document.querySelector('input[name="referencia"][value="OTROS"]');
        const visible = Boolean(otrosCheckbox && otrosCheckbox.checked);

        referenciaOtrosField.style.display = visible ? '' : 'none';

        if (!visible) {
            referenciaOtrosTexto.value = '';
        }
    }

    cantidadMuestrasInput.addEventListener('input', () => {
        const valor = Number(cantidadMuestrasInput.value || 0);

        if (valor > 5) {
            cantidadMuestrasError.style.display = 'block';
            btnEnviar.disabled = true;
        } else {
            cantidadMuestrasError.style.display = 'none';
            btnEnviar.disabled = false;
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const solicitante = solicitantes.find(x => String(x.id) === solicitanteSelect.value);

        if (!solicitante) {
            mostrarAlerta('error', 'Selecciona un solicitante.');
            return;
        }

        const clienteTexto = clienteSearchInput.value.trim();
        const clienteNuevo = clienteNuevoInput.value.trim();

        if (!clienteTexto && !clienteNuevo) {
            mostrarAlerta('error', 'Debes seleccionar un cliente o indicar un cliente nuevo.');
            return;
        }

        const cantidadMuestras = Number(cantidadMuestrasInput.value || 0);

        if (!cantidadMuestras || cantidadMuestras < 1) {
            mostrarAlerta('error', 'La cantidad de muestras es obligatoria.');
            return;
        }

        if (cantidadMuestras > 5) {
            mostrarAlerta('error', 'máximo 5');
            return;
        }

        const producto = document.getElementById('producto').value.trim();
        const solicitud = document.getElementById('solicitud').value.trim();
        const sustrato = document.getElementById('sustrato').value.trim();

        if (!producto || !solicitud || !sustrato) {
            mostrarAlerta('error', 'Completa producto, solicitud y sustrato.');
            return;
        }

        const referenciaSeleccionada = Array.from(
            document.querySelectorAll('input[name="referencia"]:checked')
        ).map(x => x.value);

        const destinoSeleccionado = Array.from(
            document.querySelectorAll('input[name="destinoMuestras"]:checked')
        ).map(x => x.value);

        const otrosCheckbox = document.querySelector('input[name="referencia"][value="OTROS"]');

        const payload = {
            solicitanteId: Number(solicitante.id),
            solicitanteNombre: solicitante.nombre,
            clienteId: clienteIdInput.value ? Number(clienteIdInput.value) : null,
            clienteNombre: clienteSeleccionado ? clienteSeleccionado.nombre : clienteTexto,
            clienteNuevo: clienteNuevo || null,
            producto,
            solicitud,
            cantidadMuestras,
            destinoMuestras: destinoSeleccionado.join(', '),
            sustrato,
            referencia: referenciaSeleccionada.join(', '),
            referenciaOtrosTexto: otrosCheckbox && otrosCheckbox.checked
                ? referenciaOtrosTexto.value.trim()
                : null
        };

        await enviarSolicitud(payload);
    });

    async function subirAdjuntos(solicitudId) {
        const archivos = Array.from(adjuntosInput.files || []);

        if (!archivos.length) {
            return;
        }

        for (const archivo of archivos) {
            const formData = new FormData();
            formData.append('archivo', archivo);

            const response = await fetch(
                `${apiBaseUrl}solicitudes-estructural/${solicitudId}/adjuntos`,
                {
                    method: 'POST',
                    body: formData
                }
            );

            if (!response.ok) {
                throw new Error(`Error al subir archivo: ${archivo.name}`);
            }
        }
    }

    async function enviarSolicitud(payload) {
        try {
            bloquearFormulario(true);
            mostrarAlerta('success', 'Enviando solicitud...');

            const response = await fetch(`${apiBaseUrl}solicitudes-estructural`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error((data && (data.message || data.error)) || 'No fue posible crear la solicitud.');
            }

            const solicitudId = data.id;

            if (solicitudId) {
                mostrarAlerta('success', 'Solicitud creada. Subiendo adjuntos...');

                await subirAdjuntos(solicitudId);

                mostrarAlerta('success', 'Adjuntos registrados. Enviando notificación...');

                const notificarResponse = await fetch(
                    `${apiBaseUrl}solicitudes-estructural/${solicitudId}/notificar`,
                    { method: 'POST' }
                );

                if (!notificarResponse.ok) {
                    throw new Error('La solicitud fue creada, pero no fue posible enviar la notificación.');
                }
            }

            form.reset();
            adjuntosPreview.innerHTML = '<span class="muted">No hay archivos seleccionados</span>';
            clienteSeleccionado = null;
            clienteIdInput.value = '';
            actualizarVisibilidadOtros();

            mostrarAlerta('success', 'Solicitud registrada y notificación enviada correctamente.');

        } catch (error) {
            mostrarAlerta('error', error.message);
        } finally {
            bloquearFormulario(false);
        }
    }

    function bloquearFormulario(bloquear) {
        btnEnviar.disabled = bloquear;
        btnEnviar.innerHTML = bloquear
            ? '<i class="bi bi-hourglass-split"></i> Enviando...'
            : '<i class="bi bi-send-fill"></i> Enviar solicitud';
    }

    function mostrarAlerta(tipo, mensaje) {
        formAlert.className = `form-alert ${tipo}`;
        formAlert.textContent = mensaje;
        formAlert.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function escapeHtml(texto) {
        return String(texto)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
