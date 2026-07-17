<?php
ob_start();
?>

<div class="hero">
    <h1>Formularios</h1>
    <p>Selecciona el área para ver sus formularios disponibles. No se requiere iniciar sesión.</p>
</div>

<div class="module-grid">

   <a href="https://solicitudes.faret.cl/app/formularios/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-truck"></i>
        </div>

        <h2>Logística</h2>
        <p>Formularios operacionales del área de logística.</p>
    </a>

    <a href="/modules/formularios/publico/diseno/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-palette-fill"></i>
        </div>

        <h2>Diseño</h2>
        <p>Solicitudes de desarrollo gráfico y estructural.</p>
    </a>

    <a href="/modules/formularios/publico/rrhh/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-people-fill"></i>
        </div>

        <h2>RRHH</h2>
        <p>Registro de producción de desgaje.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
