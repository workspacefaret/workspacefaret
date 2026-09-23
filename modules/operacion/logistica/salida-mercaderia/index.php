<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('logistica');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$nombreUsuarioActual = currentUser()['nombre'];
$puedeEliminar = moduleAccessLevel('logistica') === 'gestionar';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Salida de Mercadería</h1>
        <p>Registro de mercadería que sale de bodega/planta, con folio automático.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/operacion/logistica/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver a Logística
        </a>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Nueva salida</h2>
                <p>El folio se genera automáticamente al guardar.</p>
            </div>
        </div>

        <div id="alertaRegistro" class="admin-alert hidden"></div>

        <form id="formRegistro" class="admin-form-grid" autocomplete="off">
            <div class="admin-form-field">
                <label for="smEmpresa">Empresa</label>
                <select id="smEmpresa" required>
                    <option value="">-</option>
                    <option value="FARET">Faret</option>
                    <option value="INNPACK">Innpack</option>
                    <option value="PHARPACK">Pharpack</option>
                    <option value="LENIAN">Lenian</option>
                </select>
            </div>
            <div class="admin-form-field">
                <label for="smFecha">Fecha</label>
                <input type="date" id="smFecha" required>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="smTipoMercaderia">Tipo de mercadería</label>
                <input type="text" id="smTipoMercaderia" placeholder="Ej: 10 pliegos cartón para sacar molde" required>
            </div>
            <div class="admin-form-field">
                <label for="smCantidad">Cantidad</label>
                <input type="number" id="smCantidad" min="0.01" step="0.01" required>
            </div>
            <div class="admin-form-field">
                <label for="smUnidadMedida">Unidad de medida</label>
                <select id="smUnidadMedida" required>
                    <option value="">-</option>
                    <option value="UND">Unidad</option>
                    <option value="CAJA">Caja</option>
                    <option value="PALLET">Pallet</option>
                    <option value="KG">Kilogramo</option>
                    <option value="LT">Litro</option>
                    <option value="ROLLO">Rollo</option>
                    <option value="SACO">Saco</option>
                    <option value="BULTO">Bulto</option>
                    <option value="M">Metro</option>
                </select>
            </div>
            <div class="admin-form-field">
                <label for="smRetiradoPor">Retirado por</label>
                <input type="text" id="smRetiradoPor" required>
            </div>
            <div class="admin-form-field">
                <label for="smRutRetira">RUT de quien retira</label>
                <input type="text" id="smRutRetira" placeholder="Ej: 12912159-9">
            </div>
            <div class="admin-form-field">
                <label for="smPatente">Patente</label>
                <input type="text" id="smPatente" placeholder="Opcional">
            </div>
            <div class="admin-form-field">
                <label for="smAutorizadoPor">Autorizado por</label>
                <input type="text" id="smAutorizadoPor" required>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="smObservaciones">Observaciones</label>
                <textarea id="smObservaciones" rows="2"></textarea>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarRegistro">
                    <i class="bi bi-save"></i>
                    Guardar registro
                </button>
            </div>
        </form>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Registros</h2>
                <p>Filtra, busca y edita las salidas registradas.</p>
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

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroSmBuscar">Buscar</label>
                <input type="text" id="filtroSmBuscar" placeholder="Folio, tipo, retirado por, RUT, patente...">
            </div>
            <div class="admin-filter-field">
                <label for="filtroSmEmpresa">Empresa</label>
                <select id="filtroSmEmpresa">
                    <option value="">Todas</option>
                    <option value="FARET">Faret</option>
                    <option value="INNPACK">Innpack</option>
                    <option value="PHARPACK">Pharpack</option>
                    <option value="LENIAN">Lenian</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroSmUnidadMedida">Unidad</label>
                <select id="filtroSmUnidadMedida">
                    <option value="">Todas</option>
                    <option value="UND">Unidad</option>
                    <option value="CAJA">Caja</option>
                    <option value="PALLET">Pallet</option>
                    <option value="KG">Kilogramo</option>
                    <option value="LT">Litro</option>
                    <option value="ROLLO">Rollo</option>
                    <option value="SACO">Saco</option>
                    <option value="BULTO">Bulto</option>
                    <option value="M">Metro</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroSmFechaDesde">Fecha desde</label>
                <input type="date" id="filtroSmFechaDesde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroSmFechaHasta">Fecha hasta</label>
                <input type="date" id="filtroSmFechaHasta">
            </div>
            <div class="admin-filter-field">
                <label for="filtroSmIncluirAnulados">Anulados</label>
                <select id="filtroSmIncluirAnulados">
                    <option value="false">Ocultar anulados</option>
                    <option value="true">Incluir anulados</option>
                </select>
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltrosRegistro">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap admin-table-wrap-sticky">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Empresa</th>
                        <th>Fecha</th>
                        <th>Tipo de mercadería</th>
                        <th>Cantidad</th>
                        <th>Unidad</th>
                        <th>Retirado por</th>
                        <th>RUT</th>
                        <th>Patente</th>
                        <th>Autorizado por</th>
                        <th>Observaciones</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaRegistrosBody">
                    <tr><td colspan="12" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="paginacionRegistros"></div>
    </div>
