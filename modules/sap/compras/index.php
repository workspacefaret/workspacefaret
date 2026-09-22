<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_compras');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$docNum = trim($_GET['docNum'] ?? '');
$proveedor = trim($_GET['proveedor'] ?? '');
$docNumInvalido = $docNum !== '' && !ctype_digit($docNum);

function formatoFechaSap($fecha)
{
    if (!$fecha) {
        return '-';
    }

    $timestamp = strtotime($fecha);

    return $timestamp ? date('d-m-Y', $timestamp) : $fecha;
}

function badgeEstado($estado)
{
    $abierto = stripos((string) $estado, 'open') !== false;
    $clase = $abierto ? 'status-pending' : 'status-ok';

    return '<span class="status-badge ' . $clase . '">' . htmlspecialchars($estado ?: '-') . '</span>';
}

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

    $filtro = implode('&', $params) . '&top=30';

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

    <div class="filter-group">
        <label>N° de documento (DocNum)</label>
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
        <p>"<?= htmlspecialchars($docNum) ?>" no es un número. El DocNum debe ser numérico.</p>
    </div>

<?php elseif ($docNum !== '' || $proveedor !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Pedidos de compra</h2>
            </div>
        </div>

        <?php if (!$respuestaPedidos['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudieron buscar pedidos de compra. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaPedidos)) ?></p>
            </div>
        <?php elseif (count($resultadosPedidos) === 0): ?>
            <p>Sin pedidos de compra que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosPedidos as $p): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($p['docNum'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($p['proveedorNombre'] ?? $p['proveedorCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($p['fecha'] ?? null)) ?></td>
                                <td><?= badgeEstado($p['estado'] ?? null) ?></td>
                                <td>
                                    <a class="btn-secondary" href="?empresa=<?= rawurlencode($empresa) ?>&docNum=<?= rawurlencode($docNum) ?>&proveedor=<?= rawurlencode($proveedor) ?>&verDocEntry=<?= (int) ($p['docEntry'] ?? 0) ?>#lineasPedido">
                                        Ver líneas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($verDocEntry !== null): ?>

        <div class="table-card" style="margin-top:32px;" id="lineasPedido">
            <div class="table-header">
                <div>
                    <h2>Líneas del Pedido de compra #<?= htmlspecialchars($fichaPedido['docNum'] ?? $verDocEntry) ?></h2>
                    <p>Cantidad pendiente por línea (lo que aún falta por recibir).</p>
                </div>
            </div>

            <?php if (!$respuestaLineasPedido['ok']): ?>
                <div class="card">
                    <h2>Error de conexión con apifaret</h2>
                    <p>No se pudieron obtener las líneas. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaLineasPedido)) ?></p>
                </div>
            <?php elseif ($fichaPedido === null): ?>
                <div class="card">
                    <h2>No encontrado</h2>
                    <p>No se encontró el Pedido de compra #<?= (int) $verDocEntry ?> en <?= htmlspecialchars($empresa) ?>.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ítem</th>
                                <th>Descripción</th>
                                <th>Almacén</th>
                                <th>Cantidad</th>
                                <th>Pendiente</th>
                                <th>Estado línea</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineasPedido as $ln): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ln['itemCode'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($ln['descripcion'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($ln['almacen'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars((string) ($ln['cantidad'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($ln['cantidadPendiente'] ?? '-')) ?></td>
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
