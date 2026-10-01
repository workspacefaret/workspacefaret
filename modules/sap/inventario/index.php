<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$texto = trim($_GET['texto'] ?? '');
$item = trim($_GET['item'] ?? '');
$lote = trim($_GET['lote'] ?? '');
$verVentas = hasModuleAccess('portal_sap_ventas');

// "pagina" la usan la búsqueda de artículos y la antigüedad, que nunca se muestran
// juntas (la antigüedad solo aparece sin búsqueda en curso).
$pagina = sapLeerPagina();
$porPagina = sapLeerPorPagina();
$paramsPagina = sapParamsPaginacion(['pagina' => $pagina], $porPagina);
$paramsEstado = array_filter(['empresa' => $empresa, 'texto' => $texto, 'item' => $item, 'lote' => $lote], fn($valor) => $valor !== '') + $paramsPagina;

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
    $respuestaBusqueda = ApiFaretClient::get('articulos/buscar?texto=' . rawurlencode($texto) . '&' . sapQueryPagina($pagina, $porPagina), $empresa);

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

    // apifaret trae todos los lotes del artículo (sin paginación en este endpoint);
    // solo avisa con "truncado" si llegó a su límite interno.
    $resultadoLotesItem = sapResultado($respuestaLotesItem, true);
    $lotesItem = $resultadoLotesItem['filas'];
}

// Búsqueda de lote exacto
$resultadosLote = [];
$respuestaLote = null;

if ($lote !== '') {
    // lotes/{lote} tampoco acepta "empresa" (mismo motivo que lotes/item arriba).
    $respuestaLote = ApiFaretClient::get('lotes/' . rawurlencode($lote));
    $resultadoLote = sapResultado($respuestaLote, true);
    $resultadosLote = $resultadoLote['filas'];
}

// A17: trazabilidad comprobable del lote — exige los 3 (empresa + artículo + lote), porque el
// número de lote no es único entre artículos. Se activa cuando item y lote vienen juntos en la
// URL (ya pasa al usar "Ver ubicación"/"Ver artículo" de abajo, o el link de Antigüedad).
$trazabilidad = null;
$respuestaTrazabilidad = null;

if ($item !== '' && $lote !== '') {
    $respuestaTrazabilidad = ApiFaretClient::get('lotes/' . rawurlencode($lote) . '/trazabilidad?empresa=' . rawurlencode($empresa) . '&articulo=' . rawurlencode($item), $empresa);

    if ($respuestaTrazabilidad['ok']) {
        $trazabilidad = $respuestaTrazabilidad['data']['data'][0] ?? null;
    }
}

// Antigüedad de inventario: solo cuando no hay una búsqueda puntual en curso,
// para no pedirle a SAP este listado en cada búsqueda de artículo/lote.
$sinFiltros = $texto === '' && $item === '' && $lote === '';
$respuestaAntiguedad = null;
$lotesAntiguos = [];
$totalAntiguos = null;
$lotesSinFecha = 0;
$diasAntiguedad = 90;

