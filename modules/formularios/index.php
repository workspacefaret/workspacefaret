<?php
ob_start();
?>

<section class="hero">
    <h1>Formularios Corporativos</h1>
    <p>Centralización de formularios internos por área, conectados a APIs corporativas y registros operables.</p>
</section>

<section class="section">
    <div class="grid-3">

        <a class="action-card" href="/modules/formularios/desarrollo/">
            <div class="action-card-icon">
                <i class="bi bi-palette-fill"></i>
            </div>
            <h3>Desarrollo</h3>
            <p>Solicitudes de desarrollo gráfico, registros, adjuntos y seguimiento operacional.</p>
        </a>

        <a class="action-card" href="/modules/en-proceso/?modulo=Formularios Comercial">
            <div class="action-card-icon">
                <i class="bi bi-briefcase-fill"></i>
            </div>
            <h3>Comercial</h3>
            <p>Formularios comerciales, solicitudes y registros asociados al área.</p>
        </a>

        <a class="action-card" href="/modules/en-proceso/?modulo=Registros Formularios">
            <div class="action-card-icon">
                <i class="bi bi-table"></i>
            </div>
            <h3>Registros</h3>
            <p>Vista general futura para operar información como planilla corporativa.</p>
        </a>

    </div>
</section>

<section class="section">
    <div class="panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Estado del módulo</h2>
                <p>Primera etapa enfocada en Desarrollo Gráfico, consumiendo API REST publicada.</p>
            </div>
            <span class="badge badge-primary">API conectada</span>
        </div>

        <div class="grid-4">
            <div class="stat-card">
                <span>Áreas</span>
                <strong>2</strong>
                <small>Desarrollo y Comercial como estructura inicial.</small>
            </div>

            <div class="stat-card">
                <span>Formulario activo</span>
                <strong>1</strong>
                <small>Solicitud de Desarrollo Gráfico.</small>
            </div>

            <div class="stat-card">
                <span>Arquitectura</span>
                <strong>REST</strong>
                <small>Frontend conectado solo mediante API corporativa.</small>
            </div>

            <div class="stat-card">
                <span>Siguiente</span>
                <strong>Registros</strong>
                <small>Operación tipo planilla editable.</small>
            </div>
        </div>
    </div>
</section>

<?php
$contenido = ob_get_clean();
include '../../layouts/app.php';
?>
