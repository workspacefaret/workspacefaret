<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_ventas');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$docNum = trim($_GET['docNum'] ?? '');
$cliente = trim($_GET['cliente'] ?? '');
$docNumInvalido = $docNum !== '' && !ctype_digit($docNum);

// Opcional: limita la búsqueda a un solo tipo de documento (ej. "Buscar NV con este N°"
// desde Inventario). Sin este parámetro se buscan los 3 tipos, como antes.
$tiposValidos = ['nv', 'cotizaciones', 'facturas'];
$tipo = in_array($_GET['tipo'] ?? '', $tiposValidos, true) ? $_GET['tipo'] : '';
$buscarNV = $tipo === '' || $tipo === 'nv';
$buscarCotizaciones = $tipo === '' || $tipo === 'cotizaciones';
$buscarFacturas = $tipo === '' || $tipo === 'facturas';

// Cada tipo de documento se pagina por separado (total real en "paginacion").
$porPagina = sapLeerPorPagina();
$paginas = [
    'paginaNV' => sapLeerPagina('paginaNV'),
    'paginaCotizaciones' => sapLeerPagina('paginaCotizaciones'),
    'paginaFacturas' => sapLeerPagina('paginaFacturas'),
];

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

    $filtro = implode('&', $params);

    if ($buscarNV) {
        $respuestaNV = ApiFaretClient::get('ventas/notaventa/buscar?' . $filtro . '&' . sapQueryPagina($paginas['paginaNV'], $porPagina), $empresa);

        if ($respuestaNV['ok']) {
            $resultadosNV = $respuestaNV['data']['data'] ?? [];
        }
    }

    if ($buscarCotizaciones) {
        $respuestaCotizaciones = ApiFaretClient::get('ventas/cotizaciones/buscar?' . $filtro . '&' . sapQueryPagina($paginas['paginaCotizaciones'], $porPagina), $empresa);

        if ($respuestaCotizaciones['ok']) {
            $resultadosCotizaciones = $respuestaCotizaciones['data']['data'] ?? [];
        }
    }

    if ($buscarFacturas) {
        $respuestaFacturas = ApiFaretClient::get('ventas/facturas/buscar?' . $filtro . '&' . sapQueryPagina($paginas['paginaFacturas'], $porPagina), $empresa);

        if ($respuestaFacturas['ok']) {
            $resultadosFacturas = $respuestaFacturas['data']['data'] ?? [];
        }
    }
}

$verDocEntry = null;

if (isset($_GET['verDocEntry']) && ctype_digit((string) $_GET['verDocEntry'])) {
    $verDocEntry = (int) $_GET['verDocEntry'];
}

$lineasNV = [];
$fichaNV = null;
$respuestaLineasNV = null;

if ($verDocEntry !== null && $buscarNV) {
    $respuestaLineasNV = ApiFaretClient::get('ventas/notaventa/' . $verDocEntry, $empresa);

    if ($respuestaLineasNV['ok']) {
        $fichaNV = $respuestaLineasNV['data']['data'][0] ?? null;
        $lineasNV = $fichaNV['lineas'] ?? [];
    }
}

// Conserva los filtros activos (incluido "tipo") y las páginas en los links internos de la página.
$paramsBusqueda = array_filter(['empresa' => $empresa, 'docNum' => $docNum, 'cliente' => $cliente, 'tipo' => $tipo], fn($v) => $v !== '')
    + sapParamsPaginacion($paginas, $porPagina);

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
    <?= sapInputPorPagina($porPagina) ?>

    <div class="filter-group">
        <label>N° de documento</label>
        <input type="text" name="docNum" maxlength="100" placeholder="Ej: 22929" value="<?= htmlspecialchars($docNum) ?>">
    </div>

    <div class="filter-group">
        <label>Cliente (código exacto SAP)</label>
        <input type="text" name="cliente" maxlength="100" placeholder="Ej: C0001" value="<?= htmlspecialchars($cliente) ?>">
    </div>

    <div class="filter-group">
        <label>Buscar en</label>
        <select name="tipo">
            <option value="" <?= $tipo === '' ? 'selected' : '' ?>>Notas de venta, cotizaciones y facturas</option>
            <option value="nv" <?= $tipo === 'nv' ? 'selected' : '' ?>>Solo notas de venta</option>
            <option value="cotizaciones" <?= $tipo === 'cotizaciones' ? 'selected' : '' ?>>Solo cotizaciones</option>
            <option value="facturas" <?= $tipo === 'facturas' ? 'selected' : '' ?>>Solo facturas</option>
        </select>
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
        <p>"<?= htmlspecialchars($docNum) ?>" no es un número. El N° de documento debe ser numérico.</p>
    </div>

