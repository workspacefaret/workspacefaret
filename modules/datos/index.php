<?php
ob_start();
?>

<div class="hero">
    <h1>Centro de Control</h1>
    <p>Revisión, análisis y exportación de información operacional.</p>
</div>

<div class="cards">
    <a class="card" href="mejora-continua/">

        <div class="card-icon">
            <i class="bi bi-clipboard-check"></i>
        </div>

        <h2>Mejora Continua</h2>

        <p>
            Gestión de no conformidades, análisis de causa raíz y planes de acción.
        </p>

    </a>

    <a class="card" href="guardias/">

        <div class="card-icon">
            <i class="bi bi-shield-check"></i>
        </div>

        <h2>Guardias</h2>

        <p>
            Revisión de recorridos, puntos de control y registros operacionales.
        </p>

    </a>

    <a class="card" href="dashboard/">

        <div class="card-icon">
            <i class="bi bi-speedometer2"></i>
        </div>

        <h2>Dashboard</h2>

        <p>
            Indicadores operacionales y métricas generales.
        </p>

    </a>

    <a class="card" href="reportes/">

        <div class="card-icon">
            <i class="bi bi-file-earmark-bar-graph-fill"></i>
        </div>

        <h2>Reportes</h2>

        <p>
            Consulta histórica y búsqueda de registros.
        </p>

    </a>

    <a class="card" href="exportaciones/">

        <div class="card-icon">
            <i class="bi bi-file-earmark-excel-fill"></i>
        </div>

        <h2>Exportaciones Excel</h2>

        <p>
            Descarga de información para auditorías y análisis.
        </p>

    </a>

</div>

<?php
$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
