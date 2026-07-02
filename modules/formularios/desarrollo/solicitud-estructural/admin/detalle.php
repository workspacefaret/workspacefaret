<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1 id="detalleTitulo">Detalle Solicitud</h1>
        <p>Información completa y adjuntos de la solicitud de desarrollo estructural.</p>
    </div>

    <div class="admin-hero-actions">
        <a class="admin-btn admin-btn-secondary" href="/modules/formularios/desarrollo/solicitud-estructural/admin/">
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
            </div>

            <div class="admin-detail-list" id="detalleContenido"></div>
        </div>

    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Adjuntos</h2>
                <p>Archivos cargados en la solicitud.</p>
            </div>
        </div>
        <div id="detalleAdjuntos" class="admin-list-box">Cargando adjuntos...</div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const solicitudId = <?= $id ?>;
    const apiBaseUrl = '<?= htmlspecialchars(API_FORMULARIOS) ?>';

    cargarTodo();

    async function cargarTodo() {
        if (!solicitudId) {
            document.getElementById('detalleContenido').innerHTML = '<div class="admin-empty">ID de solicitud no válido.</div>';
            return;
        }

        await cargarDetalle();
        await cargarAdjuntos();
    }

    async function cargarDetalle() {
        const response = await fetch(`${apiBaseUrl}solicitudes-estructural/${solicitudId}/detalle`);
        const item = await response.json();

        document.getElementById('detalleTitulo').textContent = item.codigo;
        document.getElementById('detalleSubtitulo').textContent = `${item.clienteNombre} · ${item.producto}`;
        document.getElementById('btnPdfDetalle').href = `${apiBaseUrl}solicitudes-estructural/${item.id}/pdf`;

        const referencia = item.referenciaOtrosTexto
            ? `${item.referencia || ''} (${item.referenciaOtrosTexto})`
            : (item.referencia || '-');

        document.getElementById('detalleContenido').innerHTML = `
            ${campo('Código', item.codigo)}
            ${campo('Fecha registro', formatearFecha(item.fechaRegistro))}
            ${campo('Solicitante', item.solicitanteNombre)}
            ${campo('Cliente', item.clienteNombre)}
            ${campo('Cliente nuevo', item.clienteNuevo)}
            ${campo('Producto', item.producto, true)}
            ${campo('Solicitud', item.solicitud, true)}
            ${campo('Cantidad muestras', item.cantidadMuestras)}
            ${campo('Destino muestras', item.destinoMuestras)}
            ${campo('Sustrato', item.sustrato, true)}
            ${campo('Referencia', referencia)}
        `;
    }

    async function cargarAdjuntos() {
        const contenedor = document.getElementById('detalleAdjuntos');

        try {
            const response = await fetch(`${apiBaseUrl}solicitudes-estructural/${solicitudId}/adjuntos`);
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
                <a class="admin-file-link" href="${apiBaseUrl}solicitudes-estructural/adjuntos/${a.id}/download" target="_blank">
                    <i class="bi bi-paperclip"></i>
                    <span>${escapeHtml(a.nombreArchivo || 'Adjunto')}</span>
                </a>
            `).join('');
        } catch {
            contenedor.innerHTML = '<div class="admin-empty-box">No se pudieron cargar los adjuntos.</div>';
        }
    }

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
include '../../../../../layouts/app.php';
?>
