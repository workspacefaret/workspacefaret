<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$texto = trim($_GET['texto'] ?? '');
$item = trim($_GET['item'] ?? '');
$lote = trim($_GET['lote'] ?? '');

function formatoCantidad($n)
{
    if ($n === null || $n === '') {
        return '-';
    }

    $n = (float)$n;
    $decimales = floor($n) == $n ? 0 : 2;

    return number_format($n, $decimales, ',', '.');
}

function formatoFechaSap($fecha)
{
    if (!$fecha) {
        return '-';
    }

    $timestamp = strtotime($fecha);

    return $timestamp ? date('d-m-Y', $timestamp) : $fecha;
}

function erroresPorEmpresa($respuesta)
{
    if (!$respuesta['ok'] || !is_array($respuesta['data'])) {
        return [];
    }

    return $respuesta['data']['errors'] ?? [];
}

// Arma un link "?empresa=..&texto=..&item=..&lote=.." combinando los filtros ya
// activos con el nuevo, para que al navegar a un artículo/lote no se pierda de
// vista ni la búsqueda ni la empresa de donde se vino.
function urlInventario($empresa, $texto, $item, $lote, array $nuevo)
{
    $params = array_filter(
        $nuevo + ['empresa' => $empresa, 'texto' => $texto, 'item' => $item, 'lote' => $lote],
        fn($valor) => $valor !== ''
    );

    return '?' . http_build_query($params);
}

// Búsqueda de artículos
$articulos = [];
$respuestaBusqueda = null;

if ($texto !== '') {
    $respuestaBusqueda = ApiFaretClient::get('articulos/buscar?texto=' . rawurlencode($texto) . '&top=50', $empresa);

    if ($respuestaBusqueda['ok']) {
        $articulos = $respuestaBusqueda['data']['data'] ?? [];
    }
}

// Detalle de un artículo: ficha + stock + lotes
$fichas = [];
$stocks = [];
$lotesItem = [];
$respuestaFicha = null;
$respuestaStock = null;
$respuestaLotesItem = null;

if ($item !== '') {
    $respuestaFicha = ApiFaretClient::get('articulos/' . rawurlencode($item), $empresa);
    $respuestaStock = ApiFaretClient::get('articulos/' . rawurlencode($item) . '/stock', $empresa);
    // lotes/item no acepta "empresa" en apifaret (LotesController.cs): siempre consulta
    // y mezcla las 4 compañías configuradas, a diferencia de articulos/* de arriba.
    $respuestaLotesItem = ApiFaretClient::get('lotes/item/' . rawurlencode($item));

    if ($respuestaFicha['ok']) {
        $fichas = $respuestaFicha['data']['data'] ?? [];
    }

    if ($respuestaStock['ok']) {
        $stocks = $respuestaStock['data']['data'] ?? [];
    }

    if ($respuestaLotesItem['ok']) {
        $lotesItem = $respuestaLotesItem['data']['data'] ?? [];
    }
}

// Búsqueda de lote exacto
$resultadosLote = [];
$respuestaLote = null;

if ($lote !== '') {
    // lotes/{lote} tampoco acepta "empresa" (mismo motivo que lotes/item arriba).
    $respuestaLote = ApiFaretClient::get('lotes/' . rawurlencode($lote));

    if ($respuestaLote['ok']) {
        $resultadosLote = $respuestaLote['data']['data'] ?? [];
    }
}

// Antigüedad de inventario: solo cuando no hay una búsqueda puntual en curso,
// para no pedirle a SAP este listado en cada búsqueda de artículo/lote.
$sinFiltros = $texto === '' && $item === '' && $lote === '';
$respuestaAntiguedad = null;
$lotesAntiguos = [];
$totalAntiguos = 0;

if ($sinFiltros) {
    $respuestaAntiguedad = ApiFaretClient::get('inventario/antiguedad?diasMinimos=90&top=100', $empresa);

    if ($respuestaAntiguedad['ok']) {
        $lotesAntiguos = $respuestaAntiguedad['data']['data'] ?? [];
        $totalAntiguos = $respuestaAntiguedad['data']['total'] ?? count($lotesAntiguos);
    }
}

?>