<?php elseif ($docNum !== '' || $cliente !== ''): ?>

    <?php if ($docNum !== '' && $tipo === ''): ?>
        <p class="sap-nota" style="margin:0 0 16px;">
            <i class="bi bi-info-circle"></i>
            Cada tipo de documento tiene su propia numeración: una nota de venta, una cotización y una factura con el mismo N° no están necesariamente relacionadas.
        </p>
    <?php endif; ?>

    <?php if ($buscarNV): ?>

    <div class="table-card" id="notasVenta">
        <div class="table-header">
            <div>
                <h2>Notas de venta</h2>
            </div>
        </div>

        <?php if (!$respuestaNV['ok']): ?>
            <?= sapErrorCard('No se pudieron buscar notas de venta.', $respuestaNV) ?>
        <?php elseif (count($resultadosNV) === 0): ?>
            <p>Sin notas de venta que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Cliente</th>
                            <th>Ref. cliente</th>
                            <th>Fecha</th>
                            <th>Entrega</th>
                            <th>Estado</th>
                            <th>Comentarios</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosNV as $nv): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($nv['docNum'] ?? '-') ?></strong>
                                    <?php if (!empty($nv['cancelado'])): ?> <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($nv['clienteNombre'] ?? $nv['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($nv['referenciaCliente'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($nv['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(sapFecha($nv['fechaEntrega'] ?? null)) ?></td>
                                <td><?= sapBadgeEstado($nv['estado'] ?? '') ?></td>
                                <td><?= sapTextoCorto($nv['comentarios'] ?? '') ?></td>
                                <td>
                                    <a class="btn-secondary" href="?<?= htmlspecialchars(http_build_query($paramsBusqueda + ['verDocEntry' => (int) ($nv['docEntry'] ?? 0)])) ?>#lineasNV">
                                        Ver líneas
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaNV, $paramsBusqueda, 'paginaNV', 'notasVenta') ?>
        <?php endif; ?>
    </div>

    <?php if ($verDocEntry !== null): ?>

        <div class="table-card" style="margin-top:32px;" id="lineasNV">
            <div class="table-header">
                <div>
                    <h2>Líneas de la nota de venta N° <?= htmlspecialchars($fichaNV['docNum'] ?? '') ?></h2>
                    <p>Pendiente: cantidad que según SAP aún falta entregar en cada línea.</p>
                    <?php if (!empty($fichaNV['cancelado'])): ?>
                        <p><span class="badge badge-danger">Documento cancelado en SAP</span></p>
                    <?php endif; ?>
                    <?php if (!empty($fichaNV['referenciaCliente']) || !empty($fichaNV['fechaActualizacion']) || !empty($fichaNV['cartulinaAsignada']) || !empty($fichaNV['fechaSolicitadaCliente'])): ?>
                        <p class="sap-nota">
                            <?php if (!empty($fichaNV['referenciaCliente'])): ?>Ref. cliente: <strong><?= htmlspecialchars($fichaNV['referenciaCliente']) ?></strong>. <?php endif; ?>
                            <?php if (!empty($fichaNV['fechaSolicitadaCliente'])): ?>Fecha solicitada por el cliente: <?= htmlspecialchars(sapFecha($fichaNV['fechaSolicitadaCliente'])) ?>. <?php endif; ?>
                            <?php if (!empty($fichaNV['cartulinaAsignada'])): ?>Cartulina asignada: <?= htmlspecialchars($fichaNV['cartulinaAsignada']) ?>. <?php endif; ?>
                            <?php if (!empty($fichaNV['fechaActualizacion'])): ?>Actualizado en SAP: <?= htmlspecialchars(sapFecha($fichaNV['fechaActualizacion'])) ?>.<?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$respuestaLineasNV['ok']): ?>
                <?= sapErrorCard('No se pudieron obtener las líneas.', $respuestaLineasNV) ?>
            <?php elseif ($fichaNV === null): ?>
                <div class="card">
                    <h2>No encontrada</h2>
                    <p>No se encontró esa nota de venta en <?= htmlspecialchars($empresa) ?>.</p>
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
                                <th>Estado línea</th>
                                <th>Picking</th>
                                <th>OT / Certificación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineasNV as $ln): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($ln['itemCode'] ?? '-') ?></strong><br>
                                        <span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($ln['descripcion'] ?? '') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($ln['almacen'] ?? '-') ?></td>
                                    <td class="sap-num"><?= sapCantidad($ln['cantidad'] ?? null) ?> <?= htmlspecialchars($ln['unidad'] ?? '') ?></td>
                                    <td class="sap-num"><?= sapCantidad($ln['cantidadPendiente'] ?? null) ?></td>
                                    <td>
                                        <span class="status-badge <?= empty($ln['cerrada']) ? 'status-pending' : 'status-ok' ?>">
                                            <?= empty($ln['cerrada']) ? 'Abierta' : 'Cerrada' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($ln['estadoPicking'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($ln['numeroOT']) || !empty($ln['certificacion'])): ?>
                                            <?= htmlspecialchars($ln['numeroOT'] ?? '-') ?>
                                            <?php if (!empty($ln['certificacion'])): ?> · <?= htmlspecialchars($ln['certificacion']) ?><?= !empty($ln['porcentajeCertificacion']) ? ' (' . htmlspecialchars($ln['porcentajeCertificacion']) . ')' : '' ?><?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (count($lineasNV) === 0): ?>
                                <tr>
                                    <td colspan="7">Sin líneas.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php endif; // $buscarNV ?>

    <?php if ($buscarCotizaciones): ?>

    <div class="table-card" style="margin-top:32px;" id="cotizaciones">
        <div class="table-header">
            <div>
                <h2>Cotizaciones</h2>
            </div>
        </div>

        <?php if (!$respuestaCotizaciones['ok']): ?>
            <?= sapErrorCard('No se pudieron buscar cotizaciones.', $respuestaCotizaciones) ?>
        <?php elseif (count($resultadosCotizaciones) === 0): ?>
            <p>Sin cotizaciones que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Cliente</th>
                            <th>Ref. cliente</th>
                            <th>Fecha</th>
                            <th>Válida hasta</th>
                            <th>Estado</th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosCotizaciones as $cot): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($cot['docNum'] ?? '-') ?></strong>
                                    <?php if (!empty($cot['cancelado'])): ?> <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($cot['clienteNombre'] ?? $cot['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($cot['referenciaCliente'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($cot['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(sapFecha($cot['validaHasta'] ?? null)) ?></td>
                                <td><?= sapBadgeEstado($cot['estado'] ?? '') ?></td>
                                <td><?= sapTextoCorto($cot['comentarios'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaCotizaciones, $paramsBusqueda, 'paginaCotizaciones', 'cotizaciones') ?>
        <?php endif; ?>
    </div>

    <?php endif; // $buscarCotizaciones ?>

    <?php if ($buscarFacturas): ?>

    <div class="table-card" style="margin-top:32px;" id="facturas">
        <div class="table-header">
            <div>
                <h2>Facturas</h2>
            </div>
        </div>

        <?php if (!$respuestaFacturas['ok']): ?>
            <?= sapErrorCard('No se pudieron buscar facturas.', $respuestaFacturas) ?>
        <?php elseif (count($resultadosFacturas) === 0): ?>
            <p>Sin facturas que coincidan con la búsqueda en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Cliente</th>
                            <th>Ref. cliente</th>
                            <th>Fecha</th>
                            <th>Vencimiento</th>
                            <th>Estado <?= sapAyuda('Estado del documento en SAP. En facturas, "Abierta" normalmente indica saldo pendiente de pago.') ?></th>
                            <th>Comentarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosFacturas as $f): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($f['docNum'] ?? '-') ?></strong>
                                    <?php if (!empty($f['cancelado'])): ?> <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($f['clienteNombre'] ?? $f['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['referenciaCliente'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($f['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars(sapFecha($f['fechaVencimiento'] ?? null)) ?></td>
                                <td><?= sapBadgeEstado($f['estado'] ?? '') ?></td>
                                <td><?= sapTextoCorto($f['comentarios'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaFacturas, $paramsBusqueda, 'paginaFacturas', 'facturas') ?>
        <?php endif; ?>
    </div>

    <?php endif; // $buscarFacturas ?>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
