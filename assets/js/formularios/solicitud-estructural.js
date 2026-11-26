document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = document.getElementById('apiBaseUrl').value;

    const form = document.getElementById('solicitudEstructuralForm');
    const formAlert = document.getElementById('formAlert');

    const prioridadSelect = document.getElementById('prioridadId');
    const solicitanteSelect = document.getElementById('solicitanteId');

    const clienteSearchInput = document.getElementById('clienteSearch');
    const clienteIdInput = document.getElementById('clienteId');
    const clienteNuevoInput = document.getElementById('clienteNuevo');
    const clientesResults = document.getElementById('clientesResults');

    const cantidadMuestrasInput = document.getElementById('cantidadMuestras');
    const cantidadMuestrasError = document.getElementById('cantidadMuestrasError');

    const referenciaOtrosField = document.getElementById('referenciaOtrosField');
    const referenciaOtrosTexto = document.getElementById('referenciaOtrosTexto');

    const esMultiProductoSelect = document.getElementById('esMultiProducto');
    const cantidadProductosField = document.getElementById('cantidadProductosField');
    const cantidadProductosInput = document.getElementById('cantidadProductos');

    const btnEnviar = document.getElementById('btnEnviarSolicitud');
    const adjuntosInput = document.getElementById('adjuntos');
    const adjuntosPreview = document.getElementById('adjuntosPreview');

    let prioridades = [];
    let solicitantes = [];
    let clienteSeleccionado = null;
    let clienteTimer = null;

    const MENSAJE_FALTA_FOTO = 'NO SE PUEDE GENERAR LA SOLICITUD POR QUE FALTA LA FOTO';
    // Límite del servidor para una petición (100 MB), con margen para los datos del formulario.
    const MAXIMO_BYTES_PERFIL = 99 * 1024 * 1024;

    const esPerfilSelect = document.getElementById('esPerfil');
    // Una clave por solicitud pendiente: al reintentar se reutiliza y el servidor no duplica.
    let clavesEnvio = [];
    let enviando = false;

    cargarPrioridades();
    cargarSolicitantes();
    actualizarVisibilidadOtros();
    actualizarVisibilidadCantidadProductos();
    actualizarAvisoFotoPerfil();

    esPerfilSelect.addEventListener('change', actualizarAvisoFotoPerfil);

    function actualizarAvisoFotoPerfil() {
        const visible = esPerfilSelect.value === 'SI';
        document.querySelectorAll('.aviso-foto-perfil').forEach(aviso => aviso.hidden = !visible);
    }

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

    async function cargarPrioridades() {
        try {
            const response = await fetch(`${apiBaseUrl}catalogos/prioridades`);

            if (!response.ok) {
                throw new Error('No fue posible cargar las prioridades.');
            }

            prioridades = await response.json();

            prioridadSelect.innerHTML = '';

            const optionDefault = document.createElement('option');
            optionDefault.value = '';
            optionDefault.textContent = 'Seleccione prioridad';
            prioridadSelect.appendChild(optionDefault);

            prioridades.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.nombre;
                prioridadSelect.appendChild(option);
            });

            const prioridadMedia = prioridades.find(x => x.nombre === 'MEDIA');
            if (prioridadMedia) {
                prioridadSelect.value = prioridadMedia.id;
            }

        } catch (error) {
            mostrarAlerta('error', error.message);
        }
    }

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

    esMultiProductoSelect.addEventListener('change', actualizarVisibilidadCantidadProductos);

    function actualizarVisibilidadCantidadProductos() {
        const visible = esMultiProductoSelect.value === 'SI';

        cantidadProductosField.style.display = visible ? '' : 'none';

        if (!visible) {
            cantidadProductosInput.value = '';
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

        // Evita un segundo envío mientras se revisan las fotografías o se está enviando.
        if (enviando) return;
        enviando = true;

        try {
            await procesarEnvio();
        } finally {
            enviando = false;
        }
    });

    async function procesarEnvio() {
        const prioridad = prioridades.find(x => String(x.id) === prioridadSelect.value);
        const solicitante = solicitantes.find(x => String(x.id) === solicitanteSelect.value);

        if (!prioridad || !solicitante) {
            mostrarAlerta('error', 'Completa prioridad y solicitante.');
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

        const esMultiProducto = esMultiProductoSelect.value === 'SI';
        const cantidadProductos = Number(cantidadProductosInput.value || 0);

        if (esMultiProducto && (!Number.isInteger(cantidadProductos) || cantidadProductos < 2 || cantidadProductos > 50)) {
            mostrarAlerta('error', 'Indica una cantidad de productos entre 2 y 50.');
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
            prioridad: prioridad.nombre,
            solicitanteId: Number(solicitante.id),
            solicitanteNombre: solicitante.nombre,
            clienteId: clienteIdInput.value ? Number(clienteIdInput.value) : null,
            clienteNombre: clienteSeleccionado ? clienteSeleccionado.nombre : clienteTexto,
            clienteNuevo: clienteNuevo || null,
            oc: document.getElementById('oc').value.trim(),
            producto,
            solicitud,
            cantidadMuestras,
            destinoMuestras: destinoSeleccionado.join(', '),
            sustrato,
            referencia: referenciaSeleccionada.join(', '),
            referenciaOtrosTexto: otrosCheckbox && otrosCheckbox.checked
                ? referenciaOtrosTexto.value.trim()
                : null,
            esPerfil: esPerfilSelect.value === 'SI'
        };

        if (payload.esPerfil && !(await validarAdjuntosPerfil())) {
            return;
        }

        if (esMultiProducto) {
            await enviarSolicitudesMultiples(payload, cantidadProductos);
        } else if (payload.esPerfil) {
            await enviarSolicitudPerfil(payload);
        } else {
            await enviarSolicitud(payload);
        }
    }

    // No borra nada del formulario: se puede adjuntar la foto y continuar.
    async function validarAdjuntosPerfil() {
        const archivos = Array.from(adjuntosInput.files || []);
        let hayFotografia = false;

        for (const archivo of archivos) {
            if (await esFotografia(archivo)) {
                hayFotografia = true;
                break;
            }
        }

        if (!hayFotografia) {
            mostrarAlerta('error', MENSAJE_FALTA_FOTO);
            alert(MENSAJE_FALTA_FOTO);
            return false;
        }

        if (archivos.some(a => a.size === 0)) {
            mostrarAlerta('error', 'Hay un archivo vacío entre los adjuntos. Quítalo y vuelve a enviar.');
            return false;
        }

        if (archivos.reduce((total, a) => total + a.size, 0) > MAXIMO_BYTES_PERFIL) {
            mostrarAlerta('error', 'Los adjuntos superan 99 MB en total. Reduce su tamaño y vuelve a enviar.');
            return false;
        }

        return true;
    }

    // Revisa el contenido (no la extensión). El servidor vuelve a validar.
    async function esFotografia(archivo) {
        const b = new Uint8Array(await archivo.slice(0, 12).arrayBuffer());
        if (b.length < 12) return false;

        const inicia = (...firma) => firma.every((valor, i) => b[i] === valor);
        const texto = (desde, hasta) => String.fromCharCode(...b.slice(desde, hasta));

        const firmaValida = inicia(0xFF, 0xD8, 0xFF)
            || inicia(0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A)
            || ['GIF87a', 'GIF89a'].includes(texto(0, 6))
            || texto(0, 2) === 'BM'
            || (texto(0, 4) === 'RIFF' && texto(8, 12) === 'WEBP');

        if (!firmaValida) return false;
        if (typeof createImageBitmap !== 'function') return true;

        try {
            (await createImageBitmap(archivo)).close();
            return true;
        } catch {
            return false;
        }
    }

    function claveEnvio(indice) {
        if (!clavesEnvio[indice]) {
            clavesEnvio[indice] = crypto.randomUUID
                ? crypto.randomUUID()
                : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c =>
                    (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
        }

        return clavesEnvio[indice];
    }

    // Perfil: datos y archivos viajan juntos; el servidor crea todo o nada.
    async function crearSolicitudPerfil(payload, indice) {
        const formData = new FormData();
        formData.append('solicitud', JSON.stringify(payload));
        formData.append('claveEnvio', claveEnvio(indice));
        Array.from(adjuntosInput.files).forEach(archivo => formData.append('archivos', archivo));

        const sinConfirmacion = 'No se pudo confirmar el registro. Tus datos se conservan: '
            + 'vuelve a presionar Enviar (no recargues la página) y la solicitud no se duplicará.';

        let response;
        try {
            response = await fetch(`${apiBaseUrl}solicitudes-estructural/perfil`, {
                method: 'POST',
                body: formData
            });
        } catch {
            throw new Error(sinConfirmacion);
        }

        if (response.status === 400) {
            const texto = await response.text();
            if (texto === MENSAJE_FALTA_FOTO) alert(MENSAJE_FALTA_FOTO);
            throw new Error(texto && !texto.startsWith('{') ? texto : 'No fue posible crear la solicitud. Revisa los datos.');
        }

        if (response.status === 404 || response.status === 405) {
            throw new Error('El servidor todavía no admite solicitudes de Perfil. No se creó la solicitud.');
        }

        if (response.status === 413) {
            throw new Error('Los adjuntos son demasiado grandes. No se creó la solicitud.');
        }

        const data = response.ok ? await response.json().catch(() => null) : null;

        if (!data || !data.id) {
            throw new Error(sinConfirmacion);
        }

        return data;
    }

    async function enviarSolicitudPerfil(payload) {
        try {
            bloquearFormulario(true);
            mostrarAlerta('success', 'Enviando solicitud y fotografía...');

            const data = await crearSolicitudPerfil(payload, 0);
            const registro = data.yaExistia
                ? `Tu envío anterior ya había quedado registrado como ${data.codigo}; no se creó otra solicitud.`
                : `Solicitud ${data.codigo} registrada.`;

            let notificada = true;
            try {
                mostrarAlerta('success', 'Solicitud creada. Enviando notificación...');
                await notificarSolicitud(data.id);
            } catch {
                notificada = false;
            }

            // La solicitud ya existe: se limpia el formulario para que no se vuelva a crear.
            resetearFormularioTrasEnvio();
            mostrarAlerta(
                notificada ? 'success' : 'error',
                notificada
                    ? `${registro} Notificación enviada correctamente.`
                    : `${registro} No fue posible enviar la notificación por correo; no la envíes de nuevo y avisa a Desarrollo.`
            );

        } catch (error) {
            mostrarAlerta('error', error.message);
        } finally {
            bloquearFormulario(false);
        }
    }

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

    async function crearUnaSolicitud(payload) {
        const response = await fetch(`${apiBaseUrl}solicitudes-estructural`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error((data && (data.message || data.error)) || 'No fue posible crear la solicitud.');
        }

        if (data.id) {
            await subirAdjuntos(data.id);
        }

        return data;
    }

    async function notificarSolicitud(solicitudId) {
        const response = await fetch(
            `${apiBaseUrl}solicitudes-estructural/${solicitudId}/notificar`,
            { method: 'POST' }
        );

        if (!response.ok) {
            throw new Error('La solicitud fue creada, pero no fue posible enviar la notificación.');
        }
    }

    function resetearFormularioTrasEnvio() {
        form.reset();
        adjuntosPreview.innerHTML = '<span class="muted">No hay archivos seleccionados</span>';
        clienteSeleccionado = null;
        clienteIdInput.value = '';
        clavesEnvio = [];
        actualizarVisibilidadOtros();
        actualizarVisibilidadCantidadProductos();
        actualizarAvisoFotoPerfil();

        const prioridadMedia = prioridades.find(x => x.nombre === 'MEDIA');
        if (prioridadMedia) {
            prioridadSelect.value = prioridadMedia.id;
        }
    }

    async function enviarSolicitud(payload) {
        try {
            bloquearFormulario(true);
            mostrarAlerta('success', 'Enviando solicitud...');

            const data = await crearUnaSolicitud(payload);

            if (data.id) {
                mostrarAlerta('success', 'Solicitud creada. Enviando notificación...');
                await notificarSolicitud(data.id);
            }

            resetearFormularioTrasEnvio();
            mostrarAlerta('success', 'Solicitud registrada y notificación enviada correctamente.');

        } catch (error) {
            mostrarAlerta('error', error.message);
        } finally {
            bloquearFormulario(false);
        }
    }

    async function enviarSolicitudesMultiples(payload, cantidad) {
        const codigos = [];

        try {
            bloquearFormulario(true);

            for (let i = 1; i <= cantidad; i++) {
                mostrarAlerta('success', `Creando solicitud ${i} de ${cantidad}...`);
                const data = payload.esPerfil
                    ? await crearSolicitudPerfil(payload, i - 1)
                    : await crearUnaSolicitud(payload);
                if (data.codigo) codigos.push(data.codigo);
            }

            resetearFormularioTrasEnvio();
            mostrarAlerta(
                'success',
                `Se crearon ${cantidad} solicitudes: ${codigos.join(', ')}. No se enviaron notificaciones automáticas por tratarse de una carga múltiple.`
            );

        } catch (error) {
            mostrarAlerta(
                'error',
                `${error.message} (se alcanzaron a crear ${codigos.length} de ${cantidad}: ${codigos.join(', ') || 'ninguna'})`
            );
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
