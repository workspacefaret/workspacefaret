<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('pilas_cartulina');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$nombreUsuarioActual = currentUser()['nombre'];

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/formularios.css">

<section class="form-mobile-shell">

    <div class="form-hero-mobile">
        <div class="form-hero-icon">
            <i class="bi bi-layers-fill"></i>
        </div>

        <div>
            <span class="form-eyebrow">Producción</span>
            <h1>Entrega de pilas</h1>
            <p>Ingresa la NP, revisa los datos traídos desde producción y registra la entrega.</p>
        </div>
    </div>

    <div id="formAlert" class="form-alert" style="display:none;"></div>

    <form id="entregaForm" class="form-card-mobile" autocomplete="off">

        <div class="form-section-title">
            <span>1</span>
            <div>
                <h2>Nota de pedido</h2>
                <p>Busca la NP para autocompletar cliente, cartulina, formato y pliegos.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="np">NP</label>
                <div class="pc-np-row">
                    <input type="text" id="np" name="np" inputmode="numeric" placeholder="Ej: 23067" required>
                    <button type="button" id="btnBuscarNp" class="pc-buscar-btn">
                        <i class="bi bi-search"></i>
                        Buscar
                    </button>
                </div>
            </div>
        </div>

        <div id="npResumen" class="pc-resumen" style="display:none; margin-top:14px;"></div>

        <div id="procesosWrap" style="display:none; margin-top:14px;">
            <div class="field">
                <label>Pila / proceso de dimensionado</label>
            </div>
            <div id="procesosLista"></div>
        </div>

        <div class="form-grid-mobile" style="margin-top:14px;">
            <div class="field">
                <label for="cliente">Cliente</label>
                <input type="text" id="cliente" name="cliente" placeholder="Se completa desde FPS">
            </div>

            <div class="field">
                <label for="cartulina">Cartulina</label>
                <input type="text" id="cartulina" name="cartulina" placeholder="Se completa desde FPS">
            </div>

            <div class="field">
                <label for="formato">Formato</label>
                <input type="text" id="formato" name="formato" placeholder="Ej: 88x132">
            </div>
        </div>

        <input type="hidden" id="empresa">
        <input type="hidden" id="ordenTrabajo">
        <input type="hidden" id="procesoFps">
        <input type="hidden" id="pliegosFps">
        <input type="hidden" id="productos">

        <div class="form-section-title">
            <span>2</span>
            <div>
                <h2>Entrega</h2>
                <p>Cantidades, palets y responsables del traspaso.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="cantidadSolicitada">Cantidad solicitada</label>
                <input type="number" id="cantidadSolicitada" name="cantidadSolicitada" inputmode="decimal" min="0.01" step="0.01" required>
            </div>

            <div class="field">
                <label for="cantidadEntregada">Cantidad entregada</label>
                <input type="number" id="cantidadEntregada" name="cantidadEntregada" inputmode="decimal" min="0" step="0.01" required>
            </div>

            <div class="field">
                <label for="numeroPalets">N° de palets</label>
                <input type="number" id="numeroPalets" name="numeroPalets" inputmode="numeric" min="0" step="1" required>
            </div>

            <div class="field autocomplete-field">
                <label for="entregadoPor">Entregado por</label>
                <input type="text" id="entregadoPor" name="entregadoPor" placeholder="Nombre de quien entrega" required>
                <div id="entregadoPorResults" class="autocomplete-results"></div>
            </div>

            <div class="field autocomplete-field">
                <label for="recibidoPor">Entregar a (quien recibe)</label>
                <input type="text" id="recibidoPor" name="recibidoPor" placeholder="Opcional, nombre de quien recibirá">
                <div id="recibidoPorResults" class="autocomplete-results"></div>
            </div>

            <div class="field field-full">
                <label for="observacionesEntrega">Observaciones</label>
                <textarea id="observacionesEntrega" name="observacionesEntrega" rows="3"></textarea>
            </div>
        </div>

        <button type="submit" id="btnRegistrarEntrega" class="form-submit-btn">
            <i class="bi bi-send-fill"></i>
            Registrar entrega
        </button>

    </form>

</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
</script>
<script src="/assets/js/formularios/pilas-cartulina-comun.js"></script>
<script src="/assets/js/formularios/pilas-cartulina-entrega.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
