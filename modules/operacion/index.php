<?php
ob_start();

$areas = [
    [
        'titulo' => 'Logística',
        'descripcion' => 'Formularios, registros, reportes y recursos del área logística.',
        'url' => '/modules/operacion/logistica/',
        'icono' => 'bi-truck'
    ],
    [
        'titulo' => 'Diseño',
        'descripcion' => 'Solicitudes, aprobaciones, registros y recursos del área de diseño.',
        'url' => '/modules/en-proceso/?modulo=Diseño',
        'icono' => 'bi-palette-fill'
    ],
    [
        'titulo' => 'Contabilidad',
        'descripcion' => 'Solicitudes administrativas, registros financieros y documentación.',
        'url' => '/modules/en-proceso/?modulo=Contabilidad',
        'icono' => 'bi-calculator-fill'
    ],
    [
        'titulo' => 'RRHH',
        'descripcion' => 'Gestión de módulos de Recursos Humanos, guardias y administración interna.',
        'url' => '/modules/rrhh/',
        'icono' => 'bi-people-fill'
    ]
];
?>

<div class="page">

    <section class="hero">
        <h1>Operación</h1>
        <p>Selecciona un área de trabajo para acceder a sus formularios, registros, reportes y recursos.</p>
    </section>

    <section class="section">
        <div class="grid-4">

            <?php foreach ($areas as $area): ?>

                <a class="action-card"
                    href="<?= htmlspecialchars($area['url']) ?>">

                    <div class="action-card-icon">
                        <i class="bi <?= htmlspecialchars($area['icono']) ?>"></i>
                    </div>

                    <h3><?= htmlspecialchars($area['titulo']) ?></h3>

                    <p><?= htmlspecialchars($area['descripcion']) ?></p>

                </a>

            <?php endforeach; ?>

        </div>
    </section>

    <section class="section">

        <div class="panel">

            <div class="section-header">
                <div class="section-title">
                    <h2>Estado general de operación</h2>
                    <p>Resumen inicial de actividad operacional por departamento.</p>
                </div>

                <span class="badge badge-primary">Vista demo</span>
            </div>

            <div class="grid-4">

                <div class="stat-card">
                    <span>Áreas disponibles</span>
                    <strong>4</strong>
                    <small>Departamentos preparados para carga de formularios</small>
                </div>

                <div class="stat-card">
                    <span>Formularios activos</span>
                    <strong>2</strong>
                    <small>Actualmente enlazados desde solicitudes.faret.cl</small>
                </div>

                <div class="stat-card">
                    <span>Módulos creados</span>
                    <strong>5</strong>
                    <small>Dashboard, formularios, registros, reportes y recursos</small>
                </div>

                <div class="stat-card">
                    <span>Próxima etapa</span>
                    <strong>API</strong>
                    <small>Conexión futura a registros y estadísticas reales</small>
                </div>

            </div>

        </div>

    </section>

    <section class="section">

        <div class="table-card">

            <div class="table-header">
                <div>
                    <h2>Formularios operacionales existentes</h2>
                    <p>Formularios actuales disponibles mientras se ordena la nueva estructura por áreas.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th>Formulario</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acceso</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>Logística</td>
                            <td>Inspección Vehículos</td>
                            <td>Registro de inspecciones operacionales.</td>
                            <td><span class="badge badge-success">Activo</span></td>
                            <td>
                                <a class="badge badge-primary"
                                    href="https://solicitudes.faret.cl/app/formularios/views/inspeccion_vehiculo/"
                                    target="_blank">
                                    Abrir
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <td>Logística</td>
                            <td>Revisión Camión Jornada</td>
                            <td>Registro de revisión de camión, llaves y cierre operacional.</td>
                            <td><span class="badge badge-success">Activo</span></td>
                            <td>
                                <a class="badge badge-primary"
                                    href="https://solicitudes.faret.cl/app/formularios/views/revision_camion_jornada/"
                                    target="_blank">
                                    Abrir
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </section>

</div>

<?php
$contenido = ob_get_clean();

include '../../layouts/app.php';
?>
