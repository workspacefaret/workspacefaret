<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('control_moldes');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$nombreUsuarioActual = currentUser()['nombre'];

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Control de Moldes</h1>
        <p>Reemplaza la planilla de Control de Moldes (PLA-MNF-COM-V2): registra y consulta el avance completo de cada NP, desde el ingreso del layout hasta la planilla final.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/planificacion/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver a Perfiles y Moldes
        </a>
        <a href="/modules/planificacion/registro-molde/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-clipboard-check"></i>
            Registro de Molde
        </a>
        <?php if (hasModuleAccess('stock_moldes')): ?>
            <a href="/modules/planificacion/stock-moldes/" class="admin-btn admin-btn-secondary">
                <i class="bi bi-archive"></i>
                Stock de Moldes
            </a>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Nuevo registro</h2>
                <p>Completa la información por sección. Los campos que aún no aplican pueden quedar en blanco.</p>
            </div>
        </div>

        <div id="alertaRegistro" class="admin-alert hidden"></div>

        <form id="formRegistro" autocomplete="off">
            <details class="admin-section-toggle" open>
                <summary>Información NP</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="comIngresoLayout">Ingreso Layout</label>
                        <input type="date" id="comIngresoLayout">
                    </div>
                    <div class="admin-form-field">
                        <label for="comNombreOperador">Nombre del Operador</label>
                        <input type="text" id="comNombreOperador" list="listaComOperadoresSugeridos">
                        <datalist id="listaComOperadoresSugeridos">
                            <option value="Sergio Herrera"></option>
                        </datalist>
                    </div>
                    <div class="admin-form-field">
                        <label for="comNp">NP</label>
                        <input type="text" id="comNp">
                    </div>
                    <div class="admin-form-field">
                        <label for="comCliente">Cliente</label>
                        <input type="text" id="comCliente" list="listaComClientes">
                        <datalist id="listaComClientes"></datalist>
                    </div>
                    <div class="admin-form-field">
                        <label for="comRubro">Rubro</label>
                        <select id="comRubro">
                            <option value="">-</option>
                            <option value="INDUSTRIAL">INDUSTRIAL</option>
                            <option value="FARMACEUTICO">FARMACEUTICO</option>
                            <option value="COSMETICO">COSMETICO</option>
                            <option value="AGROINDUSTRIAL">AGROINDUSTRIAL</option>
                            <option value="LICORES">LICORES</option>
                            <option value="PRUEBA INDUSTRIAL">PRUEBA INDUSTRIAL</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="comSolicitud">Solicitud</label>
                        <select id="comSolicitud">
                            <option value="">-</option>
                            <option value="NORMAL">NORMAL</option>
                            <option value="URGENTE">URGENTE</option>
                            <option value="SIN IMPRESIÓN">SIN IMPRESIÓN</option>
                            <option value="SIN IMPRESIÓN URGENTE">SIN IMPRESIÓN URGENTE</option>
                            <option value="REPOSICIÓN URGENTE">REPOSICIÓN URGENTE</option>
                            <option value="PRUEBA INDUSTRIAL">PRUEBA INDUSTRIAL</option>
                            <option value="PRUEBA INDUSTRIAL URGENTE">PRUEBA INDUSTRIAL URGENTE</option>
                            <option value="PRUEBA INDUSTRIAL SIN IMPRESION">PRUEBA INDUSTRIAL SIN IMPRESION</option>
                            <option value="ORTHOTEC">ORTHOTEC</option>
                            <option value="ORTHOTEC URGENTE">ORTHOTEC URGENTE</option>
                            <option value="REPETITIVA">REPETITIVA</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="comFechaEntrega">Fecha de Entrega</label>
                        <input type="date" id="comFechaEntrega">
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle" open>
                <summary>Molde</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="comFormato">Formato</label>
                        <input type="text" id="comFormato">
                    </div>
                    <div class="admin-form-field">
                        <label for="comTiraje">Tiraje</label>
                        <input type="text" id="comTiraje">
                    </div>
                    <div class="admin-form-field">
                        <label for="comStatus">Status</label>
                        <select id="comStatus">
                            <option value="">-</option>
                            <option value="MOLDE NUEVO">MOLDE NUEVO</option>
                            <option value="HAY MOLDE">HAY MOLDE</option>
                            <option value="MOLDE CON CAMBIOS">MOLDE CON CAMBIOS</option>
                            <option value="POR CONFIRMAR">POR CONFIRMAR</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="comNpAntigua">NP Antigua</label>
                        <input type="text" id="comNpAntigua">
                    </div>
                    <div class="admin-form-field">
                        <label for="comNpOrigen">NP Origen</label>
                        <input type="text" id="comNpOrigen">
                    </div>
                    <div class="admin-form-field">
                        <label for="comCodigoMolde">Código de Molde</label>
                        <input type="text" id="comCodigoMolde" list="listaComCodigosMolde">
                        <datalist id="listaComCodigosMolde"></datalist>
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Cartulina</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="comSustrato">Sustrato</label>
                        <input type="text" id="comSustrato">
                    </div>
                    <div class="admin-form-field">
                        <label for="comGramaje">Gramaje</label>
                        <input type="text" id="comGramaje">
                    </div>
                    <div class="admin-form-field">
                        <label for="comEmplacado">Emplacado</label>
                        <input type="text" id="comEmplacado">
                    </div>
                    <div class="admin-form-field">
                        <label for="comLaminado">Laminado</label>
                        <input type="text" id="comLaminado">
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Troquelado</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="comTroquelado">Troquelado</label>
                        <select id="comTroquelado">
                            <option value="">-</option>
                            <option value="TROQUELADO">TROQUELADO</option>
                            <option value="TROQUELADO 1 CORTE">TROQUELADO 1 CORTE</option>
                            <option value="TROQUELADO Y TROQUELADO 1 CORTE">TROQUELADO Y TROQUELADO 1 CORTE</option>
                            <option value="TROQUELADO CON MEDIO CORTE">TROQUELADO CON MEDIO CORTE</option>
                            <option value="TROQUELADO CON MEDIO CORTE Y 1 CORTE">TROQUELADO CON MEDIO CORTE Y 1 CORTE</option>
                            <option value="TROQUELADO CON DESGAJE DINAMICO">TROQUELADO CON DESGAJE DINAMICO</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="comPinza">Pinza</label>
                        <input type="text" id="comPinza" placeholder="Ej: 13 MM">
                    </div>
                    <div class="admin-form-field">
                        <label for="comSeparacion">Separación</label>
                        <input type="text" id="comSeparacion" placeholder="Ej: 4 MM">
                    </div>
                    <div class="admin-form-field">
                        <label for="comExtras">Extras</label>
                        <select id="comExtras">
                            <option value="">-</option>
                            <option value="BRAILE">BRAILE</option>
                            <option value="BRAILE APARTE">BRAILE APARTE</option>
                            <option value="BRAILE EN LINEA">BRAILE EN LINEA</option>
                            <option value="RELIEVE">RELIEVE</option>
                            <option value="RELIEVE APARTE">RELIEVE APARTE</option>
                            <option value="RELIEVE EN LINEA">RELIEVE EN LINEA</option>
                            <option value="FOLIA">FOLIA</option>
                            <option value="FOLIA Y RELIEVE">FOLIA Y RELIEVE</option>
                            <option value="FOLIA Y BRAILE">FOLIA Y BRAILE</option>
                            <option value="BRAILE Y RELIEVE">BRAILE Y RELIEVE</option>
                        </select>
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Comentarios</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field admin-form-field-full">
                        <label for="comComentarios">Comentarios</label>
                        <textarea id="comComentarios" rows="2"></textarea>
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Planilla</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="comLayoutEstado">Layout</label>
                        <select id="comLayoutEstado">
                            <option value="">-</option>
                            <option value="OK">OK</option>
                            <option value="EN PROCESO">EN PROCESO</option>
                            <option value="ANULADA">ANULADA</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="comMoldeEstado">Molde</label>
                        <select id="comMoldeEstado">
                            <option value="">-</option>
                            <option value="OK">OK</option>
                            <option value="EN PROCESO">EN PROCESO</option>
                            <option value="ANULADA">ANULADA</option>
                        </select>
                    </div>
                </div>
            </details>

            <div class="admin-form-grid">
                <div class="admin-form-field">
                    <label for="comEnviarCorreo">¿Desea enviar correo?</label>
                    <select id="comEnviarCorreo">
                        <option value="NO">No</option>
                        <option value="SI">Sí</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarRegistro">
                    <i class="bi bi-save"></i>
                    Guardar registro
                </button>
            </div>
        </form>
    </div>
