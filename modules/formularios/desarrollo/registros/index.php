<?php
ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero">
    <h1>Registros Desarrollo</h1>
    <p>Selecciona el panel administrativo que deseas revisar.</p>
</section>

<section class="section">
    <div class="grid-3">

        <a class="action-card" href="/modules/formularios/desarrollo/admin/">
            <div class="action-card-icon">
                <i class="bi bi-table"></i>
            </div>
            <h3>Registros Solicitudes Gráficas</h3>
            <p>Panel administrativo para revisar, filtrar y gestionar solicitudes gráficas.</p>
        </a>

        <a class="action-card" href="/modules/formularios/desarrollo/solicitud-estructural/admin/">
            <div class="action-card-icon">
                <i class="bi bi-table"></i>
            </div>
            <h3>Registros Solicitudes Estructurales</h3>
            <p>Panel administrativo para revisar, filtrar y gestionar solicitudes estructurales.</p>
        </a>

        <a class="action-card" href="/modules/formularios/desarrollo/">
            <div class="action-card-icon">
                <i class="bi bi-arrow-left-circle-fill"></i>
            </div>
            <h3>Volver</h3>
            <p>Regresar al módulo de Desarrollo.</p>
        </a>

    </div>
</section>

<?php
$contenido = ob_get_clean();
include '../../../../layouts/app.php';
?>
