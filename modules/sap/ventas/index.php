<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_ventas');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$docNum = trim($_GET['docNum'] ?? '');
$cliente = trim($_GET['cliente'] ?? '');
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

$resultadosNV = [];
$resultadosCotizaciones = [];
$resultadosFacturas = [];
$respuestaNV = null;
$respuestaCotizaciones = null;
$respuestaFacturas = null;

if (!$docNumInvalido && ($docNum !== '' || $cliente !== '')) {
    $params = [];

    if ($docNum !== '') {
        $params[] = 'docNum=' . rawurlencode($docNum);
    }

    if ($cliente !== '') {
        $params[] = 'cliente=' . rawurlencode($cliente);
    }

    $filtro = implode('&', $params) . '&top=30';

    $respuestaNV = ApiFaretClient::get('ventas/notaventa/buscar?' . $filtro, $empresa);

    if ($respuestaNV['ok']) {
        $resultadosNV = $respuestaNV['data']['data'] ?? [];
    }

    $respuestaCotizaciones = ApiFaretClient::get('ventas/cotizaciones/buscar?' . $filtro, $empresa);

    if ($respuestaCotizaciones['ok']) {
        $resultadosCotizaciones = $respuestaCotizaciones['data']['data'] ?? [];
    }

    $respuestaFacturas = ApiFaretClient::get('ventas/facturas/buscar?' . $filtro, $empresa);

    if ($respuestaFacturas['ok']) {
        $resultadosFacturas = $respuestaFacturas['data']['data'] ?? [];
    }
}

$verDocEntry = null;

if (isset($_GET['verDocEntry']) && ctype_digit((string) $_GET['verDocEntry'])) {
    $verDocEntry = (int) $_GET['verDocEntry'];
}

$lineasNV = [];
$fichaNV = null;
$respuestaLineasNV = null;

if ($verDocEntry !== null) {
    $respuestaLineasNV = ApiFaretClient::get('ventas/notaventa/' . $verDocEntry, $empresa);

    if ($respuestaLineasNV['ok']) {
        $fichaNV = $respuestaLineasNV['data']['data'][0] ?? null;
        $lineasNV = $fichaNV['lineas'] ?? [];
    }
}

?>

<div class="hero">
    <h1>Ventas SAP</h1>
    <p>Cotizaciones, notas de venta y facturas. Solo lectura.</p>
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

<?php if ($docNum === '' && $cliente === ''): ?>
    <div class="sap-guide">
        <span class="sap-guide-icon"><i class="bi bi-info-circle"></i></span>
        <div>
            <h3>¿Qué necesitas para buscar?</h3>
            <p>SAP exige un N° de documento y/o el código exacto de un cliente — no admite buscar por texto libre.</p>
            <p>¿No tienes el código del cliente? Búscalo primero por nombre en <a href="/modules/sap/clientes/?empresa=<?= rawurlencode($empresa) ?>">Clientes</a> y desde su ficha vuelve aquí con un clic.</p>
        </div>
    </div>
<?php endif; ?>

<form class="filter-card" method="GET">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">

    <div class="filter-group">
        <label>N° de documento (DocNum)</label>
        <input type="text" name="docNum" maxlength="100" placeholder="Ej: 22929" value="<?= htmlspecialchars($docNum) ?>">
    </div>

    <div class="filter-group">
        <label>Cliente (código exacto SAP)</label>
        <input type="text" name="cliente" maxlength="100" placeholder="Ej: C0001" value="<?= htmlspecialchars($cliente) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn-primary">
            <i class="bi bi-search"></i>
            Buscar
        </button>

        <?php if ($docNum !== '' || $cliente !== ''): ?>
            <a href="?empresa=<?= rawurlencode($empresa) ?>" class="btn-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($docNumInvalido): ?>

    <div class="card">
        <h2>Número de documento inválido</h2>
        <p>"<?= htmlspecialchars($docNum) ?>" no es un número. El DocNum debe ser numérico.</p>
    </div>

<?php elseif ($docNum !== '' || $cliente !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Notas de venta</h2>
            </div>
        </div>

        <?php if (!$respuestaNV['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudieron buscar notas de venta. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaNV)) ?></p>
            </div>
        <?php elseif (count($resultadosNV) === 0): ?>
            <p>Sin notas de venta que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>F. entrega</th>
                            <th>Estado</th>
                            <th>Comentarios</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosNV as $nv): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($nv['docNum'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($nv['clienteNombre'] ?? $nv['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($nv['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($nv['fechaEntrega'] ?? null)) ?></td>
                                <td><?= badgeEstado($nv['estado'] ?? null) ?></td>
                                <td><?= htmlspecialchars($nv['comentarios'] ?? '-') ?></td>
                                <td>
                                    <a class="btn-secondary" href="?empresa=<?= rawurlencode($empresa) ?>&docNum=<?= rawurlencode($docNum) ?>&cliente=<?= rawurlencode($cliente) ?>&verDocEntry=<?= (int) ($nv['docEntry'] ?? 0) ?>#lineasNV">
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

        <div class="table-card" style="margin-top:32px;" id="lineasNV">
            <div class="table-header">
                <div>
                    <h2>Líneas de la Nota de Venta #<?= htmlspecialchars($fichaNV['docNum'] ?? $verDocEntry) ?></h2>
                    <p>Cantidad pendiente por línea (lo que aún falta por despachar).</p>
                </div>
            </div>

            <?php if (!$respuestaLineasNV['ok']): ?>
                <div class="card">
                    <h2>Error de conexión con apifaret</h2>
                    <p>No se pudieron obtener las líneas. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaLineasNV)) ?></p>
                </div>
            <?php elseif ($fichaNV === null): ?>
                <div class="card">
                    <h2>No encontrada</h2>
                    <p>No se encontró la Nota de Venta #<?= (int) $verDocEntry ?> en <?= htmlspecialchars($empresa) ?>.</p>
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
                            <?php foreach ($lineasNV as $ln): ?>
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

                            <?php if (count($lineasNV) === 0): ?>
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

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Cotizaciones</h2>
            </div>
        </div>

        <?php if (!$respuestaCotizaciones['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudieron buscar cotizaciones. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaCotizaciones)) ?></p>
            </div>
        <?php elseif (count($resultadosCotizaciones) === 0): ?>
            <p>Sin cotizaciones que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Válida hasta</th>
                            <th>Estado</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosCotizaciones as $cot): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($cot['docNum'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($cot['clienteNombre'] ?? $cot['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($cot['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($cot['validaHasta'] ?? null)) ?></td>
                                <td><?= badgeEstado($cot['estado'] ?? null) ?></td>
                                <td><?= htmlspecialchars($cot['comentarios'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Facturas</h2>
            </div>
        </div>

        <?php if (!$respuestaFacturas['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudieron buscar facturas. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaFacturas)) ?></p>
            </div>
        <?php elseif (count($resultadosFacturas) === 0): ?>
            <p>Sin facturas que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosFacturas as $f): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($f['docNum'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['clienteNombre'] ?? $f['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($f['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(formatoFechaSap($f['fechaVencimiento'] ?? null)) ?></td>
                                <td><?= badgeEstado($f['estado'] ?? null) ?></td>
                                <td><?= htmlspecialchars($f['comentarios'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
