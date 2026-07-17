<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('rrhh');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Panel Administrativo - Registro de Desgaje</h1>
        <p>Gestión centralizada de registros de producción de desgaje: filtros, validación y anulación.</p>
    </div>

    <div class="admin-hero-actions">
        <a class="admin-btn admin-btn-secondary" href="/modules/rrhh/">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
        <button class="admin-btn admin-btn-primary" id="btnRefrescarRegistros">
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
            <span>Total registros</span>
            <strong id="kpiTotal">0</strong>
            <small>Según filtro aplicado.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Estuches totales</span>
            <strong id="kpiEstuches">0</strong>
            <small>Suma según filtro aplicado.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Valor total</span>
            <strong id="kpiValor">$0</strong>
            <small>Suma según filtro aplicado.</small>
        </div>

        <div class="admin-kpi-card">
            <span>Pendientes de validar</span>
            <strong id="kpiPendientes">0</strong>
            <small>Registros en estado Firmado.</small>
        </div>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Registros</h2>
                <p>Filtra, revisa y gestiona los registros de desgaje.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidadRegistros">0 registros</span>
        </div>

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroFechaDesde">Fecha desde</label>
                <input type="date" id="filtroFechaDesde">
            </div>

            <div class="admin-filter-field">
                <label for="filtroFechaHasta">Fecha hasta</label>
                <input type="date" id="filtroFechaHasta">
            </div>

            <div class="admin-filter-field">
                <label for="filtroTaller">Taller</label>
                <select id="filtroTaller">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="admin-filter-field">
                <label for="filtroOperador">Operador</label>
                <select id="filtroOperador">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="admin-filter-field">
                <label for="filtroCliente">Cliente</label>
                <input type="text" id="filtroCliente" placeholder="Nombre cliente">
            </div>

            <div class="admin-filter-field">
                <label for="filtroNp">NP</label>
                <input type="text" id="filtroNp" placeholder="Número de proceso">
            </div>

            <div class="admin-filter-field">
                <label for="filtroTipo">Tipo de desgaje</label>
                <select id="filtroTipo">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="admin-filter-field">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="BORRADOR">Borrador</option>
                    <option value="FIRMADO">Firmado</option>
                    <option value="VALIDADO">Validado</option>
                    <option value="ANULADO">Anulado</option>
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
            <table class="admin-table admin-table-desgaje">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Taller</th>
                        <th>Operador</th>
                        <th>Estado</th>
                        <th>Trabajos</th>
                        <th>Estuches</th>
                        <th>Valor</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaRegistrosBody">
                    <tr>
                        <td colspan="9" class="admin-empty">Cargando registros...</td>
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
<script src="/assets/js/rrhh/desgaje-admin.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
