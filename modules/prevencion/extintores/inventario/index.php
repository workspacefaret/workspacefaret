<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('prevencion_riesgos');

if (moduleAccessLevel('prevencion_riesgos') !== 'gestionar') {
    mostrarAccesoDenegado('Administrar el inventario de extintores requiere nivel «Gestionar» en Prevención de Riesgos.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Inventario de extintores</h1>
        <p>Agrega extintores, corrige ubicación, tipo y vencimiento, o dalos de baja. Las inspecciones pasadas se conservan.</p>
    </div>
    <div class="admin-hero-actions">
        <button type="button" class="admin-btn admin-btn-primary" id="btnNuevo">
            <i class="bi bi-plus-circle"></i>
            Nuevo extintor
        </button>
        <a href="/modules/prevencion/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Extintores</h2>
                <p>Haz clic en «Editar» para modificar un extintor.</p>
            </div>
            <span class="badge badge-primary" id="badgeCantidad">0 extintores</span>
        </div>

        <div id="alertaListado" class="admin-alert hidden"></div>

        <div class="admin-filters">
            <div class="admin-filter-field">
                <label for="filtroPlanta">Planta</label>
                <select id="filtroPlanta"></select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroArea">Área</label>
                <select id="filtroArea"></select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado">
                    <option value="ACTIVOS">Activos</option>
                    <option value="BAJA">De baja</option>
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroBuscar">Buscar</label>
                <input type="text" id="filtroBuscar" placeholder="Ubicación, N° o tipo">
            </div>
        </div>

        <div class="admin-table-wrap admin-table-wrap-sticky">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Planta</th>
                        <th>N°</th>
                        <th>Ubicación</th>
                        <th>Tipo</th>
                        <th>Área</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr><td colspan="8" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ================= MODAL CREAR / EDITAR ================= -->
<div class="admin-modal-overlay hidden" id="modalExtintor">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2 id="modalTitulo">Nuevo extintor</h2>
                <p>El N° se asigna automáticamente dentro de la planta.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModal" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaModal" class="admin-alert hidden"></div>

        <form id="formExtintor" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="extintorId">

            <div class="admin-form-field">
                <label for="formPlanta">Planta</label>
                <select id="formPlanta" required></select>
            </div>
            <div class="admin-form-field">
                <label for="formTipo">Tipo</label>
                <select id="formTipo" required></select>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="formUbicacion">Ubicación</label>
                <input type="text" id="formUbicacion" maxlength="200" list="listaUbicaciones" required>
                <datalist id="listaUbicaciones"></datalist>
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label for="formArea">Área</label>
                <select id="formArea"></select>
            </div>
            <div class="admin-form-field">
                <label for="formCapacidad">Capacidad (kg)</label>
                <input type="number" id="formCapacidad" min="0.5" max="999" step="0.5" placeholder="Ej: 10">
            </div>
            <div class="admin-form-field">
                <label for="formVencimiento">Vencimiento</label>
                <input type="date" id="formVencimiento">
            </div>
            <div class="admin-form-field admin-form-field-full">
                <label class="pr-check">
                    <input type="checkbox" id="formActivo" checked>
                    Extintor activo (desmarca para darlo de baja)
                </label>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardar">
                    <i class="bi bi-save"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    // Las llamadas administrativas pasan por api.php (valida sesión y permiso en el servidor); no hay claves en el navegador.
    window.PR_ADMIN = true;
</script>
<script src="/assets/js/prevencion/extintores-comun.js"></script>
<script src="/assets/js/prevencion/extintores-inventario.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
