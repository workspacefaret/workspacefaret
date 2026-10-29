<?php
// Formulario PÚBLICO (sin login) de inspección de extintores. Solo consulta datos mínimos y registra inspecciones
// contra los endpoints api/prevencion/extintores/publico/* de Formularios.Api. No define ni recibe la clave de administración.
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$extintorId = isset($_GET['e']) ? (int) $_GET['e'] : 0;

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/formularios.css">

<section class="form-mobile-shell">

    <div class="form-hero-mobile">
        <div class="form-hero-icon">
            <i class="bi bi-fire"></i>
        </div>

        <div>
            <span class="form-eyebrow">Prevención de Riesgos</span>
            <h1>Inspección de extintores</h1>
            <p><?= $extintorId > 0
                ? 'Responde el checklist. Si todo cumple, usa «Todo cumple» y corrige solo las excepciones.'
                : 'Selecciona tu nombre y toca el extintor que vas a inspeccionar este mes.' ?></p>
        </div>
    </div>

    <div id="formAlert" class="form-alert" style="display:none;"></div>

    <?php if ($extintorId <= 0): ?>

        <div class="form-card-mobile">
            <div class="form-grid-mobile">
                <div class="field">
                    <label for="selectBrigadista">Brigadista a cargo de la inspección</label>
                    <select id="selectBrigadista"></select>
                </div>
            </div>

            <div id="bloqueFiltros" style="display:none;">
                <div class="form-grid-mobile">
                    <div class="field">
                        <label for="selectPlanta">Planta</label>
                        <select id="selectPlanta"></select>
                    </div>

                    <div class="field">
                        <label for="selectArea">Área</label>
                        <select id="selectArea"></select>
                    </div>

                    <div class="field">
                        <label for="buscarExtintor">Buscar</label>
                        <input type="search" id="buscarExtintor" placeholder="Ubicación, N° o tipo">
                    </div>
                </div>

                <div class="pr-progreso" id="prProgreso" style="display:none;">
                    <div class="pr-progreso-texto"><strong id="prProgresoTexto"></strong><span id="prProgresoPeriodo"></span></div>
                    <div class="pr-progreso-barra"><div id="prProgresoRelleno"></div></div>
                </div>

                <div class="pr-filtros" id="prFiltros">
                    <button type="button" class="pr-chip is-active" data-filtro="PENDIENTES">Pendientes</button>
                    <button type="button" class="pr-chip" data-filtro="TODOS">Todos</button>
                </div>
            </div>

            <div id="listaExtintores" class="pr-lista">
                <p class="pr-muted">Cargando...</p>
            </div>
        </div>

    <?php else: ?>

        <form id="inspeccionForm" class="form-card-mobile" autocomplete="off" novalidate style="display:none;">

            <div class="form-grid-mobile">
                <div class="field">
                    <label for="selectBrigadista">Brigadista a cargo de la inspección</label>
                    <select id="selectBrigadista" required></select>
                </div>
            </div>

            <div id="bloqueChecklist" style="display:none;">
                <div id="resumenExtintor" class="pc-resumen"></div>
                <div id="avisoYaInspeccionado" class="form-alert" style="display:none; margin-top:14px;"></div>

                <div class="form-section-title">
                    <span><i class="bi bi-list-check"></i></span>
                    <div>
                        <h2>Checklist</h2>
                        <p>Si algo no cumple, indica el motivo. El resto no necesita texto.</p>
                    </div>
                </div>

                <button type="button" id="btnTodoCumple" class="pr-todo-cumple">
                    <i class="bi bi-check2-all"></i>
                    Todo cumple
                </button>

                <div id="listaCriterios"></div>

                <button type="submit" id="btnEnviar" class="form-submit-btn">
                    <i class="bi bi-send-check"></i>
                    Registrar inspección
                </button>
            </div>
        </form>

        <p><a href="/modules/prevencion/extintores/inspeccion/" class="pr-muted">Volver a la lista de extintores</a></p>

    <?php endif; ?>

</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.prExtintorId = <?= $extintorId ?>;
</script>
<script src="/assets/js/prevencion/extintores-comun.js"></script>
<script src="/assets/js/prevencion/extintores-inspeccion.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
