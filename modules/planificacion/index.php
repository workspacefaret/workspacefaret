<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('planificacion');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$nombreUsuarioActual = currentUser()['nombre'];

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Correlativos Perfiles y Moldes</h1>
        <p>Reemplaza el Excel de correlativos de Perfiles y Moldes: asigna correlativos, registra y consulta el histórico.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/planificacion/registro-molde/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-clipboard-check"></i>
            Registro de Molde
        </a>
        <a href="/modules/planificacion/control-moldes/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-diagram-3"></i>
            Control de Moldes
        </a>
    </div>
</section>

<section class="section">
    <div class="admin-tabs" role="tablist">
        <button type="button" class="admin-tab is-active" data-tab="perfiles">Perfiles</button>
        <button type="button" class="admin-tab" data-tab="moldes">Moldes</button>
        <button type="button" class="admin-tab" data-tab="moldes-nr">Moldes no repetitivos</button>
    </div>
</section>

<!-- ================= PERFILES ================= -->
<section class="section admin-tab-panel is-active" id="panelPerfiles">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Nuevo perfil</h2>
                <p>El correlativo se genera automáticamente al guardar.</p>
            </div>
        </div>

        <div id="alertaPerfil" class="admin-alert hidden"></div>

        <form id="formPerfil" class="admin-form-grid" autocomplete="off">
            <div class="admin-form-field">
                <label for="perfilCliente">Cliente</label>
                <input type="text" id="perfilCliente">
            </div>
            <div class="admin-form-field">
                <label for="perfilMedidas">Medidas</label>
                <input type="text" id="perfilMedidas">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="perfilDescripcion">Descripción</label>
                <input type="text" id="perfilDescripcion">
            </div>
            <div class="admin-form-field">
                <label for="perfilNumeroDesarrollo">N° Desarrollo</label>
                <input type="text" id="perfilNumeroDesarrollo">
            </div>
            <div class="admin-form-field">
                <label for="perfilFecha">Fecha</label>
                <input type="date" id="perfilFecha">
            </div>
            <div class="admin-form-field">
                <label for="perfilRubro">Rubro</label>
                <input type="text" id="perfilRubro" list="listaPerfilRubros">
                <datalist id="listaPerfilRubros"></datalist>
            </div>
            <div class="admin-form-field">
                <label for="perfilOperador">Operador</label>
                <input type="text" id="perfilOperador" list="listaPerfilOperadores">
                <datalist id="listaPerfilOperadores"></datalist>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarPerfil">
                    <i class="bi bi-save"></i>
                    Guardar perfil
                </button>
            </div>
        </form>
    </div>
</section>

<section class="section admin-tab-panel is-active" id="panelPerfilesTabla">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Perfiles</h2>
                <p>Filtra, busca y edita los perfiles registrados.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidadPerfiles">0 registros</span>
        </div>

        <div class="admin-filters admin-filters-cpm">
            <div class="admin-filter-field">
                <label for="filtroPerfilBuscar">Buscar</label>
                <input type="text" id="filtroPerfilBuscar" placeholder="N° perfil, cliente, descripción...">
            </div>
            <div class="admin-filter-field">
                <label for="filtroPerfilCliente">Cliente</label>
                <input type="text" id="filtroPerfilCliente">
            </div>
            <div class="admin-filter-field">
                <label for="filtroPerfilRubro">Rubro</label>
                <input type="text" id="filtroPerfilRubro" list="listaPerfilRubros">
            </div>
            <div class="admin-filter-field">
                <label for="filtroPerfilOperador">Operador</label>
                <input type="text" id="filtroPerfilOperador" list="listaPerfilOperadores">
            </div>
            <div class="admin-filter-field">
                <label for="filtroPerfilFechaDesde">Fecha desde</label>
                <input type="date" id="filtroPerfilFechaDesde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroPerfilFechaHasta">Fecha hasta</label>
                <input type="date" id="filtroPerfilFechaHasta">
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltrosPerfil">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-cpm-perfiles">
                <thead>
                    <tr>
                        <th>N° Perfil</th>
                        <th>Cliente</th>
                        <th>Medidas</th>
                        <th>Descripción</th>
                        <th>N° Desarrollo</th>
                        <th>Fecha</th>
                        <th>Rubro</th>
                        <th>Operador</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaPerfilesBody">
                    <tr><td colspan="9" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="paginacionPerfiles"></div>
    </div>
