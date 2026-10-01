<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_compras');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$docNum = trim($_GET['docNum'] ?? '');
$proveedor = trim($_GET['proveedor'] ?? '');
$docNumInvalido = $docNum !== '' && !ctype_digit($docNum);
$pagina = sapLeerPagina();
$porPagina = sapLeerPorPagina();

$resultadosPedidos = [];
$respuestaPedidos = null;

if (!$docNumInvalido && ($docNum !== '' || $proveedor !== '')) {
    $params = [];

    if ($docNum !== '') {
        $params[] = 'docNum=' . rawurlencode($docNum);
    }

    if ($proveedor !== '') {
        $params[] = 'proveedor=' . rawurlencode($proveedor);
    }

    $filtro = implode('&', $params) . '&' . sapQueryPagina($pagina, $porPagina);

    $respuestaPedidos = ApiFaretClient::get('compras/pedidos/buscar?' . $filtro, $empresa);

    if ($respuestaPedidos['ok']) {
        $resultadosPedidos = $respuestaPedidos['data']['data'] ?? [];
    }
}

$verDocEntry = null;

if (isset($_GET['verDocEntry']) && ctype_digit((string) $_GET['verDocEntry'])) {
    $verDocEntry = (int) $_GET['verDocEntry'];
}

$lineasPedido = [];
$fichaPedido = null;
$respuestaLineasPedido = null;

if ($verDocEntry !== null) {
    $respuestaLineasPedido = ApiFaretClient::get('compras/pedidos/' . $verDocEntry, $empresa);

    if ($respuestaLineasPedido['ok']) {
        $fichaPedido = $respuestaLineasPedido['data']['data'][0] ?? null;
        $lineasPedido = $fichaPedido['lineas'] ?? [];
    }
}

// Conserva filtros y página en los links internos de la página.
$paramsBusqueda = array_filter(['empresa' => $empresa, 'docNum' => $docNum, 'proveedor' => $proveedor], fn($v) => $v !== '')
    + sapParamsPaginacion(['pagina' => $pagina], $porPagina);

// Link a la ficha unificada (empresa + flujo documental), con "volver" apuntando a esta misma búsqueda.
$urlFicha = fn(string $tipoDoc, int $docEntry) => '/modules/sap/documento/?' . http_build_query([
    'empresa' => $empresa, 'tipo' => $tipoDoc, 'docEntry' => $docEntry, 'volver' => $_SERVER['REQUEST_URI'],
]);

?>

<div class="hero">
    <h1>Compras SAP</h1>
    <p>Pedidos de compra. Solo lectura.</p>
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

<?php if ($docNum === '' && $proveedor === ''): ?>
    <div class="sap-guide">
        <span class="sap-guide-icon"><i class="bi bi-info-circle"></i></span>
        <div>
            <h3>¿Qué necesitas para buscar?</h3>
            <p>SAP exige un N° de documento y/o el código exacto de un proveedor — no admite buscar por texto libre.</p>
            <p>¿No tienes el código del proveedor? Búscalo primero por nombre en <a href="/modules/sap/proveedores/?empresa=<?= rawurlencode($empresa) ?>">Proveedores</a> y desde su ficha vuelve aquí con un clic.</p>
        </div>
    </div>
<?php endif; ?>

<form class="filter-card" method="GET">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
    <?= sapInputPorPagina($porPagina) ?>

    <div class="filter-group">
        <label>N° de documento</label>
        <input type="text" name="docNum" maxlength="100" placeholder="Ej: 40522" value="<?= htmlspecialchars($docNum) ?>">
    </div>

    <div class="filter-group">
        <label>Proveedor (código exacto SAP)</label>
        <input type="text" name="proveedor" maxlength="100" placeholder="Ej: P0001" value="<?= htmlspecialchars($proveedor) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn-primary">
            <i class="bi bi-search"></i>
            Buscar
        </button>

        <?php if ($docNum !== '' || $proveedor !== ''): ?>
            <a href="?empresa=<?= rawurlencode($empresa) ?>" class="btn-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($docNumInvalido): ?>

    <div class="card">
        <h2>Número de documento inválido</h2>
        <p>"<?= htmlspecialchars($docNum) ?>" no es un número. El N° de documento debe ser numérico.</p>
    </div>

