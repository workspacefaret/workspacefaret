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

// Cada tabla se pagina por separado en apifaret (pagina/porPagina, total real en
// "paginacion"). Los documentos paginados vienen solo con cabecera: las líneas se
// verán en la ficha del documento, no en este listado.
$porPagina = sapLeerPorPagina();
$paginas = [
    'paginaTraslados' => sapLeerPagina('paginaTraslados'),
    'paginaRecepciones' => sapLeerPagina('paginaRecepciones'),
    'paginaDespachos' => sapLeerPagina('paginaDespachos'),
    'paginaSolicitudes' => sapLeerPagina('paginaSolicitudes'),
    'paginaPicking' => sapLeerPagina('paginaPicking'),
    'paginaLineasPicking' => sapLeerPagina('paginaLineasPicking'),
    'paginaLineasSolicitudes' => sapLeerPagina('paginaLineasSolicitudes'),
];
// Estado de la URL que conserva cada link de paginación.
$paramsEstado = ['empresa' => $empresa, 'desde' => $desdeInput, 'hasta' => $hastaInput] + sapParamsPaginacion($paginas, $porPagina);

// Link a la ficha unificada (empresa + flujo documental), con "volver" apuntando a esta misma búsqueda.
$urlFicha = fn(string $tipoDoc, int $docEntry) => '/modules/sap/documento/?' . http_build_query([
    'empresa' => $empresa, 'tipo' => $tipoDoc, 'docEntry' => $docEntry, 'volver' => $_SERVER['REQUEST_URI'],
]);

$resultadosTraslados = [];
$resultadosRecepciones = [];
$resultadosDespachos = [];
$respuestaTraslados = null;
$respuestaRecepciones = null;
$respuestaDespachos = null;

if ($rangoValido) {
    $filtroFecha = 'desde=' . $desdeSap . '&hasta=' . $hastaSap;

    $respuestaTraslados = ApiFaretClient::get('documentos/traslados?' . $filtroFecha . '&' . sapQueryPagina($paginas['paginaTraslados'], $porPagina), $empresa);

    if ($respuestaTraslados['ok']) {
        $resultadosTraslados = $respuestaTraslados['data']['data'] ?? [];
    }

    $respuestaRecepciones = ApiFaretClient::get('documentos/recepciones?' . $filtroFecha . '&' . sapQueryPagina($paginas['paginaRecepciones'], $porPagina), $empresa);

    if ($respuestaRecepciones['ok']) {
        $resultadosRecepciones = $respuestaRecepciones['data']['data'] ?? [];
    }

    $respuestaDespachos = ApiFaretClient::get('documentos/despachos?' . $filtroFecha . '&' . sapQueryPagina($paginas['paginaDespachos'], $porPagina), $empresa);

    if ($respuestaDespachos['ok']) {
        $resultadosDespachos = $respuestaDespachos['data']['data'] ?? [];
    }
}

$respuestaPicking = ApiFaretClient::get('documentos/picking/pendientes?' . sapQueryPagina($paginas['paginaPicking'], $porPagina), $empresa);
$resultadosPicking = [];

if ($respuestaPicking['ok']) {
    $resultadosPicking = $respuestaPicking['data']['data'] ?? [];
}

$respuestaSolicitudesTraslado = ApiFaretClient::get('documentos/traslados/pendientes?' . sapQueryPagina($paginas['paginaSolicitudes'], $porPagina), $empresa);
$resultadosSolicitudesTraslado = [];

if ($respuestaSolicitudesTraslado['ok']) {
    $resultadosSolicitudesTraslado = $respuestaSolicitudesTraslado['data']['data'] ?? [];
}

// A16: líneas planas (artículo/cliente/almacén/bins ya resueltos por apifaret, sin N+1) —
// se agregan debajo de los listados de cabecera de arriba, no los reemplazan.
$respuestaLineasPicking = null;
$resultadosLineasPicking = [];

if ($rangoValido) {
    $respuestaLineasPicking = ApiFaretClient::get('documentos/picking/lineas?empresa=' . rawurlencode($empresa) . '&desde=' . $desdeSap . '&hasta=' . $hastaSap . '&estado=abiertas&' . sapQueryPagina($paginas['paginaLineasPicking'], $porPagina), $empresa);

    if ($respuestaLineasPicking['ok']) {
        $resultadosLineasPicking = $respuestaLineasPicking['data']['data'] ?? [];
    }
}

