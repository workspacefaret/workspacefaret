document.addEventListener('DOMContentLoaded', () => {
    const PC = window.PilasCartulina;
    const apiBaseUrl = PC.apiBaseUrl;
    const appRoot = apiBaseUrl.replace(/api\/?$/, '');
    const usuarioActual = window.currentUserNombre || '';
    const registroId = Number(window.pilasRecepcionId || 0);

    const formAlert = document.getElementById('formAlert');

    if (registroId > 0) {
        cargarDetalle();
    } else {
        cargarPendientes();
    }

    // ---------------- Lista de pendientes (sin id) ----------------
    async function cargarPendientes() {
        const contenedor = document.getElementById('listaPendientes');

        try {
            const response = await fetch(`${apiBaseUrl}pilas-cartulina/registros?estado=PENDIENTE_RECEPCION&porPagina=200`);
            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible cargar las entregas pendientes.'));

            const resultado = await response.json();
            const items = resultado.items || [];

            if (!items.length) {
                contenedor.innerHTML = '<p class="admin-empty-box" style="color:#94a3b8;">No hay entregas pendientes de recepción.</p>';
                return;
            }

            contenedor.innerHTML = items.map(item => `
                <a class="pc-pendiente" href="/modules/formularios/pilas-cartulina/recepcion/?id=${item.id}">
                    <strong>${PC.escapeHtml(item.folio)} · NP ${PC.escapeHtml(item.np)}</strong>
                    <p>${PC.escapeHtml(item.cliente || 'Cliente no informado')}<br>
                       ${PC.escapeHtml(item.cartulina || '')}${item.formato ? ' · ' + PC.escapeHtml(item.formato) : ''}<br>
                       Entregado: <b>${PC.formatearNumero(item.cantidadEntregada)}</b> · Palets: <b>${item.numeroPalets}</b></p>
                    <small>${PC.formatearFechaHora(item.fechaEntrega)} · Entrega ${PC.escapeHtml(item.entregadoPor)}${item.recibidoPor ? ' → ' + PC.escapeHtml(item.recibidoPor) : ''}</small>
                </a>
            `).join('');

        } catch (error) {
            contenedor.innerHTML = '';
            mostrarAlerta('error', error.message);
        }
    }

    // ---------------- Detalle + recepción (con id) ----------------
    async function cargarDetalle() {
        try {
            const response = await fetch(`${apiBaseUrl}pilas-cartulina/registros/${registroId}`);
            if (!response.ok) throw new Error(await PC.leerError(response, 'No fue posible cargar la entrega.'));

            renderDetalle(await response.json());
        } catch (error) {
            document.getElementById('folioTitulo').textContent = 'Entrega no disponible';
            mostrarAlerta('error', error.message);
        }
    }

    function renderDetalle(item) {
        document.getElementById('folioTitulo').textContent = `${item.folio} · NP ${item.np}`;
        document.getElementById('folioSubtitulo').textContent = `${PC.etiquetaEstado(item.estado)} · ${PC.formatearFechaHora(item.fechaEntrega)}`;

        document.getElementById('detalleResumen').innerHTML = `
            <div class="pc-dato"><span>Cliente</span><strong>${PC.escapeHtml(item.cliente || '-')}</strong></div>
            <div class="pc-dato"><span>OT / Empresa</span><strong>${PC.escapeHtml(item.ordenTrabajo || '-')}${item.empresa ? ' · ' + PC.escapeHtml(item.empresa) : ''}</strong></div>
            <div class="pc-dato"><span>Formato</span><strong class="pc-destacado">${PC.escapeHtml(item.formato || '-')}</strong></div>
            <div class="pc-dato pc-dato-full"><span>Cartulina</span><strong>${PC.escapeHtml(item.cartulina || '-')}${item.procesoFps ? '<br><small style="color:#94a3b8;">' + PC.escapeHtml(item.procesoFps) + (item.pliegosFps ? ' · Pliegos FPS: ' + PC.formatearNumero(item.pliegosFps) : '') + '</small>' : ''}</strong></div>
            <div class="pc-dato"><span>Cantidad solicitada</span><strong class="pc-destacado">${PC.formatearNumero(item.cantidadSolicitada)}</strong></div>
            <div class="pc-dato"><span>Cantidad entregada</span><strong class="pc-destacado">${PC.formatearNumero(item.cantidadEntregada)}</strong></div>
            <div class="pc-dato"><span>N° de palets</span><strong class="pc-destacado">${PC.escapeHtml(item.numeroPalets)}</strong></div>
            <div class="pc-dato"><span>Entregado por</span><strong>${PC.escapeHtml(item.entregadoPor)}</strong></div>
            <div class="pc-dato"><span>Para</span><strong>${PC.escapeHtml(item.recibidoPor || '-')}</strong></div>
            <div class="pc-dato pc-dato-full"><span>Productos</span><strong>${PC.escapeHtml(item.productos || '-').replaceAll('; ', '<br>')}</strong></div>
            <div class="pc-dato pc-dato-full"><span>Observaciones de entrega</span><strong>${PC.escapeHtml(item.observacionesEntrega || '-')}</strong></div>
        `;

        const form = document.getElementById('recepcionForm');
        const cerrada = document.getElementById('recepcionCerrada');

        if (item.estado === 'PENDIENTE_RECEPCION') {
            form.style.display = '';
            cerrada.style.display = 'none';
            inicializarFormulario(item);
            return;
        }

        form.style.display = 'none';
        cerrada.style.display = '';
        cerrada.innerHTML = renderCierre(item);
    }

    function renderCierre(item) {
        const pdfUrl = `${apiBaseUrl}pilas-cartulina/registros/${item.id}/pdf`;

        if (item.estado === 'ANULADO') {
            return `
                <div class="form-alert error" style="display:block;">Esta entrega fue anulada.${item.motivoAnulacion ? ' Motivo: ' + PC.escapeHtml(item.motivoAnulacion) : ''}</div>
                <a class="btn-clear-firma" href="${pdfUrl}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
            `;
        }

        return `
            <div class="form-alert success" style="display:block;">Recepción validada por <strong>${PC.escapeHtml(item.recibidoPor || '-')}</strong> el ${PC.formatearFechaHora(item.fechaRecepcion)}.</div>
            <div class="pc-resumen">
                <div class="pc-dato pc-dato-full"><span>Observaciones de recepción</span><strong>${PC.escapeHtml(item.observacionesRecepcion || '-')}</strong></div>
                <div class="pc-dato pc-dato-full"><span>Firma</span>${item.firmaRuta ? `<img class="pc-firma-img" src="${PC.escapeHtml(appRoot + item.firmaRuta.replace(/^\//, ''))}" alt="Firma de recepción">` : '<strong>-</strong>'}</div>
            </div>
            <div style="margin-top:14px;">
                <a class="btn-clear-firma" href="${pdfUrl}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
            </div>
        `;
    }

    function inicializarFormulario(item) {
        const form = document.getElementById('recepcionForm');
        const recibidoPorInput = document.getElementById('recibidoPor');
        const btnValidar = document.getElementById('btnValidar');

        recibidoPorInput.value = item.recibidoPor || '';
        PC.personas.vincular(recibidoPorInput, document.getElementById('recibidoPorResults'));

        const firma = PC.crearFirma(document.getElementById('firmaCanvas'), document.getElementById('btnLimpiarFirma'));

        form.addEventListener('submit', async event => {
            event.preventDefault();

            const recibidoPor = recibidoPorInput.value.trim();

            if (!recibidoPor) {
                mostrarAlerta('error', 'Indica quién recibe.');
                return;
            }

            if (!firma.tieneTrazo()) {
                mostrarAlerta('error', 'La firma es obligatoria para validar la recepción.');
                return;
            }

            btnValidar.disabled = true;
            btnValidar.innerHTML = '<i class="bi bi-hourglass-split"></i> Validando...';

            try {
                const response = await fetch(`${apiBaseUrl}pilas-cartulina/registros/${item.id}/recibir`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        recibidoPor,
                        firmaBase64: firma.dataUrl(),
                        observacionesRecepcion: document.getElementById('observacionesRecepcion').value.trim() || null,
                        usuario: usuarioActual
                    })
                });

                if (!response.ok) {
                    throw new Error(await PC.leerError(response, 'No fue posible validar la recepción.'));
                }

                const actualizado = await response.json();
                PC.personas.lista = null;
                PC.personas.cargando = null;

                renderDetalle(actualizado);
                mostrarAlerta('success', `Recepción de ${actualizado.folio} validada correctamente.`);

            } catch (error) {
                mostrarAlerta('error', error.message);
                btnValidar.disabled = false;
                btnValidar.innerHTML = '<i class="bi bi-check2-circle"></i> Aceptar entrega y firmar';
            }
        });
    }

    function mostrarAlerta(tipo, mensaje) {
        formAlert.className = `form-alert ${tipo}`;
        formAlert.textContent = mensaje;
        formAlert.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
