<?php
require_once '../../../../layouts/app.php';
?>

<div class="page">

    <section class="hero">
        <h1>Registros Logística</h1>
        <p>Consulta el historial de formularios, inspecciones y controles registrados por el área.</p>
    </section>

    <section class="section">

        <div class="filter-card">

            <div class="filter-group">
                <label>Formulario</label>
                <select>
                    <option>Todos</option>
                    <option>Inspección Vehículos</option>
                    <option>Revisión Camión Jornada</option>
                    <option>Recepción Materiales</option>
                    <option>Control Despachos</option>
                    <option>Control Bodega</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Estado</label>
                <select>
                    <option>Todos</option>
                    <option>Completado</option>
                    <option>Pendiente</option>
                    <option>Observado</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Desde</label>
                <input type="date">
            </div>

            <div class="filter-group">
                <label>Hasta</label>
                <input type="date">
            </div>

            <div class="filter-actions">
                <button class="btn-primary">
                    <i class="bi bi-search"></i>
                    Buscar
                </button>

                <button class="btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i>
                    Limpiar
                </button>
            </div>

        </div>

    </section>

    <section class="section">

        <div class="table-card">

            <div class="table-header">
                <div>
                    <h2>Historial de registros</h2>
                    <p>Listado demo de registros operacionales de logística.</p>
                </div>

                <a href="#" class="btn-export">
                    <i class="bi bi-file-earmark-excel-fill"></i>
                    Exportar
                </a>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Formulario</th>
                            <th>Usuario</th>
                            <th>Área</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>17-06-2026 11:32</td>
                            <td>Inspección Vehículos</td>
                            <td>Juan Pérez</td>
                            <td>Logística</td>
                            <td>
                                <span class="badge badge-success">Completado</span>
                            </td>
                            <td>
                                <a href="#" class="badge badge-primary">Ver detalle</a>
                            </td>
                        </tr>

                        <tr>
                            <td>17-06-2026 10:48</td>
                            <td>Revisión Camión Jornada</td>
                            <td>Pedro Soto</td>
                            <td>Logística</td>
                            <td>
                                <span class="badge badge-success">Completado</span>
                            </td>
                            <td>
                                <a href="#" class="badge badge-primary">Ver detalle</a>
                            </td>
                        </tr>

                        <tr>
                            <td>17-06-2026 09:15</td>
                            <td>Recepción Materiales</td>
                            <td>María López</td>
                            <td>Logística</td>
                            <td>
                                <span class="badge badge-warning">Pendiente</span>
                            </td>
                            <td>
                                <a href="#" class="badge badge-primary">Ver detalle</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <a href="#">Anterior</a>
                <span>Página 1 de 1</span>
                <a href="#">Siguiente</a>
            </div>

        </div>

    </section>

</div>
