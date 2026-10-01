<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

// Catálogo de tipos de documento que soporta el flujo documental (A14). Cuando "endpoint"
// es null, apifaret no tiene ficha propia para ese tipo (solo aparece como nodo del flujo
// de otro documento) — se muestra con los datos que ya trae el nodo, sin líneas.
const SAP_DOC_TIPOS = [
    'notaventa' => ['etiqueta' => 'Nota de venta', 'modulo' => 'portal_sap_ventas', 'endpoint' => 'ventas/notaventa/%d'],
    'cotizacion' => ['etiqueta' => 'Cotización', 'modulo' => 'portal_sap_ventas', 'endpoint' => 'ventas/cotizaciones/%d'],
    'factura' => ['etiqueta' => 'Factura', 'modulo' => 'portal_sap_ventas', 'endpoint' => 'ventas/facturas/%d'],
    'notacredito' => ['etiqueta' => 'Nota de crédito', 'modulo' => 'portal_sap_ventas', 'endpoint' => null],
    'devolucion' => ['etiqueta' => 'Devolución', 'modulo' => 'portal_sap_ventas', 'endpoint' => null],
    'pedidocompra' => ['etiqueta' => 'Pedido de compra', 'modulo' => 'portal_sap_compras', 'endpoint' => 'compras/pedidos/%d'],
    'facturaproveedor' => ['etiqueta' => 'Factura de proveedor', 'modulo' => 'portal_sap_compras', 'endpoint' => null],
    'notacreditoproveedor' => ['etiqueta' => 'Nota de crédito de proveedor', 'modulo' => 'portal_sap_compras', 'endpoint' => null],
    'devolucionproveedor' => ['etiqueta' => 'Devolución a proveedor', 'modulo' => 'portal_sap_compras', 'endpoint' => null],
    'despacho' => ['etiqueta' => 'Despacho', 'modulo' => 'portal_sap_logistica', 'endpoint' => 'documentos/despachos/%d'],
    'recepcion' => ['etiqueta' => 'Recepción', 'modulo' => 'portal_sap_logistica', 'endpoint' => 'documentos/recepciones/%d'],
    'traslado' => ['etiqueta' => 'Traslado', 'modulo' => 'portal_sap_logistica', 'endpoint' => 'documentos/traslados/%d'],
    'solicitudtraslado' => ['etiqueta' => 'Solicitud de traslado', 'modulo' => 'portal_sap_logistica', 'endpoint' => 'documentos/traslados/pendientes/%d'],
    'picking' => ['etiqueta' => 'Lista de picking', 'modulo' => 'portal_sap_logistica', 'endpoint' => 'documentos/picking/pendientes/%d'],
];

$tipo = (string) ($_GET['tipo'] ?? '');
$tipoValido = isset(SAP_DOC_TIPOS[$tipo]);

if ($tipoValido) {
    requireModuleAccess(SAP_DOC_TIPOS[$tipo]['modulo']);
} else {
    requireLogin();
}

ob_start();

$empresa = ApiFaretClient::empresaActual();
$docEntry = isset($_GET['docEntry']) && ctype_digit((string) $_GET['docEntry']) ? (int) $_GET['docEntry'] : null;

// "volver" solo acepta rutas internas de Portal SAP (nunca una URL externa / de otro módulo).
$volverRaw = (string) ($_GET['volver'] ?? '');
$volver = str_starts_with($volverRaw, '/modules/sap/') ? $volverRaw : null;

// Construye el link a la ficha de otro documento (nodo del flujo), siempre dentro de empresa+tipo+docEntry.
function urlDocumento(string $empresa, string $tipo, int $docEntry): string
{
    return '/modules/sap/documento/?' . http_build_query(['empresa' => $empresa, 'tipo' => $tipo, 'docEntry' => $docEntry]);
}

$ficha = null;
$respuestaFicha = null;
$flujo = null;
$respuestaFlujo = null;