<?php elseif ($docNum !== '' || $proveedor !== ''): ?>

    <div class="table-card" id="pedidos">
        <div class="table-header">
            <div>
                <h2>Pedidos de compra</h2>
            </div>
        </div>

        <?php if (!$respuestaPedidos['ok']): ?>
            <?= sapErrorCard('No se pudieron buscar pedidos de compra.', $respuestaPedidos) ?>
        <?php elseif (count($resultadosPedidos) === 0): ?>
            <p>Sin pedidos de compra que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Proveedor</th>
                            <th>Ref. proveedor</th>
                            <th>Fecha</th>
                            <th>Entrega</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosPedidos as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($p['docNum'] ?? '-') ?></strong>
                                    <?php if (!empty($p['cancelado'])): ?> <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['proveedorNombre'] ?? $p['proveedorCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($p['referenciaProveedor'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($p['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(sapFecha($p['fechaEntrega'] ?? null)) ?></td>
                                <td><?= sapBadgeEstado($p['estado'] ?? '') ?></td>
                                <td>
                                    <a class="btn-secondary" href="?<?= htmlspecialchars(http_build_query($paramsBusqueda + ['verDocEntry' => (int) ($p['docEntry'] ?? 0)])) ?>#lineasPedido">
                                        Ver líneas
                                    </a>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('pedidocompra', (int) ($p['docEntry'] ?? 0))) ?>">
                                        Ficha y flujo
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaPedidos, $paramsBusqueda, 'pagina', 'pedidos') ?>
        <?php endif; ?>
    </div>

    <?php if ($verDocEntry !== null): ?>

        <div class="table-card" style="margin-top:32px;" id="lineasPedido">
            <div class="table-header">
                <div>
                    <h2>Líneas del pedido de compra N° <?= htmlspecialchars($fichaPedido['docNum'] ?? '') ?></h2>
                    <p>Pendiente: cantidad que según SAP aún falta recibir en cada línea.</p>
                    <?php if (!empty($fichaPedido['cancelado'])): ?>
                        <p><span class="badge badge-danger">Documento cancelado en SAP</span></p>
                    <?php endif; ?>
                    <?php if (!empty($fichaPedido['referenciaProveedor']) || !empty($fichaPedido['fechaActualizacion'])): ?>
                        <p class="sap-nota">
                            <?php if (!empty($fichaPedido['referenciaProveedor'])): ?>Ref. proveedor: <strong><?= htmlspecialchars($fichaPedido['referenciaProveedor']) ?></strong>. <?php endif; ?>
                            <?php if (!empty($fichaPedido['fechaActualizacion'])): ?>Actualizado en SAP: <?= htmlspecialchars(sapFecha($fichaPedido['fechaActualizacion'])) ?>.<?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$respuestaLineasPedido['ok']): ?>
                <?= sapErrorCard('No se pudieron obtener las líneas.', $respuestaLineasPedido) ?>
            <?php elseif ($fichaPedido === null): ?>
                <div class="card">
                    <h2>No encontrado</h2>
                    <p>No se encontró ese pedido de compra en <?= htmlspecialchars($empresa) ?>.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table sap-tabla">
                        <thead>
                            <tr>
                                <th>Artículo</th>
                                <th>Almacén</th>
                                <th class="sap-num">Cantidad</th>
                                <th class="sap-num">Pendiente</th>
                                <th>Entrega línea</th>
                                <th>Estado línea</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineasPedido as $ln): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($ln['itemCode'] ?? '-') ?></strong><br>
                                        <span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($ln['descripcion'] ?? '') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($ln['almacen'] ?? '-') ?></td>
                                    <td class="sap-num"><?= sapCantidad($ln['cantidad'] ?? null) ?> <?= htmlspecialchars($ln['unidad'] ?? '') ?></td>
                                    <td class="sap-num"><?= sapCantidad($ln['cantidadPendiente'] ?? null) ?></td>
                                    <td><?= htmlspecialchars(sapFecha($ln['fechaEntrega'] ?? null)) ?></td>
                                    <td>
                                        <span class="status-badge <?= empty($ln['cerrada']) ? 'status-pending' : 'status-ok' ?>">
                                            <?= empty($ln['cerrada']) ? 'Abierta' : 'Cerrada' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($lineasPedido) === 0): ?>
                                <tr>
                                    <td colspan="6">Sin líneas.</td>
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
