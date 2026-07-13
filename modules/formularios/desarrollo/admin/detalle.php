<?php
ob_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1 id="detalleTitulo">Detalle Solicitud</h1>
        <p>Información completa, adjuntos, historial y cambio de estado.</p>
    </div>

    <div class="admin-hero-actions">
        <a class="admin-btn admin-btn-secondary" href="/modules/formularios/desarrollo/admin/">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>

        <a class="admin-btn admin-btn-primary" id="btnPdfDetalle" href="#" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i>
            PDF
        </a>
    </div>
</section>

<section class="section">
    <div class="admin-detail-layout">

        <div class="panel admin-panel admin-detail-main">
            <div class="section-header">
                <div class="section-title">
                    <h2>Información General</h2>
                    <p id="detalleSubtitulo">Cargando información...</p>
                </div>
                <span class="badge badge-primary" id="detalleEstado">-</span>
            </div>

            <div class="admin-detail-list" id="detalleContenido"></div>
        </div>

        <div class="panel admin-panel admin-detail-side">
            <div class="section-header">
                <div class="section-title">
                    <h2>Cambiar Estado</h2>
                    <p>Actualiza el estado operacional.</p>
                </div>
            </div>

            <div class="admin-change-state">
                <label for="nuevoEstado">Nuevo estado</label>
                <select id="nuevoEstado"></select>

                <label for="editorAsignado">Editor asignado</label>
                <input type="text" id="editorAsignado" placeholder="Nombre del editor">

                <label for="nivelComplejidad">Nivel de complejidad</label>
                <select id="nivelComplejidad">
                    <option value="">Sin definir</option>
                    <option value="BAJA">Baja</option>
                    <option value="MEDIA">Media</option>
                    <option value="ALTA">Alta</option>
                </select>

                <label for="observacionEstado">Observación</label>
                <textarea id="observacionEstado" rows="5" placeholder="Comentario opcional"></textarea>

                <button class="admin-btn admin-btn-primary" id="btnCambiarEstado">
                    <i class="bi bi-check-circle"></i>
                    Guardar cambio
                </button>

                <button class="admin-btn admin-btn-secondary" id="btnReabrir" style="display:none;">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Reabrir solicitud
                </button>
            </div>
        </div>

    </div>
</section>

