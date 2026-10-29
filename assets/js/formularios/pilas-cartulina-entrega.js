document.addEventListener('DOMContentLoaded', () => {
    const PC = window.PilasCartulina;
    const apiBaseUrl = PC.apiBaseUrl;
    const usuarioActual = window.currentUserNombre || '';

    const form = document.getElementById('entregaForm');
    const formAlert = document.getElementById('formAlert');
    const npInput = document.getElementById('np');
    const btnBuscarNp = document.getElementById('btnBuscarNp');
    const npResumen = document.getElementById('npResumen');
    const procesosWrap = document.getElementById('procesosWrap');
    const procesosLista = document.getElementById('procesosLista');
    const btnRegistrar = document.getElementById('btnRegistrarEntrega');

    const campo = id => document.getElementById(id);

    let datosFps = null;
    let timerNp = null;

    PC.personas.vincular(campo('entregadoPor'), document.getElementById('entregadoPorResults'));
    PC.personas.vincular(campo('recibidoPor'), document.getElementById('recibidoPorResults'));

    btnBuscarNp.addEventListener('click', () => buscarNp());

    npInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscarNp();
        }
    });

    // Al cambiar la NP se descartan los datos FPS anteriores; se busca solo al pedirlo (o Enter).
    npInput.addEventListener('input', () => {
        clearTimeout(timerNp);
        if (datosFps && String(datosFps.np) !== npInput.value.trim()) {
            limpiarDatosFps();
        }
    });

    async function buscarNp() {
        const np = npInput.value.trim();

        if (!np) {
            mostrarAlerta('error', 'Ingresa una NP para buscar.');
            return;
        }

        btnBuscarNp.disabled = true;
        btnBuscarNp.innerHTML = '<i class="bi bi-hourglass-split"></i> Buscando...';
        ocultarAlerta();

        try {
            const response = await fetch(`${apiBaseUrl}pilas-cartulina/fps?np=${encodeURIComponent(np)}`);

            if (!response.ok) {
                throw new Error(await PC.leerError(response, 'No fue posible consultar producción.'));
            }

            const resultado = await response.json();

            if (!resultado.ok || !resultado.data) {
                limpiarDatosFps();
                const aviso = resultado.codigo === 'NP_NO_ENCONTRADA'
                    ? `La NP ${np} no existe en producción. Puedes completar los datos manualmente.`
                    : `${resultado.mensaje || 'Producción FPS no disponible.'} Puedes completar los datos manualmente.`;
                mostrarAlerta('error', aviso);
                return;
            }

            datosFps = resultado.data;
            renderDatosFps(datosFps);

        } catch (error) {
            limpiarDatosFps();
            mostrarAlerta('error', `${error.message} Puedes completar los datos manualmente.`);
        } finally {
            btnBuscarNp.disabled = false;
            btnBuscarNp.innerHTML = '<i class="bi bi-search"></i> Buscar';
        }
    }

    function renderDatosFps(datos) {
        const productos = (datos.productos || []);
        const productosTexto = productos
            .map(p => `${p.item ? p.item + ': ' : ''}${p.producto || p.itemCode || ''}${p.cantidad ? ' (' + PC.formatearNumero(p.cantidad) + ')' : ''}`)
            .join('; ');

        campo('empresa').value = datos.empresa || '';
        campo('ordenTrabajo').value = datos.ordenTrabajo || '';
        campo('cliente').value = datos.cliente || '';
        campo('formato').value = datos.formato || '';
        campo('productos').value = productosTexto.slice(0, 1000);

        npResumen.innerHTML = `
            <div class="pc-dato"><span>Cliente</span><strong>${PC.escapeHtml(datos.cliente || '-')}</strong></div>
            <div class="pc-dato"><span>OT</span><strong>${PC.escapeHtml(datos.ordenTrabajo || '-')}</strong></div>
            <div class="pc-dato"><span>Empresa</span><strong>${PC.escapeHtml(datos.empresa || '-')}</strong></div>
            <div class="pc-dato"><span>Estado OT</span><strong>${PC.escapeHtml(datos.estadoDocumento || '-')}</strong></div>
            <div class="pc-dato"><span>Formato</span><strong class="pc-destacado">${PC.escapeHtml(datos.formato || 'No informado')}</strong></div>
            <div class="pc-dato"><span>Fecha entrega OT</span><strong>${PC.formatearFecha(datos.fechaEntrega)}</strong></div>
            <div class="pc-dato pc-dato-full"><span>Productos (${productos.length})</span><strong>${productos.length ? productos.map(p => PC.escapeHtml(`${p.item ? p.item + '. ' : ''}${p.producto || ''} — ${PC.formatearNumero(p.cantidad)}`)).join('<br>') : '-'}</strong></div>
        `;
        npResumen.style.display = '';

        const procesos = datos.dimensionado || [];
        procesosLista.innerHTML = '';

        if (!procesos.length) {
            procesosWrap.style.display = 'none';
            campo('cartulina').value = '';
            campo('procesoFps').value = '';
            campo('pliegosFps').value = '';
            mostrarAlerta('success', 'NP encontrada, pero sin proceso de dimensionado en FPS. Completa cartulina y cantidades manualmente.');
            return;
        }

        procesos.forEach((proceso, index) => {
            const label = document.createElement('label');
            label.className = 'pc-proceso';
            label.innerHTML = `
                <input type="radio" name="procesoSeleccionado" value="${index}">
                <div>
                    <strong>${PC.escapeHtml(proceso.nombreProceso || 'Proceso')}</strong>
                    <small>${PC.escapeHtml(proceso.cartulina || '')}</small>
                    <small>Pliegos: ${PC.formatearNumero(proceso.cantidadTotal)}${proceso.obs ? ' · ' + PC.escapeHtml(proceso.obs) : ''}</small>
                </div>
            `;
            label.querySelector('input').addEventListener('change', () => seleccionarProceso(index));
            procesosLista.appendChild(label);
        });

        procesosWrap.style.display = '';

        // Preferir la pila con el formato detectado; si no, la primera.
        const formato = (datos.formato || '').replace(/\s/g, '').toLowerCase();
        let preferido = procesos.findIndex(p => formato && (p.nombreProceso || '').replace(/\s/g, '').toLowerCase().includes(formato) && /pila/i.test(p.nombreProceso || ''));
        if (preferido < 0) preferido = procesos.findIndex(p => /pila/i.test(p.nombreProceso || ''));
        if (preferido < 0) preferido = 0;

        procesosLista.querySelectorAll('input[type="radio"]')[preferido].checked = true;
        seleccionarProceso(preferido);

        mostrarAlerta('success', `NP ${datos.np} cargada desde producción. Revisa los datos y completa la entrega.`);
    }

    function seleccionarProceso(index) {
        const proceso = (datosFps && datosFps.dimensionado || [])[index];
        if (!proceso) return;

        procesosLista.querySelectorAll('.pc-proceso').forEach((el, i) => el.classList.toggle('is-selected', i === index));

        campo('cartulina').value = proceso.cartulina || '';
        campo('procesoFps').value = proceso.nombreProceso || '';
        campo('pliegosFps').value = proceso.cantidadTotal !== null && proceso.cantidadTotal !== undefined ? proceso.cantidadTotal : '';

        // Sugerencia editable: la cantidad solicitada parte en los pliegos del proceso.
        if (proceso.cantidadTotal) {
            campo('cantidadSolicitada').value = proceso.cantidadTotal;
        }
    }

    function limpiarDatosFps() {
        datosFps = null;
        npResumen.style.display = 'none';
        npResumen.innerHTML = '';
        procesosWrap.style.display = 'none';
        procesosLista.innerHTML = '';
        ['empresa', 'ordenTrabajo', 'procesoFps', 'pliegosFps', 'productos'].forEach(id => { campo(id).value = ''; });
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const np = npInput.value.trim();
        const cantidadSolicitada = Number(campo('cantidadSolicitada').value);
        const cantidadEntregada = Number(campo('cantidadEntregada').value);
        const numeroPalets = Number(campo('numeroPalets').value);
        const entregadoPor = campo('entregadoPor').value.trim();

        if (!np) {
            mostrarAlerta('error', 'La NP es obligatoria.');
            return;
        }

        if (!(cantidadSolicitada > 0)) {
            mostrarAlerta('error', 'La cantidad solicitada debe ser mayor a 0.');
            return;
        }

        if (!(cantidadEntregada >= 0)) {
            mostrarAlerta('error', 'Ingresa la cantidad entregada.');
            return;
        }

        if (!Number.isInteger(numeroPalets) || numeroPalets < 0) {
            mostrarAlerta('error', 'El número de palets debe ser un entero mayor o igual a 0.');
            return;
        }

        if (!entregadoPor) {
            mostrarAlerta('error', 'Indica quién entrega.');
            return;
        }

        const payload = {
            np,
            empresa: campo('empresa').value || null,
            ordenTrabajo: campo('ordenTrabajo').value ? Number(campo('ordenTrabajo').value) : null,
            cliente: campo('cliente').value.trim() || null,
            cartulina: campo('cartulina').value.trim() || null,
            formato: campo('formato').value.trim() || null,
            procesoFps: campo('procesoFps').value || null,
            pliegosFps: campo('pliegosFps').value ? Number(campo('pliegosFps').value) : null,
            productos: campo('productos').value || null,
            cantidadSolicitada,
            cantidadEntregada,
            numeroPalets,
            entregadoPor,
            recibidoPor: campo('recibidoPor').value.trim() || null,
            observacionesEntrega: campo('observacionesEntrega').value.trim() || null,
            usuario: usuarioActual
        };

        bloquear(true);

        try {
            const response = await fetch(`${apiBaseUrl}pilas-cartulina/registros`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error(await PC.leerError(response, 'No fue posible registrar la entrega.'));
            }

            const creado = await response.json();

            // Invalida el caché de sugerencias: pueden haberse creado nombres nuevos.
            PC.personas.lista = null;
            PC.personas.cargando = null;

            form.reset();
            limpiarDatosFps();

            const urlRecepcion = `/modules/formularios/pilas-cartulina/recepcion/?id=${creado.id}`;
            formAlert.className = 'form-alert success';
            formAlert.innerHTML = `Entrega <strong>${PC.escapeHtml(creado.folio)}</strong> registrada. Quien recibe debe validarla y firmar en `
                + `<a href="${urlRecepcion}" style="color:inherit;text-decoration:underline;">Recepción</a>.`;
            formAlert.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });

        } catch (error) {
            mostrarAlerta('error', error.message);
        } finally {
            bloquear(false);
        }
    });

    function bloquear(bloqueado) {
        btnRegistrar.disabled = bloqueado;
        btnRegistrar.innerHTML = bloqueado
            ? '<i class="bi bi-hourglass-split"></i> Registrando...'
            : '<i class="bi bi-send-fill"></i> Registrar entrega';
    }

    function mostrarAlerta(tipo, mensaje) {
        formAlert.className = `form-alert ${tipo}`;
        formAlert.textContent = mensaje;
        formAlert.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function ocultarAlerta() {
        formAlert.style.display = 'none';
    }
});
