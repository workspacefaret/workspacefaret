<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/formularios.css">

<section class="form-mobile-shell">

    <div class="form-hero-mobile">
        <div class="form-hero-icon">
            <i class="bi bi-bounding-box-circles"></i>
        </div>

        <div>
            <span class="form-eyebrow">Desarrollo Estructural</span>
            <h1>Nueva Solicitud</h1>
            <p>Completa la información necesaria para ingresar una solicitud de desarrollo estructural.</p>
        </div>
    </div>

    <div id="formAlert" class="form-alert" style="display:none;"></div>

    <form id="solicitudEstructuralForm" class="form-card-mobile" autocomplete="off">

        <input type="hidden" id="apiBaseUrl" value="<?= htmlspecialchars(API_FORMULARIOS) ?>">

        <div class="form-section-title">
            <span>1</span>
            <div>
                <h2>Solicitante y cliente</h2>
                <p>Quién solicita y a qué cliente corresponde.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="solicitanteId">Solicitante</label>
                <select id="solicitanteId" name="solicitanteId" required>
                    <option value="">Cargando...</option>
                </select>
            </div>

            <div class="field autocomplete-field">
                <label for="clienteSearch">Cliente</label>
                <input type="text" id="clienteSearch" placeholder="Buscar cliente..." required>
                <input type="hidden" id="clienteId" name="clienteId">
                <div id="clientesResults" class="autocomplete-results"></div>
            </div>

            <div class="field">
                <label for="clienteNuevo">Cliente nuevo</label>
                <input type="text" id="clienteNuevo" name="clienteNuevo" placeholder="Si seleccionó Cliente Nuevo agregar nombre del cliente">
            </div>

            <div class="field">
                <label for="oc">OC</label>
                <input type="text" id="oc" name="oc" placeholder="N° orden de compra">
            </div>
        </div>

        <div class="form-section-title">
            <span>2</span>
            <div>
                <h2>Referencia</h2>
                <p>Selecciona una o más referencias de la solicitud.</p>
            </div>
        </div>

        <div class="chips-box">
            <label class="chip-check">
                <input type="checkbox" name="referencia" value="MUESTRA FISICA">
                <span>Muestra Física</span>
            </label>

            <label class="chip-check">
                <input type="checkbox" name="referencia" value="ARCHIVO ADJUNTO">
                <span>Archivo Adjunto</span>
            </label>

            <label class="chip-check">
                <input type="checkbox" name="referencia" value="OTROS">
                <span>Otros</span>
            </label>
        </div>

        <div class="form-grid-mobile">
            <div class="field field-full" id="referenciaOtrosField">
                <label for="referenciaOtrosTexto">Especifique otros</label>
                <input type="text" id="referenciaOtrosTexto" name="referenciaOtrosTexto" placeholder="Detalle si seleccionó Otros">
            </div>
        </div>

        <div class="form-section-title">
            <span>3</span>
            <div>
                <h2>Producto y solicitud</h2>
                <p>Información técnica de la solicitud.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field field-full">
                <label for="producto">Producto</label>
                <textarea id="producto" name="producto" rows="3" required></textarea>
            </div>

            <div class="field field-full">
                <label for="solicitud">Solicitud</label>
                <textarea id="solicitud" name="solicitud" rows="5" required></textarea>
            </div>

            <div class="field field-full">
                <label for="sustrato">Sustrato (nombre, gramaje, calibre)</label>
                <textarea id="sustrato" name="sustrato" rows="3" required></textarea>
            </div>
        </div>

        <div class="form-section-title">
            <span>4</span>
            <div>
                <h2>Muestras</h2>
                <p>Cantidad y destino de las muestras requeridas.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="cantidadMuestras">Cantidad de muestras requeridas</label>
                <input type="number" id="cantidadMuestras" name="cantidadMuestras" min="1" max="5" value="1" required>
                <small id="cantidadMuestrasError" style="display:none;color:#dc2626;font-size:12px;margin-top:4px;">máximo 5</small>
            </div>
        </div>

        <div class="chips-box">
            <label class="chip-check">
                <input type="checkbox" name="destinoMuestras" value="MUESTRAS PARA COTIZAR">
                <span>Muestras para cotizar</span>
            </label>

            <label class="chip-check">
                <input type="checkbox" name="destinoMuestras" value="MUESTRAS PARA EL CLIENTE">
                <span>Muestras para el cliente</span>
            </label>

            <label class="chip-check">
                <input type="checkbox" name="destinoMuestras" value="MUESTRAS PRUEBA INTERNA">
                <span>Muestras prueba interna</span>
            </label>

            <label class="chip-check">
                <input type="checkbox" name="destinoMuestras" value="N.A.">
                <span>N.A.</span>
            </label>
        </div>

        <div class="form-section-title">
            <span>5</span>
            <div>
                <h2>Adjuntos</h2>
                <p>Fotografías, artes, PDFs o documentos relacionados.</p>
            </div>
        </div>

        <div class="upload-card">

            <input
                type="file"
                id="adjuntos"
                multiple
                accept="image/*,.pdf,.ai,.eps,.zip,.rar">

            <div id="adjuntosPreview" class="adjuntos-preview">
                <span class="muted">
                    No hay archivos seleccionados
                </span>
            </div>

        </div>

        <button type="submit" id="btnEnviarSolicitud" class="form-submit-btn">
            <i class="bi bi-send-fill"></i>
            Enviar solicitud
        </button>

    </form>

</section>

<script src="/assets/js/formularios/solicitud-estructural.js"></script>

<?php
$contenido = ob_get_clean();
include '../../../../layouts/app.php';
?>