<div class="hero">
    <h1>Inventario SAP</h1>
    <p>Artículos, stock por almacén y ubicación, lotes y antigüedad en bodega. Solo lectura.</p>
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
<script src="/assets/js/sap/autocomplete.js"></script>

<form class="sap-search" method="GET" id="buscarArticulo">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
    <span class="bi bi-search"></span>
    <input type="text" id="buscarArticuloTexto" name="texto" maxlength="100" placeholder="Buscar artículo por código o nombre..." value="<?= htmlspecialchars($texto) ?>" data-sap-autocomplete="articulos" data-sap-target="item">
    <button type="submit">Buscar</button>
</form>

<form class="sap-search" method="GET" id="buscarLote">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
    <span class="bi bi-upc-scan"></span>
    <input type="text" name="lote" maxlength="100" placeholder="Buscar lote exacto..." value="<?= htmlspecialchars($lote) ?>">
    <button type="submit">Buscar</button>
</form>

<?php if ($texto !== '' || $item !== '' || $lote !== ''): ?>
    <p style="margin:-14px 0 20px;">
        <a href="?empresa=<?= rawurlencode($empresa) ?>" class="btn-secondary">
            <i class="bi bi-x-lg"></i>
            Limpiar
        </a>
    </p>
<?php endif; ?>