</section>

<section class="section">
    <div class="panel admin-panel">
        <div class="section-header">
            <div class="section-title">
                <h2>Registros</h2>
                <p>Filtra, busca y edita los registros de control de moldes.</p>
            </div>
            <div class="admin-hero-actions">
                <span class="badge badge-primary" id="badgeCantidadRegistros">0 registros</span>
                <button type="button" class="admin-btn admin-btn-secondary" id="btnImprimirRegistros">
                    <i class="bi bi-printer"></i>
                    Imprimir
                </button>
            </div>
        </div>

        <div class="admin-filters admin-filters-cpm">
            <div class="admin-filter-field">
                <label for="filtroComBuscar">Buscar</label>
                <input type="text" id="filtroComBuscar" placeholder="NP, cliente, operador, código molde...">
            </div>
            <div class="admin-filter-field">
                <label for="filtroComCliente">Cliente</label>
                <input type="text" id="filtroComCliente" list="listaComClientes">
            </div>
            <div class="admin-filter-field">
                <label for="filtroComNp">NP</label>
                <input type="text" id="filtroComNp">
            </div>
            <div class="admin-filter-field">
                <label for="filtroComCodigoMolde">Código Molde</label>
                <input type="text" id="filtroComCodigoMolde" list="listaComCodigosMolde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroComStatus">Status</label>
                <select id="filtroComStatus">
                    <option value="">Todos</option>
                    <option value="MOLDE NUEVO">MOLDE NUEVO</option>
                    <option value="HAY MOLDE">HAY MOLDE</option>
                    <option value="MOLDE CON CAMBIOS">MOLDE CON CAMBIOS</option>
                    <option value="POR CONFIRMAR">POR CONFIRMAR</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroComLayoutEstado">Estado Layout</label>
                <select id="filtroComLayoutEstado">
                    <option value="">Todos</option>
                    <option value="OK">OK</option>
                    <option value="EN PROCESO">EN PROCESO</option>
                    <option value="ANULADA">ANULADA</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroComMoldeEstado">Estado Molde</label>
                <select id="filtroComMoldeEstado">
                    <option value="">Todos</option>
                    <option value="OK">OK</option>
                    <option value="EN PROCESO">EN PROCESO</option>
                    <option value="ANULADA">ANULADA</option>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="filtroComFechaDesde">Ingreso desde</label>
                <input type="date" id="filtroComFechaDesde">
            </div>
            <div class="admin-filter-field">
                <label for="filtroComFechaHasta">Ingreso hasta</label>
                <input type="date" id="filtroComFechaHasta">
            </div>
            <div class="admin-filter-field admin-filter-actions">
                <button type="button" class="admin-btn admin-btn-secondary" id="btnLimpiarFiltrosRegistro">Limpiar</button>
            </div>
        </div>

        <div class="admin-table-wrap admin-table-wrap-sticky">
            <table class="admin-table admin-table-com-registros">
                <thead>
                    <tr>
                        <th>Ingreso Layout</th>
                        <th>Operador</th>
                        <th>NP</th>
                        <th>Cliente</th>
                        <th>Rubro</th>
                        <th>Solicitud</th>
                        <th>Fecha Entrega</th>
                        <th>Formato</th>
                        <th>Tiraje</th>
                        <th>Status</th>
                        <th>NP Antigua</th>
                        <th>NP Origen</th>
                        <th>Código Molde</th>
                        <th>Sustrato</th>
                        <th>Gramaje</th>
                        <th>Emplacado</th>
                        <th>Laminado</th>
                        <th>Troquelado</th>
                        <th>Pinza</th>
                        <th>Separación</th>
                        <th>Extras</th>
                        <th>Comentarios</th>
                        <th>Layout</th>
                        <th>Molde</th>
                        <th class="admin-table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaRegistrosBody">
                    <tr><td colspan="25" class="admin-empty">Cargando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination" id="paginacionRegistros"></div>
    </div>
