<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_logistica');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();

// Rango de fecha para traslados/recepciones/despachos: por defecto, últimos 7 días.
// El formulario recibe fechas en yyyy-mm-dd (input type="date") y se convierten
// a yyyyMMdd, que es el formato que exige apifaret (DocumentosController.cs).
$desdeInput = trim($_GET['desde'] ?? '');
$hastaInput = trim($_GET['hasta'] ?? '');

if ($desdeInput === '') {
    $desdeInput = date('Y-m-d', strtotime('-7 days'));
}

if ($hastaInput === '') {
    $hastaInput = date('Y-m-d');
}

function aFechaSap($fechaIso)
{
    $timestamp = strtotime($fechaIso);

    return $timestamp ? date('Ymd', $timestamp) : null;
}

$desdeSap = aFechaSap($desdeInput);
$hastaSap = aFechaSap($hastaInput);
$rangoValido = $desdeSap !== null && $hastaSap !== null;

function formatoFechaSap($fecha)
{
    if (!$fecha) {
        return '-';
    }

    $timestamp = strtotime($fecha);

    return $timestamp ? date('d-m-Y', $timestamp) : $fecha;
}

$resultadosTraslados = [];
$resultadosRecepciones = [];
$resultadosDespachos = [];
$respuestaTraslados = null;
$respuestaRecepciones = null;
$respuestaDespachos = null;

if ($rangoValido) {
    $filtroFecha = 'desde=' . $desdeSap . '&hasta=' . $hastaSap . '&top=50';

    $respuestaTraslados = ApiFaretClient::get('documentos/traslados?' . $filtroFecha, $empresa);

    if ($respuestaTraslados['ok']) {
        $resultadosTraslados = $respuestaTraslados['data']['data'] ?? [];
    }

    $respuestaRecepciones = ApiFaretClient::get('documentos/recepciones?' . $filtroFecha, $empresa);

    if ($respuestaRecepciones['ok']) {
        $resultadosRecepciones = $respuestaRecepciones['data']['data'] ?? [];
    }

    $respuestaDespachos = ApiFaretClient::get('documentos/despachos?' . $filtroFecha, $empresa);

    if ($respuestaDespachos['ok']) {
        $resultadosDespachos = $respuestaDespachos['data']['data'] ?? [];
    }
}

$respuestaPicking = ApiFaretClient::get('documentos/picking/pendientes?top=50', $empresa);
$resultadosPicking = [];

if ($respuestaPicking['ok']) {
    $resultadosPicking = $respuestaPicking['data']['data'] ?? [];
}

$respuestaSolicitudesTraslado = ApiFaretClient::get('documentos/traslados/pendientes?top=50', $empresa);
$resultadosSolicitudesTraslado = [];

if ($respuestaSolicitudesTraslado['ok']) {
    $resultadosSolicitudesTraslado = $respuestaSolicitudesTraslado['data']['data'] ?? [];
}

?>

<div class="hero">
    <h1>Logística SAP</h1>
    <p>Traslados, recepciones, despachos y picking pendiente. Solo lectura.</p>
</div>

<div class="filter-card">
    <div class="filter-group">
        <label for="selectorEmpresa">Empresa</label>
        <select id="selectorEmpresa">
            <?php foreach (ApiFaretClient::EMPRESAS_VALIDAS as $emp): ?>
                <option value="<?= htmlspecialchars($emp) ?>" <?= $emp === $empresa ? 'selected' : '' ?>><?= htmlspecialchars($emp) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<script src="/assets/js/sap/empresa-selector.js"></script>

<div class="sap-guide">
    <span class="sap-guide-icon"><i class="bi bi-info-circle"></i></span>
    <div>
        <h3>¿Qué muestra este filtro?</h3>
        <p>Traslados, recepciones y despachos se filtran por el rango de fechas de abajo (por defecto, últimos 7 días).</p>
        <p>"Solicitudes de traslado pendientes" y "Picking pendiente" no usan este filtro: siempre muestran lo abierto en este momento.</p>
    </div>
