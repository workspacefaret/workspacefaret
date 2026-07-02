<?php
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
    <div class="panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Resumen Desarrollo Gráfico</h2>
                <p>Indicadores principales cargados desde la API corporativa de formularios.</p>
            </div>
            <a class="badge badge-primary" href="/modules/formularios/desarrollo/admin/">Ver registros completos</a>
        </div>

        <div class="grid-4">
            <div class="stat-card">
                <span>Total solicitudes</span>
                <strong id="dashTotalSolicitudes">0</strong>
                <small>Solicitudes registradas en la API.</small>
            </div>

            <div class="stat-card">
                <span>Recibidas</span>
                <strong id="dashRecibidas">0</strong>
                <small>Solicitudes nuevas pendientes de gestión.</small>
            </div>

            <div class="stat-card">
                <span>Urgentes</span>
                <strong id="dashUrgentes">0</strong>
                <small>Registros con prioridad URGENTE.</small>
            </div>

            <div class="stat-card">
                <span>Terminadas</span>
                <strong id="dashTerminadas">0</strong>
                <small>Solicitudes finalizadas.</small>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid">
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

        <div class="chart-card">
            <h3>Solicitantes</h3>
            <div id="chartSolicitantes">Cargando...</div>
        </div>
    </div>
</section>

<script>
    window.API_FORMULARIOS = 'https://api.faret.cl/formularios/api/';
</script>
<script src="/assets/js/formularios/desarrollo-dashboard.js"></script>

<?php
$contenido = ob_get_clean();
include '../../../layouts/app.php';
?>
