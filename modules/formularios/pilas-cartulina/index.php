<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('pilas_cartulina');

ob_start();
?>

<div class="hero">
    <h1>Pilas Cartulina Dimensionado</h1>
    <p>Entrega y recepción firmada de pilas de cartulina por NP. Los datos de la NP se traen desde producción (FPS).</p>
</div>

<div class="module-grid">

    <a href="/modules/formularios/pilas-cartulina/entrega/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-box-arrow-right"></i>
        </div>

        <h2>Nueva entrega</h2>
        <p>Registra la salida de pilas: NP, cantidades, palets y quién entrega.</p>
    </a>

    <a href="/modules/formularios/pilas-cartulina/recepcion/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-pen"></i>
        </div>

        <h2>Recepción</h2>
        <p>Revisa las entregas pendientes, valida lo recibido y firma para cerrar el ciclo.</p>
    </a>

    <a href="/modules/formularios/pilas-cartulina/admin/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-table"></i>
        </div>

        <h2>Registros</h2>
        <p>Tabla completa de traspasos: filtros, historial, PDF, exportación a Excel y administración.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
