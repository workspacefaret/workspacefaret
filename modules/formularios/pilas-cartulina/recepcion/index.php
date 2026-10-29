<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('pilas_cartulina');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$nombreUsuarioActual = currentUser()['nombre'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/formularios.css">

<section class="form-mobile-shell">

    <div class="form-hero-mobile">
        <div class="form-hero-icon">
            <i class="bi bi-pen"></i>
        </div>

        <div>
            <span class="form-eyebrow">Producción</span>
            <h1 id="tituloRecepcion"><?= $id > 0 ? 'Recepción de pilas' : 'Entregas pendientes' ?></h1>
            <p id="subtituloRecepcion"><?= $id > 0 ? 'Revisa lo entregado, firma y valida para cerrar el ciclo.' : 'Selecciona una entrega para revisarla, firmar y validar la recepción.' ?></p>
        </div>
    </div>

    <div id="formAlert" class="form-alert" style="display:none;"></div>

    <?php if ($id <= 0): ?>

        <div class="form-card-mobile">
            <div id="listaPendientes" class="pc-lista-pendientes">
                <p class="admin-empty-box" style="color:#94a3b8;">Cargando entregas pendientes...</p>
            </div>
        </div>

    <?php else: ?>

        <div class="form-card-mobile">

            <div class="form-section-title">
                <span><i class="bi bi-box-seam"></i></span>
                <div>
                    <h2 id="folioTitulo">Cargando...</h2>
                    <p id="folioSubtitulo">Datos de la entrega.</p>
                </div>
            </div>

            <div id="detalleResumen" class="pc-resumen"></div>

            <form id="recepcionForm" autocomplete="off" style="display:none;">

                <div class="form-section-title">
                    <span><i class="bi bi-check2-circle"></i></span>
                    <div>
                        <h2>Validar recepción</h2>
                        <p>Confirma quién recibe, deja observaciones si corresponde y firma.</p>
                    </div>
                </div>

                <div class="form-grid-mobile">
                    <div class="field autocomplete-field">
                        <label for="recibidoPor">Recibido por</label>
                        <input type="text" id="recibidoPor" name="recibidoPor" placeholder="Nombre de quien recibe" required>
                        <div id="recibidoPorResults" class="autocomplete-results"></div>
                    </div>

                    <div class="field field-full">
                        <label for="observacionesRecepcion">Observaciones de recepción</label>
                        <textarea id="observacionesRecepcion" name="observacionesRecepcion" rows="3" placeholder="Opcional: diferencias, estado de las pilas, etc."></textarea>
                    </div>
                </div>

                <div class="form-section-title">
                    <span><i class="bi bi-pen"></i></span>
                    <div>
                        <h2>Firma</h2>
                        <p>Quien recibe firma para aceptar lo entregado.</p>
                    </div>
                </div>

                <div class="firma-canvas-wrap">
                    <canvas id="firmaCanvas"></canvas>
                </div>

                <div class="firma-actions">
                    <button type="button" id="btnLimpiarFirma" class="btn-clear-firma">
                        <i class="bi bi-eraser"></i>
                        Limpiar firma
                    </button>
                </div>

                <button type="submit" id="btnValidar" class="form-submit-btn">
                    <i class="bi bi-check2-circle"></i>
                    Aceptar entrega y firmar
                </button>
            </form>

            <div id="recepcionCerrada" style="display:none; margin-top:18px;"></div>

        </div>

    <?php endif; ?>

</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
    window.pilasRecepcionId = <?= $id ?>;
</script>
<script src="/assets/js/formularios/pilas-cartulina-comun.js"></script>
<script src="/assets/js/formularios/pilas-cartulina-recepcion.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