</section>

<!-- ================= MOLDES (REPETITIVO) ================= -->
<section class="section admin-tab-panel" id="panelMoldes">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Nuevo molde</h2>
                <p>El correlativo se genera automáticamente al guardar.</p>
            </div>
        </div>

        <div id="alertaMolde" class="admin-alert hidden"></div>

        <form id="formMolde" class="admin-form-grid" autocomplete="off">
            <div class="admin-form-field">
                <label for="moldeCliente">Cliente</label>
                <input type="text" id="moldeCliente">
            </div>
            <div class="admin-form-field">
                <label for="moldeRubro">Rubro</label>
                <input type="text" id="moldeRubro" list="listaMoldeRubros">
                <datalist id="listaMoldeRubros"></datalist>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="moldeProducto">Producto</label>
                <input type="text" id="moldeProducto">
            </div>
            <div class="admin-form-field">
                <label for="moldeNp">NP (1ra entrada)</label>
                <input type="text" id="moldeNp">
            </div>
            <div class="admin-form-field">
                <label for="moldePerfil">Perfil</label>
                <input type="text" id="moldePerfil">
            </div>
            <div class="admin-form-field">
                <label for="moldeIngreso">Ingreso</label>
                <input type="date" id="moldeIngreso">
            </div>
            <div class="admin-form-field">
                <label for="moldeOperador">Operador</label>
                <input type="text" id="moldeOperador" list="listaMoldeOperadores">
                <datalist id="listaMoldeOperadores"></datalist>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="moldeComentarios">Comentarios/Observaciones</label>
                <textarea id="moldeComentarios" rows="2"></textarea>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarMolde">
                    <i class="bi bi-save"></i>
                    Guardar molde
                </button>
            </div>
        </form>
    </div>
</section>

<section class="section admin-tab-panel" id="panelMoldesTabla">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Moldes (repetitivos)</h2>
                <p>Filtra, busca y edita los moldes registrados.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidadMolde">0 registros</span>
        </div>

        <div class="admin-filters admin-filters-cpm">
            <div class="admin-filter-field">
                <label for="filtroMoldeBuscar">Buscar</label>
                <input type="text" id="filtroMoldeBuscar" placeholder="Código, cliente, producto...">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeCliente">Cliente</label>
                <input type="text" id="filtroMoldeCliente">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeRubro">Rubro</label>
                <input type="text" id="filtroMoldeRubro" list="listaMoldeRubros">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeOperador">Operador</label>
                <input type="text" id="filtroMoldeOperador" list="listaMoldeOperadores">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeFechaDesde">Ingreso desde</label>
                <input type="date" id="filtroMoldeFechaDesde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeFechaHasta">Ingreso hasta</label>
                <input type="date" id="filtroMoldeFechaHasta">
            </div>
            <div class="admin-filter-field admin-filter-checkbox">
                <label for="filtroMoldeObsoletos">
                    <input type="checkbox" id="filtroMoldeObsoletos">
                    Incluir obsoletos
                </label>
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltrosMolde">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-cpm-moldes">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Rubro</th>
                        <th>Producto</th>
                        <th>NP</th>
                        <th>Perfil</th>
                        <th>Ingreso</th>
                        <th>Operador</th>
                        <th>Comentarios</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaMoldeBody">
                    <tr><td colspan="10" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="paginacionMolde"></div>
    </div>
</section>

