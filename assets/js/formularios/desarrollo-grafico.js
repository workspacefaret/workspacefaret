document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = document.getElementById('apiBaseUrl').value;

    const form = document.getElementById('solicitudGraficaForm');
    const formAlert = document.getElementById('formAlert');

    const prioridadSelect = document.getElementById('prioridadId');
    const solicitanteSelect = document.getElementById('solicitanteId');
    const emailSolicitanteInput = document.getElementById('emailSolicitante');
    const tipoProcesoSelect = document.getElementById('tipoProceso');
    const terminacionesBox = document.getElementById('terminacionesBox');

    const clienteSearchInput = document.getElementById('clienteSearch');
    const clienteIdInput = document.getElementById('clienteId');
    const clienteNuevoInput = document.getElementById('clienteNuevo');
    const clientesResults = document.getElementById('clientesResults');

    const cantidadMuestrasInput = document.getElementById('cantidadMuestras');
    const cantidadMuestrasError = document.getElementById('cantidadMuestrasError');
    const cantidadItemsInput = document.getElementById('cantidadItems');
    const cantidadItemsError = document.getElementById('cantidadItemsError');

    const btnEnviar = document.getElementById('btnEnviarSolicitud');
    const adjuntosInput = document.getElementById('adjuntos');
    const adjuntosPreview = document.getElementById('adjuntosPreview');
    adjuntosInput?.addEventListener('change', () => {

        const archivos = Array.from(adjuntosInput.files);

        if (!archivos.length) {

            adjuntosPreview.innerHTML =
                '<span class="muted">No hay archivos seleccionados</span>';

            return;
        }

        adjuntosPreview.innerHTML = '';

        archivos.forEach(file => {

            const item = document.createElement('div');

            item.className = 'adjunto-item';

            item.textContent =
                `${file.name} (${Math.round(file.size / 1024)} KB)`;

            adjuntosPreview.appendChild(item);
        });
    });
    let catalogos = {
        solicitantes: [],
        prioridades: [],
        procesos: [],
        terminaciones: []
    };

    let clienteSeleccionado = null;
    let clienteTimer = null;

    cargarCatalogos();

    async function cargarCatalogos() {
        try {
            const response = await fetch(`${apiBaseUrl}catalogos`);

            if (!response.ok) {
                throw new Error('No fue posible cargar los catálogos.');
            }

            catalogos = await response.json();

            cargarSelect(prioridadSelect, catalogos.prioridades, 'Seleccione prioridad');
            cargarSelect(solicitanteSelect, catalogos.solicitantes, 'Seleccione solicitante');
            cargarSelect(tipoProcesoSelect, catalogos.procesos, 'Seleccione proceso');
            cargarTerminaciones(catalogos.terminaciones);

            const prioridadMedia = catalogos.prioridades.find(x => x.nombre === 'MEDIA');
            if (prioridadMedia) {
                prioridadSelect.value = prioridadMedia.id;
            }

        } catch (error) {
            mostrarAlerta('error', error.message);
        }
    }

    function cargarSelect(select, items, placeholder) {
        select.innerHTML = '';

        const optionDefault = document.createElement('option');
        optionDefault.value = '';
        optionDefault.textContent = placeholder;
        select.appendChild(optionDefault);

        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.nombre;
            select.appendChild(option);
        });
    }

    function cargarTerminaciones(items) {
        terminacionesBox.innerHTML = '';

        items.forEach(item => {
            const label = document.createElement('label');
            label.className = 'chip-check';

            label.innerHTML = `
                <input type="checkbox" name="terminaciones" value="${escapeHtml(item.nombre)}">
                <span>${escapeHtml(item.nombre)}</span>
            `;

            terminacionesBox.appendChild(label);
        });
    }

    const SOLICITANTES_EMAIL_MAP = {
        'CARLOS MESSINA': 'cmessina@innpack.cl',
        'FRANCISCO ARTEAGA': 'farteaga@pharpack.cl',
        'DIEGO CARRASCO': 'dcarrasco@innpack.cl',
        'DAMARIS ALARCON': 'dalarcon@innpack.cl',
        'DIANA TAMAYO': 'dtamayo@faret.cl',
        'EDUARDO SEDAN': 'esedan@faret.cl',
        'FRANCISCO HUMENYI': 'fhumenyi@faret.cl',
        'HECTOR GARCIA': 'hgarcia@innpack.cl',
        'MARIA NELLY': 'mmorante@faret.cl',
        'MARIA NELLY MORANTE': 'mmorante@faret.cl',
        'NICOLAS FARET': 'nfaret@faret.cl',
        'PATRICIO CABELLO': 'pcabello@innpack.cl',
        'PAULINA FARET': 'pfaret@innpack.cl',
        'PEDRO': 'pcroa@faret.cl',
        'PEDRO ROA': 'pcroa@faret.cl'
    };

    function normalizarNombre(nombre) {
        return String(nombre || '')
            .trim()
            .toUpperCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/\s+/g, ' ');
    }

    solicitanteSelect.addEventListener('change', () => {
        const solicitante = catalogos.solicitantes.find(x => String(x.id) === solicitanteSelect.value);
        const correo = solicitante ? SOLICITANTES_EMAIL_MAP[normalizarNombre(solicitante.nombre)] : null;

        emailSolicitanteInput.value = correo || '';
    });

    [
        [cantidadMuestrasInput, cantidadMuestrasError],
        [cantidadItemsInput, cantidadItemsError]
    ].forEach(([input, errorEl]) => {
        input.addEventListener('input', () => validarCantidadUnica(input, errorEl));
    });

    function validarCantidadUnica(input, errorEl) {
        const valor = Number(input.value || 0);
        const esValida = valor === 1;

        errorEl.style.display = esValida ? 'none' : 'block';
        btnEnviar.disabled = !esValida
            || Number(cantidadMuestrasInput.value || 0) !== 1
            || Number(cantidadItemsInput.value || 0) !== 1;

        return esValida;
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const prioridad = catalogos.prioridades.find(x => String(x.id) === prioridadSelect.value);
        const solicitante = catalogos.solicitantes.find(x => String(x.id) === solicitanteSelect.value);
        const proceso = catalogos.procesos.find(x => String(x.id) === tipoProcesoSelect.value);

        if (!prioridad || !solicitante || !proceso) {
            mostrarAlerta('error', 'Completa prioridad, solicitante y tipo de proceso.');
            return;
        }

        const clienteTexto = clienteSearchInput.value.trim();
        const clienteNuevo = clienteNuevoInput.value.trim();

        if (!clienteTexto && !clienteNuevo) {
            mostrarAlerta('error', 'Debes seleccionar un cliente o indicar un cliente nuevo.');
            return;
        }

        if (!validarCantidadUnica(cantidadMuestrasInput, cantidadMuestrasError) || !validarCantidadUnica(cantidadItemsInput, cantidadItemsError)) {
            mostrarAlerta('error', 'máximo 1');
            return;
        }

        const terminacionesSeleccionadas = Array.from(
            document.querySelectorAll('input[name="terminaciones"]:checked')
        ).map(x => x.value);

        const payload = {
            prioridadId: Number(prioridad.id),
            prioridad: prioridad.nombre,
            solicitanteId: Number(solicitante.id),
            solicitanteNombre: solicitante.nombre,
            emailSolicitante: emailSolicitanteInput.value.trim(),
            clienteId: clienteIdInput.value ? Number(clienteIdInput.value) : null,
            clienteNombre: clienteSeleccionado ? clienteSeleccionado.nombre : clienteTexto,
            clienteNuevo: clienteNuevo || null,
            producto: document.getElementById('producto').value.trim(),
            sustrato: document.getElementById('sustrato').value.trim(),
            tipoProceso: proceso.nombre,
            perfil: document.getElementById('perfil').value.trim(),
            terminaciones: terminacionesSeleccionadas.join(', '),
            solicitud: document.getElementById('solicitud').value.trim(),
            cantidadMuestras: Number(document.getElementById('cantidadMuestras').value || 0),
            cantidadItems: Number(document.getElementById('cantidadItems').value || 1),
            observacion: document.getElementById('observacion').value.trim()
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
                `${apiBaseUrl}solicitudes/${solicitudId}/adjuntos`,
                {
                    method: 'POST',
                    body: formData
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Error al subir archivo: ${archivo.name}`
                );
            }
        }
    }

    async function enviarSolicitud(payload) {
        try {
            bloquearFormulario(true);
            mostrarAlerta('success', 'Enviando solicitud...');

            const response = await fetch(`${apiBaseUrl}solicitudes`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(data?.message || data?.error || 'No fue posible crear la solicitud.');
            }

            const solicitudId = data.id;

            if (solicitudId) {

                mostrarAlerta(
                    'success',
                    'Solicitud creada. Subiendo adjuntos...'
                );

                await subirAdjuntos(solicitudId);

                mostrarAlerta(
                    'success',
                    'Adjuntos registrados. Enviando notificación...'
                );

                const notificarResponse = await fetch(
                    `${apiBaseUrl}solicitudes/${solicitudId}/notificar`,
                    {
                        method: 'POST'
                    }
                );

                if (!notificarResponse.ok) {
                    throw new Error('La solicitud fue creada, pero no fue posible enviar la notificación.');
                }
            }

            form.reset();
            adjuntosPreview.innerHTML =
                '<span class="muted">No hay archivos seleccionados</span>';
            clienteSeleccionado = null;
            clienteIdInput.value = '';
            cargarTerminaciones(catalogos.terminaciones);

            const prioridadMedia = catalogos.prioridades.find(x => x.nombre === 'MEDIA');
            if (prioridadMedia) {
                prioridadSelect.value = prioridadMedia.id;
            }

            mostrarAlerta(
                'success',
                'Solicitud registrada y notificación enviada correctamente.'
            );

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
