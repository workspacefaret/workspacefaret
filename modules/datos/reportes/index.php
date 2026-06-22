<?php ob_start(); ?>

<div class="hero">
    <h1>Reportes</h1>
    <p>Consulta histórica y búsqueda de registros.</p>
</div>

<div class="cards">

    <a class="card" href="../guardias/">
        <div class="card-icon">
            <i class="bi bi-shield-check"></i>
        </div>

        <h2>Reporte Guardias</h2>

        <p>
            Revisión de recorridos, puntos de control y registros operacionales.
        </p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
