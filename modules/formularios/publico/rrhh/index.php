<?php
ob_start();
?>

<div class="hero">
    <h1>Formularios de RRHH</h1>
    <p>Selecciona el formulario que deseas completar.</p>
</div>

<div class="module-grid">

    <a href="/modules/rrhh/desgaje/registro/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-scissors"></i>
        </div>

        <h2>Registro de Desgaje</h2>
        <p>Registro de producción de desgaje con checklist y firma del operador.</p>
    </a>

    <a href="/modules/formularios/publico/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-arrow-left-circle-fill"></i>
        </div>

        <h2>Volver</h2>
        <p>Regresar a Formularios.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