</div>

<form class="filter-card" method="GET">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">

    <div class="filter-group">
        <label>Desde</label>
        <input type="date" name="desde" value="<?= htmlspecialchars($desdeInput) ?>">
    </div>

    <div class="filter-group">
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?= htmlspecialchars($hastaInput) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn-primary">
            <i class="bi bi-funnel"></i>
            Filtrar
        </button>
    </div>
</form>

<?php if (!$rangoValido): ?>

    <div class="card">
        <h2>Rango de fechas inválido</h2>
        <p>Revisa las fechas "desde" y "hasta".</p>
    </div>

<?php else: ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Traslados</h2>
                <p>Entre bodegas, del <?= htmlspecialchars(formatoFechaSap($desdeSap)) ?> al <?= htmlspecialchars(formatoFechaSap($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaTraslados['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo obtener el listado de traslados. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaTraslados)) ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosTraslados as $t): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($t['docNum'] ?? $t['docEntry'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($t['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($t['almacenOrigen'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['almacenDestino'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['comentarios'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosTraslados) === 0): ?>
                            <tr>
                                <td colspan="5">Sin traslados en el rango seleccionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Recepciones</h2>
                <p>Del <?= htmlspecialchars(formatoFechaSap($desdeSap)) ?> al <?= htmlspecialchars(formatoFechaSap($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaRecepciones['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo obtener el listado de recepciones. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaRecepciones)) ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosRecepciones as $r): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($r['docNum'] ?? $r['docEntry'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['proveedorNombre'] ?? $r['proveedorCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($r['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($r['comentarios'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosRecepciones) === 0): ?>
                            <tr>
                                <td colspan="4">Sin recepciones en el rango seleccionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Despachos</h2>
                <p>Del <?= htmlspecialchars(formatoFechaSap($desdeSap)) ?> al <?= htmlspecialchars(formatoFechaSap($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaDespachos['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo obtener el listado de despachos. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaDespachos)) ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Dirección</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosDespachos as $d): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($d['docNum'] ?? $d['docEntry'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['clienteNombre'] ?? $d['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($d['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($d['direccionDespacho'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['comentarios'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosDespachos) === 0): ?>
                            <tr>
                                <td colspan="5">Sin despachos en el rango seleccionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<div class="table-card" style="margin-top:32px;">
    <div class="table-header">
        <div>
            <h2>Solicitudes de traslado pendientes</h2>
            <p>Abiertas ahora mismo en <?= htmlspecialchars($empresa) ?> (no usa el filtro de fechas de arriba).</p>
        </div>
    </div>

    <?php if (!$respuestaSolicitudesTraslado['ok']): ?>
        <div class="card">
            <h2>Error de conexión con apifaret</h2>
            <p>No se pudo obtener el listado de solicitudes de traslado. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaSolicitudesTraslado)) ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Doc</th>
                        <th>Fecha</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosSolicitudesTraslado as $s): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($s['docNum'] ?? $s['docEntry'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(formatoFechaSap($s['fecha'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($s['almacenOrigen'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($s['almacenDestino'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($s['comentarios'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($resultadosSolicitudesTraslado) === 0): ?>
                        <tr>
                            <td colspan="5">No hay solicitudes de traslado pendientes en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="table-card" style="margin-top:32px;">
    <div class="table-header">
        <div>
            <h2>Picking pendiente</h2>
            <p>Listas liberadas y pendientes de picking en <?= htmlspecialchars($empresa) ?>.</p>
        </div>
    </div>

    <?php if (!$respuestaPicking['ok']): ?>
        <div class="card">
            <h2>Error de conexión con apifaret</h2>
            <p>No se pudo obtener el listado de picking. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaPicking)) ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Líneas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosPicking as $pk): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($pk['absEntry'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(formatoFechaSap($pk['fecha'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($pk['estado'] ?? '-') ?></td>
                            <td><?= count($pk['lineas'] ?? []) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($resultadosPicking) === 0): ?>
                        <tr>
                            <td colspan="4">No hay picking pendiente en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
