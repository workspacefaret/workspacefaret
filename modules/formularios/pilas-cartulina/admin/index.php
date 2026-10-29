<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('pilas_cartulina');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$nombreUsuarioActual = currentUser()['nombre'];
$puedeGestionar = moduleAccessLevel('pilas_cartulina') === 'gestionar';
$estadoInicial = isset($_GET['estado']) ? (string) $_GET['estado'] : '';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Registros Pilas Cartulina</h1>
        <p>Traspasos de pilas de cartulina dimensionada: entrega, recepción firmada e historial.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/formularios/pilas-cartulina/entrega/" class="admin-btn admin-btn-primary">
            <i class="bi bi-plus-circle"></i>
            Nueva entrega
        </a>
        <a href="/modules/formularios/pilas-cartulina/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </div>
</section>

<section class="section">
    <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
            <span><i class="bi bi-hourglass-split"></i>Pendientes de recepción</span>
            <strong id="kpiPendientes">-</strong>
            <small>Entregadas y aún sin firma de quien recibe</small>
        </div>
        <div class="admin-kpi-card">
            <span><i class="bi bi-check2-circle"></i>Recibidos</span>
            <strong id="kpiRecibidos">-</strong>
            <small>Ciclo cerrado con firma</small>
        </div>
        <div class="admin-kpi-card">
            <span><i class="bi bi-x-circle"></i>Anulados</span>
            <strong id="kpiAnulados">-</strong>
            <small>Entregas anuladas con motivo</small>
        </div>
        <div class="admin-kpi-card">
            <span><i class="bi bi-layers-fill"></i>Total</span>
            <strong id="kpiTotal">-</strong>
            <small>Todos los traspasos registrados</small>
        </div>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Traspasos</h2>
                <p>Filtra, revisa, exporta y administra los registros.</p>
            </div>
            <div class="admin-hero-actions">
                <span class="badge badge-primary" id="badgeCantidadRegistros">0 registros</span>
                <button type="button" class="admin-btn admin-btn-secondary" id="btnExportarExcel">
                    <i class="bi bi-file-earmark-excel"></i>
                    Exportar Excel
                </button>
                <button type="button" class="admin-btn admin-btn-secondary" id="btnImprimirRegistros">
                    <i class="bi bi-printer"></i>
                    Imprimir
                </button>
            </div>
        </div>

        <div id="alertaListado" class="admin-alert hidden"></div>

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroBuscar">Buscar</label>
                <input type="text" id="filtroBuscar" placeholder="Folio, NP, cliente, cartulina, persona...">
            </div>
            <div class="admin-filter-field">
                <label for="filtroNp">NP</label>
                <input type="text" id="filtroNp" placeholder="NP exacta">
            </div>
            <div class="admin-filter-field">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="PENDIENTE_RECEPCION">Pendiente de recepción</option>
                    <option value="RECIBIDO">Recibido</option>
                    <option value="ANULADO">Anulado</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroFechaDesde">Entrega desde</label>
                <input type="date" id="filtroFechaDesde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroFechaHasta">Entrega hasta</label>
                <input type="date" id="filtroFechaHasta">
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltros">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap admin-table-wrap-sticky">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha entrega</th>
                        <th>NP</th>
                        <th>Cliente</th>
                        <th>Cartulina</th>
                        <th>Formato</th>
                        <th>Solicitada</th>
                        <th>Entregada</th>
                        <th>Palets</th>
                        <th>Entregado por</th>
                        <th>Recibido por</th>
                        <th>Fecha recepción</th>
                        <th>Estado</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaRegistrosBody">
                    <tr><td colspan="14" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="paginacionRegistros"></div>
    </div>
</section>

