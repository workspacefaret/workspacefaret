<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_calidad');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

ob_start();

// recepcion/bobinas (RecepcionController.cs en apifaret) es el módulo QCC de Control de
// Recepción - Calidad, y solo existe para INNPACK y FARET (empresa!=esas dos => 400 en
// apifaret). No es un descuido: no hay dato de bobinas para PHARPACK/LENIAN.
const EMPRESAS_CALIDAD = ['INNPACK', 'FARET'];

$empresa = ApiFaretClient::empresaActual();
$empresaSoportada = in_array($empresa, EMPRESAS_CALIDAD, true);

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

$itemLote = trim($_GET['itemLote'] ?? '');
$fechaLote = trim($_GET['fechaLote'] ?? '');

$resultadosBobinas = [];
$respuestaBobinas = null;

if ($empresaSoportada && $rangoValido) {
    $respuestaBobinas = ApiFaretClient::get('recepcion/bobinas?desde=' . $desdeSap . '&hasta=' . $hastaSap, $empresa);

    if ($respuestaBobinas['ok']) {
        $resultadosBobinas = $respuestaBobinas['data']['data'] ?? [];
    }
}

$resultadosLotesBobina = [];
$respuestaLotesBobina = null;

if ($empresaSoportada && $itemLote !== '' && $fechaLote !== '') {
    $fechaLoteSap = aFechaSap($fechaLote);

    if ($fechaLoteSap !== null) {
        $respuestaLotesBobina = ApiFaretClient::get('recepcion/bobinas/lotes?itemCode=' . rawurlencode($itemLote) . '&fecha=' . $fechaLoteSap, $empresa);

        if ($respuestaLotesBobina['ok']) {
            $resultadosLotesBobina = $respuestaLotesBobina['data']['data'] ?? [];
        }
    }
}

?>

<div class="hero">
    <h1>Calidad SAP</h1>
    <p>Recepción de bobinas de materia prima (Control de Recepción - Calidad). Solo lectura.</p>
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

<?php if (!$empresaSoportada): ?>

    <div class="sap-guide">
        <span class="sap-guide-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <div>
            <h3>No disponible para <?= htmlspecialchars($empresa) ?></h3>
            <p>La recepción de bobinas (Control de Recepción - Calidad) solo existe para INNPACK y FARET. Cambia la empresa para consultar.</p>
        </div>
    </div>

<?php else: ?>

    <div class="sap-guide">
        <span class="sap-guide-icon"><i class="bi bi-info-circle"></i></span>
        <div>
            <h3>¿Qué muestra este filtro?</h3>
            <p>Recepciones de bobinas de materia prima por rango de fechas (por defecto, últimos 7 días). Desde cada fila puedes ver los lotes/bobinas creados ese mismo día para el artículo.</p>
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
                    <h2>Recepciones de bobinas</h2>
                    <p>Del <?= htmlspecialchars(sapFecha($desdeSap)) ?> al <?= htmlspecialchars(sapFecha($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?>.</p>
                </div>
            </div>

            <?php if (!$respuestaBobinas['ok']): ?>
                <?= sapErrorCard('No se pudo obtener la recepción de bobinas.', $respuestaBobinas) ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th>Guía</th>
                                <th>Ítem</th>
                                <th>Descripción</th>
                                <th class="sap-num">Cantidad</th>
                                <th class="sap-num">Ancho declarado</th>
                                <th class="sap-num">Gramaje declarado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultadosBobinas as $b): ?>
                                <tr>
                                    <td><?= htmlspecialchars(sapFecha($b['fechaRecepcion'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars($b['proveedor'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($b['guia'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($b['itemCode'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($b['descripcion'] ?? '-') ?></td>
                                    <td class="sap-num"><?= sapCantidad($b['cantidadRecibida'] ?? 0) ?></td>
                                    <td class="sap-num"><?= sapCantidad($b['anchoDeclarado'] ?? null) ?></td>
                                    <td class="sap-num"><?= sapCantidad($b['gramajeDeclarado'] ?? null) ?></td>
                                    <td>
                                        <a class="btn-secondary" href="?empresa=<?= rawurlencode($empresa) ?>&desde=<?= rawurlencode($desdeInput) ?>&hasta=<?= rawurlencode($hastaInput) ?>&itemLote=<?= rawurlencode($b['itemCode'] ?? '') ?>&fechaLote=<?= rawurlencode(date('Y-m-d', strtotime((string) ($b['fechaRecepcion'] ?? '')) ?: time())) ?>#lotesBobina" aria-label="Ver lotes de <?= htmlspecialchars($b['itemCode'] ?? '') ?>">
                                            Ver lotes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($resultadosBobinas) === 0): ?>
                                <tr>
                                    <td colspan="9">Sin recepciones de bobinas en el rango seleccionado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?= sapNotaTruncadoApi($respuestaBobinas, 'SAP entregó una lista limitada de recepciones. Acota el rango de fechas para verlas todas.') ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if ($itemLote !== '' && $fechaLote !== ''): ?>

        <div class="table-card" style="margin-top:32px;" id="lotesBobina">
            <div class="table-header">
                <div>
                    <h2>Lotes/bobinas de <?= htmlspecialchars($itemLote) ?> (<?= htmlspecialchars(sapFecha($fechaLote)) ?>)</h2>
                    <p>Trazabilidad aproximada: son lotes creados ese mismo día para este artículo, no un vínculo real de documento a lote (SAP no expone esa relación vía Service Layer).</p>
                </div>
            </div>

            <?php if ($respuestaLotesBobina === null): ?>
                <div class="card">
                    <h2>Fecha inválida</h2>
                    <p>No se pudo interpretar la fecha de la recepción.</p>
                </div>
            <?php elseif (!$respuestaLotesBobina['ok']): ?>
                <?= sapErrorCard('No se pudieron obtener los lotes.', $respuestaLotesBobina) ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>N° bobina</th>
                                <th>Fecha de creación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultadosLotesBobina as $l): ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['numeroBobina'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(sapFecha($l['fechaCreacion'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($resultadosLotesBobina) === 0): ?>
                                <tr>
                                    <td colspan="2">No se encontraron lotes creados ese día para este artículo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