if ($tipoValido && $docEntry !== null) {
    $config = SAP_DOC_TIPOS[$tipo];

    // Secuencial a propósito: el Service Layer penaliza fuerte las llamadas simultáneas,
    // incluso entre compañías distintas (ver nota de concurrencia en CLAUDE.md / _ui.php).
    if ($config['endpoint'] !== null) {
        $respuestaFicha = ApiFaretClient::get(sprintf($config['endpoint'], $docEntry) . '?empresa=' . rawurlencode($empresa), $empresa);

        if ($respuestaFicha['ok']) {
            $ficha = $respuestaFicha['data']['data'][0] ?? null;
        }
    }

    $respuestaFlujo = ApiFaretClient::get('documentos/' . $tipo . '/' . $docEntry . '/flujo?empresa=' . rawurlencode($empresa), $empresa);

    if ($respuestaFlujo['ok']) {
        $flujo = $respuestaFlujo['data']['data'][0] ?? null;
    }
}

// Campos de cabecera: solo se listan los que efectivamente vienen en esta respuesta — el
// mismo documento (ficha) trae campos distintos según el tipo (NV, traslado, despacho...).
$campos = [];

if ($ficha !== null) {
    if (!empty($ficha['docNum'])) {
        $campos['N° de documento'] = htmlspecialchars((string) $ficha['docNum']);
    } elseif (!empty($ficha['absEntry'])) {
        $campos['N° (AbsEntry)'] = htmlspecialchars((string) $ficha['absEntry']);
    }

    if (!empty($ficha['fecha'])) {
        $campos['Fecha'] = htmlspecialchars(sapFecha($ficha['fecha']));
    }

    if (!empty($ficha['hora'])) {
        $campos['Hora'] = htmlspecialchars(substr((string) $ficha['hora'], 0, 5));
    }

    if (isset($ficha['estado']) && $ficha['estado'] !== '') {
        $campos['Estado'] = sapBadgeEstado($ficha['estado']);
    }

    if (!empty($ficha['cancelado'])) {
        $campos['Cancelado'] = '<span class="badge badge-danger">Sí, cancelado en SAP</span>';
    }

    if (!empty($ficha['clienteNombre']) || !empty($ficha['clienteCodigo'])) {
        $campos['Cliente'] = htmlspecialchars($ficha['clienteNombre'] ?? $ficha['clienteCodigo']);
    }

    if (!empty($ficha['proveedorNombre']) || !empty($ficha['proveedorCodigo'])) {
        $campos['Proveedor'] = htmlspecialchars($ficha['proveedorNombre'] ?? $ficha['proveedorCodigo']);
    }

    if (!empty($ficha['referenciaCliente'])) {
        $campos['Ref. cliente'] = htmlspecialchars($ficha['referenciaCliente']);
    }

    if (!empty($ficha['referenciaProveedor'])) {
        $campos['Ref. proveedor'] = htmlspecialchars($ficha['referenciaProveedor']);
    }

    if (!empty($ficha['almacenOrigen']) || !empty($ficha['almacenDestino'])) {
        $campos['Origen → Destino'] = htmlspecialchars(($ficha['almacenOrigen'] ?? '-') . ' → ' . ($ficha['almacenDestino'] ?? '-'));
    }

    if (!empty($ficha['fechaVencimiento'])) {
        $campos['Vencimiento'] = htmlspecialchars(sapFecha($ficha['fechaVencimiento']));
    }

    if (!empty($ficha['fechaEntrega'])) {
        $campos['Entrega'] = htmlspecialchars(sapFecha($ficha['fechaEntrega']));
    }

    if (!empty($ficha['folio'])) {
        $campos['Folio'] = htmlspecialchars((string) $ficha['folio']);
    }

    if (!empty($ficha['direccionDespacho'])) {
        $campos['Dirección de despacho'] = sapTextoCorto($ficha['direccionDespacho'], 120);
    }

    if (!empty($ficha['nombreTransporte'])) {
        $campos['Transporte'] = htmlspecialchars($ficha['nombreTransporte']);
    }

    if (!empty($ficha['numeroPallet'])) {
        $campos['N° Pallet'] = htmlspecialchars($ficha['numeroPallet']);
    }

    if (!empty($ficha['nombre'])) {
        $campos['Nombre (SAP)'] = htmlspecialchars($ficha['nombre']);
    }

    if (!empty($ficha['comentarios'])) {
        $campos['Comentarios'] = sapTextoCorto($ficha['comentarios'], 160);
    }

    if (!empty($ficha['fechaActualizacion'])) {
        $campos['Actualizado en SAP'] = htmlspecialchars(sapFecha($ficha['fechaActualizacion']));
    }
}