<section class="section">
    <div class="admin-detail-layout">
        <div class="panel admin-panel">
            <div class="section-header">
                <div class="section-title">
                    <h2>Adjuntos</h2>
                    <p>Archivos cargados en la solicitud.</p>
                </div>
            </div>
            <div id="detalleAdjuntos" class="admin-list-box">Cargando adjuntos...</div>
        </div>

        <div class="panel admin-panel">
            <div class="section-header">
                <div class="section-title">
                    <h2>Historial</h2>
                    <p>Registro de creación y cambios de estado.</p>
                </div>
            </div>
            <div id="detalleHistorial" class="admin-list-box">Cargando historial...</div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const solicitudId = <?= $id ?>;
    const apiBaseUrl = 'https://api.faret.cl/formularios/api/';

    cargarTodo();

    async function cargarTodo() {
        if (!solicitudId) {
            document.getElementById('detalleContenido').innerHTML = '<div class="admin-empty">ID de solicitud no válido.</div>';
            return;
        }

        await cargarEstados();
        await cargarDetalle();
        await cargarAdjuntos();
        await cargarHistorial();
    }

    async function cargarEstados() {
        const response = await fetch(`${apiBaseUrl}catalogos/estados`);
        const estados = await response.json();

        const select = document.getElementById('nuevoEstado');
        select.innerHTML = '';

        estados.forEach(estado => {
            const option = document.createElement('option');
            option.value = estado.id;
            option.textContent = estado.nombre;
            select.appendChild(option);
        });
    }

    async function cargarDetalle() {
        const response = await fetch(`${apiBaseUrl}solicitudes/${solicitudId}/detalle`);
        const item = await response.json();

        document.getElementById('detalleTitulo').textContent = item.codigo;
        document.getElementById('detalleSubtitulo').textContent = `${item.clienteNombre} · ${item.producto}`;
        document.getElementById('detalleEstado').textContent = item.estado;
        document.getElementById('btnPdfDetalle').href = `${apiBaseUrl}solicitudes/${item.id}/pdf`;
        document.getElementById('nuevoEstado').value = item.estadoId;
        document.getElementById('editorAsignado').value = item.operadorEdicion || '';
        document.getElementById('nivelComplejidad').value = item.nivelComplejidad || '';

        const estadosCerrados = [4, 5, 6]; // Terminado, Rechazado, Anulado
        document.getElementById('btnReabrir').style.display = estadosCerrados.includes(item.estadoId) ? '' : 'none';

        document.getElementById('detalleContenido').innerHTML = `
            ${campo('Código', item.codigo)}
            ${campo('Estado', item.estado)}
            ${campo('Editor asignado', item.operadorEdicion)}
            ${campo('Nivel de complejidad', item.nivelComplejidad)}
            ${campo('Prioridad', item.prioridad)}
            ${campo('Solicitante', item.solicitanteNombre)}
            ${campo('Email solicitante', item.emailSolicitante)}
            ${campo('Cliente', item.clienteNombre)}
            ${campo('Cliente nuevo', item.clienteNuevo)}
            ${campo('OC', item.oc)}
            ${campo('Producto', item.producto)}
            ${campo('Sustrato', item.sustrato)}
            ${campo('Tipo proceso', item.tipoProceso)}
            ${campo('Perfil', item.perfil)}
            ${campo('Terminaciones', item.terminaciones)}
            ${campo('Cantidad muestras', item.cantidadMuestras)}
            ${campo('Cantidad items', item.cantidadItems)}
            ${campo('Solicitud', item.solicitud, true)}
            ${campo('Observación', item.observacion, true)}
            ${campo('Fecha registro', formatearFecha(item.fechaRegistro))}
        `;
    }

    async function cargarAdjuntos() {
        const contenedor = document.getElementById('detalleAdjuntos');

        try {
            const response = await fetch(`${apiBaseUrl}solicitudes/${solicitudId}/adjuntos`);
            if (!response.ok) {
                contenedor.innerHTML = '<div class="admin-empty-box">No se pudieron cargar los adjuntos.</div>';
                return;
            }

            const adjuntos = await response.json();

            if (!adjuntos.length) {
                contenedor.innerHTML = '<div class="admin-empty-box">Sin adjuntos registrados.</div>';
                return;
            }

            contenedor.innerHTML = adjuntos.map(a => `
                <a class="admin-file-link" href="${apiBaseUrl}adjuntos/${a.id}/download" target="_blank">
                    <i class="bi bi-paperclip"></i>
                    <span>${escapeHtml(a.nombreOriginal || a.nombreArchivo || 'Adjunto')}</span>
                </a>
            `).join('');
        } catch {
            contenedor.innerHTML = '<div class="admin-empty-box">No se pudieron cargar los adjuntos.</div>';
        }
    }

    async function cargarHistorial() {
        const response = await fetch(`${apiBaseUrl}solicitudes/${solicitudId}/historial`);
        const historial = await response.json();

        const contenedor = document.getElementById('detalleHistorial');

        if (!historial.length) {
            contenedor.innerHTML = '<div class="admin-empty-box">Sin historial registrado.</div>';
            return;
        }

        contenedor.innerHTML = historial.map(h => `
            <div class="admin-history-item">
                <div class="admin-history-top">
                    <strong>${escapeHtml(h.accion)}</strong>
                    <span>${formatearFecha(h.fechaRegistro)}</span>
                </div>
                <p>${escapeHtml(h.estadoAnterior || '-')} → ${escapeHtml(h.estadoNuevo || '-')}</p>
                <small>${escapeHtml(h.usuario || '-')}</small>
                ${h.observacion ? `<div class="admin-history-note">${escapeHtml(h.observacion)}</div>` : ''}
            </div>
        `).join('');
    }

    document.getElementById('btnCambiarEstado').addEventListener('click', async () => {
        const estadoId = Number(document.getElementById('nuevoEstado').value);
        const observacion = document.getElementById('observacionEstado').value.trim();
        const operadorEdicion = document.getElementById('editorAsignado').value.trim();
        const nivelComplejidad = document.getElementById('nivelComplejidad').value;

        const response = await fetch(`${apiBaseUrl}solicitudes/${solicitudId}/estado`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                estadoId,
                usuario: 'Administrador Workspace',
                observacion,
                operadorEdicion,
                nivelComplejidad
            })
        });

        if (!response.ok && response.status !== 204) {
            alert('No se pudo cambiar el estado.');
            return;
        }

        alert('Estado actualizado correctamente.');
        location.reload();
    });

    document.getElementById('btnReabrir').addEventListener('click', () => {
        document.getElementById('nuevoEstado').value = '2';
        document.getElementById('observacionEstado').focus();
    });

    function campo(label, valor, full = false) {
        return `
            <div class="admin-detail-item ${full ? 'admin-detail-item-full' : ''}">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(valor || '-')}</strong>
            </div>
        `;
    }

    function formatearFecha(valor) {
        if (!valor) return '-';
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return valor;
        return fecha.toLocaleString('es-CL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function escapeHtml(texto) {
        return String(texto ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
</script>

<?php
$contenido = ob_get_clean();
include '../../../../layouts/app.php';
?>
