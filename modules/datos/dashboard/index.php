<?php ob_start(); ?>

<div class="hero">
    <h1>Dashboard</h1>
    <p>Indicadores operacionales y métricas generales.</p>
</div>

<div class="cards">

    <div class="card">
        <div class="card-icon">
            <i class="bi bi-speedometer2"></i>
        </div>

        <h2>Próximamente</h2>

        <p>
            Aquí se visualizarán indicadores consolidados de los módulos operacionales.
        </p>
    </div>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
