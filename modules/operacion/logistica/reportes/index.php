<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('logistica');

require_once '../../../../layouts/app.php';
?>

<div class="page">

    <section class="hero">
        <h1>Reportes Logística</h1>
        <p>Indicadores, gráficos y exportaciones para seguimiento operacional del área.</p>
    </section>

    <section class="section">
        <div class="grid-4">

            <div class="stat-card">
                <span>Total registros</span>
                <strong>148</strong>
                <small>Registros acumulados del mes</small>
            </div>

            <div class="stat-card">
                <span>Completados</span>
                <strong>132</strong>
                <small>Controles finalizados correctamente</small>
            </div>

            <div class="stat-card">
                <span>Pendientes</span>
                <strong>11</strong>
                <small>Registros pendientes de revisión</small>
            </div>

            <div class="stat-card">
                <span>Observados</span>
                <strong>5</strong>
                <small>Registros con observaciones</small>
            </div>

        </div>
    </section>

    <section class="section">

        <div class="grid-2">

            <div class="panel">
                <div class="section-header">
                    <div class="section-title">
                        <h2>Actividad mensual</h2>
                        <p>Vista demo para futuros gráficos de registros por día o semana.</p>
                    </div>
                </div>

                <div class="chart-placeholder">
                    Gráfico de actividad mensual pendiente de conectar
                </div>
            </div>

            <div class="panel">
                <div class="section-header">
                    <div class="section-title">
                        <h2>Registros por formulario</h2>
                        <p>Distribución de uso de formularios logísticos.</p>
                    </div>
                </div>

                <div class="chart-placeholder">
                    Gráfico de formularios pendiente de conectar
                </div>
            </div>

        </div>

    </section>

    <section class="section">

        <div class="table-card">

            <div class="table-header">
                <div>
                    <h2>Resumen por formulario</h2>
                    <p>Indicadores demo para visualizar el comportamiento del área.</p>
                </div>

                <a href="#" class="btn-export">
                    <i class="bi bi-file-earmark-excel-fill"></i>
                    Exportar reporte
                </a>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Formulario</th>
                            <th>Total</th>
                            <th>Completados</th>
                            <th>Pendientes</th>
                            <th>Observados</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>Inspección Vehículos</td>
                            <td>62</td>
                            <td>58</td>
                            <td>3</td>
                            <td>1</td>
                        </tr>

                        <tr>
                            <td>Revisión Camión Jornada</td>
                            <td>41</td>
                            <td>36</td>
                            <td>3</td>
                            <td>2</td>
                        </tr>

                        <tr>
                            <td>Recepción Materiales</td>
                            <td>29</td>
                            <td>25</td>
                            <td>3</td>
                            <td>1</td>
                        </tr>

                        <tr>
                            <td>Control Despachos</td>
                            <td>16</td>
                            <td>13</td>
                            <td>2</td>
                            <td>1</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </section>

</div>