</section>

<!-- ================= MODAL EDITAR REGISTRO ================= -->
<div class="admin-modal-overlay hidden" id="modalEditarRegistro">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Editar registro <span id="modalEditarRegistroCodigo"></span></h2>
                <p>Actualiza cualquier campo de este NP.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalEditarRegistro" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="alertaEditarRegistro" class="admin-alert hidden"></div>

        <form id="formEditarRegistro" autocomplete="off">
            <input type="hidden" id="editarComId">

            <details class="admin-section-toggle" open>
                <summary>Información NP</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="editarComIngresoLayout">Ingreso Layout</label>
                        <input type="date" id="editarComIngresoLayout">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComNombreOperador">Nombre del Operador</label>
                        <input type="text" id="editarComNombreOperador" list="listaComOperadoresSugeridos">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComNp">NP</label>
                        <input type="text" id="editarComNp">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComCliente">Cliente</label>
                        <input type="text" id="editarComCliente" list="listaComClientes">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComRubro">Rubro</label>
                        <select id="editarComRubro">
                            <option value="">-</option>
                            <option value="INDUSTRIAL">INDUSTRIAL</option>
                            <option value="FARMACEUTICO">FARMACEUTICO</option>
                            <option value="COSMETICO">COSMETICO</option>
                            <option value="AGROINDUSTRIAL">AGROINDUSTRIAL</option>
                            <option value="LICORES">LICORES</option>
                            <option value="PRUEBA INDUSTRIAL">PRUEBA INDUSTRIAL</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComSolicitud">Solicitud</label>
                        <select id="editarComSolicitud">
                            <option value="">-</option>
                            <option value="NORMAL">NORMAL</option>
                            <option value="URGENTE">URGENTE</option>
                            <option value="SIN IMPRESIÓN">SIN IMPRESIÓN</option>
                            <option value="SIN IMPRESIÓN URGENTE">SIN IMPRESIÓN URGENTE</option>
                            <option value="REPOSICIÓN URGENTE">REPOSICIÓN URGENTE</option>
                            <option value="PRUEBA INDUSTRIAL">PRUEBA INDUSTRIAL</option>
                            <option value="PRUEBA INDUSTRIAL URGENTE">PRUEBA INDUSTRIAL URGENTE</option>
                            <option value="PRUEBA INDUSTRIAL SIN IMPRESION">PRUEBA INDUSTRIAL SIN IMPRESION</option>
                            <option value="ORTHOTEC">ORTHOTEC</option>
                            <option value="ORTHOTEC URGENTE">ORTHOTEC URGENTE</option>
                            <option value="REPETITIVA">REPETITIVA</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComFechaEntrega">Fecha de Entrega</label>
                        <input type="date" id="editarComFechaEntrega">
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle" open>
                <summary>Molde</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="editarComFormato">Formato</label>
                        <input type="text" id="editarComFormato">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComTiraje">Tiraje</label>
                        <input type="text" id="editarComTiraje">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComStatus">Status</label>
                        <select id="editarComStatus">
                            <option value="">-</option>
                            <option value="MOLDE NUEVO">MOLDE NUEVO</option>
                            <option value="HAY MOLDE">HAY MOLDE</option>
                            <option value="MOLDE CON CAMBIOS">MOLDE CON CAMBIOS</option>
                            <option value="POR CONFIRMAR">POR CONFIRMAR</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComNpAntigua">NP Antigua</label>
                        <input type="text" id="editarComNpAntigua">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComNpOrigen">NP Origen</label>
                        <input type="text" id="editarComNpOrigen">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComCodigoMolde">Código de Molde</label>
                        <input type="text" id="editarComCodigoMolde" list="listaComCodigosMolde">
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Cartulina</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="editarComSustrato">Sustrato</label>
                        <input type="text" id="editarComSustrato">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComGramaje">Gramaje</label>
                        <input type="text" id="editarComGramaje">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComEmplacado">Emplacado</label>
                        <input type="text" id="editarComEmplacado">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComLaminado">Laminado</label>
                        <input type="text" id="editarComLaminado">
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Troquelado</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="editarComTroquelado">Troquelado</label>
                        <select id="editarComTroquelado">
                            <option value="">-</option>
                            <option value="TROQUELADO">TROQUELADO</option>
                            <option value="TROQUELADO 1 CORTE">TROQUELADO 1 CORTE</option>
                            <option value="TROQUELADO Y TROQUELADO 1 CORTE">TROQUELADO Y TROQUELADO 1 CORTE</option>
                            <option value="TROQUELADO CON MEDIO CORTE">TROQUELADO CON MEDIO CORTE</option>
                            <option value="TROQUELADO CON MEDIO CORTE Y 1 CORTE">TROQUELADO CON MEDIO CORTE Y 1 CORTE</option>
                            <option value="TROQUELADO CON DESGAJE DINAMICO">TROQUELADO CON DESGAJE DINAMICO</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComPinza">Pinza</label>
                        <input type="text" id="editarComPinza">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComSeparacion">Separación</label>
                        <input type="text" id="editarComSeparacion">
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComExtras">Extras</label>
                        <select id="editarComExtras">
                            <option value="">-</option>
                            <option value="BRAILE">BRAILE</option>
                            <option value="BRAILE APARTE">BRAILE APARTE</option>
                            <option value="BRAILE EN LINEA">BRAILE EN LINEA</option>
                            <option value="RELIEVE">RELIEVE</option>
                            <option value="RELIEVE APARTE">RELIEVE APARTE</option>
                            <option value="RELIEVE EN LINEA">RELIEVE EN LINEA</option>
                            <option value="FOLIA">FOLIA</option>
                            <option value="FOLIA Y RELIEVE">FOLIA Y RELIEVE</option>
                            <option value="FOLIA Y BRAILE">FOLIA Y BRAILE</option>
                            <option value="BRAILE Y RELIEVE">BRAILE Y RELIEVE</option>
                        </select>
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Comentarios</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field admin-form-field-full">
                        <label for="editarComComentarios">Comentarios</label>
                        <textarea id="editarComComentarios" rows="2"></textarea>
                    </div>
                </div>
            </details>

            <details class="admin-section-toggle">
                <summary>Planilla</summary>
                <div class="admin-section-toggle-body admin-form-grid">
                    <div class="admin-form-field">
                        <label for="editarComLayoutEstado">Layout</label>
                        <select id="editarComLayoutEstado">
                            <option value="">-</option>
                            <option value="OK">OK</option>
                            <option value="EN PROCESO">EN PROCESO</option>
                            <option value="ANULADA">ANULADA</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="editarComMoldeEstado">Molde</label>
                        <select id="editarComMoldeEstado">
                            <option value="">-</option>
                            <option value="OK">OK</option>
                            <option value="EN PROCESO">EN PROCESO</option>
                            <option value="ANULADA">ANULADA</option>
                        </select>
                    </div>
                </div>
            </details>

            <div class="admin-form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" id="btnGuardarEdicionRegistro">
                    <i class="bi bi-save"></i>
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL HISTORIAL ================= -->
<div class="admin-modal-overlay hidden" id="modalHistorial">
    <div class="panel admin-panel admin-modal">
        <div class="admin-modal-header">
            <div class="section-title">
                <h2>Historial <span id="modalHistorialCodigo"></span></h2>
                <p>Registro de creación y ediciones, con el usuario responsable de cada cambio.</p>
            </div>
            <button class="admin-icon-btn" id="btnCerrarModalHistorial" type="button" title="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="admin-list-box" id="historialLista"></div>
    </div>
</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
</script>
<script src="/assets/js/planificacion/print-tabla.js"></script>
<script src="/assets/js/planificacion/com-registros.js"></script>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
