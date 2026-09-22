<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$almacen = trim($_GET['almacen'] ?? '');
$item = trim($_GET['item'] ?? '');

function formatoCantidad($n)
{
    if ($n === null || $n === '') {
        return '-';
    }

    $n = (float)$n;
    $decimales = floor($n) == $n ? 0 : 2;

    return number_format($n, $decimales, ',', '.');
}

// Arma un link "?empresa=..&almacen=..&item=.." combinando los filtros ya
// activos con el nuevo, mismo patrón que urlInventario() en inventario/index.php.
function urlAlmacenes($empresa, $almacen, $item, array $nuevo)
{
    $params = array_filter(
        $nuevo + ['empresa' => $empresa, 'almacen' => $almacen, 'item' => $item],
        fn($valor) => $valor !== ''
    );

    return '?' . http_build_query($params);
}

$almacenes = [];
$respuestaAlmacenes = ApiFaretClient::get('almacenes', $empresa);

if ($respuestaAlmacenes['ok']) {
    $almacenes = $respuestaAlmacenes['data']['data'] ?? [];
}

$stockAlmacen = [];
$respuestaStock = null;

if ($almacen !== '') {
    $endpointStock = 'almacenes/' . rawurlencode($almacen) . '/stock';

    if ($item !== '') {
        $endpointStock .= '?item=' . rawurlencode($item);
    }

    $respuestaStock = ApiFaretClient::get($endpointStock, $empresa);

    if ($respuestaStock['ok']) {
        $stockAlmacen = $respuestaStock['data']['data'] ?? [];
    }
}

?>

<div class="hero">
    <h1>Almacenes SAP</h1>
    <p>Catálogo de almacenes y mapa de stock por ubicación (bin). Solo lectura.</p>
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

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Almacenes en <?= htmlspecialchars($empresa) ?></h2>
            <p><?= count($almacenes) ?> almacenes activos.</p>
        </div>
    </div>

    <?php if (!$respuestaAlmacenes['ok']): ?>
        <div class="card">
            <h2>Error de conexión con apifaret</h2>
            <p>No se pudo obtener el catálogo de almacenes. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaAlmacenes)) ?></p>
        </div>
    <?php elseif (count($almacenes) === 0): ?>
        <p style="color:var(--muted);">No hay almacenes activos en <?= htmlspecialchars($empresa) ?>.</p>
    <?php else: ?>

        <?php foreach ($almacenes as $a): ?>
            <a class="sap-list-row" href="<?= htmlspecialchars(urlAlmacenes($empresa, $a['codigo'] ?? '', '', [])) ?>" aria-label="Ver stock de <?= htmlspecialchars($a['codigo'] ?? '') ?>">
                <span class="sap-list-icon"><i class="bi bi-building"></i></span>
                <span class="sap-list-main">
                    <span class="sap-list-title"><?= htmlspecialchars($a['nombre'] ?? '-') ?></span><br>
                    <span class="sap-list-sub"><?= htmlspecialchars($a['codigo'] ?? '-') ?><?= !empty($a['usaUbicaciones']) ? ' · usa ubicaciones' : '' ?></span>
                </span>
                <i class="bi bi-chevron-right sap-list-chevron"></i>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($almacen !== ''): ?>

    <form class="filter-card" method="GET">
        <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
        <input type="hidden" name="almacen" value="<?= htmlspecialchars($almacen) ?>">

        <div class="filter-group">
            <label>Filtrar por artículo (opcional)</label>
            <input type="text" name="item" maxlength="100" placeholder="Código de artículo" value="<?= htmlspecialchars($item) ?>">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-funnel"></i>
                Filtrar
            </button>

            <?php if ($item !== ''): ?>
                <a href="<?= htmlspecialchars(urlAlmacenes($empresa, $almacen, '', [])) ?>" class="btn-secondary">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Stock en <?= htmlspecialchars($almacen) ?></h2>
                <p><?= count($stockAlmacen) ?> filas (máximo 300).</p>
            </div>
        </div>

        <?php if (!$respuestaStock['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo obtener el stock del almacén. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaStock)) ?></p>
            </div>
        <?php else: ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ubicación (bin)</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Lote</th>
                            <th>Cantidad</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockAlmacen as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['bin'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['itemCode'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['itemName'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['lote'] ?? '-') ?></td>
                                <td><?= formatoCantidad($s['cantidad'] ?? 0) ?></td>
                                <td>
                                    <a class="btn-secondary" href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>&item=<?= rawurlencode($s['itemCode'] ?? '') ?>" aria-label="Ver artículo <?= htmlspecialchars($s['itemCode'] ?? '') ?>">Ver artículo</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($stockAlmacen) === 0): ?>
                            <tr>
                                <td colspan="6">Sin stock registrado.</td>
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