</section>

<!-- ================= MODAL EDITAR REGISTRO ================= -->
<div class="admin-modal-overlay hidden" id="modalEditarRegistro">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Editar registro <span id="modalEditarRegistroFolio"></span></h2>
                <p>Actualiza cualquier campo de esta salida.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalEditarRegistro" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaEditarRegistro" class="admin-alert hidden"></div>

        <form id="formEditarRegistro" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="editarSmId">

            <div class="admin-form-field">
                <label for="editarSmEmpresa">Empresa</label>
                <select id="editarSmEmpresa" required>
                    <option value="">-</option>
                    <option value="FARET">Faret</option>
                    <option value="INNPACK">Innpack</option>
                    <option value="PHARPACK">Pharpack</option>
                    <option value="LENIAN">Lenian</option>
                </select>
            </div>
            <div class="admin-form-field">
                <label for="editarSmFecha">Fecha</label>
                <input type="date" id="editarSmFecha" required>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarSmTipoMercaderia">Tipo de mercadería</label>
                <input type="text" id="editarSmTipoMercaderia" required>
            </div>
            <div class="admin-form-field">
                <label for="editarSmCantidad">Cantidad</label>
                <input type="number" id="editarSmCantidad" min="0.01" step="0.01" required>
            </div>
            <div class="admin-form-field">
                <label for="editarSmUnidadMedida">Unidad de medida</label>
                <select id="editarSmUnidadMedida" required>
                    <option value="">-</option>
                    <option value="UND">Unidad</option>
                    <option value="CAJA">Caja</option>
                    <option value="PALLET">Pallet</option>
                    <option value="KG">Kilogramo</option>
                    <option value="LT">Litro</option>
                    <option value="ROLLO">Rollo</option>
                    <option value="SACO">Saco</option>
                    <option value="BULTO">Bulto</option>
                    <option value="M">Metro</option>
                </select>
            </div>
            <div class="admin-form-field">
                <label for="editarSmRetiradoPor">Retirado por</label>
                <input type="text" id="editarSmRetiradoPor" required>
            </div>
            <div class="admin-form-field">
                <label for="editarSmRutRetira">RUT de quien retira</label>
                <input type="text" id="editarSmRutRetira">
            </div>
            <div class="admin-form-field">
                <label for="editarSmPatente">Patente</label>
                <input type="text" id="editarSmPatente">
            </div>
            <div class="admin-form-field">
                <label for="editarSmAutorizadoPor">Autorizado por</label>
                <input type="text" id="editarSmAutorizadoPor" required>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarSmObservaciones">Observaciones</label>
                <textarea id="editarSmObservaciones" rows="2"></textarea>
            </div>
            <div class="admin-form-field">
                <label for="editarSmAnulado">Estado</label>
                <select id="editarSmAnulado">
                    <option value="0">Activo</option>
                    <option value="1">Anulado</option>
                </select>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarEdicionRegistro">
                    <i class="bi bi-save"></i>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
    window.puedeEliminarSalidaMercaderia = <?= $puedeEliminar ? 'true' : 'false' ?>;
    <?php if ($puedeEliminar): ?>
    window.API_ADMIN_DELETE_KEY = <?= json_encode(defined('API_ADMIN_DELETE_KEY') ? API_ADMIN_DELETE_KEY : '') ?>;
    <?php endif; ?>
</script>
<script src="/assets/js/planificacion/print-tabla.js"></script>
<script src="/assets/js/logistica/salida-mercaderia.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
