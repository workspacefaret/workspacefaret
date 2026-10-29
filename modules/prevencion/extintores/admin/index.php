<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('prevencion_riesgos');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Panel de inspecciones de extintores</h1>
        <p>Estado mensual de todos los extintores: pendientes, conformes, con hallazgos y vencimientos.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/prevencion/extintores/inspeccion/" class="admin-btn admin-btn-primary">
            <i class="bi bi-fire"></i>
            Inspeccionar
        </a>
        <a href="/modules/prevencion/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </div>
</section>

<section class="section">
    <div class="admin-kpi-grid">
        <div class="admin-kpi-card admin-kpi-card-link" style="--kpi-color:#3b82f6" data-filtro="">
            <span><i class="bi bi-fire"></i>Extintores activos</span>
            <strong id="kpiTotal">-</strong>
            <small>En la planta y periodo seleccionados</small>
        </div>
        <div class="admin-kpi-card admin-kpi-card-link pr-kpi-click" style="--kpi-color:#f59e0b" data-filtro="PENDIENTE" role="button" tabindex="0">
            <span><i class="bi bi-hourglass-split"></i>Pendientes</span>
            <strong id="kpiPendientes">-</strong>
            <small>Sin inspección en el periodo</small>
        </div>
        <div class="admin-kpi-card admin-kpi-card-link pr-kpi-click" style="--kpi-color:#22c55e" data-filtro="CONFORME" role="button" tabindex="0">
            <span><i class="bi bi-check2-circle"></i>Conformes</span>
            <strong id="kpiConformes">-</strong>
            <small>Inspeccionados sin observaciones</small>
        </div>
        <div class="admin-kpi-card admin-kpi-card-link pr-kpi-click" style="--kpi-color:#ef4444" data-filtro="CON_HALLAZGOS" role="button" tabindex="0">
            <span><i class="bi bi-exclamation-triangle"></i>Con hallazgos</span>
            <strong id="kpiHallazgos">-</strong>
            <small>Al menos un criterio en «No»</small>
        </div>
        <div class="admin-kpi-card admin-kpi-card-link pr-kpi-click" style="--kpi-color:#dc2626" data-filtro="VENCIDO" role="button" tabindex="0">
            <span><i class="bi bi-calendar-x"></i>Vencidos</span>
            <strong id="kpiVencidos">-</strong>
            <small>Fecha de vencimiento ya cumplida</small>
        </div>
        <div class="admin-kpi-card admin-kpi-card-link" style="--kpi-color:#a855f7" data-filtro="">
            <span><i class="bi bi-calendar-event"></i>Vencen en 60 días</span>
            <strong id="kpiPorVencer">-</strong>
            <small>Próximos a vencer</small>
        </div>
    </div>
    <p class="pr-hint"><i class="bi bi-hand-index"></i>Haz clic en Pendientes, Conformes, Con hallazgos o Vencidos para filtrar la tabla.</p>
</section>

<section class="section">
    <div class="pr-cumplimiento chart-card">
        <div>
            <h3>Avance de inspección del mes</h3>
            <p class="pr-cumplimiento-sub" id="avanceTexto">-</p>
        </div>
        <div class="pr-progreso" aria-hidden="true"><div class="pr-progreso-fill" id="avanceBarra" style="width:0%"></div></div>
        <strong class="pr-cumplimiento-pct" id="avancePct">-</strong>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid dashboard-charts-grid-3">
        <div class="chart-card">
            <h3>Estado de los extintores</h3>
            <div id="chartEstados" class="pr-chart">Cargando...</div>
        </div>
        <div class="chart-card">
            <h3>Por tipo de extintor</h3>
            <div id="chartTipos" class="pr-chart">Cargando...</div>
        </div>
        <div class="chart-card">
            <h3>Criterios con más hallazgos</h3>
            <div id="chartCriterios" class="pr-chart">Cargando...</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid">
        <div class="chart-card">
            <h3>Cumplimiento por planta</h3>
            <div id="chartPlantas" class="pr-chart">Cargando...</div>
        </div>
        <div class="chart-card">
            <h3>Pendientes por área</h3>
            <div id="chartAreas" class="pr-chart">Cargando...</div>
        </div>
    </div>
</section>

<section class="section">
    <div class="dashboard-charts-grid">
        <div class="chart-card">
            <h3>Vencidos y por vencer (60 días)</h3>
            <div id="listaVencimientos" class="pr-chart">Cargando...</div>
        </div>
        <div class="chart-card">
            <h3>Hallazgos que requieren atención</h3>
            <div id="listaHallazgos" class="pr-chart">Cargando...</div>
        </div>
    </div>
</section>

<section class="section" id="seccionTabla">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Extintores</h2>
                <p>Haz clic en un extintor inspeccionado para ver el detalle.</p>
            </div>
            <div class="admin-hero-actions">
                <span class="badge badge-primary" id="badgeCantidad">0 extintores</span>
                <button type="button" class="admin-btn admin-btn-secondary" id="btnExportarExcel">
                    <i class="bi bi-file-earmark-excel"></i>
                    Exportar Excel
                </button>
            </div>
        </div>

        <div id="alertaListado" class="admin-alert hidden"></div>

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroPeriodo">Periodo</label>
                <input type="month" id="filtroPeriodo">
            </div>
            <div class="admin-filter-field">
                <label for="filtroPlanta">Planta</label>
                <select id="filtroPlanta"></select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="CONFORME">Conforme</option>
                    <option value="CON_HALLAZGOS">Con hallazgos</option>
                    <option value="VENCIDO">Vencido</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroTipo">Tipo</label>
                <select id="filtroTipo"></select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroBuscar">Buscar</label>
                <input type="text" id="filtroBuscar" placeholder="Ubicación, N°, área, inspector...">
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltros">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap admin-table-wrap-sticky">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Planta</th>
                        <th>N°</th>
                        <th>Ubicación</th>
                        <th>Área</th>
                        <th>Tipo</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Hallazgos</th>
                        <th>Inspector</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr><td colspan="10" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    // Las llamadas administrativas pasan por api.php (valida sesión y permiso en el servidor); no hay claves en el navegador.
    window.PR_ADMIN = true;
</script>
<script src="/assets/js/dashboard-charts.js"></script>
<script src="/assets/js/prevencion/extintores-comun.js"></script>
<script src="/assets/js/prevencion/extintores-admin.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