<!-- ================= MOLDES NO REPETITIVOS ================= -->
<section class="section admin-tab-panel" id="panelMoldesNr">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Nuevo molde no repetitivo</h2>
                <p>El correlativo se genera automáticamente al guardar.</p>
            </div>
        </div>

        <div id="alertaMoldeNr" class="admin-alert hidden"></div>

        <form id="formMoldeNr" class="admin-form-grid" autocomplete="off">
            <div class="admin-form-field">
                <label for="moldeNrCliente">Cliente</label>
                <input type="text" id="moldeNrCliente">
            </div>
            <div class="admin-form-field">
                <label for="moldeNrRubro">Rubro</label>
                <input type="text" id="moldeNrRubro" list="listaMoldeRubros">
            </div>
            <div class="admin-form-field">
                <label for="moldeNrNp">NP (1ra entrada)</label>
                <input type="text" id="moldeNrNp">
            </div>
            <div class="admin-form-field">
                <label for="moldeNrIngreso">Ingreso</label>
                <input type="date" id="moldeNrIngreso">
            </div>
            <div class="admin-form-field">
                <label for="moldeNrOperador">Operador</label>
                <input type="text" id="moldeNrOperador" list="listaMoldeOperadores">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="moldeNrComentarios">Comentarios/Observaciones</label>
                <textarea id="moldeNrComentarios" rows="2"></textarea>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarMoldeNr">
                    <i class="bi bi-save"></i>
                    Guardar molde no repetitivo
                </button>
            </div>
        </form>
    </div>
</section>

<section class="section admin-tab-panel" id="panelMoldesNrTabla">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Moldes no repetitivos</h2>
                <p>Filtra, busca y edita los moldes no repetitivos registrados.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidadMoldeNr">0 registros</span>
        </div>

        <div class="admin-filters admin-filters-cpm">
            <div class="admin-filter-field">
                <label for="filtroMoldeNrBuscar">Buscar</label>
                <input type="text" id="filtroMoldeNrBuscar" placeholder="Código, cliente, producto...">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeNrCliente">Cliente</label>
                <input type="text" id="filtroMoldeNrCliente">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeNrRubro">Rubro</label>
                <input type="text" id="filtroMoldeNrRubro" list="listaMoldeRubros">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeNrOperador">Operador</label>
                <input type="text" id="filtroMoldeNrOperador" list="listaMoldeOperadores">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeNrFechaDesde">Ingreso desde</label>
                <input type="date" id="filtroMoldeNrFechaDesde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroMoldeNrFechaHasta">Ingreso hasta</label>
                <input type="date" id="filtroMoldeNrFechaHasta">
            </div>
            <div class="admin-filter-field admin-filter-checkbox">
                <label for="filtroMoldeNrObsoletos">
                    <input type="checkbox" id="filtroMoldeNrObsoletos">
                    Incluir obsoletos
                </label>
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltrosMoldeNr">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table-cpm-moldes-nr">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Rubro</th>
                        <th>NP</th>
                        <th>Ingreso</th>
                        <th>Operador</th>
                        <th>Comentarios</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaMoldeNrBody">
                    <tr><td colspan="8" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="paginacionMoldeNr"></div>
    </div>
</section>

