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
            <i class="bi bi-speedometer2"></i>
        </div>

        <h2>Panel de administración</h2>
        <p>Dashboard de inspecciones, pendientes y vencimientos, con exportación a Excel<?= $puedeGestionar ? '. También administra el inventario de extintores, los brigadistas y las áreas' : '' ?>.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
