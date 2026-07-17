<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('exportaciones');

ob_start();
?>

<div class="hero">
    <h1>Exportaciones Excel</h1>
    <p>Descarga de información operacional para auditorías y análisis.</p>
</div>

<div class="cards">

    <a class="card" href="/exports/guardias/recorridos-excel.php">
        <div class="card-icon">
            <i class="bi bi-file-earmark-excel-fill"></i>
        </div>

        <h2>Recorridos Guardias</h2>

        <p>
            Descargar reporte Excel con recorridos, estados, plantas y ubicaciones.
        </p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
