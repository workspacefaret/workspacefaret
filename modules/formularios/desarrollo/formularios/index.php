<?php
ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero">
    <h1>Formularios Desarrollo</h1>
    <p>Selecciona el tipo de solicitud que deseas ingresar.</p>
</section>

<section class="section">
    <div class="grid-3">

        <a class="action-card" href="/modules/formularios/desarrollo/solicitud-grafica/">
            <div class="action-card-icon">
                <i class="bi bi-palette-fill"></i>
            </div>
            <h3>Solicitud Desarrollo Gráfico</h3>
            <p>Ingreso de nuevas solicitudes de desarrollo gráfico.</p>
        </a>

        <a class="action-card" href="/modules/formularios/desarrollo/solicitud-estructural/">
            <div class="action-card-icon">
                <i class="bi bi-bounding-box-circles"></i>
            </div>
            <h3>Solicitud Desarrollo Estructural</h3>
            <p>Ingreso de nuevas solicitudes de desarrollo estructural.</p>
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