// Columnas de líneas: solo se muestran las que al menos una línea trae con datos, porque
// cada tipo de documento tiene un esquema de línea distinto (ver NotaVentaLineaDto,
// DocumentoTrasladoLineaDto, ListaPickingLineaDto, etc. en apisapfaret).
$lineas = is_array($ficha['lineas'] ?? null) ? $ficha['lineas'] : [];
$tieneColumna = function (array $lineas, array $claves): bool {
    foreach ($lineas as $ln) {
        foreach ($claves as $clave) {
            if (!empty($ln[$clave])) {
                return true;
            }
        }
    }

    return false;
};
$colArticulo = $tieneColumna($lineas, ['itemCode']);
$colAlmacenSimple = $tieneColumna($lineas, ['almacen']) && !$tieneColumna($lineas, ['almacenOrigen', 'almacenDestino']);
$colAlmacenFlujo = $tieneColumna($lineas, ['almacenOrigen', 'almacenDestino']);
$colCantidad = $tieneColumna($lineas, ['cantidad']);
$colPendiente = $tieneColumna($lineas, ['cantidadPendiente']);
$colEstado = $tieneColumna($lineas, ['cerrada', 'estadoLinea']);
$colCliente = $tieneColumna($lineas, ['clienteNombre', 'clienteCodigo']);

?>

<div class="hero">
    <h1>Documento SAP</h1>
    <p>Ficha y flujo documental (de dónde viene, qué generó). Solo lectura.</p>
</div>

<?php if ($volver !== null): ?>
    <p style="margin:0 0 20px;">
        <a href="<?= htmlspecialchars($volver) ?>" class="btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </p>
<?php endif; ?>

<?php if (!$tipoValido): ?>

    <div class="card">
        <h2>Tipo de documento no reconocido</h2>
        <p>El parámetro "tipo" de la URL no corresponde a ningún tipo de documento soportado.</p>
    </div>

<?php elseif ($docEntry === null): ?>

    <div class="card">
        <h2>Documento no especificado</h2>
        <p>Falta el N° interno (docEntry) del documento a mostrar.</p>
    </div>

