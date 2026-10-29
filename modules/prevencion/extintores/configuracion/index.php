<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('prevencion_riesgos');

if (moduleAccessLevel('prevencion_riesgos') !== 'gestionar') {
    mostrarAccesoDenegado('Administrar brigadistas y áreas requiere nivel «Gestionar» en Prevención de Riesgos.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Brigadistas y áreas</h1>
        <p>Define qué áreas ve cada brigadista en el formulario de inspección. Un brigadista sin áreas asignadas ve todos los extintores.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/prevencion/extintores/inventario/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-boxes"></i>
            Asignar extintores a áreas
        </a>
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
                <h2>Brigadistas</h2>
                <p>Haz clic en «Editar» para cambiar sus áreas o desactivarlo.</p>
            </div>
            <button type="button" class="admin-btn admin-btn-primary" id="btnNuevoBrigadista">
                <i class="bi bi-person-plus"></i>
                Nuevo brigadista
            </button>
        </div>

        <div id="alertaBrigadistas" class="admin-alert hidden"></div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Brigadista</th>
                        <th>Áreas asignadas</th>
                        <th>Estado</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaBrigadistas">
                    <tr><td colspan="4" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Áreas de inspección</h2>
                <p>Los extintores se asignan a un área desde el inventario.</p>
            </div>
            <button type="button" class="admin-btn admin-btn-primary" id="btnNuevaArea">
                <i class="bi bi-plus-circle"></i>
                Nueva área
            </button>
        </div>

        <div id="alertaAreas" class="admin-alert hidden"></div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Área</th>
                        <th>Extintores</th>
                        <th>Estado</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaAreas">
                    <tr><td colspan="4" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ================= MODAL BRIGADISTA ================= -->
<div class="admin-modal-overlay hidden" id="modalBrigadista">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2 id="tituloBrigadista">Nuevo brigadista</h2>
                <p>Sin ningún área marcada, el brigadista ve todos los extintores.</p>
            </div>
            <button class="admin-icon-btn" id="cerrarBrigadista" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaModalBrigadista" class="admin-alert hidden"></div>

        <form id="formBrigadista" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="brigadistaId">

            <div class="admin-form-field admin-form-field-full">
                <label for="brigadistaNombre">Nombre</label>
                <input type="text" id="brigadistaNombre" maxlength="120" required>
            </div>

            <div class="admin-form-field admin-form-field-full">
                <label>Áreas que puede inspeccionar</label>
                <div id="brigadistaAreas"></div>
            </div>

            <div class="admin-form-field admin-form-field-full">
                <label class="pr-check">
                    <input type="checkbox" id="brigadistaActivo" checked>
                    Activo (desmarca para quitarlo de la lista del formulario)
                </label>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="guardarBrigadista">
                    <i class="bi bi-save"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL AREA ================= -->
<div class="admin-modal-overlay hidden" id="modalArea">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2 id="tituloArea">Nueva área</h2>
            </div>
            <button class="admin-icon-btn" id="cerrarArea" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaModalArea" class="admin-alert hidden"></div>

        <form id="formArea" class="admin-form-grid" autocomplete="off">
            <input type="hidden" id="areaId">

            <div class="admin-form-field admin-form-field-full">
                <label for="areaNombre">Nombre</label>
                <input type="text" id="areaNombre" maxlength="120" required>
            </div>

            <div class="admin-form-field admin-form-field-full">
                <label class="pr-check">
                    <input type="checkbox" id="areaActiva" checked>
                    Área activa
                </label>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="guardarArea">
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
<script src="/assets/js/prevencion/extintores-configuracion.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
