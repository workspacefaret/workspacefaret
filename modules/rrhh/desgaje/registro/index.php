<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/formularios.css">

<section class="form-mobile-shell">

    <div class="form-hero-mobile">
        <div class="form-hero-icon">
            <i class="bi bi-scissors"></i>
        </div>

        <div>
            <span class="form-eyebrow">RRHH</span>
            <h1>Registro de Desgaje</h1>
            <p>Completa el registro de producción de desgaje, revisa el checklist y firma para finalizar.</p>
        </div>
    </div>

    <div id="formAlert" class="form-alert" style="display:none;"></div>

    <form id="desgajeForm" class="form-card-mobile" autocomplete="off">

        <input type="hidden" id="apiBaseUrl" value="<?= htmlspecialchars(API_FORMULARIOS) ?>">

        <div class="form-section-title">
            <span>1</span>
            <div>
                <h2>Datos generales</h2>
                <p>Taller, operador y fecha del registro.</p>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label for="tallerId">Taller</label>
                <select id="tallerId" name="tallerId" required>
                    <option value="">Cargando...</option>
                </select>
            </div>

            <div class="field autocomplete-field">
                <label for="operadorSearch">Operador</label>
                <input type="text" id="operadorSearch" placeholder="Buscar o escribir operador..." required>
                <input type="hidden" id="operadorId" name="operadorId">
                <div id="operadoresResults" class="autocomplete-results"></div>
            </div>

            <div class="field">
                <label for="fechaRegistro">Fecha de registro</label>
                <input type="date" id="fechaRegistro" name="fechaRegistro" required>
            </div>
        </div>

        <div class="form-grid-mobile">
            <div class="field field-full">
                <label for="observacionesGenerales">Observaciones generales</label>
                <textarea id="observacionesGenerales" name="observacionesGenerales" rows="3"></textarea>
            </div>
        </div>

        <div class="form-section-title">
            <span>2</span>
            <div>
                <h2>Checklist</h2>
                <p>Marca cada punto revisado antes y después de producir.</p>
            </div>
        </div>

        <div class="checklist-box">

            <div class="checklist-item">
                <label class="chip-check">
                    <input type="checkbox" id="checklistEntradaCumple">
                    <span>Limpieza de área de entrada - Cumple</span>
                </label>
                <input type="text" id="checklistEntradaObservacion" placeholder="Observación (opcional)">
            </div>

            <div class="checklist-item">
                <label class="chip-check">
                    <input type="checkbox" id="checklistInicioCumple">
                    <span>Inicio de producción - Cumple</span>
                </label>
                <input type="text" id="checklistInicioObservacion" placeholder="Observación (opcional)">
            </div>

            <div class="checklist-item">
                <label class="chip-check">
                    <input type="checkbox" id="checklistSalidaCumple">
                    <span>Limpieza de área de salida - Cumple</span>
                </label>
                <input type="text" id="checklistSalidaObservacion" placeholder="Observación (opcional)">
            </div>

        </div>

        <div class="form-section-title">
            <span>3</span>
            <div>
                <h2>Trabajos registrados</h2>
                <p>Agrega una fila por cada trabajo de desgaje realizado.</p>
            </div>
        </div>

        <div id="trabajosContainer"></div>

        <button type="button" id="btnAgregarTrabajo" class="btn-add-trabajo">
            <i class="bi bi-plus-circle"></i>
            Agregar trabajo
        </button>

        <div class="form-section-title">
            <span>4</span>
            <div>
                <h2>Firma</h2>
                <p>El operador debe firmar para finalizar el registro.</p>
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

        <button type="submit" id="btnGuardarRegistro" class="form-submit-btn">
            <i class="bi bi-send-fill"></i>
            Guardar y finalizar registro
        </button>

    </form>

</section>

<script id="trabajoRowTemplate" type="text/x-template">
    <div class="trabajo-card" data-row>
        <div class="trabajo-card-header">
            <strong>Trabajo <span data-row-numero></span></strong>
            <button type="button" class="btn-remove-trabajo" data-remove-row>
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <div class="form-grid-mobile">
            <div class="field">
                <label>NP</label>
                <input type="text" data-field="np" required>
            </div>

            <div class="field autocomplete-field">
                <label>Cliente</label>
                <input type="text" data-field="clienteSearch" placeholder="Buscar o escribir cliente..." required>
                <input type="hidden" data-field="clienteId">
                <div class="autocomplete-results" data-clientes-results></div>
            </div>

            <div class="field">
                <label>N&deg; Pliego</label>
                <input type="text" data-field="numeroPliego" required>
            </div>

            <div class="field">
                <label>Tipo de desgaje</label>
                <select data-field="tipoDesgajeId" required>
                    <option value="">Cargando...</option>
                </select>
            </div>

            <div class="field">
                <label>Cantidad de pliegos</label>
                <input type="number" data-field="cantidadPliegos" min="1" required>
            </div>

            <div class="field">
                <label>N&deg; de moldes</label>
                <input type="number" data-field="numeroMoldes" min="1" required>
            </div>

            <div class="field field-full">
                <label>Observaciones</label>
                <input type="text" data-field="observaciones" placeholder="Observaciones (opcional)">
            </div>
        </div>
    </div>
</script>

<script src="/assets/js/rrhh/desgaje-registro.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