<?php if ($texto !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Artículos para "<?= htmlspecialchars($texto) ?>"</h2>
                <p><?= count($articulos) ?> resultados en <?= htmlspecialchars($empresa) ?> (máximo 50).</p>
            </div>
        </div>

        <?php if (!$respuestaBusqueda['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo realizar la búsqueda de artículos. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusqueda)) ?></p>
            </div>
        <?php else: ?>

            <?php foreach (erroresPorEmpresa($respuestaBusqueda) as $err): ?>
                <p><strong><?= htmlspecialchars($err['empresa'] ?? '-') ?>:</strong> <?= htmlspecialchars($err['mensaje'] ?? 'Error') ?></p>
            <?php endforeach; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Grupo</th>
                            <th>Unidad</th>
                            <th>Lotes</th>
                            <th>Vigente</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articulos as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['empresa'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['itemCode'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['itemName'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['grupoNombre'] ?? $a['grupoCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['unidad'] ?? '-') ?></td>
                                <td><?= !empty($a['manejaLotes']) ? 'Sí' : 'No' ?></td>
                                <td>
                                    <span class="status-badge <?= !empty($a['vigente']) ? 'status-ok' : 'status-pending' ?>">
                                        <?= !empty($a['vigente']) ? 'Sí' : 'No' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars(urlInventario($empresa, $texto, $item, $lote, ['item' => $a['itemCode'] ?? ''])) ?>" aria-label="Ver stock de <?= htmlspecialchars($a['itemCode'] ?? '') ?>">Ver stock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($articulos) === 0): ?>
                            <tr>
                                <td colspan="8">No se encontraron artículos.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($item !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Artículo <?= htmlspecialchars($item) ?></h2>
                <p><?= htmlspecialchars($stocks[0]['itemName'] ?? $fichas[0]['itemName'] ?? '') ?></p>
            </div>
        </div>

        <?php if (!$respuestaFicha['ok'] && !$respuestaStock['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo consultar el artículo. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaFicha)) ?></p>
            </div>
        <?php else: ?>

            <?php if (count($fichas) === 0 && count($stocks) === 0): ?>
                <p>El artículo no existe en <?= htmlspecialchars($empresa) ?>.</p>
            <?php endif; ?>

            <?php if (count($fichas) > 0 && hasModuleAccess('portal_sap_precios')): ?>
                <p>
                    <a class="btn-secondary" href="/modules/sap/precios/?empresa=<?= rawurlencode($empresa) ?>&item=<?= rawurlencode($item) ?>">
                        <i class="bi bi-tag"></i>
                        Ver precios de este artículo
                    </a>
                </p>
            <?php endif; ?>

            <?php if (count($fichas) > 0): ?>
                <h3>Ficha</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Grupo</th>
                                <th>Unidad</th>
                                <th>Vigente</th>
                                <th>Lotes</th>
                                <th>Gramaje</th>
                                <th>Ancho</th>
                                <th>Categoría</th>
                                <th>Calibre</th>
                                <th>Perfil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fichas as $f): ?>
                                <?php $at = $f['atributosTecnicos'] ?? []; ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['empresa'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($f['grupoNombre'] ?? $f['grupoCodigo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($f['unidad'] ?? '-') ?></td>
                                    <td><?= !empty($f['vigente']) ? 'Sí' : 'No' ?></td>
                                    <td><?= !empty($f['manejaLotes']) ? 'Sí' : 'No' ?></td>
                                    <td><?= htmlspecialchars($at['gramaje'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($at['ancho'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($at['categoria'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($at['calibre'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($at['perfil'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php foreach ($stocks as $s): ?>
                <h3 style="margin-top:24px;">
                    Stock en <?= htmlspecialchars($s['empresa'] ?? '-') ?>:
                    <?= formatoCantidad($s['stockTotal'] ?? 0) ?>
                </h3>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Almacén</th>
                                <th>Disponible</th>
                                <th>Comprometido</th>
                                <th>Pedido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($s['porAlmacen'] ?? [] as $alm): ?>
                                <tr>
                                    <td><?= htmlspecialchars($alm['almacen'] ?? '-') ?></td>
                                    <td><?= formatoCantidad($alm['disponible'] ?? 0) ?></td>
                                    <td><?= formatoCantidad($alm['comprometido'] ?? 0) ?></td>
                                    <td><?= formatoCantidad($alm['pedido'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($s['porAlmacen'] ?? []) === 0): ?>
                                <tr>
                                    <td colspan="4">Sin stock en ningún almacén.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($s['porBin'] ?? []) > 0): ?>
                    <div class="table-responsive" style="margin-top:12px;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Almacén</th>
                                    <th>Ubicación (bin)</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($s['porBin'] as $bin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($bin['almacen'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($bin['bin'] ?? '-') ?></td>
                                        <td><?= formatoCantidad($bin['cantidad'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <h3 style="margin-top:24px;">Lotes del artículo (<?= count($lotesItem) ?>) — todas las compañías</h3>

            <?php if (!$respuestaLotesItem['ok']): ?>
                <p>No se pudieron consultar los lotes del artículo.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Lote</th>
                                <th>Stock</th>
                                <th>Unidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lotesItem as $l): ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['empresa'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($l['lote'] ?? '-') ?></td>
                                    <td><?= formatoCantidad($l['stock'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($l['unidad'] ?? '-') ?></td>
                                    <td>
                                        <a class="btn-secondary" href="<?= htmlspecialchars(urlInventario($empresa, $texto, $item, $lote, ['lote' => $l['lote'] ?? ''])) ?>" aria-label="Ver ubicación del lote <?= htmlspecialchars($l['lote'] ?? '') ?>">Ver ubicación</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($lotesItem) === 0): ?>
                                <tr>
                                    <td colspan="5">El artículo no tiene lotes registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (count($fichas) > 0): ?>
                <script>
                    window.SapAutocomplete && window.SapAutocomplete.registrarReciente(
                        'articulos',
                        <?= json_encode($fichas[0]['itemCode'] ?? $item) ?>,
                        <?= json_encode($fichas[0]['itemName'] ?? '') ?>,
                        <?= json_encode($fichas[0]['itemCode'] ?? $item) ?>
                    );
                </script>
            <?php endif; ?>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($lote !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Stock del lote "<?= htmlspecialchars($lote) ?>"</h2>
                <p>Resultado en todas las compañías (la búsqueda por lote no admite filtrar por empresa).</p>
            </div>
        </div>

        <?php if (!$respuestaLote['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo consultar el lote. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaLote)) ?></p>
            </div>
        <?php else: ?>

            <?php foreach (erroresPorEmpresa($respuestaLote) as $err): ?>
                <p><strong><?= htmlspecialchars($err['empresa'] ?? '-') ?>:</strong> <?= htmlspecialchars($err['mensaje'] ?? 'Error') ?></p>
            <?php endforeach; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Ítem</th>
                            <th>Descripción</th>
                            <th>Unidad</th>
                            <th>Stock</th>
                            <th>Ubicación</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosLote as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['empresa'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['itemCode'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['itemName'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['unidad'] ?? '-') ?></td>
                                <td><?= formatoCantidad($r['stock'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($r['ubicacion'] ?? '-') ?></td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars(urlInventario($empresa, $texto, $item, $lote, ['item' => $r['itemCode'] ?? ''])) ?>" aria-label="Ver artículo <?= htmlspecialchars($r['itemCode'] ?? '') ?>">Ver artículo</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosLote) === 0): ?>
                            <tr>
                                <td colspan="7">No se encontró el lote en <?= htmlspecialchars($empresa) ?>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if (!$sinFiltros): ?>

    <div class="card" id="antiguedad">
        <h2>Antigüedad de inventario</h2>
        <p>Se omite mientras hay una búsqueda de artículo o lote en curso. <a href="?empresa=<?= rawurlencode($empresa) ?>#antiguedad">Quitar filtros para verla</a>.</p>
    </div>

<?php else: ?>

<div class="kpi-grid" id="antiguedad">
    <div class="kpi-card">
        <span>Lotes con más de 90 días</span>
        <strong><?= $totalAntiguos ?></strong>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Antigüedad de inventario</h2>
            <p>Lotes del grupo "Terminados" con 90 días o más en bodega. La fecha de ingreso es la del primer ingreso histórico del lote en SAP y puede estar desfasada.</p>
        </div>
    </div>

    <?php if (!$respuestaAntiguedad['ok']): ?>
        <div class="card">
            <h2>Error de conexión con apifaret</h2>
            <p>No se pudo obtener la antigüedad de inventario. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaAntiguedad)) ?></p>
        </div>
    <?php else: ?>

        <?php foreach (erroresPorEmpresa($respuestaAntiguedad) as $err): ?>
            <p><strong><?= htmlspecialchars($err['empresa'] ?? '-') ?>:</strong> <?= htmlspecialchars($err['mensaje'] ?? 'Error') ?></p>
        <?php endforeach; ?>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Ítem</th>
                        <th>Descripción</th>
                        <th>Almacén</th>
                        <th>Ubicación</th>
                        <th>Lote</th>
                        <th>Cantidad</th>
                        <th>Ingreso</th>
                        <th>Días</th>
                        <th>Posible NV</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lotesAntiguos as $la): ?>
                        <tr>
                            <td><?= htmlspecialchars($la['empresa'] ?? '-') ?></td>
                            <td>
                                <a href="?item=<?= rawurlencode($la['itemCode'] ?? '') ?>"><?= htmlspecialchars($la['itemCode'] ?? '-') ?></a>
                            </td>
                            <td><?= htmlspecialchars($la['itemName'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($la['almacen'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($la['bin'] ?? '-') ?></td>
                            <td>
                                <a href="?lote=<?= rawurlencode($la['lote'] ?? '') ?>"><?= htmlspecialchars($la['lote'] ?? '-') ?></a>
                            </td>
                            <td><?= formatoCantidad($la['cantidad'] ?? 0) ?></td>
                            <td><?= htmlspecialchars(formatoFechaSap($la['fechaIngreso'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($la['diasEnBodega'] ?? '-') ?></td>
                            <td>
                                <?php
                                    $posibleNV = $la['posibleNotaVenta'] ?? '';
                                    // La fila trae el CompanyDB crudo de SAP (ej. "FARET_PRODUCCION"),
                                    // no el código normalizado que usa el resto del portal — mapeo
                                    // 1:1 confirmado contra appsettings.json de apisapfaret.
                                    $empresaFila = strtoupper(preg_replace('/_PRODUCCION$/', '', $la['empresa'] ?? ''));
                                    if (!in_array($empresaFila, ApiFaretClient::EMPRESAS_VALIDAS, true)) {
                                        $empresaFila = $empresa;
                                    }
                                ?>
                                <?php if ($posibleNV !== '' && ctype_digit($posibleNV)): ?>
                                    <a href="/modules/sap/ventas/?empresa=<?= rawurlencode($empresaFila) ?>&docNum=<?= rawurlencode($posibleNV) ?>" title="Buscar esta Nota de Venta en Ventas">
                                        <?= htmlspecialchars($posibleNV) ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($posibleNV !== '' ? $posibleNV : '-') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($lotesAntiguos) === 0): ?>
                        <tr>
                            <td colspan="10">No hay lotes con 90 días o más en bodega.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