<?php if ($puedeGestionar): ?>
<!-- ================= MODAL EDITAR ================= -->
<div class="admin-modal-overlay hidden" id="modalEditar">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Editar traspaso <span id="modalEditarFolio"></span></h2>
                <p>Corrige los datos de la entrega. El estado, la firma y las fechas no se editan aquí.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalEditar" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaEditar" class="admin-alert hidden"></div>

        <form id="formEditar" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="editarId">

            <div class="admin-form-field">
                <label for="editarNp">NP</label>
                <input type="text" id="editarNp" required>
            </div>
            <div class="admin-form-field">
                <label for="editarFechaEntrega">Fecha de entrega</label>
                <input type="datetime-local" id="editarFechaEntrega">
            </div>
            <div class="admin-form-field">
                <label for="editarCliente">Cliente</label>
                <input type="text" id="editarCliente">
            </div>
            <div class="admin-form-field">
                <label for="editarFormato">Formato</label>
                <input type="text" id="editarFormato">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarCartulina">Cartulina</label>
                <input type="text" id="editarCartulina">
            </div>
            <div class="admin-form-field">
                <label for="editarCantidadSolicitada">Cantidad solicitada</label>
                <input type="number" id="editarCantidadSolicitada" min="0.01" step="0.01" required>
            </div>
            <div class="admin-form-field">
                <label for="editarCantidadEntregada">Cantidad entregada</label>
                <input type="number" id="editarCantidadEntregada" min="0" step="0.01" required>
            </div>
            <div class="admin-form-field">
                <label for="editarNumeroPalets">N° de palets</label>
                <input type="number" id="editarNumeroPalets" min="0" step="1" required>
            </div>
            <div class="admin-form-field">
                <label for="editarEntregadoPor">Entregado por</label>
                <input type="text" id="editarEntregadoPor" required>
            </div>
            <div class="admin-form-field">
                <label for="editarRecibidoPor">Recibido por</label>
                <input type="text" id="editarRecibidoPor">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarProductos">Productos</label>
                <textarea id="editarProductos" rows="2"></textarea>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarObservacionesEntrega">Observaciones de entrega</label>
                <textarea id="editarObservacionesEntrega" rows="2"></textarea>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarObservacionesRecepcion">Observaciones de recepción</label>
                <textarea id="editarObservacionesRecepcion" rows="2"></textarea>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarEdicion">
                    <i class="bi bi-save"></i>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL ANULAR ================= -->
<div class="admin-modal-overlay hidden" id="modalAnular">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Anular traspaso <span id="modalAnularFolio"></span></h2>
                <p>El registro queda marcado como anulado y se conserva en el historial.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalAnular" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaAnular" class="admin-alert hidden"></div>

        <form id="formAnular" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="anularId">
            <div class="admin-form-field admin-form-field-full">
                <label for="anularMotivo">Motivo</label>
                <textarea id="anularMotivo" rows="3" required></textarea>
            </div>
            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnConfirmarAnular">
                    <i class="bi bi-x-circle"></i>
                    Anular
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL HISTORIAL ================= -->
<div class="admin-modal-overlay hidden" id="modalHistorial">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Historial <span id="modalHistorialFolio"></span></h2>
                <p>Flujo completo del traspaso: creación, recepción, ediciones y anulación.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalHistorial" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="historialContenido" class="admin-change-state">
            <p class="admin-empty-box">Cargando...</p>
        </div>
    </div>
</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
    window.pilasPuedeGestionar = <?= $puedeGestionar ? 'true' : 'false' ?>;
    window.pilasEstadoInicial = <?= json_encode($estadoInicial) ?>;
    <?php if ($puedeGestionar): ?>
    window.API_ADMIN_DELETE_KEY = <?= json_encode(defined('API_ADMIN_DELETE_KEY') ? API_ADMIN_DELETE_KEY : '') ?>;
    <?php endif; ?>
</script>
<script src="/assets/js/planificacion/print-tabla.js"></script>
<script src="/assets/js/formularios/pilas-cartulina-comun.js"></script>
<script src="/assets/js/formularios/pilas-cartulina-admin.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
