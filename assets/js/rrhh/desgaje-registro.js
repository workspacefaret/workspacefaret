document.addEventListener('DOMContentLoaded', () => {
    const apiBaseUrl = document.getElementById('apiBaseUrl').value;

    const form = document.getElementById('desgajeForm');
    const formAlert = document.getElementById('formAlert');

    const tallerSelect = document.getElementById('tallerId');
    const operadorSearchInput = document.getElementById('operadorSearch');
    const operadorIdInput = document.getElementById('operadorId');
    const operadoresResults = document.getElementById('operadoresResults');
    const fechaRegistroInput = document.getElementById('fechaRegistro');

    const trabajosContainer = document.getElementById('trabajosContainer');
    const btnAgregarTrabajo = document.getElementById('btnAgregarTrabajo');
    const trabajoRowTemplate = document.getElementById('trabajoRowTemplate').innerHTML;

    const btnGuardar = document.getElementById('btnGuardarRegistro');

    const firmaCanvas = document.getElementById('firmaCanvas');
    const firmaCtx = firmaCanvas.getContext('2d');
    const btnLimpiarFirma = document.getElementById('btnLimpiarFirma');

    let catalogos = { tipos: [], talleres: [], operadores: [] };
    let clienteTimerPorFila = new WeakMap();
    let firmaTieneTrazo = false;

    fechaRegistroInput.value = new Date().toISOString().slice(0, 10);

    cargarCatalogos();
    agregarFilaTrabajo();
    inicializarFirma();

    async function cargarCatalogos() {
        try {
            const response = await fetch(`${apiBaseUrl}desgaje/catalogos`);

            if (!response.ok) {
                throw new Error('No fue posible cargar los catálogos.');
            }

            catalogos = await response.json();

            tallerSelect.innerHTML = '';
            const optionDefault = document.createElement('option');
            optionDefault.value = '';
            optionDefault.textContent = 'Seleccione taller';
            tallerSelect.appendChild(optionDefault);

            (catalogos.talleres || []).filter(t => t.activo).forEach(taller => {
                const option = document.createElement('option');
                option.value = taller.id;
                option.textContent = taller.nombre;
                tallerSelect.appendChild(option);
            });

            actualizarSelectsTipoEnFilas();

        } catch (error) {
            mostrarAlerta('error', error.message);
        }
    }

    operadorSearchInput.addEventListener('focus', () => mostrarSugerenciasOperador(operadorSearchInput.value));

    operadorSearchInput.addEventListener('input', () => {
        operadorIdInput.value = '';
        mostrarSugerenciasOperador(operadorSearchInput.value);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('#operadorSearch') && !event.target.closest('#operadoresResults')) {
            operadoresResults.style.display = 'none';
        }
    });

    function mostrarSugerenciasOperador(search) {
        operadoresResults.innerHTML = '';
        operadoresResults.style.display = 'block';

        const texto = search.trim().toLowerCase();

        const coincidencias = (catalogos.operadores || [])
            .filter(op => op.activo && (!texto || op.nombre.toLowerCase().includes(texto)))
            .slice(0, 30);

        if (!coincidencias.length) {
            const item = document.createElement('div');
            item.className = 'autocomplete-item autocomplete-empty';
            item.textContent = 'Sin operadores encontrados. Se usará el nombre escrito.';
            operadoresResults.appendChild(item);
            return;
        }

        coincidencias.forEach(operador => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.innerHTML = `<strong>${escapeHtml(operador.nombre)}</strong>`;

            item.addEventListener('click', () => {
                operadorIdInput.value = operador.id;
                operadorSearchInput.value = operador.nombre;
                operadoresResults.style.display = 'none';
            });

            operadoresResults.appendChild(item);
        });
    }

    btnAgregarTrabajo.addEventListener('click', () => agregarFilaTrabajo());

    function agregarFilaTrabajo() {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = trabajoRowTemplate.trim();
        const fila = wrapper.firstElementChild;

        trabajosContainer.appendChild(fila);
        actualizarNumerosFila();
        actualizarSelectTipoEnFila(fila);

        const clienteSearchInput = fila.querySelector('[data-field="clienteSearch"]');
        const clienteIdInput = fila.querySelector('[data-field="clienteId"]');
        const clientesResults = fila.querySelector('[data-clientes-results]');

        clienteSearchInput.addEventListener('input', () => {
            clienteIdInput.value = '';

            const search = clienteSearchInput.value.trim();
            clearTimeout(clienteTimerPorFila.get(fila));

            if (search.length < 3) {
                clientesResults.style.display = 'none';
                clientesResults.innerHTML = '';
                return;
            }

            clienteTimerPorFila.set(fila, setTimeout(() => buscarClientes(fila, search), 300));
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.autocomplete-field')) {
                clientesResults.style.display = 'none';
            }
        });

        fila.querySelector('[data-remove-row]').addEventListener('click', () => {
            if (trabajosContainer.children.length <= 1) {
                mostrarAlerta('error', 'Debe existir al menos un trabajo.');
                return;
            }

            fila.remove();
            actualizarNumerosFila();
        });
    }

    async function buscarClientes(fila, search) {
        const clientesResults = fila.querySelector('[data-clientes-results]');
        const clienteIdInput = fila.querySelector('[data-field="clienteId"]');
        const clienteSearchInput = fila.querySelector('[data-field="clienteSearch"]');

        try {
            clientesResults.innerHTML = '';
            clientesResults.style.display = 'block';

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
                item.textContent = 'Sin clientes encontrados. Se usará el texto escrito.';
                clientesResults.appendChild(item);
                return;
            }

            clientes.slice(0, 30).forEach(cliente => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.innerHTML = `<strong>${escapeHtml(cliente.nombre)}</strong>`;

                item.addEventListener('click', () => {
                    clienteIdInput.value = cliente.id;
                    clienteSearchInput.value = cliente.nombre;
                    clientesResults.style.display = 'none';
                });

                clientesResults.appendChild(item);
            });

        } catch (error) {
            clientesResults.innerHTML = '';
            const item = document.createElement('div');
            item.className = 'autocomplete-item autocomplete-empty';
            item.textContent = error.message;
            clientesResults.appendChild(item);
        }
    }

    function actualizarNumerosFila() {
        Array.from(trabajosContainer.children).forEach((fila, index) => {
            fila.querySelector('[data-row-numero]').textContent = index + 1;
        });
    }

    function actualizarSelectsTipoEnFilas() {
        Array.from(trabajosContainer.children).forEach(fila => actualizarSelectTipoEnFila(fila));
    }

    function actualizarSelectTipoEnFila(fila) {
        const select = fila.querySelector('[data-field="tipoDesgajeId"]');
        const valorActual = select.value;

        select.innerHTML = '';

        const optionDefault = document.createElement('option');
        optionDefault.value = '';
        optionDefault.textContent = 'Seleccione tipo';
        select.appendChild(optionDefault);

        (catalogos.tipos || []).filter(t => t.activo).forEach(tipo => {
            const option = document.createElement('option');
            option.value = tipo.id;
            option.textContent = tipo.nombre;
            select.appendChild(option);
        });

        if (valorActual) {
            select.value = valorActual;
        }
    }

    function inicializarFirma() {
        ajustarTamanoCanvas();
        window.addEventListener('resize', ajustarTamanoCanvas);

        let dibujando = false;

        firmaCanvas.addEventListener('pointerdown', (event) => {
            dibujando = true;
            firmaTieneTrazo = true;
            const punto = obtenerPunto(event);
            firmaCtx.beginPath();
            firmaCtx.moveTo(punto.x, punto.y);
        });

        firmaCanvas.addEventListener('pointermove', (event) => {
            if (!dibujando) return;
            const punto = obtenerPunto(event);
            firmaCtx.lineTo(punto.x, punto.y);
            firmaCtx.stroke();
        });

        ['pointerup', 'pointerleave', 'pointercancel'].forEach(evento => {
            firmaCanvas.addEventListener(evento, () => { dibujando = false; });
        });

        btnLimpiarFirma.addEventListener('click', () => limpiarFirma());
    }

    function ajustarTamanoCanvas() {
        const ratio = window.devicePixelRatio || 1;
        const anchoCss = firmaCanvas.clientWidth || firmaCanvas.parentElement.clientWidth;
        const altoCss = 180;

        firmaCanvas.style.height = `${altoCss}px`;
        firmaCanvas.width = anchoCss * ratio;
        firmaCanvas.height = altoCss * ratio;

        firmaCtx.scale(ratio, ratio);
        firmaCtx.lineWidth = 2.4;
        firmaCtx.lineCap = 'round';
        firmaCtx.lineJoin = 'round';
        firmaCtx.strokeStyle = '#0f172a';

        firmaCtx.fillStyle = '#ffffff';
        firmaCtx.fillRect(0, 0, anchoCss, altoCss);

        firmaTieneTrazo = false;
    }

    function limpiarFirma() {
        ajustarTamanoCanvas();
    }

    function obtenerPunto(event) {
        const rect = firmaCanvas.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top
        };
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!tallerSelect.value) {
            mostrarAlerta('error', 'Selecciona un taller.');
            return;
        }

        if (!operadorSearchInput.value.trim()) {
            mostrarAlerta('error', 'Selecciona o escribe un operador.');
            return;
        }

        if (!fechaRegistroInput.value) {
            mostrarAlerta('error', 'La fecha de registro es obligatoria.');
            return;
        }

        const filas = Array.from(trabajosContainer.children);
        const detalles = [];

        for (let i = 0; i < filas.length; i++) {
            const fila = filas[i];
            const numeroFila = i + 1;

            const np = fila.querySelector('[data-field="np"]').value.trim();
            const clienteId = fila.querySelector('[data-field="clienteId"]').value;
            const clienteNombre = fila.querySelector('[data-field="clienteSearch"]').value.trim();
            const numeroPliego = fila.querySelector('[data-field="numeroPliego"]').value.trim();
            const tipoDesgajeId = fila.querySelector('[data-field="tipoDesgajeId"]').value;
            const cantidadPliegos = Number(fila.querySelector('[data-field="cantidadPliegos"]').value || 0);
            const numeroMoldes = Number(fila.querySelector('[data-field="numeroMoldes"]').value || 0);
            const observaciones = fila.querySelector('[data-field="observaciones"]').value.trim();

            if (!np || !clienteNombre || !numeroPliego || !tipoDesgajeId) {
                mostrarAlerta('error', `Completa todos los campos obligatorios del trabajo ${numeroFila}.`);
                return;
            }

            if (cantidadPliegos <= 0 || numeroMoldes <= 0) {
                mostrarAlerta('error', `La cantidad de pliegos y el número de moldes deben ser mayores a 0 (trabajo ${numeroFila}).`);
                return;
            }

            detalles.push({
                np,
                clienteId: clienteId ? Number(clienteId) : null,
                clienteNombre,
                numeroPliego,
                cantidadPliegos,
                numeroMoldes,
                tipoDesgajeId: Number(tipoDesgajeId),
                observaciones: observaciones || null
            });
        }

        if (!firmaTieneTrazo) {
            mostrarAlerta('error', 'La firma es obligatoria para finalizar el registro.');
            return;
        }

        const usuarioCreador = operadorSearchInput.value.trim();

        const payload = {
            fechaRegistro: fechaRegistroInput.value,
            operadorId: operadorIdInput.value ? Number(operadorIdInput.value) : null,
            operadorNombre: usuarioCreador,
            tallerId: Number(tallerSelect.value),
            observacionesGenerales: document.getElementById('observacionesGenerales').value.trim() || null,
            checklistEntradaCumple: document.getElementById('checklistEntradaCumple').checked,
            checklistEntradaObservacion: document.getElementById('checklistEntradaObservacion').value.trim() || null,
            checklistInicioCumple: document.getElementById('checklistInicioCumple').checked,
            checklistInicioObservacion: document.getElementById('checklistInicioObservacion').value.trim() || null,
            checklistSalidaCumple: document.getElementById('checklistSalidaCumple').checked,
            checklistSalidaObservacion: document.getElementById('checklistSalidaObservacion').value.trim() || null,
            usuarioCreador,
            detalles
        };

        await guardarYFinalizar(payload, usuarioCreador);
    });

    async function guardarYFinalizar(payload, usuarioCreador) {
        try {
            bloquearFormulario(true);
            mostrarAlerta('success', 'Guardando registro...');

            const crearResponse = await fetch(`${apiBaseUrl}desgaje/registros`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const crearTexto = await crearResponse.text();

            if (!crearResponse.ok) {
                throw new Error(crearTexto || 'No fue posible crear el registro.');
            }

            const registroCreado = JSON.parse(crearTexto);

            mostrarAlerta('success', 'Registro creado. Finalizando con firma...');

            const firmaBase64 = firmaCanvas.toDataURL('image/png');

            const finalizarResponse = await fetch(`${apiBaseUrl}desgaje/registros/${registroCreado.id}/finalizar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ firmaBase64, usuarioCreador })
            });

            const finalizarTexto = await finalizarResponse.text();

            if (!finalizarResponse.ok) {
                throw new Error(finalizarTexto || 'El registro fue creado, pero no fue posible finalizarlo con la firma.');
            }

            const registroFinal = JSON.parse(finalizarTexto);

            reiniciarFormulario();

            const pdfUrl = `${apiBaseUrl}desgaje/registros/${registroFinal.id}/pdf`;
            formAlert.className = 'form-alert success';
            formAlert.innerHTML = `Registro <strong>${escapeHtml(registroFinal.codigo)}</strong> finalizado correctamente. <a href="${pdfUrl}" target="_blank" style="color:inherit;text-decoration:underline;">Descargar PDF</a>`;
            formAlert.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });

        } catch (error) {
            mostrarAlerta('error', error.message);
        } finally {
            bloquearFormulario(false);
        }
    }

    function reiniciarFormulario() {
        form.reset();
        fechaRegistroInput.value = new Date().toISOString().slice(0, 10);
        trabajosContainer.innerHTML = '';
        agregarFilaTrabajo();
        operadorIdInput.value = '';
        operadoresResults.style.display = 'none';
        limpiarFirma();
    }

    function bloquearFormulario(bloquear) {
        btnGuardar.disabled = bloquear;
        btnGuardar.innerHTML = bloquear
            ? '<i class="bi bi-hourglass-split"></i> Guardando...'
            : '<i class="bi bi-send-fill"></i> Guardar y finalizar registro';
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
