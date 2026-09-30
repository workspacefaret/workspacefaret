<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_logistica');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

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

$topMovimientos = 50;
$topPendientes = 50;
$resultadosTraslados = [];
$resultadosRecepciones = [];
$resultadosDespachos = [];
$respuestaTraslados = null;
$respuestaRecepciones = null;
$respuestaDespachos = null;

if ($rangoValido) {
    $filtroFecha = 'desde=' . $desdeSap . '&hasta=' . $hastaSap . '&top=' . $topMovimientos;

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

$respuestaPicking = ApiFaretClient::get('documentos/picking/pendientes?top=' . $topPendientes, $empresa);
$resultadosPicking = [];

if ($respuestaPicking['ok']) {
    $resultadosPicking = $respuestaPicking['data']['data'] ?? [];
}

$respuestaSolicitudesTraslado = ApiFaretClient::get('documentos/traslados/pendientes?top=' . $topPendientes, $empresa);
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
        <p>"Solicitudes de traslado abiertas" y "Picking liberado" no usan este filtro: siempre muestran lo abierto en SAP en este momento.</p>
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
                <p>Entre bodegas, del <?= htmlspecialchars(sapFecha($desdeSap)) ?> al <?= htmlspecialchars(sapFecha($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaTraslados['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el listado de traslados.', $respuestaTraslados) ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Fecha</th>
                            <th>Origen → Destino</th>
                            <th class="sap-num">Líneas</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosTraslados as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['docNum'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars(sapFecha($t['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($t['almacenOrigen'] ?? '-') ?> → <?= htmlspecialchars($t['almacenDestino'] ?? '-') ?></td>
                                <td class="sap-num"><?= count($t['lineas'] ?? []) ?></td>
                                <td><?= sapTextoCorto($t['comentarios'] ?? '') ?></td>
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
            <?= sapNotaTruncado(count($resultadosTraslados), $topMovimientos) ?>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Recepciones</h2>
                <p>Del <?= htmlspecialchars(sapFecha($desdeSap)) ?> al <?= htmlspecialchars(sapFecha($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaRecepciones['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el listado de recepciones.', $respuestaRecepciones) ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th class="sap-num">Líneas</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosRecepciones as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['docNum'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($r['proveedorNombre'] ?? $r['proveedorCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($r['fecha'] ?? null)) ?><?= !empty($r['hora']) ? ' <span style="color:var(--muted);">' . htmlspecialchars(substr((string) $r['hora'], 0, 5)) . '</span>' : '' ?></td>
                                <td class="sap-num"><?= count($r['lineas'] ?? []) ?></td>
                                <td><?= sapTextoCorto($r['comentarios'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosRecepciones) === 0): ?>
                            <tr>
                                <td colspan="5">Sin recepciones en el rango seleccionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= sapNotaTruncado(count($resultadosRecepciones), $topMovimientos) ?>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Despachos</h2>
                <p>Del <?= htmlspecialchars(sapFecha($desdeSap)) ?> al <?= htmlspecialchars(sapFecha($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaDespachos['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el listado de despachos.', $respuestaDespachos) ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Dirección de despacho</th>
                            <th class="sap-num">Líneas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosDespachos as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['docNum'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars($d['clienteNombre'] ?? $d['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($d['fecha'] ?? null)) ?><?= !empty($d['hora']) ? ' <span style="color:var(--muted);">' . htmlspecialchars(substr((string) $d['hora'], 0, 5)) . '</span>' : '' ?></td>
                                <td><?= sapTextoCorto($d['direccionDespacho'] ?? '') ?></td>
                                <td class="sap-num"><?= count($d['lineas'] ?? []) ?></td>
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
            <?= sapNotaTruncado(count($resultadosDespachos), $topMovimientos) ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<div class="table-card" style="margin-top:32px;">
    <div class="table-header">
        <div>
            <h2>Solicitudes de traslado abiertas</h2>
            <p>
                Abiertas en SAP en este momento, en <?= htmlspecialchars($empresa) ?> (no usa el filtro de fechas de arriba).
                <?= sapAyuda('Una solicitud sigue abierta hasta que alguien la cierra en SAP. Algunas pueden estar ya ejecutadas físicamente y no haberse cerrado.') ?>
            </p>
        </div>
    </div>

    <?php if (!$respuestaSolicitudesTraslado['ok']): ?>
        <?= sapErrorCard('No se pudo obtener el listado de solicitudes de traslado.', $respuestaSolicitudesTraslado) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table sap-tabla">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th class="sap-num">Días abierta</th>
                        <th>Origen → Destino</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosSolicitudesTraslado as $s): ?>
                        <?php $diasAbierta = sapDiasDesde($s['fecha'] ?? null); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['docNum'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars(sapFecha($s['fecha'] ?? null)) ?></td>
                            <td class="sap-num"><?= $diasAbierta !== null ? $diasAbierta : '-' ?></td>
                            <td><?= htmlspecialchars($s['almacenOrigen'] ?? '-') ?> → <?= htmlspecialchars($s['almacenDestino'] ?? '-') ?></td>
                            <td><?= sapTextoCorto($s['comentarios'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($resultadosSolicitudesTraslado) === 0): ?>
                        <tr>
                            <td colspan="5">No hay solicitudes de traslado abiertas en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= sapNotaTruncado(count($resultadosSolicitudesTraslado), $topPendientes) ?>
    <?php endif; ?>
</div>

<div class="table-card" style="margin-top:32px;">
    <div class="table-header">
        <div>
            <h2>Picking liberado</h2>
            <p>Listas de picking liberadas en SAP en <?= htmlspecialchars($empresa) ?>.</p>
        </div>
    </div>

    <?php if (!$respuestaPicking['ok']): ?>
        <?= sapErrorCard('No se pudo obtener el listado de picking.', $respuestaPicking) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table sap-tabla">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosPicking as $pk): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pk['absEntry'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars(sapFecha($pk['fecha'] ?? null)) ?></td>
                            <td><?= sapBadgeEstado($pk['estado'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($resultadosPicking) === 0): ?>
                        <tr>
                            <td colspan="3">No hay picking liberado en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= sapNotaTruncado(count($resultadosPicking), $topPendientes) ?>
    <?php endif; ?>
</div>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