$respuestaLineasSolicitudes = ApiFaretClient::get('documentos/solicitudes-traslado/lineas?empresa=' . rawurlencode($empresa) . '&' . sapQueryPagina($paginas['paginaLineasSolicitudes'], $porPagina), $empresa);
$resultadosLineasSolicitudes = [];

if ($respuestaLineasSolicitudes['ok']) {
    $resultadosLineasSolicitudes = $respuestaLineasSolicitudes['data']['data'] ?? [];
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
    <?= sapInputPorPagina($porPagina) ?>

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

    <div class="table-card" id="traslados">
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
                            <th>Comentarios</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosTraslados as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['docNum'] ?? '-') ?></strong></td>
                                <td><?= htmlspecialchars(sapFecha($t['fecha'] ?? null)) ?></td>
                                <td><?= htmlspecialchars($t['almacenOrigen'] ?? '-') ?> → <?= htmlspecialchars($t['almacenDestino'] ?? '-') ?></td>
                                <td><?= sapTextoCorto($t['comentarios'] ?? '') ?></td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('traslado', (int) ($t['docEntry'] ?? 0))) ?>">
                                        Ficha y flujo
                                    </a>
                                </td>
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
            <?= sapPaginador($respuestaTraslados, $paramsEstado, 'paginaTraslados', 'traslados') ?>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;" id="recepciones">
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
                            <th>Ref. proveedor</th>
                            <th>Fecha</th>
                            <th>Comentarios</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosRecepciones as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($r['docNum'] ?? '-') ?></strong>
                                    <?php if (!empty($r['cancelado'])): ?> <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['proveedorNombre'] ?? $r['proveedorCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['referenciaProveedor'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($r['fecha'] ?? null)) ?><?= !empty($r['hora']) ? ' <span style="color:var(--muted);">' . htmlspecialchars(substr((string) $r['hora'], 0, 5)) . '</span>' : '' ?></td>
                                <td><?= sapTextoCorto($r['comentarios'] ?? '') ?></td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('recepcion', (int) ($r['docEntry'] ?? 0))) ?>">
                                        Ficha y flujo
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosRecepciones) === 0): ?>
                            <tr>
                                <td colspan="6">Sin recepciones en el rango seleccionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaRecepciones, $paramsEstado, 'paginaRecepciones', 'recepciones') ?>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;" id="despachos">
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
                            <th>Ref. cliente</th>
                            <th>Fecha</th>
                            <th>Dirección de despacho</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadosDespachos as $d): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($d['docNum'] ?? '-') ?></strong>
                                    <?php if (!empty($d['cancelado'])): ?> <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($d['clienteNombre'] ?? $d['clienteCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['referenciaCliente'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(sapFecha($d['fecha'] ?? null)) ?><?= !empty($d['hora']) ? ' <span style="color:var(--muted);">' . htmlspecialchars(substr((string) $d['hora'], 0, 5)) . '</span>' : '' ?></td>
                                <td><?= sapTextoCorto($d['direccionDespacho'] ?? '') ?></td>
                                <td>
                                    <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('despacho', (int) ($d['docEntry'] ?? 0))) ?>">
                                        Ficha y flujo
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($resultadosDespachos) === 0): ?>
                            <tr>
                                <td colspan="6">Sin despachos en el rango seleccionado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= sapPaginador($respuestaDespachos, $paramsEstado, 'paginaDespachos', 'despachos') ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<div class="table-card" style="margin-top:32px;" id="solicitudes">
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
                        <th></th>
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
                            <td>
                                <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('solicitudtraslado', (int) ($s['docEntry'] ?? 0))) ?>">
                                    Ficha y flujo
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($resultadosSolicitudesTraslado) === 0): ?>
                        <tr>
                            <td colspan="6">No hay solicitudes de traslado abiertas en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= sapPaginador($respuestaSolicitudesTraslado, $paramsEstado, 'paginaSolicitudes', 'solicitudes') ?>
    <?php endif; ?>
</div>

