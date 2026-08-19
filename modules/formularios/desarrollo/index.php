<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('desarrollo');

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero">
    <h1>Desarrollo</h1>
    <p>Acceso centralizado a formularios y registros del área de desarrollo gráfico corporativo.</p>
</section>

<section class="section">
    <div class="grid-3">

        <a class="action-card" href="/modules/formularios/desarrollo/formularios/">
            <div class="action-card-icon">
                <i class="bi bi-file-earmark-plus-fill"></i>
            </div>
            <h3>Formularios</h3>
            <p>Ingreso de nuevas solicitudes de desarrollo (gráfico y estructural).</p>
        </a>

        <a class="action-card" href="/modules/formularios/desarrollo/registros/">
            <div class="action-card-icon">
                <i class="bi bi-table"></i>
            </div>
            <h3>Registros</h3>
            <p>Paneles administrativos para revisar, filtrar y gestionar solicitudes existentes.</p>
        </a>

        <a class="action-card" href="/modules/operacion/">
            <div class="action-card-icon">
                <i class="bi bi-arrow-left-circle-fill"></i>
            </div>
            <h3>Volver</h3>
            <p>Regresar al módulo de operación.</p>
        </a>

    </div>
</section>

<section class="section">
    <div class="admin-tabs" role="tablist">
        <button type="button" class="admin-tab is-active" data-dev-tab="grafico">Desarrollo Gráfico</button>
        <button type="button" class="admin-tab" data-dev-tab="estructural">Desarrollo Estructural</button>
    </div>
</section>

<div class="admin-tab-panel is-active" data-dev-panel="grafico">

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Resumen Desarrollo Gráfico</h2>
                <p>Indicadores principales cargados desde la API corporativa de formularios.</p>
            </div>
            <a class="badge badge-primary" href="/modules/formularios/desarrollo/admin/">Ver registros completos</a>
        </div>

        <div class="admin-kpi-grid">
            <div class="admin-kpi-card">
                <span>Total solicitudes</span>
                <strong id="dashTotalSolicitudes">0</strong>
            </div>
            <div class="admin-kpi-card">
                <span>Recibidas</span>
                <strong id="dashRecibidas">0</strong>
            </div>
            <div class="admin-kpi-card">
                <span>Urgentes</span>
                <strong id="dashUrgentes">0</strong>
            </div>
            <div class="admin-kpi-card">
                <span>Terminadas</span>
                <strong id="dashTerminadas">0</strong>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid dashboard-charts-grid-3">
        <div class="chart-card">
            <h3>Estados</h3>
            <div id="chartEstados">Cargando...</div>
        </div>

        <div class="chart-card">
            <h3>Prioridades</h3>
            <div id="chartPrioridades">Cargando...</div>
        </div>

        <div class="chart-card">
            <h3>Procesos</h3>
            <div id="chartProcesos">Cargando...</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="chart-card">
        <h3>Solicitantes</h3>
        <div id="chartSolicitantes">Cargando...</div>
    </div>
</section>

<section class="section" id="seccionEvolucion">
    <div class="chart-card">
        <h3>Evolución de solicitudes</h3>
        <div id="chartEvolucion">Cargando...</div>
    </div>
</section>

</div>

<div class="admin-tab-panel" data-dev-panel="estructural">

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Resumen Desarrollo Estructural</h2>
                <p>Indicadores principales cargados desde la API corporativa de formularios.</p>
            </div>
            <a class="badge badge-primary" href="/modules/formularios/desarrollo/solicitud-estructural/admin/">Ver registros completos</a>
        </div>

        <div class="admin-kpi-grid">
            <div class="admin-kpi-card">
                <span>Total solicitudes</span>
                <strong id="dashEstructuralTotal">0</strong>
            </div>
            <div class="admin-kpi-card">
                <span>Con archivo adjunto</span>
                <strong id="dashEstructuralAdjunto">0</strong>
            </div>
            <div class="admin-kpi-card">
                <span>Cliente nuevo</span>
                <strong id="dashEstructuralClienteNuevo">0</strong>
            </div>
            <div class="admin-kpi-card">
                <span>Últimos 7 días</span>
                <strong id="dashEstructuralRecientes">0</strong>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid">
        <div class="chart-card">
            <h3>Productos más solicitados</h3>
            <div id="chartEstructuralProducto">Cargando...</div>
        </div>

        <div class="chart-card">
            <h3>Sustratos más utilizados</h3>
            <div id="chartEstructuralSustrato">Cargando...</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid">
        <div class="chart-card">
            <h3>Destino muestras</h3>
            <div id="chartEstructuralDestino">Cargando...</div>
        </div>

        <div class="chart-card">
            <h3>Solicitantes</h3>
            <div id="chartEstructuralSolicitantes">Cargando...</div>
        </div>
    </div>
</section>

</div>

<script>
    window.API_FORMULARIOS = 'https://api.faret.cl/formularios/api/';
</script>
<script src="/assets/js/dashboard-charts.js"></script>
<script src="/assets/js/formularios/desarrollo-dashboard-utils.js"></script>
<script src="/assets/js/formularios/desarrollo-dashboard.js"></script>
<script src="/assets/js/formularios/desarrollo-estructural-dashboard.js"></script>

<?php
$contenido = ob_get_clean();
include '../../../layouts/app.php';
?>
