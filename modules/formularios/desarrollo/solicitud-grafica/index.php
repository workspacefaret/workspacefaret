<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/formularios.css">

<section class="form-mobile-shell">

    <div class="form-hero-mobile">
        <div class="form-hero-icon">
            <i class="bi bi-palette-fill"></i>
        </div>

        <div>
            <span class="form-eyebrow">Desarrollo Gráfico</span>
            <h1>Nueva Solicitud</h1>
            <p>Completa la información necesaria para ingresar una solicitud de desarrollo gráfico.</p>
        </div>
    </div>

    <div id="formAlert" class="form-alert" style="display:none;"></div>

    <form id="solicitudGraficaForm" class="form-card-mobile" autocomplete="off">

        <input type="hidden" id="apiBaseUrl" value="<?= htmlspecialchars(API_FORMULARIOS) ?>">

        <div class="form-section-title">
            <span>1</span>
            <div>
                <h2>Datos principales</h2>
                <p>Prioridad, solicitante y cliente asociado.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="prioridadId">Prioridad</label>
                <select id="prioridadId" name="prioridadId" required>
                    <option value="">Cargando...</option>
                </select>
            </div>

            <div class="field">
                <label for="solicitanteId">Solicitante</label>
                <select id="solicitanteId" name="solicitanteId" required>
                    <option value="">Cargando...</option>
                </select>
            </div>

            <div class="field">
                <label for="emailSolicitante">Email solicitante</label>
                <input type="email" id="emailSolicitante" name="emailSolicitante" placeholder="correo@faret.cl">
            </div>

            <div class="field autocomplete-field">
                <label for="clienteSearch">Cliente</label>
                <input type="text" id="clienteSearch" placeholder="Buscar cliente..." required>
                <input type="hidden" id="clienteId" name="clienteId">
                <div id="clientesResults" class="autocomplete-results"></div>
            </div>

            <div class="field">
                <label for="clienteNuevo">Cliente nuevo opcional</label>
                <input type="text" id="clienteNuevo" name="clienteNuevo" placeholder="Solo si no existe en catálogo">
            </div>

            <div class="field">
                <label for="oc">OC</label>
                <input type="text" id="oc" name="oc" placeholder="N° orden de compra">
            </div>
        </div>

        <div class="form-section-title">
            <span>2</span>
            <div>
                <h2>Producto y proceso</h2>
                <p>Información técnica base de la solicitud.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="producto">Producto</label>
                <input type="text" id="producto" name="producto" required>
            </div>

            <div class="field">
                <label for="sustrato">Sustrato</label>
                <input type="text" id="sustrato" name="sustrato">
            </div>

            <div class="field">
                <label for="tipoProceso">Tipo proceso</label>
                <select id="tipoProceso" name="tipoProceso" required>
                    <option value="">Seleccione...</option>
                </select>
            </div>

            <div class="field">
                <label for="perfil">Perfil</label>
                <input type="text" id="perfil" name="perfil" placeholder="Ej: FOGRA39">
            </div>
        </div>

        <div class="form-section-title">
            <span>3</span>
            <div>
                <h2>Terminaciones</h2>
                <p>Selecciona una o más terminaciones aplicables.</p>
            </div>
        </div>

        <div id="terminacionesBox" class="chips-box">
            <span class="muted">Cargando terminaciones...</span>
        </div>

        <div class="form-section-title">
            <span>4</span>
            <div>
                <h2>Detalle de solicitud</h2>
                <p>Describe claramente lo requerido.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field field-full">
                <label for="solicitud">Solicitud</label>
                <textarea id="solicitud" name="solicitud" rows="5" required></textarea>
            </div>

            <div class="field">
                <label for="cantidadMuestras">Cantidad muestras</label>
                <input type="number" id="cantidadMuestras" name="cantidadMuestras" min="1" max="1" value="1" required>
                <small id="cantidadMuestrasError" style="display:none;color:#dc2626;font-size:12px;margin-top:4px;">máximo 1</small>
            </div>

            <div class="field">
                <label for="cantidadItems">Cantidad items</label>
                <input type="number" id="cantidadItems" name="cantidadItems" min="1" max="1" value="1" required>
                <small id="cantidadItemsError" style="display:none;color:#dc2626;font-size:12px;margin-top:4px;">máximo 1</small>
            </div>

            <div class="field field-full">
                <label for="observacion">Observación</label>
                <textarea id="observacion" name="observacion" rows="4"></textarea>
            </div>
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

<script src="/assets/js/formularios/desarrollo-grafico.js"></script>

<?php
$contenido = ob_get_clean();
include '../../../../layouts/app.php';
?>