<?php else: ?>

    <div class="table-card" id="ficha">
        <div class="table-header">
            <div>
                <h2><?= htmlspecialchars(SAP_DOC_TIPOS[$tipo]['etiqueta']) ?></h2>
                <p><?= htmlspecialchars($empresa) ?> · DocEntry <?= htmlspecialchars((string) $docEntry) ?></p>
            </div>
        </div>

        <?php if (SAP_DOC_TIPOS[$tipo]['endpoint'] === null): ?>
            <p class="sap-nota">
                <i class="bi bi-info-circle"></i>
                Portal SAP no tiene una ficha completa para este tipo de documento todavía. Los datos de abajo vienen del flujo documental del documento que lo originó.
            </p>
        <?php elseif (!$respuestaFicha['ok']): ?>
            <?= sapErrorCard('No se pudo obtener la ficha del documento.', $respuestaFicha) ?>
        <?php elseif ($ficha === null): ?>
            <p>No se encontró ese documento en <?= htmlspecialchars($empresa) ?>.</p>
        <?php endif; ?>

        <?php if (count($campos) > 0): ?>
            <?php foreach ($campos as $etiqueta => $valor): ?>
                <p class="sap-nota"><strong><?= htmlspecialchars($etiqueta) ?>:</strong> <?= $valor ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (count($lineas) > 0): ?>
            <div class="table-responsive" style="margin-top:16px;">
                <table class="data-table sap-tabla">
                    <thead>
                        <tr>
                            <?php if ($colArticulo): ?><th>Artículo</th><?php endif; ?>
                            <?php if ($colAlmacenSimple): ?><th>Almacén</th><?php endif; ?>
                            <?php if ($colAlmacenFlujo): ?><th>Origen → Destino</th><?php endif; ?>
                            <?php if ($colCliente): ?><th>Cliente</th><?php endif; ?>
                            <?php if ($colCantidad): ?><th class="sap-num">Cantidad</th><?php endif; ?>
                            <?php if ($colPendiente): ?><th class="sap-num">Pendiente</th><?php endif; ?>
                            <?php if ($colEstado): ?><th>Estado línea</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineas as $ln): ?>
                            <tr>
                                <?php if ($colArticulo): ?>
                                    <td>
                                        <strong><?= htmlspecialchars($ln['itemCode'] ?? '-') ?></strong>
                                        <?php if (!empty($ln['descripcion'])): ?><br><span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($ln['descripcion']) ?></span><?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($colAlmacenSimple): ?>
                                    <td><?= htmlspecialchars($ln['almacen'] ?? '-') ?></td>
                                <?php endif; ?>
                                <?php if ($colAlmacenFlujo): ?>
                                    <td><?= htmlspecialchars(($ln['almacenOrigen'] ?? $ln['almacen'] ?? '-') . ' → ' . ($ln['almacenDestino'] ?? '-')) ?></td>
                                <?php endif; ?>
                                <?php if ($colCliente): ?>
                                    <td><?= htmlspecialchars($ln['clienteNombre'] ?? $ln['clienteCodigo'] ?? '-') ?></td>
                                <?php endif; ?>
                                <?php if ($colCantidad): ?>
                                    <td class="sap-num"><?= sapCantidad($ln['cantidad'] ?? null) ?> <?= htmlspecialchars($ln['unidad'] ?? '') ?></td>
                                <?php endif; ?>
                                <?php if ($colPendiente): ?>
                                    <td class="sap-num"><?= sapCantidad($ln['cantidadPendiente'] ?? null) ?></td>
                                <?php endif; ?>
                                <?php if ($colEstado): ?>
                                    <td>
                                        <?php if (isset($ln['cerrada'])): ?>
                                            <span class="status-badge <?= empty($ln['cerrada']) ? 'status-pending' : 'status-ok' ?>">
                                                <?= empty($ln['cerrada']) ? 'Abierta' : 'Cerrada' ?>
                                            </span>
                                        <?php elseif (!empty($ln['estadoLinea'])): ?>
                                            <?= sapBadgeEstado($ln['estadoLinea']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-card" style="margin-top:32px;" id="flujo">
        <div class="table-header">
            <div>
                <h2>Flujo documental</h2>
                <p>De dónde viene y qué se generó a partir de este documento (solo un nivel, sin inventar relaciones).</p>
            </div>
        </div>

        <?php if ($respuestaFlujo === null): ?>
            <p>-</p>
        <?php elseif (!$respuestaFlujo['ok']): ?>
            <?= sapErrorCard('No se pudo obtener el flujo documental.', $respuestaFlujo) ?>
        <?php elseif ($flujo === null): ?>
            <p>Sin información de flujo para este documento.</p>
        <?php else: ?>

            <?php if (count($flujo['origenes'] ?? []) === 0): ?>
                <p class="sap-nota"><i class="bi bi-info-circle"></i> Sin documentos de origen conocidos.</p>
            <?php else: ?>
                <h3 style="font-size:14px;color:var(--muted);margin:0 0 8px;">Viene de</h3>
                <?php foreach ($flujo['origenes'] as $nodo): ?>
                    <?php if (!empty($nodo['resuelto']) && !empty($nodo['tipo']) && isset(SAP_DOC_TIPOS[$nodo['tipo']])): ?>
                        <a class="sap-list-row" href="<?= htmlspecialchars(urlDocumento(sapEmpresa($nodo['empresa'] ?? null, $empresa), $nodo['tipo'], (int) $nodo['docEntry'])) ?>">
                            <span class="sap-list-icon"><i class="bi bi-arrow-up-left"></i></span>
                            <span class="sap-list-main">
                                <span class="sap-list-title"><?= htmlspecialchars(SAP_DOC_TIPOS[$nodo['tipo']]['etiqueta']) ?> <?= htmlspecialchars((string) ($nodo['docNum'] ?? $nodo['docEntry'])) ?></span><br>
                                <span class="sap-list-sub">
                                    <?= htmlspecialchars(sapEmpresa($nodo['empresa'] ?? null, $empresa) ?? '-') ?>
                                    <?php if (!empty($nodo['cancelado'])): ?> · <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </span>
                            </span>
                            <i class="bi bi-chevron-right sap-list-chevron"></i>
                        </a>
                    <?php else: ?>
                        <div class="sap-list-row">
                            <span class="sap-list-icon"><i class="bi bi-question-lg"></i></span>
                            <span class="sap-list-main">
                                <span class="sap-list-title">Documento no disponible</span><br>
                                <span class="sap-list-sub">Objeto SAP <?= htmlspecialchars($nodo['objetoSap'] ?? '-') ?>, vía <?= htmlspecialchars($nodo['via'] ?? '-') ?></span>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (count($flujo['destinos'] ?? []) === 0): ?>
                <p class="sap-nota" style="margin-top:16px;"><i class="bi bi-info-circle"></i> Sin documentos generados a partir de este.</p>
            <?php else: ?>
                <h3 style="font-size:14px;color:var(--muted);margin:16px 0 8px;">Generó</h3>
                <?php foreach ($flujo['destinos'] as $nodo): ?>
                    <?php if (!empty($nodo['resuelto']) && !empty($nodo['tipo']) && isset(SAP_DOC_TIPOS[$nodo['tipo']])): ?>
                        <a class="sap-list-row" href="<?= htmlspecialchars(urlDocumento(sapEmpresa($nodo['empresa'] ?? null, $empresa), $nodo['tipo'], (int) $nodo['docEntry'])) ?>">
                            <span class="sap-list-icon"><i class="bi bi-arrow-down-right"></i></span>
                            <span class="sap-list-main">
                                <span class="sap-list-title"><?= htmlspecialchars(SAP_DOC_TIPOS[$nodo['tipo']]['etiqueta']) ?> <?= htmlspecialchars((string) ($nodo['docNum'] ?? $nodo['docEntry'])) ?></span><br>
                                <span class="sap-list-sub">
                                    <?= htmlspecialchars(sapEmpresa($nodo['empresa'] ?? null, $empresa) ?? '-') ?>
                                    <?php if (!empty($nodo['cancelado'])): ?> · <span class="badge badge-danger">Cancelado</span><?php endif; ?>
                                </span>
                            </span>
                            <i class="bi bi-chevron-right sap-list-chevron"></i>
                        </a>
                    <?php else: ?>
                        <div class="sap-list-row">
                            <span class="sap-list-icon"><i class="bi bi-question-lg"></i></span>
                            <span class="sap-list-main">
                                <span class="sap-list-title">Documento no disponible</span><br>
                                <span class="sap-list-sub">Objeto SAP <?= htmlspecialchars($nodo['objetoSap'] ?? '-') ?>, vía <?= htmlspecialchars($nodo['via'] ?? '-') ?></span>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (count($flujo['relacionesEntreEmpresas'] ?? []) > 0): ?>
                <h3 style="font-size:14px;color:var(--muted);margin:16px 0 8px;">Relación con otra compañía</h3>
                <?php foreach ($flujo['relacionesEntreEmpresas'] as $rel): ?>
                    <?php if (($rel['estado'] ?? '') === 'confirmada' && !empty($rel['documento']['tipo']) && isset(SAP_DOC_TIPOS[$rel['documento']['tipo']])): ?>
                        <a class="sap-list-row" href="<?= htmlspecialchars(urlDocumento(sapEmpresa($rel['documento']['empresa'] ?? $rel['empresaReferida'] ?? null, $empresa), $rel['documento']['tipo'], (int) $rel['documento']['docEntry'])) ?>">
                            <span class="sap-list-icon"><i class="bi bi-link-45deg"></i></span>
                            <span class="sap-list-main">
                                <span class="sap-list-title">Confirmada con <?= htmlspecialchars(sapEmpresaEtiqueta($rel['empresaReferida'] ?? '')) ?></span><br>
                                <span class="sap-list-sub"><?= htmlspecialchars(SAP_DOC_TIPOS[$rel['documento']['tipo']]['etiqueta']) ?> <?= htmlspecialchars((string) ($rel['documento']['docNum'] ?? $rel['documento']['docEntry'] ?? '')) ?></span>
                            </span>
                            <i class="bi bi-chevron-right sap-list-chevron"></i>
                        </a>
                    <?php else: ?>
                        <p class="sap-nota">
                            <i class="bi bi-info-circle"></i>
                            Relación con <?= htmlspecialchars(sapEmpresaEtiqueta($rel['empresaReferida'] ?? '')) ?>: no resuelta<?= !empty($rel['motivo']) ? ' (' . htmlspecialchars($rel['motivo']) . ')' : '' ?>.
                        </p>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (count($flujo['relacionesNoDisponibles'] ?? []) > 0): ?>
                <?php foreach ($flujo['relacionesNoDisponibles'] as $texto): ?>
                    <p class="sap-nota" style="margin-top:8px;"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($texto) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
