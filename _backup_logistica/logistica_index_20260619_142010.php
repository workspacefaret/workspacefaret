<?php
require_once '../../../layouts/app.php';
?>

<div class="page">

    <section class="hero">
        <h1>Logística</h1>
        <p>Gestión de inspecciones, transporte, despacho, recepción de materiales y control operacional.</p>
    </section>

    <section class="section">
        <div class="grid-4">

            <div class="stat-card">
                <span>Inspecciones hoy</span>
                <strong>24</strong>
                <small>Registros ingresados durante la jornada</small>
            </div>

            <div class="stat-card">
                <span>Pendientes</span>
                <strong>3</strong>
                <small>Revisiones pendientes de cierre</small>
            </div>

            <div class="stat-card">
                <span>Camiones revisados</span>
                <strong>18</strong>
                <small>Controles completados correctamente</small>
            </div>

            <div class="stat-card">
                <span>Último registro</span>
                <strong>11:32</strong>
                <small>Última actividad registrada</small>
            </div>

        </div>
    </section>

    <section class="section">

        <div class="section-header">
            <div class="section-title">
                <h2>Módulos de Logística</h2>
                <p>Accede a formularios, registros, reportes y recursos compartidos del área.</p>
            </div>
        </div>

        <div class="grid-4">

            <a href="/modules/operacion/logistica/formularios/" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-clipboard2-check-fill"></i>
                </div>
                <h3>Formularios</h3>
                <p>Crear nuevos registros operacionales del área logística.</p>
            </a>

            <a href="/modules/operacion/logistica/registros/" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-database-fill"></i>
                </div>
                <h3>Registros</h3>
                <p>Consultar historial, estados y registros existentes.</p>
            </a>

            <a href="/modules/operacion/logistica/reportes/" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-bar-chart-fill"></i>
                </div>
                <h3>Reportes</h3>
                <p>Revisar indicadores, gráficos y exportaciones del área.</p>
            </a>

            <a href="/modules/operacion/logistica/recursos/" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-folder-fill"></i>
                </div>
                <h3>Recursos</h3>
                <p>Acceder a planillas, documentos, instructivos y enlaces compartidos.</p>
            </a>

        </div>

    </section>

    <section class="section">

        <div class="panel">

            <div class="section-header">
                <div class="section-title">
                    <h2>Actividad reciente</h2>
                    <p>Últimos movimientos registrados en el módulo de logística.</p>
                </div>

                <span class="badge badge-primary">
                    Vista demo
                </span>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Usuario</th>
                            <th>Actividad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>11:32</td>
                            <td>Juan Pérez</td>
                            <td>Inspección Vehículos</td>
                            <td>
                                <span class="badge badge-success">Completado</span>
                            </td>
                        </tr>

                        <tr>
                            <td>10:48</td>
                            <td>Pedro Soto</td>
                            <td>Revisión Camión Jornada</td>
                            <td>
                                <span class="badge badge-success">Completado</span>
                            </td>
                        </tr>

                        <tr>
                            <td>09:15</td>
                            <td>María López</td>
                            <td>Recepción Materiales</td>
                            <td>
                                <span class="badge badge-warning">Pendiente</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </section>

</div>