if ($sinFiltros) {
    // El filtro de días lo aplica apifaret sobre todos los lotes del grupo (más
    // antiguos primero) y "totalDisponible" es el total real con ese filtro.
    $respuestaAntiguedad = ApiFaretClient::get('inventario/antiguedad?diasMinimos=' . $diasAntiguedad . '&' . sapQueryPagina($pagina, $porPagina), $empresa);

    if ($respuestaAntiguedad['ok']) {
        $lotesAntiguos = $respuestaAntiguedad['data']['data'] ?? [];
        $totalAntiguos = sapTotalDisponible($respuestaAntiguedad);
        // apifaret incluye (al final de la lista) los lotes sin fecha de ingreso legible.
        $lotesSinFecha = count(array_filter($lotesAntiguos, fn($la) => ($la['diasEnBodega'] ?? null) === null));
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
    <?= sapInputPorPagina($porPagina) ?>
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
                <p>En <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if (!$respuestaBusqueda['ok']): ?>
            <?= sapErrorCard('No se pudo realizar la búsqueda de artículos.', $respuestaBusqueda) ?>
        <?php else: ?>

            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Grupo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articulos as $a): ?>
                            <?php $urlArticulo = urlInventario($empresa, $texto, $item, $lote, ['item' => $a['itemCode'] ?? ''] + $paramsPagina); ?>
                            <tr>
                                <td><a href="<?= htmlspecialchars($urlArticulo) ?>"><strong><?= htmlspecialchars($a['itemCode'] ?? '-') ?></strong></a></td>
                                <td><?= htmlspecialchars($a['itemName'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($a['grupoNombre'] ?? $a['grupoCodigo'] ?? '-') ?></td>
                                <td>
                                    <span class="status-badge <?= !empty($a['vigente']) ? 'status-ok' : 'status-pending' ?>">
                                        <?= !empty($a['vigente']) ? 'Vigente' : 'No vigente' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlArticulo) ?>" aria-label="Ver stock de <?= htmlspecialchars($a['itemCode'] ?? '') ?>">Ver stock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($articulos) === 0): ?>
                            <tr>
                                <td colspan="5">No se encontraron artículos para "<?= htmlspecialchars($texto) ?>" en <?= htmlspecialchars($empresa) ?>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?= sapPaginador($respuestaBusqueda, $paramsEstado, 'pagina') ?>

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
            <?= sapErrorCard('No se pudo consultar el artículo.', $respuestaFicha) ?>
        <?php else: ?>

            <?php if (!$respuestaFicha['ok']): ?>
                <?= sapErrorCard('No se pudo consultar la ficha del artículo. El stock sí está disponible.', $respuestaFicha) ?>
            <?php elseif (!$respuestaStock['ok']): ?>
                <?= sapErrorCard('No se pudo consultar el stock del artículo. La ficha sí está disponible.', $respuestaStock) ?>
            <?php endif; ?>

            <?php if ($respuestaFicha['ok'] && $respuestaStock['ok'] && count($fichas) === 0 && count($stocks) === 0): ?>
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
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>Unidad</th>
                                <th>Estado</th>
                                <th>Maneja lotes</th>
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
                                    <td><?= htmlspecialchars($f['grupoNombre'] ?? $f['grupoCodigo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($f['unidad'] ?? '-') ?></td>
                                    <td><?= !empty($f['vigente']) ? 'Vigente' : 'No vigente' ?></td>
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
                <?php
                    // En apifaret "disponible" es el stock físico (InStock de SAP), no lo libre:
                    // Libre = En stock − Comprometido. "Pedido" = cantidad en pedidos por recibir.
                    $almacenes = $s['porAlmacen'] ?? [];
                    $sumaEnStock = array_sum(array_column($almacenes, 'disponible'));
                    $sumaComprometido = array_sum(array_column($almacenes, 'comprometido'));
                    $sumaPedido = array_sum(array_column($almacenes, 'pedido'));
                ?>
                <div class="sap-kpi-grid" style="margin-top:24px;">
                    <div class="sap-kpi-card">
                        <span class="sap-kpi-body">
                            <span>En stock <?= sapAyuda('Cantidad física en bodega en ' . sapEmpresaEtiqueta($s['empresa'] ?? $empresa) . '.') ?></span>
                            <strong><?= sapCantidad($sumaEnStock) ?></strong>
                        </span>
                    </div>
                    <div class="sap-kpi-card">
                        <span class="sap-kpi-body">
                            <span>Comprometido <?= sapAyuda('Reservado por notas de venta abiertas. SAP no indica aquí cuáles son.') ?></span>
                            <strong><?= sapCantidad($sumaComprometido) ?></strong>
                        </span>
                    </div>
                    <div class="sap-kpi-card">
                        <span class="sap-kpi-body">
                            <span>Libre <?= sapAyuda('En stock menos comprometido.') ?></span>
                            <strong><?= sapCantidad($sumaEnStock - $sumaComprometido) ?></strong>
                        </span>
                    </div>
                    <div class="sap-kpi-card">
                        <span class="sap-kpi-body">
                            <span>En pedido <?= sapAyuda('Cantidad en pedidos aún por recibir.') ?></span>
                            <strong><?= sapCantidad($sumaPedido) ?></strong>
                        </span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>Almacén</th>
                                <th class="sap-num">En stock</th>
                                <th class="sap-num">Comprometido</th>
                                <th class="sap-num">Libre</th>
                                <th class="sap-num">En pedido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($almacenes as $alm): ?>
                                <tr>
                                    <td><?= htmlspecialchars($alm['almacen'] ?? '-') ?></td>
                                    <td class="sap-num"><?= sapCantidad($alm['disponible'] ?? 0) ?></td>
                                    <td class="sap-num"><?= sapCantidad($alm['comprometido'] ?? 0) ?></td>
                                    <td class="sap-num"><?= sapCantidad(($alm['disponible'] ?? 0) - ($alm['comprometido'] ?? 0)) ?></td>
                                    <td class="sap-num"><?= sapCantidad($alm['pedido'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($almacenes) === 0): ?>
                                <tr>
                                    <td colspan="5">Sin stock ni movimientos pendientes en ningún almacén de <?= htmlspecialchars($empresa) ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($s['porBin'] ?? []) > 0): ?>
                    <div class="table-responsive" style="margin-top:12px;">
                        <table class="data-table sap-tabla">
                            <thead>
                                <tr>
                                    <th>Almacén</th>
                                    <th>Ubicación</th>
                                    <th class="sap-num">En stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($s['porBin'] as $bin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($bin['almacen'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($bin['bin'] ?? '-') ?></td>
                                        <td class="sap-num"><?= sapCantidad($bin['cantidad'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <h3 style="margin-top:24px;">Lotes del artículo — todas las empresas</h3>

            <?php if ($resultadoLotesItem['estado'] === 'error'): ?>
                <?= sapErrorCard('No se pudieron consultar los lotes del artículo.', $respuestaLotesItem) ?>
            <?php else: ?>
                <?= sapAvisoParcial($resultadoLotesItem['empresasFallidas']) ?>
                <div class="table-responsive">
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>Lote</th>
                                <th>Empresa</th>
                                <th class="sap-num">En stock</th>
                                <th>Unidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lotesItem as $l): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($l['lote'] ?? '-') ?></strong></td>
                                    <td><span class="sap-list-chip"><?= htmlspecialchars(sapEmpresaEtiqueta($l['empresa'] ?? '')) ?></span></td>
                                    <td class="sap-num"><?= sapCantidad($l['stock'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($l['unidad'] ?? '-') ?></td>
                                    <td>
                                        <a class="btn-secondary" href="<?= htmlspecialchars(urlInventario(sapEmpresa($l['empresa'] ?? '', $empresa), $texto, $item, $lote, ['lote' => $l['lote'] ?? ''])) ?>" aria-label="Ver trazabilidad del lote <?= htmlspecialchars($l['lote'] ?? '') ?>">Ver ubicación y trazabilidad</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($lotesItem) === 0): ?>
                                <tr>
                                    <td colspan="5">No se encontraron lotes registrados para este artículo<?= count($resultadoLotesItem['empresasFallidas']) > 0 ? ' en las empresas consultadas' : '' ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?= sapNotaTruncadoApi($respuestaLotesItem, 'SAP entregó una lista limitada de lotes para este artículo. Puede haber más lotes en SAP.') ?>
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
                <p>Se busca en todas las empresas.</p>
            </div>
        </div>

        <?php if ($resultadoLote['estado'] === 'error'): ?>
            <?= sapErrorCard('No se pudo consultar el lote.', $respuestaLote) ?>
        <?php else: ?>

            <?= sapAvisoParcial($resultadoLote['empresasFallidas']) ?>

            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th>Empresa</th>
                            <th class="sap-num">En stock</th>
                            <th>Ubicación</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosLote as $r): ?>
                            <?php
                                // El artículo se abre en la empresa donde está el lote, no en la seleccionada.
                                $empresaLote = sapEmpresa($r['empresa'] ?? '', $empresa);
                                $urlArticuloLote = urlInventario($empresaLote, $texto, $item, $lote, ['item' => $r['itemCode'] ?? '']);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= htmlspecialchars($urlArticuloLote) ?>"><strong><?= htmlspecialchars($r['itemCode'] ?? '-') ?></strong></a><br>
                                    <span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($r['itemName'] ?? '-') ?></span>
                                </td>
                                <td><span class="sap-list-chip"><?= htmlspecialchars(sapEmpresaEtiqueta($r['empresa'] ?? '')) ?></span></td>
                                <td class="sap-num"><?= sapCantidad($r['stock'] ?? 0) ?> <?= htmlspecialchars($r['unidad'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['ubicacion'] ?? '-') ?></td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlArticuloLote) ?>" aria-label="Ver artículo <?= htmlspecialchars($r['itemCode'] ?? '') ?>">Ver artículo</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosLote) === 0): ?>
                            <tr>
                                <td colspan="5">No se encontró el lote "<?= htmlspecialchars($lote) ?>"<?= count($resultadoLote['empresasFallidas']) > 0 ? ' en las empresas consultadas' : ' en ninguna empresa' ?>. Revisa que el código esté completo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($item !== '' && $lote !== ''): ?>

    <div class="table-card" id="trazabilidad">
        <div class="table-header">
            <div>
                <h2>Trazabilidad del lote <?= htmlspecialchars($lote) ?></h2>
                <p>Artículo <?= htmlspecialchars($item) ?> en <?= htmlspecialchars($empresa) ?>. Solo lo que SAP demuestra — no es el historial completo del lote.</p>
            </div>
        </div>

        <?php if (!$respuestaTrazabilidad['ok']): ?>
            <?= sapErrorCard('No se pudo obtener la trazabilidad del lote.', $respuestaTrazabilidad) ?>
        <?php elseif ($trazabilidad === null): ?>
            <p>No se encontró este lote para este artículo en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <?php $maestro = $trazabilidad['maestro'] ?? []; ?>
            <?php $stockLote = $trazabilidad['stock'] ?? []; ?>
            <?php $origen = $trazabilidad['origen'] ?? []; ?>
            <?php $nvLote = $trazabilidad['notaVenta'] ?? []; ?>
            <?php
                // El origen de un lote puede resolver a un tipo que A14/W2 no soporta como ficha
                // propia (ej. "entradamercaderia" = entrada directa de mercadería, sin documento de
                // compra asociado) — mismos 14 tipos navegables que SAP_DOC_TIPOS en documento/index.php.
                $tiposNavegables = ['notaventa', 'cotizacion', 'factura', 'notacredito', 'devolucion', 'pedidocompra', 'facturaproveedor', 'notacreditoproveedor', 'devolucionproveedor', 'despacho', 'recepcion', 'traslado', 'solicitudtraslado', 'picking'];
                $urlDocumentoTraza = fn(array $doc) => '/modules/sap/documento/?' . http_build_query(['empresa' => sapEmpresa($doc['empresa'] ?? $empresa, $empresa), 'tipo' => $doc['tipo'], 'docEntry' => $doc['docEntry'] ?? 0, 'volver' => $_SERVER['REQUEST_URI']]);
            ?>

            <h3>Maestro</h3>
            <p class="sap-nota">
                <?php if (!empty($maestro['estado'])): ?>Estado: <?= sapBadgeEstado($maestro['estado']) ?>. <?php endif; ?>
                <?php if (!empty($maestro['fechaFabricacion'])): ?>Fabricación: <?= htmlspecialchars(sapFecha($maestro['fechaFabricacion'])) ?>. <?php endif; ?>
                <?php if (!empty($maestro['fechaIngreso'])): ?>Ingreso: <?= htmlspecialchars(sapFecha($maestro['fechaIngreso'])) ?>. <?php endif; ?>
                <?php if (!empty($maestro['fechaVencimiento'])): ?>Vencimiento: <?= htmlspecialchars(sapFecha($maestro['fechaVencimiento'])) ?>. <?php endif; ?>
                <?php if (!empty($maestro['folioCC'])): ?>Folio Cert. Calidad (dato técnico, sin validar contra QCS): <?= htmlspecialchars((string) $maestro['folioCC']) ?>.<?php endif; ?>
            </p>

            <h3>Stock actual</h3>
            <p>
                <strong><?= sapCantidad($stockLote['total'] ?? 0) ?></strong> <?= htmlspecialchars($stockLote['unidad'] ?? '') ?> en total.
            </p>
            <?php if (count($stockLote['bins'] ?? []) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>Almacén</th>
                                <th>Ubicación</th>
                                <th class="sap-num">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockLote['bins'] as $bin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($bin['almacen'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($bin['binCodigo'] ?? '-') ?></td>
                                    <td class="sap-num"><?= sapCantidad($bin['cantidad'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 style="margin-top:24px;">Origen del lote</h3>
            <?php
                $origenEstado = $origen['estado'] ?? 'no_resuelto';
                $origenClase = ['confirmado' => 'badge-success', 'ambiguo' => 'badge-warning'][$origenEstado] ?? '';
                $origenTexto = ['confirmado' => 'Confirmado', 'ambiguo' => 'Ambiguo', 'no_resuelto' => 'No resuelto'][$origenEstado] ?? $origenEstado;
            ?>
            <p>
                <span class="badge <?= htmlspecialchars($origenClase) ?>"><?= htmlspecialchars($origenTexto) ?></span>
                <?php if (!empty($origen['motivo'])): ?> (<?= htmlspecialchars($origen['motivo']) ?>)<?php endif; ?>
            </p>
            <?php if (!empty($origen['documento']['resuelto']) && in_array($origen['documento']['tipo'] ?? '', $tiposNavegables, true)): ?>
                <p>
                    <a class="btn-secondary" href="<?= htmlspecialchars($urlDocumentoTraza($origen['documento'])) ?>">
                        Ver documento de origen <?= htmlspecialchars((string) ($origen['documento']['docNum'] ?? $origen['documento']['docEntry'] ?? '')) ?>
                    </a>
                </p>
            <?php elseif (!empty($origen['documento'])): ?>
                <p class="sap-nota">
                    <i class="bi bi-info-circle"></i>
                    Documento N° <?= htmlspecialchars((string) ($origen['documento']['docNum'] ?? $origen['documento']['docEntry'] ?? '-')) ?> (objeto SAP <?= htmlspecialchars($origen['documento']['objetoSap'] ?? '-') ?>) — sin ficha disponible en Portal SAP para este tipo de documento.
                </p>
            <?php endif; ?>
            <?php if ($origenEstado === 'ambiguo' && count($origen['confirmados'] ?? []) > 0): ?>
                <p class="sap-nota">Más de un documento contiene este lote, no se puede determinar cuál es el origen real:</p>
                <?php foreach ($origen['confirmados'] as $doc): ?>
                    <?php if (in_array($doc['tipo'] ?? '', $tiposNavegables, true)): ?>
                        <a class="sap-list-row" href="<?= htmlspecialchars($urlDocumentoTraza($doc)) ?>">
                            <span class="sap-list-icon"><i class="bi bi-file-text"></i></span>
                            <span class="sap-list-main">
                                <span class="sap-list-title">N° <?= htmlspecialchars((string) ($doc['docNum'] ?? $doc['docEntry'] ?? '-')) ?></span>
                            </span>
                            <i class="bi bi-chevron-right sap-list-chevron"></i>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <h3 style="margin-top:24px;">Nota de venta asociada</h3>
            <?php
                $nvEstado = $nvLote['estado'] ?? 'sin_referencia';
                $nvClase = ['comprobada' => 'badge-success', 'no_comprobada' => 'badge-warning'][$nvEstado] ?? '';
                $nvTexto = ['comprobada' => 'Comprobada', 'no_comprobada' => 'No comprobada', 'sin_referencia' => 'Sin referencia'][$nvEstado] ?? $nvEstado;
            ?>
            <p>
                <span class="badge <?= htmlspecialchars($nvClase) ?>"><?= htmlspecialchars($nvTexto) ?></span>
                <?php if (!empty($nvLote['valor'])): ?> — referencia registrada en el lote: <?= htmlspecialchars($nvLote['valor']) ?><?php endif; ?>
            </p>
            <?php if (!empty($nvLote['documento']['resuelto']) && in_array($nvLote['documento']['tipo'] ?? '', $tiposNavegables, true)): ?>
                <p>
                    <a class="btn-secondary" href="<?= htmlspecialchars($urlDocumentoTraza($nvLote['documento'])) ?>">
                        Ver nota de venta <?= htmlspecialchars((string) ($nvLote['documento']['docNum'] ?? $nvLote['documento']['docEntry'] ?? '')) ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if (!empty($trazabilidad['datosComercialesOmitidos']) && count($trazabilidad['datosComercialesOmitidos']) > 0): ?>
                <p class="sap-nota" style="margin-top:24px;"><i class="bi bi-info-circle"></i> Algunos datos comerciales de este lote no se muestran: tu acceso no incluye el permiso necesario para esa compañía (<?= htmlspecialchars(implode(', ', $trazabilidad['datosComercialesOmitidos'])) ?>).</p>
            <?php endif; ?>

            <?php if (count($trazabilidad['relacionesNoDisponibles'] ?? []) > 0): ?>
                <?php foreach ($trazabilidad['relacionesNoDisponibles'] as $texto): ?>
                    <p class="sap-nota" style="margin-top:8px;"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($texto) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
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
        <span>Lotes con ingreso inicial hace <?= $diasAntiguedad ?> días o más</span>
        <?php if ($totalAntiguos !== null): ?>
            <strong><?= sapNumero($totalAntiguos) ?></strong>
        <?php else: ?>
            <strong>—</strong>
        <?php endif; ?>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Antigüedad de inventario</h2>
            <p>
                Lotes de productos terminados en <?= htmlspecialchars($empresa) ?> cuyo ingreso inicial fue hace <?= $diasAntiguedad ?> días o más.
                <?= sapAyuda('La fecha es la del primer ingreso conocido del lote en SAP. No indica cuánto tiempo lleva sin moverse: el lote pudo tener movimientos después.') ?>
            </p>
        </div>
    </div>

    <?php if (!$respuestaAntiguedad['ok']): ?>
        <?= sapErrorCard('No se pudo obtener la antigüedad de inventario.', $respuestaAntiguedad) ?>
    <?php else: ?>

        <div class="table-responsive">
            <table class="data-table sap-tabla">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th>Lote</th>
                        <th>Ubicación</th>
                        <th class="sap-num">Cantidad</th>
                        <th>Ingreso inicial</th>
                        <th class="sap-num">Días desde ingreso inicial</th>
                        <th>NV sugerida <?= sapAyuda('Referencia registrada manualmente en el lote. No está validada contra la nota de venta y puede estar desactualizada.') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lotesAntiguos as $la): ?>
                        <?php $empresaFila = sapEmpresa($la['empresa'] ?? '', $empresa); ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars(urlInventario($empresaFila, '', '', '', ['item' => $la['itemCode'] ?? ''])) ?>"><strong><?= htmlspecialchars($la['itemCode'] ?? '-') ?></strong></a><br>
                                <span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($la['itemName'] ?? '-') ?></span>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars(urlInventario($empresaFila, '', '', '', ['lote' => $la['lote'] ?? '', 'item' => $la['itemCode'] ?? ''])) ?>"><?= htmlspecialchars($la['lote'] ?? '-') ?></a>
                            </td>
                            <td><?= htmlspecialchars($la['almacen'] ?? '-') ?><br><span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($la['bin'] ?? '') ?></span></td>
                            <td class="sap-num"><?= sapCantidad($la['cantidad'] ?? 0) ?></td>
                            <td><?= htmlspecialchars(sapFecha($la['fechaIngreso'] ?? null)) ?></td>
                            <td class="sap-num"><?= htmlspecialchars((string) ($la['diasEnBodega'] ?? '-')) ?></td>
                            <td>
                                <?php $posibleNV = trim((string) ($la['posibleNotaVenta'] ?? '')); ?>
                                <?php if ($posibleNV === ''): ?>
                                    -
                                <?php else: ?>
                                    <?= htmlspecialchars($posibleNV) ?>
                                    <span class="badge badge-warning" title="Referencia no validada: puede no corresponder a una nota de venta vigente.">No validada</span>
                                    <?php if ($verVentas && ctype_digit($posibleNV)): ?>
                                        <br>
                                        <a href="/modules/sap/ventas/?empresa=<?= rawurlencode($empresaFila) ?>&docNum=<?= rawurlencode($posibleNV) ?>&tipo=nv" style="font-size:13px;" title="Busca notas de venta con este número. Confirma que corresponda al lote antes de usarla.">Buscar NV con este N°</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($lotesAntiguos) === 0): ?>
                        <tr>
                            <td colspan="7">No hay lotes de productos terminados con ingreso inicial hace <?= $diasAntiguedad ?> días o más en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?= sapPaginador($respuestaAntiguedad, $paramsEstado, 'pagina', 'antiguedad') ?>
        <?php if ($lotesSinFecha > 0): ?>
            <p class="sap-nota"><i class="bi bi-info-circle"></i> <?= $lotesSinFecha ?> lote(s) de esta página no tienen fecha de ingreso legible en SAP (se listan al final y se incluyen en el total).</p>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
