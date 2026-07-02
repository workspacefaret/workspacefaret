<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Panel Administrativo Desarrollo Estructural</h1>
        <p>Gestión centralizada de solicitudes, adjuntos y PDF de Desarrollo Estructural.</p>
    </div>

    <div class="admin-hero-actions">
        <a class="admin-btn admin-btn-secondary" href="/modules/formularios/desarrollo/">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
        <button class="admin-btn admin-btn-primary" id="btnRefrescarSolicitudes">
            <i class="bi bi-arrow-clockwise"></i>
            Refrescar
        </button>
        <button class="admin-btn admin-btn-secondary" id="btnExportarExcel">
            <i class="bi bi-file-earmark-excel"></i>
            Exportar Excel
        </button>
    </div>
</section>

<section class="section">
    <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
            <span>Total solicitudes</span>
            <strong id="kpiTotal">0</strong>
            <small>Registros cargados desde API.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Con archivo adjunto</span>
            <strong id="kpiConAdjunto">0</strong>
            <small>Solicitudes con al menos un adjunto.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Cliente nuevo</span>
            <strong id="kpiClienteNuevo">0</strong>
            <small>Solicitudes con cliente nuevo indicado.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Últimos 7 días</span>
            <strong id="kpiRecientes">0</strong>
            <small>Solicitudes registradas la última semana.</small>
        </div>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Solicitudes</h2>
                <p>Filtra y revisa las solicitudes de desarrollo estructural.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidadSolicitudes">0 registros</span>
        </div>

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroCodigo">Código</label>
                <input type="text" id="filtroCodigo" placeholder="DE-000001">
            </div>

            <div class="admin-filter-field">
                <label for="filtroCliente">Cliente</label>
                <input type="text" id="filtroCliente" placeholder="Nombre cliente">
            </div>

            <div class="admin-filter-field">
                <label for="filtroFechaDesde">Desde</label>
                <input type="date" id="filtroFechaDesde">
            </div>

            <div class="admin-filter-field">
                <label for="filtroFechaHasta">Hasta</label>
                <input type="date" id="filtroFechaHasta">
            </div>

            <div class="admin-filter-actions">
                <button class="admin-btn admin-btn-secondary" id="btnLimpiarFiltros">
                    <i class="bi bi-x-circle"></i>
                    Limpiar
                </button>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha creación</th>
                        <th>Solicitante</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Cantidad muestras</th>
                        <th>Destino muestras</th>
                        <th>Referencia</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaSolicitudesBody">
                    <tr>
                        <td colspan="9" class="admin-empty">Cargando solicitudes...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="adminPagination"></div>
    </div>
</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
</script>
<script src="/assets/js/formularios/admin-estructural.js"></script>

<?php
$contenido = ob_get_clean();
include '../../../../../layouts/app.php';
?>
