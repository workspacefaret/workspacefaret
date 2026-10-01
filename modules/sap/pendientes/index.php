<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$verVentas = hasModuleAccess('portal_sap_ventas');
$verCompras = hasModuleAccess('portal_sap_compras');

if (!$verVentas && !$verCompras) {
    requireModuleAccess('portal_sap_ventas'); // dispara el mensaje de acceso denegado estándar
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();

// Solo 3 estados en esta primera versión: "abiertas" (sin filtro de vencimiento) puede incluir
// documentos muy antiguos sin cerrar en SAP — se muestra igual (no se esconde la realidad de SAP),
// pero "vencidas" es el default porque es lo más urgente. "cerradas"/"todas" (que exigen rango de
// fecha) quedan fuera de esta vista a propósito.
const SAP_ESTADOS_BANDEJA = ['vencidas', 'porvencer', 'abiertas'];

function sapLeerEstadoBandeja(string $param): string
{
    $valor = (string) ($_GET[$param] ?? '');

    return in_array($valor, SAP_ESTADOS_BANDEJA, true) ? $valor : 'vencidas';
}

$estadoNV = sapLeerEstadoBandeja('estadoNV');
$estadoOC = sapLeerEstadoBandeja('estadoOC');
$porPagina = sapLeerPorPagina();
$paginaNV = sapLeerPagina('paginaNV');
$paginaOC = sapLeerPagina('paginaOC');

// Link a la ficha unificada (empresa + flujo documental).
$urlFicha = fn(string $tipoDoc, int $docEntry) => '/modules/sap/documento/?' . http_build_query([
    'empresa' => $empresa, 'tipo' => $tipoDoc, 'docEntry' => $docEntry, 'volver' => $_SERVER['REQUEST_URI'],
]);

$resumenNV = null;
$respuestaResumenNV = null;
$bandejaNV = [];
$respuestaBandejaNV = null;

if ($verVentas) {
    $respuestaResumenNV = ApiFaretClient::get('ventas/pendientes/resumen?empresa=' . rawurlencode($empresa), $empresa);

    if ($respuestaResumenNV['ok']) {
        $resumenNV = $respuestaResumenNV['data']['data'][0] ?? null;
    }

    $respuestaBandejaNV = ApiFaretClient::get('ventas/notaventa/bandeja?empresa=' . rawurlencode($empresa) . '&estado=' . $estadoNV . '&' . sapQueryPagina($paginaNV, $porPagina), $empresa);

    if ($respuestaBandejaNV['ok']) {
        $bandejaNV = $respuestaBandejaNV['data']['data'] ?? [];
    }
}

$resumenOC = null;
$respuestaResumenOC = null;
$bandejaOC = [];
$respuestaBandejaOC = null;

if ($verCompras) {
    $respuestaResumenOC = ApiFaretClient::get('compras/pendientes/resumen?empresa=' . rawurlencode($empresa), $empresa);

    if ($respuestaResumenOC['ok']) {
        $resumenOC = $respuestaResumenOC['data']['data'][0] ?? null;
    }

    $respuestaBandejaOC = ApiFaretClient::get('compras/pedidos/bandeja?empresa=' . rawurlencode($empresa) . '&estado=' . $estadoOC . '&' . sapQueryPagina($paginaOC, $porPagina), $empresa);

    if ($respuestaBandejaOC['ok']) {
        $bandejaOC = $respuestaBandejaOC['data']['data'] ?? [];
    }
}

// Construye el link para cambiar de estado en una sección sin tocar la otra (vuelve a página 1).
function urlEstadoBandeja(string $empresa, string $param, string $estado, int $porPagina, string $otroParam, string $otroEstado): string
{
    $params = ['empresa' => $empresa, $param => $estado, $otroParam => $otroEstado];

    if ($porPagina !== SAP_POR_PAGINA_DEFAULT) {
        $params['porPagina'] = $porPagina;
    }

    return '?' . http_build_query($params);
}

?>

<div class="hero">
    <h1>Pendientes SAP</h1>
    <p>Notas de venta y pedidos de compra que requieren atención — vencidos, por vencer o abiertos. Solo lectura.</p>
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

<?php if ($verVentas): ?>

    <div class="table-card" id="ventas">
        <div class="table-header">
            <div>
                <h2>Notas de venta</h2>
                <p>En <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if ($resumenNV !== null): ?>
            <div class="sap-kpi-grid" style="margin-bottom:16px;">
                <a class="sap-kpi-card" href="<?= htmlspecialchars(urlEstadoBandeja($empresa, 'estadoNV', 'vencidas', $porPagina, 'estadoOC', $estadoOC)) ?>#ventas">
                    <span class="sap-kpi-icon icon-orange"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="sap-kpi-body">
                        <span>Vencidas</span>
                        <strong><?= $resumenNV['vencidas'] !== null ? sapNumero($resumenNV['vencidas']) : '—' ?></strong>
                    </span>
                </a>
                <a class="sap-kpi-card" href="<?= htmlspecialchars(urlEstadoBandeja($empresa, 'estadoNV', 'porvencer', $porPagina, 'estadoOC', $estadoOC)) ?>#ventas">
                    <span class="sap-kpi-icon icon-blue"><i class="bi bi-clock-history"></i></span>
                    <span class="sap-kpi-body">
                        <span>Por vencer (7 días)</span>
                        <strong><?= $resumenNV['porVencer'] !== null ? sapNumero($resumenNV['porVencer']) : '—' ?></strong>
                    </span>
                </a>
                <a class="sap-kpi-card" href="<?= htmlspecialchars(urlEstadoBandeja($empresa, 'estadoNV', 'abiertas', $porPagina, 'estadoOC', $estadoOC)) ?>#ventas">
                    <span class="sap-kpi-icon icon-green"><i class="bi bi-inbox"></i></span>
                    <span class="sap-kpi-body">
                        <span>Abiertas (todas)</span>
                        <strong><?= sapNumero($resumenNV['abiertas']) ?></strong>
                    </span>
                </a>
            </div>
        <?php elseif ($respuestaResumenNV !== null && !$respuestaResumenNV['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el resumen de notas de venta.', $respuestaResumenNV) ?>
        <?php endif; ?>

        <?php if ($estadoNV === 'abiertas'): ?>
            <p class="sap-nota"><i class="bi bi-info-circle"></i> "Abiertas" incluye documentos antiguos que SAP nunca cerró — no todos requieren acción. Prioriza "Vencidas" para lo realmente urgente.</p>
        <?php endif; ?>

        <?php if (!$respuestaBandejaNV['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el listado de notas de venta pendientes.', $respuestaBandejaNV) ?>
        <?php elseif (count($bandejaNV) === 0): ?>
            <p>Sin notas de venta <?= $estadoNV === 'abiertas' ? 'abiertas' : ($estadoNV === 'porvencer' ? 'por vencer' : 'vencidas') ?> en <?= htmlspecialchars($empresa) ?>.</p>
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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bandejaNV as $nv): ?>
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
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('notaventa', (int) ($nv['docEntry'] ?? 0))) ?>">
                                        Ficha y flujo
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaBandejaNV, ['empresa' => $empresa, 'estadoNV' => $estadoNV, 'estadoOC' => $estadoOC] + sapParamsPaginacion(['paginaNV' => $paginaNV], $porPagina), 'paginaNV', 'ventas') ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($verCompras): ?>

    <div class="table-card" style="margin-top:32px;" id="compras">
        <div class="table-header">
            <div>
                <h2>Pedidos de compra</h2>
                <p>En <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <?php if ($resumenOC !== null): ?>
            <div class="sap-kpi-grid" style="margin-bottom:16px;">
                <a class="sap-kpi-card" href="<?= htmlspecialchars(urlEstadoBandeja($empresa, 'estadoOC', 'vencidas', $porPagina, 'estadoNV', $estadoNV)) ?>#compras">
                    <span class="sap-kpi-icon icon-orange"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="sap-kpi-body">
                        <span>Vencidos</span>
                        <strong><?= $resumenOC['vencidas'] !== null ? sapNumero($resumenOC['vencidas']) : '—' ?></strong>
                    </span>
                </a>
                <a class="sap-kpi-card" href="<?= htmlspecialchars(urlEstadoBandeja($empresa, 'estadoOC', 'porvencer', $porPagina, 'estadoNV', $estadoNV)) ?>#compras">
                    <span class="sap-kpi-icon icon-blue"><i class="bi bi-clock-history"></i></span>
                    <span class="sap-kpi-body">
                        <span>Por vencer (7 días)</span>
                        <strong><?= $resumenOC['porVencer'] !== null ? sapNumero($resumenOC['porVencer']) : '—' ?></strong>
                    </span>
                </a>
                <a class="sap-kpi-card" href="<?= htmlspecialchars(urlEstadoBandeja($empresa, 'estadoOC', 'abiertas', $porPagina, 'estadoNV', $estadoNV)) ?>#compras">
                    <span class="sap-kpi-icon icon-green"><i class="bi bi-inbox"></i></span>
                    <span class="sap-kpi-body">
                        <span>Abiertos (todos)</span>
                        <strong><?= sapNumero($resumenOC['abiertas']) ?></strong>
                    </span>
                </a>
            </div>
        <?php elseif ($respuestaResumenOC !== null && !$respuestaResumenOC['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el resumen de pedidos de compra.', $respuestaResumenOC) ?>
        <?php endif; ?>

        <?php if ($estadoOC === 'abiertas'): ?>
            <p class="sap-nota"><i class="bi bi-info-circle"></i> "Abiertos" incluye documentos antiguos que SAP nunca cerró — no todos requieren acción. Prioriza "Vencidos" para lo realmente urgente.</p>
        <?php endif; ?>

        <?php if (!$respuestaBandejaOC['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el listado de pedidos de compra pendientes.', $respuestaBandejaOC) ?>
        <?php elseif (count($bandejaOC) === 0): ?>
            <p>Sin pedidos de compra <?= $estadoOC === 'abiertas' ? 'abiertos' : ($estadoOC === 'porvencer' ? 'por vencer' : 'vencidos') ?> en <?= htmlspecialchars($empresa) ?>.</p>
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
                        <?php foreach ($bandejaOC as $p): ?>
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
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('pedidocompra', (int) ($p['docEntry'] ?? 0))) ?>">
                                        Ficha y flujo
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaBandejaOC, ['empresa' => $empresa, 'estadoNV' => $estadoNV, 'estadoOC' => $estadoOC] + sapParamsPaginacion(['paginaOC' => $paginaOC], $porPagina), 'paginaOC', 'compras') ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
