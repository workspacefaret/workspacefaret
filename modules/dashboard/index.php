<?php

ob_start();
?>

<div class="hero">

    <h1>Workspace Faret</h1>

    <p>
        Selecciona el área de trabajo que deseas utilizar.
    </p>

</div>

<div class="cards">

    <a href="/modules/operacion/" class="card">

        <div class="card-icon">
            <i class="bi bi-clipboard2-check-fill"></i>
        </div>

        <h2>Operación</h2>

        <p>
            Formularios digitales, inspecciones,
            checklists y registros operacionales.
        </p>

    </a>

    <a href="/modules/datos/" class="card">

        <div class="card-icon">
            <i class="bi bi-database-fill"></i>
        </div>

        <h2>Administración / Registros</h2>

        <p>
            Respuestas de formularios, registros operacionales,
            dashboards, reportes y exportaciones administrativas.
        </p>

    </a>

</div>

<?php

$contenido = ob_get_clean();

include __DIR__ . '/../../layouts/app.php';
