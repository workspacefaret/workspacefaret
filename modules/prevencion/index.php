<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('prevencion_riesgos');

$puedeGestionar = moduleAccessLevel('prevencion_riesgos') === 'gestionar';

ob_start();
?>

<div class="hero">
    <h1>Prevención de Riesgos</h1>
    <p>Inspección mensual de extintores en las 4 plantas: checklist en terreno, seguimiento de pendientes y vencimientos.</p>
</div>

<div class="module-grid">

    <a href="/modules/prevencion/extintores/inspeccion/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-fire"></i>
        </div>

        <h2>Inspección de extintores</h2>
        <p>Formulario público (sin login): elige tu nombre y el extintor, responde el checklist y registra la inspección del mes.</p>
    </a>

    <a href="/modules/prevencion/extintores/admin/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-table"></i>
        </div>

        <h2>Panel de inspecciones</h2>
        <p>Pendientes, hallazgos y vencimientos por planta y mes, con exportación a Excel.</p>
    </a>

    <?php if ($puedeGestionar): ?>
        <a href="/modules/prevencion/extintores/inventario/" class="module-card">
            <div class="module-icon">
                <i class="bi bi-boxes"></i>
            </div>

            <h2>Inventario de extintores</h2>
            <p>Agrega extintores, corrige ubicación, tipo y vencimiento, o dalos de baja.</p>
        </a>

        <a href="/modules/prevencion/extintores/configuracion/" class="module-card">
            <div class="module-icon">
                <i class="bi bi-people"></i>
            </div>

            <h2>Brigadistas y áreas</h2>
            <p>Define qué áreas ve cada brigadista en el formulario de inspección.</p>
        </a>
    <?php endif; ?>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
