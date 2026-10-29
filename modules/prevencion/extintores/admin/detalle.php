<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('prevencion_riesgos');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$inspeccionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1 id="tituloDetalle">Inspección de extintor</h1>
        <p id="subtituloDetalle">Cargando...</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/prevencion/extintores/admin/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver al panel
        </a>
    </div>
</section>

<div id="alertaDetalle" class="admin-alert hidden"></div>

<section class="section" id="seccionDetalle" style="display:none;">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Datos de la inspección</h2>
            </div>
            <span id="badgeResultado"></span>
        </div>
        <div class="admin-kpi-grid" id="datosInspeccion"></div>
    </div>
</section>

<section class="section" id="seccionResultados" style="display:none;">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Checklist</h2>
                <p>Resultado por criterio.</p>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>N°</th><th>Criterio</th><th>Resultado</th><th>Año</th><th>Observación</th></tr>
                </thead>
                <tbody id="tablaResultados"></tbody>
            </table>
        </div>
    </div>
</section>

<section class="section" id="seccionHistorial" style="display:none;">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Historial del extintor</h2>
                <p>Todas las inspecciones registradas para este extintor.</p>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Periodo</th><th>Fecha</th><th>Inspector</th><th>Resultado</th></tr>
                </thead>
                <tbody id="tablaHistorial"></tbody>
            </table>
        </div>
    </div>
</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    // Las llamadas administrativas pasan por api.php (valida sesión y permiso en el servidor); no hay claves en el navegador.
    window.PR_ADMIN = true;
    window.prInspeccionId = <?= $inspeccionId ?>;
</script>
<script src="/assets/js/prevencion/extintores-comun.js"></script>
<script>
    (async function () {
        const PR = window.PrExt;
        const alerta = document.getElementById('alertaDetalle');
        const fallo = function (mensaje) { alerta.textContent = mensaje; alerta.className = 'admin-alert admin-alert-error'; };

        if (!window.prInspeccionId) { fallo('Inspección no indicada.'); return; }

        try {
            const i = await PR.api('inspecciones/' + window.prInspeccionId);
            const historial = await PR.api(i.extintorId + '/inspecciones');

            document.getElementById('tituloDetalle').textContent = 'Extintor N° ' + i.numero + ' · ' + i.plantaNombre;
            document.getElementById('subtituloDetalle').textContent = i.ubicacion + ' — inspección de ' + PR.formatearPeriodo(i.periodo);
            document.getElementById('badgeResultado').innerHTML = PR.badgeEstado(i.resultadoGeneral);

            const kpi = function (titulo, valor) {
                return '<div class="admin-kpi-card"><span>' + titulo + '</span><strong style="font-size:20px">' + valor + '</strong></div>';
            };
            document.getElementById('datosInspeccion').innerHTML =
                kpi('Tipo', PR.escapeHtml(PR.etiquetaTipo(i))) +
                kpi('Vencimiento', PR.badgeVencimiento(i.vencimiento)) +
                kpi('Inspector', PR.escapeHtml(i.usuario)) +
                kpi('Fecha', PR.escapeHtml(PR.formatearFechaHora(i.fechaInspeccion)));

            document.getElementById('tablaResultados').innerHTML = i.resultados.map(function (r) {
                return '<tr><td>' + r.orden + '</td><td>' + PR.escapeHtml(r.criterio) + '</td><td>' + PR.badgeResultado(r.resultado) +
                    '</td><td>' + (r.anio || '-') + '</td><td>' + PR.escapeHtml(r.observacion || '-') + '</td></tr>';
            }).join('');

            document.getElementById('tablaHistorial').innerHTML = historial.map(function (h) {
                const actual = h.id === i.id ? ' <small>(esta)</small>' : '';
                return '<tr><td><a href="?id=' + h.id + '">' + PR.escapeHtml(PR.formatearPeriodo(h.periodo)) + '</a>' + actual + '</td><td>' +
                    PR.escapeHtml(PR.formatearFechaHora(h.fechaInspeccion)) + '</td><td>' + PR.escapeHtml(h.usuario) +
                    '</td><td>' + PR.badgeEstado(h.resultadoGeneral) + '</td></tr>';
            }).join('');

            ['seccionDetalle', 'seccionResultados', 'seccionHistorial'].forEach(function (id) {
                document.getElementById(id).style.display = '';
            });
        } catch (error) {
            fallo(error.message);
        }
    })();
</script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
