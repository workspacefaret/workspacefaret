<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('logistica');

require_once '../../../../layouts/app.php';
?>

<div class="page">

    <section class="hero">
        <h1>Recursos Logística</h1>
        <p>Documentación, procedimientos, planillas y recursos compartidos del área logística.</p>
    </section>

    <section class="section">

        <div class="grid-4">

            <a href="#" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                </div>

                <h3>Procedimientos</h3>

                <p>
                    Procedimientos operacionales, instructivos y documentación oficial.
                </p>
            </a>

            <a href="#" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                </div>

                <h3>Planillas</h3>

                <p>
                    Acceso rápido a planillas operacionales utilizadas por el área.
                </p>
            </a>

            <a href="#" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-google"></i>
                </div>

                <h3>Google Drive</h3>

                <p>
                    Documentos compartidos y carpetas colaborativas del departamento.
                </p>
            </a>

            <a href="#" class="action-card">
                <div class="action-card-icon">
                    <i class="bi bi-folder-fill"></i>
                </div>

                <h3>Biblioteca</h3>

                <p>
                    Manuales, formatos, anexos y recursos de consulta permanente.
                </p>
            </a>

        </div>

    </section>

    <section class="section">

        <div class="table-card">

            <div class="table-header">
                <div>
                    <h2>Documentos recientes</h2>
                    <p>Últimos recursos incorporados al área logística.</p>
                </div>
            </div>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Categoría</th>
                            <th>Actualización</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>

                        <tr>
                            <td>Procedimiento Recepción Materiales</td>
                            <td>Procedimiento</td>
                            <td>15-06-2026</td>
                            <td>
                                <a href="#" class="badge badge-primary">
                                    Abrir
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <td>Checklist Inspección Vehículos</td>
                            <td>Formulario</td>
                            <td>12-06-2026</td>
                            <td>
                                <a href="#" class="badge badge-primary">
                                    Abrir
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <td>Control Despachos.xlsx</td>
                            <td>Planilla</td>
                            <td>10-06-2026</td>
                            <td>
                                <a href="#" class="badge badge-primary">
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