<div class="table-card" style="margin-top:32px;" id="lineas-solicitudes">
    <div class="table-header">
        <div>
            <h2>Qué falta trasladar</h2>
            <p>Líneas abiertas de las solicitudes de arriba, con artículo y cantidad pendiente ya resueltos.</p>
        </div>
    </div>

    <?php if (!$respuestaLineasSolicitudes['ok']): ?>
        <?= sapErrorCard('No se pudo obtener el detalle de líneas de solicitudes de traslado.', $respuestaLineasSolicitudes) ?>
    <?php elseif (count($resultadosLineasSolicitudes) === 0): ?>
        <p>No hay líneas abiertas de solicitudes de traslado en <?= htmlspecialchars($empresa) ?>.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table sap-tabla">
                <thead>
                    <tr>
                        <th>Solicitud</th>
                        <th>Artículo</th>
                        <th>Origen → Destino</th>
                        <th class="sap-num">Cantidad</th>
                        <th class="sap-num">Pendiente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosLineasSolicitudes as $ln): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($urlFicha('solicitudtraslado', (int) ($ln['docEntry'] ?? 0))) ?>"><?= htmlspecialchars((string) ($ln['docNum'] ?? $ln['docEntry'] ?? '-')) ?></a>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ln['itemCode'] ?? '-') ?></strong>
                                <?php if (!empty($ln['descripcion'])): ?><br><span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($ln['descripcion']) ?></span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($ln['almacenOrigen'] ?? '-') ?> → <?= htmlspecialchars($ln['almacenDestino'] ?? '-') ?></td>
                            <td class="sap-num"><?= sapCantidad($ln['cantidad'] ?? null) ?> <?= htmlspecialchars($ln['unidad'] ?? '') ?></td>
                            <td class="sap-num"><?= sapCantidad($ln['cantidadPendiente'] ?? null) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= sapPaginador($respuestaLineasSolicitudes, $paramsEstado, 'paginaLineasSolicitudes', 'lineas-solicitudes') ?>
    <?php endif; ?>
</div>

<div class="table-card" style="margin-top:32px;" id="picking">
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
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosPicking as $pk): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pk['absEntry'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars(sapFecha($pk['fecha'] ?? null)) ?></td>
                            <td><?= sapBadgeEstado($pk['estado'] ?? '') ?></td>
                            <td>
                                <a class="btn-secondary" href="<?= htmlspecialchars($urlFicha('picking', (int) ($pk['absEntry'] ?? 0))) ?>">
                                    Ficha y flujo
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($resultadosPicking) === 0): ?>
                        <tr>
                            <td colspan="4">No hay picking liberado en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= sapPaginador($respuestaPicking, $paramsEstado, 'paginaPicking', 'picking') ?>
    <?php endif; ?>
</div>

<div class="table-card" style="margin-top:32px;" id="lineas-picking">
    <div class="table-header">
        <div>
            <h2>Qué hay que preparar</h2>
            <p>Líneas abiertas de picking, del <?= htmlspecialchars(sapFecha($desdeSap)) ?> al <?= htmlspecialchars(sapFecha($hastaSap)) ?> en <?= htmlspecialchars($empresa) ?> — artículo, cliente y cantidad liberada ya resueltos.</p>
        </div>
    </div>

    <?php if (!$rangoValido): ?>
        <p>Corrige el rango de fechas de arriba para ver las líneas de picking.</p>
    <?php elseif (!$respuestaLineasPicking['ok']): ?>
        <?= sapErrorCard('No se pudo obtener el detalle de líneas de picking.', $respuestaLineasPicking) ?>
    <?php elseif (count($resultadosLineasPicking) === 0): ?>
        <p>No hay líneas de picking abiertas en el rango seleccionado en <?= htmlspecialchars($empresa) ?>.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table sap-tabla">
                <thead>
                    <tr>
                        <th>Lista</th>
                        <th>NV</th>
                        <th>Cliente</th>
                        <th>Artículo</th>
                        <th>Almacén</th>
                        <th class="sap-num">Liberada</th>
                        <th class="sap-num">Recogida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultadosLineasPicking as $ln): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($urlFicha('picking', (int) ($ln['absEntry'] ?? 0))) ?>"><?= htmlspecialchars((string) ($ln['absEntry'] ?? '-')) ?></a>
                            </td>
                            <td><?= htmlspecialchars((string) ($ln['documentoBaseNumero'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($ln['clienteNombre'] ?? $ln['clienteCodigo'] ?? '-') ?></td>
                            <td>
                                <strong><?= htmlspecialchars($ln['itemCode'] ?? '-') ?></strong>
                                <?php if (!empty($ln['descripcion'])): ?><br><span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($ln['descripcion']) ?></span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($ln['almacen'] ?? '-') ?></td>
                            <td class="sap-num"><?= sapCantidad($ln['cantidadLiberada'] ?? null) ?> <?= htmlspecialchars($ln['unidad'] ?? '') ?></td>
                            <td class="sap-num"><?= sapCantidad($ln['cantidadRecogida'] ?? null) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= sapPaginador($respuestaLineasPicking, $paramsEstado, 'paginaLineasPicking', 'lineas-picking') ?>
    <?php endif; ?>
</div>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
