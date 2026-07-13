<?php
ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Panel Administrativo Desarrollo Gráfico</h1>
        <p>Gestión centralizada de solicitudes, estados, PDF, adjuntos e historial operacional.</p>
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
            <span>Recibidas</span>
            <strong id="kpiRecibidas">0</strong>
            <small>Solicitudes nuevas.</small>
        </div>

        <div class="admin-kpi-card">
            <span>En edición</span>
            <strong id="kpiEdicion">0</strong>
            <small>Actualmente en proceso.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Urgentes</span>
            <strong id="kpiUrgentes">0</strong>
            <small>Prioridad URGENTE.</small>
        </div>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Solicitudes</h2>
                <p>Filtra, revisa y opera las solicitudes de desarrollo gráfico.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidadSolicitudes">0 registros</span>
        </div>

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroCodigo">Código</label>
                <input type="text" id="filtroCodigo" placeholder="DG-000001">
            </div>

            <div class="admin-filter-field">
                <label for="filtroCliente">Cliente</label>
                <input type="text" id="filtroCliente" placeholder="Nombre cliente">
            </div>

            <div class="admin-filter-field">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="admin-filter-field">
                <label for="filtroPrioridad">Prioridad</label>
                <select id="filtroPrioridad">
                    <option value="">Todas</option>
                </select>
            </div>

            <div class="admin-filter-actions">
                <button class="admin-btn admin-btn-secondary" id="btnLimpiarFiltros">
                    <i class="bi bi-x-circle"></i>
                    Limpiar
                </button>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-grafico">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Solicitante</th>
                        <th>Cliente</th>
                        <th>OC</th>
                        <th>Producto</th>
                        <th>Proceso</th>
                        <th>Fecha</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaSolicitudesBody">
                    <tr>
                        <td colspan="10" class="admin-empty">Cargando solicitudes...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="adminPagination"></div>
    </div>
</section>

<script>
    window.API_FORMULARIOS = '<?= defined('API_FORMULARIOS') ? API_FORMULARIOS : 'https://api.faret.cl/formularios/api/' ?>';
</script>
<script src="/assets/js/formularios/admin-formularios.js"></script>

<?php
$contenido = ob_get_clean();
include '../../../../layouts/app.php';
?>