<!-- ================= MODAL EDITAR PERFIL ================= -->
<div class="admin-modal-overlay hidden" id="modalEditarPerfil">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Editar perfil <span id="modalEditarPerfilCodigo"></span></h2>
                <p>El número de perfil no se puede modificar.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalEditarPerfil" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaEditarPerfil" class="admin-alert hidden"></div>

        <form id="formEditarPerfil" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="editarPerfilId">
            <div class="admin-form-field">
                <label for="editarPerfilCliente">Cliente</label>
                <input type="text" id="editarPerfilCliente">
            </div>
            <div class="admin-form-field">
                <label for="editarPerfilMedidas">Medidas</label>
                <input type="text" id="editarPerfilMedidas">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarPerfilDescripcion">Descripción</label>
                <input type="text" id="editarPerfilDescripcion">
            </div>
            <div class="admin-form-field">
                <label for="editarPerfilNumeroDesarrollo">N° Desarrollo</label>
                <input type="text" id="editarPerfilNumeroDesarrollo">
            </div>
            <div class="admin-form-field">
                <label for="editarPerfilFecha">Fecha</label>
                <input type="date" id="editarPerfilFecha">
            </div>
            <div class="admin-form-field">
                <label for="editarPerfilRubro">Rubro</label>
                <input type="text" id="editarPerfilRubro" list="listaPerfilRubros">
            </div>
            <div class="admin-form-field">
                <label for="editarPerfilOperador">Operador</label>
                <input type="text" id="editarPerfilOperador" list="listaPerfilOperadores">
            </div>
            <!-- Campos retirados de la UI (N° Caja, Unid. por Caja, Status, Estado, Perfil nuevo): se preservan
                 ocultos para no perder el histórico migrado del Excel al guardar una edición. -->
            <input type="hidden" id="editarPerfilNumeroCaja">
            <input type="hidden" id="editarPerfilUnidadesPorCaja">
            <input type="hidden" id="editarPerfilStatus">
            <input type="hidden" id="editarPerfilEstado">
            <input type="hidden" id="editarPerfilPerfilNuevo">

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarEdicionPerfil">
                    <i class="bi bi-save"></i>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL EDITAR MOLDE (repetitivo y no repetitivo) ================= -->
<div class="admin-modal-overlay hidden" id="modalEditarMolde">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Editar molde <span id="modalEditarMoldeCodigo"></span></h2>
                <p>El código no se puede modificar.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalEditarMolde" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaEditarMolde" class="admin-alert hidden"></div>

        <form id="formEditarMolde" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="editarMoldeId">
            <input type="hidden" id="editarMoldeTipo">
            <div class="admin-form-field">
                <label for="editarMoldeCliente">Cliente</label>
                <input type="text" id="editarMoldeCliente">
            </div>
            <div class="admin-form-field">
                <label for="editarMoldeRubro">Rubro</label>
                <input type="text" id="editarMoldeRubro" list="listaMoldeRubros">
            </div>
            <div class="admin-form-field admin-form-field-full" id="editarMoldeProductoWrap">
                <label for="editarMoldeProducto">Producto</label>
                <input type="text" id="editarMoldeProducto">
            </div>
            <div class="admin-form-field">
                <label for="editarMoldeNp">NP (1ra entrada)</label>
                <input type="text" id="editarMoldeNp">
            </div>
            <div class="admin-form-field" id="editarMoldePerfilWrap">
                <label for="editarMoldePerfil">Perfil</label>
                <input type="text" id="editarMoldePerfil">
            </div>
            <div class="admin-form-field">
                <label for="editarMoldeIngreso">Ingreso</label>
                <input type="date" id="editarMoldeIngreso">
            </div>
            <!-- Listo/Estado y Entrega: retirados de la UI, se preservan ocultos para no perder el histórico. -->
            <input type="hidden" id="editarMoldeListoEstado">
            <input type="hidden" id="editarMoldeEntrega">
            <div class="admin-form-field">
                <label for="editarMoldeOperador">Operador</label>
                <input type="text" id="editarMoldeOperador" list="listaMoldeOperadores">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="editarMoldeComentarios">Comentarios/Observaciones</label>
                <textarea id="editarMoldeComentarios" rows="2"></textarea>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarEdicionMolde">
                    <i class="bi bi-save"></i>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL HISTORIAL (compartido) ================= -->
<div class="admin-modal-overlay hidden" id="modalHistorial">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Historial <span id="modalHistorialCodigo"></span></h2>
                <p>Registro de creación y ediciones, con el usuario responsable de cada cambio.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalHistorial" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="admin-list-box" id="historialLista"></div>
    </div>
</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
</script>
<script src="/assets/js/planificacion/cpm-perfiles.js"></script>
<script src="/assets/js/planificacion/cpm-moldes.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
