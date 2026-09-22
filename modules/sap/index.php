<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireLogin();

// Inicio es el hub de Portal SAP: cualquiera de las 6 claves da entrada (igual
// que modules/planificacion/moldes/), y cada sección de la página se filtra
// más abajo según cuál(es) tenga el usuario — mismo patrón que modules/operacion/.
$clavesPortalSap = ['portal_sap', 'portal_sap_ventas', 'portal_sap_compras', 'portal_sap_logistica', 'portal_sap_calidad', 'portal_sap_precios'];
$tieneAccesoPortalSap = array_reduce($clavesPortalSap, fn($acc, $clave) => $acc || hasModuleAccess($clave), false);

if (!$tieneAccesoPortalSap) {
    mostrarAccesoDenegado('Tu usuario no tiene acceso a Portal SAP. Solicita el permiso al administrador TI.');
}

$verBase = hasModuleAccess('portal_sap');
$verVentas = hasModuleAccess('portal_sap_ventas');
$verCompras = hasModuleAccess('portal_sap_compras');
$verLogistica = hasModuleAccess('portal_sap_logistica');
$verCalidad = hasModuleAccess('portal_sap_calidad');
$verPrecios = hasModuleAccess('portal_sap_precios');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();

$respuestaTraslados = null;
$trasladosPendientes = [];
$totalTraslados = 0;
$respuestaAntiguedad = null;
$totalAntiguos = null;
$respuestaPicking = null;
$totalPicking = null;
$pickingPendiente = [];
$respuestaRecepciones = null;
$totalRecepcionesHoy = null;
$estadoConexion = null;
$buscar = trim($_GET['buscar'] ?? '');
$resultadosBusquedaArticulos = [];
$resultadosBusquedaLote = [];
$resultadosBusquedaClientes = [];
$resultadosBusquedaProveedores = [];
$respuestaBusquedaArticulos = null;
$respuestaBusquedaLote = null;
$respuestaBusquedaClientes = null;
$respuestaBusquedaProveedores = null;
$stockItem = trim($_GET['stockItem'] ?? '');
$stockFicha = [];
$stockDatos = [];
$respuestaStockFicha = null;
$respuestaStockDatos = null;

// Todo lo de abajo (KPIs, búsqueda global, accesos frecuentes, traslados) es
// contenido del acceso base "portal_sap" — se omite por completo si el usuario
// solo tiene una o más de las claves de área (Ventas/Compras/Logística/Calidad).
if ($verBase) {
    $respuestaTraslados = ApiFaretClient::get('documentos/traslados/pendientes?top=20', $empresa);

    if ($respuestaTraslados['ok']) {
        $trasladosPendientes = $respuestaTraslados['data']['data'] ?? [];
        $totalTraslados = $respuestaTraslados['data']['total'] ?? count($trasladosPendientes);
    }

    $respuestaAntiguedad = ApiFaretClient::get('inventario/antiguedad?diasMinimos=90&top=500', $empresa);

    if ($respuestaAntiguedad['ok']) {
        $totalAntiguos = $respuestaAntiguedad['data']['total'] ?? count($respuestaAntiguedad['data']['data'] ?? []);
    }

    $respuestaPicking = ApiFaretClient::get('documentos/picking/pendientes?top=50', $empresa);

    if ($respuestaPicking['ok']) {
        $pickingPendiente = $respuestaPicking['data']['data'] ?? [];
        $totalPicking = $respuestaPicking['data']['total'] ?? count($pickingPendiente);
    }

    $hoySap = date('Ymd');
    $respuestaRecepciones = ApiFaretClient::get('documentos/recepciones?desde=' . $hoySap . '&hasta=' . $hoySap . '&top=50', $empresa);

    if ($respuestaRecepciones['ok']) {
        $totalRecepcionesHoy = $respuestaRecepciones['data']['total'] ?? count($respuestaRecepciones['data']['data'] ?? []);
    }

    // Estado de conexión del header: si alguna de las 4 consultas base falló, se
    // avisa de forma genérica (sin exponer detalle técnico) en vez de mostrar solo
    // "0 resultados" en cada tarjeta por separado.
    $estadoConexion = $respuestaTraslados['ok'] && $respuestaAntiguedad['ok'] && $respuestaPicking['ok'] && $respuestaRecepciones['ok'];

    // Búsqueda global: artículos, lotes, clientes y proveedores — los dominios
    // que ya tienen una ficha real dentro de Portal SAP. Documentos (NV/OC/etc.)
    // queda fuera hasta que existan sus páginas de detalle.
    if ($buscar !== '') {
        $respuestaBusquedaArticulos = ApiFaretClient::get('articulos/buscar?texto=' . rawurlencode($buscar) . '&top=8', $empresa);

        if ($respuestaBusquedaArticulos['ok']) {
            $resultadosBusquedaArticulos = $respuestaBusquedaArticulos['data']['data'] ?? [];
        }

        // lotes/{lote} exige coincidencia exacta y no admite "empresa" (ver nota en inventario/index.php).
        $respuestaBusquedaLote = ApiFaretClient::get('lotes/' . rawurlencode($buscar));

        if ($respuestaBusquedaLote['ok']) {
            $resultadosBusquedaLote = $respuestaBusquedaLote['data']['data'] ?? [];
        }

        $respuestaBusquedaClientes = ApiFaretClient::get('clientes/buscar?texto=' . rawurlencode($buscar) . '&top=5', $empresa);

        if ($respuestaBusquedaClientes['ok']) {
            $resultadosBusquedaClientes = $respuestaBusquedaClientes['data']['data'] ?? [];
        }

        $respuestaBusquedaProveedores = ApiFaretClient::get('proveedores/buscar?texto=' . rawurlencode($buscar) . '&top=5', $empresa);

        if ($respuestaBusquedaProveedores['ok']) {
            $resultadosBusquedaProveedores = $respuestaBusquedaProveedores['data']['data'] ?? [];
        }
    }

    // "Consulta rápida de stock": buscador chico independiente del buscador
    // global, solo para artículos (mismos endpoints ya usados en Inventario).
    if ($stockItem !== '') {
        $respuestaStockFicha = ApiFaretClient::get('articulos/' . rawurlencode($stockItem), $empresa);

        if ($respuestaStockFicha['ok']) {
            $stockFicha = $respuestaStockFicha['data']['data'] ?? [];
        }

        $respuestaStockDatos = ApiFaretClient::get('articulos/' . rawurlencode($stockItem) . '/stock', $empresa);

        if ($respuestaStockDatos['ok']) {
            $stockDatos = $respuestaStockDatos['data']['data'] ?? [];
        }
    }
}

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

?>

<div class="hero">
    <h1>Portal SAP</h1>
    <p>Centro de consulta SAP — información operativa en tiempo real, vía Service Layer. Solo lectura.</p>

    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:14px;">
        <span class="badge badge-primary">
            <i class="bi bi-lock-fill"></i>
            Acceso restringido
        </span>

        <?php if ($estadoConexion !== null): ?>
            <span class="badge <?= $estadoConexion ? 'badge-success' : 'badge-warning' ?>">
                <i class="bi <?= $estadoConexion ? 'bi-wifi' : 'bi-exclamation-triangle-fill' ?>"></i>
                <?= $estadoConexion ? 'Conectado a SAP' : 'Conexión con problemas' ?>
            </span>
        <?php endif; ?>

        <span style="color:var(--muted);font-size:13px;">
            Última consulta: <?= date('d-m-Y H:i') ?>
        </span>
    </div>
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

<?php if ($verBase): ?>

<form class="sap-search" method="GET">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
    <span class="bi bi-search"></span>
    <input type="text" name="buscar" maxlength="100" placeholder="Buscar artículo, lote, cliente o proveedor..." value="<?= htmlspecialchars($buscar) ?>">
    <button type="submit">Buscar</button>
</form>

<?php if ($buscar !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Resultados para "<?= htmlspecialchars($buscar) ?>"</h2>
                <p>Artículos en <?= htmlspecialchars($empresa) ?>; lotes en todas las compañías (esa búsqueda no admite filtrar por empresa).</p>
            </div>
            <a href="?empresa=<?= rawurlencode($empresa) ?>" class="btn-secondary">
                <i class="bi bi-x-lg"></i>
                Limpiar
            </a>
        </div>

        <h3>Artículos</h3>

        <?php if (!$respuestaBusquedaArticulos['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo buscar artículos. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusquedaArticulos)) ?></p>
            </div>
        <?php elseif (count($resultadosBusquedaArticulos) === 0): ?>
            <p>Sin artículos que coincidan con "<?= htmlspecialchars($buscar) ?>" en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <?php foreach ($resultadosBusquedaArticulos as $a): ?>
                <a class="sap-list-row" href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>&item=<?= rawurlencode($a['itemCode'] ?? '') ?>">
                    <span class="sap-list-icon"><i class="bi bi-box-seam"></i></span>
                    <span class="sap-list-main">
                        <span class="sap-list-title"><?= htmlspecialchars($a['itemCode'] ?? '-') ?></span><br>
                        <span class="sap-list-sub"><?= htmlspecialchars($a['itemName'] ?? '-') ?></span>
                    </span>
                    <span class="sap-list-chip"><?= htmlspecialchars($a['grupoNombre'] ?? $a['grupoCodigo'] ?? '-') ?></span>
                    <i class="bi bi-chevron-right sap-list-chevron"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin-top:24px;">Lotes</h3>

        <?php if (!$respuestaBusquedaLote['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo buscar el lote. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusquedaLote)) ?></p>
            </div>
        <?php elseif (count($resultadosBusquedaLote) === 0): ?>
            <p>Sin coincidencia exacta de lote para "<?= htmlspecialchars($buscar) ?>".</p>
        <?php else: ?>
            <?php foreach ($resultadosBusquedaLote as $r): ?>
                <a class="sap-list-row" href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>&lote=<?= rawurlencode($buscar) ?>">
                    <span class="sap-list-icon"><i class="bi bi-upc-scan"></i></span>
                    <span class="sap-list-main">
                        <span class="sap-list-title"><?= htmlspecialchars($buscar) ?></span><br>
                        <span class="sap-list-sub"><?= htmlspecialchars($r['itemCode'] ?? '-') ?> · <?= htmlspecialchars($r['itemName'] ?? '-') ?></span>
                    </span>
                    <span class="sap-list-chip"><?= htmlspecialchars($r['empresa'] ?? '-') ?></span>
                    <span class="sap-list-date"><?= formatoCantidad($r['stock'] ?? 0) ?></span>
                    <i class="bi bi-chevron-right sap-list-chevron"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin-top:24px;">Clientes</h3>

        <?php if (!$respuestaBusquedaClientes['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo buscar clientes. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusquedaClientes)) ?></p>
            </div>
        <?php elseif (count($resultadosBusquedaClientes) === 0): ?>
            <p>Sin clientes que coincidan con "<?= htmlspecialchars($buscar) ?>" en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <?php foreach ($resultadosBusquedaClientes as $c): ?>
                <a class="sap-list-row" href="/modules/sap/clientes/?empresa=<?= rawurlencode($empresa) ?>&cliente=<?= rawurlencode($c['cardCode'] ?? '') ?>">
                    <span class="sap-list-icon"><i class="bi bi-person"></i></span>
                    <span class="sap-list-main">
                        <span class="sap-list-title"><?= htmlspecialchars($c['cardName'] ?? '-') ?></span><br>
                        <span class="sap-list-sub"><?= htmlspecialchars($c['cardCode'] ?? '-') ?> · <?= htmlspecialchars($c['ciudad'] ?? '-') ?></span>
                    </span>
                    <i class="bi bi-chevron-right sap-list-chevron"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin-top:24px;">Proveedores</h3>

        <?php if (!$respuestaBusquedaProveedores['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo buscar proveedores. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusquedaProveedores)) ?></p>
            </div>
        <?php elseif (count($resultadosBusquedaProveedores) === 0): ?>
            <p>Sin proveedores que coincidan con "<?= htmlspecialchars($buscar) ?>" en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <?php foreach ($resultadosBusquedaProveedores as $p): ?>
                <a class="sap-list-row" href="/modules/sap/proveedores/?empresa=<?= rawurlencode($empresa) ?>&proveedor=<?= rawurlencode($p['cardCode'] ?? '') ?>">
                    <span class="sap-list-icon"><i class="bi bi-truck"></i></span>
                    <span class="sap-list-main">
                        <span class="sap-list-title"><?= htmlspecialchars($p['cardName'] ?? '-') ?></span><br>
                        <span class="sap-list-sub"><?= htmlspecialchars($p['cardCode'] ?? '-') ?> · <?= htmlspecialchars($p['ciudad'] ?? '-') ?></span>
                    </span>
                    <i class="bi bi-chevron-right sap-list-chevron"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<div class="sap-kpi-grid">
    <div class="sap-kpi-card">
        <span class="sap-kpi-icon icon-green"><i class="bi bi-arrow-left-right"></i></span>
        <span class="sap-kpi-body">
            <span>Traslados pendientes</span>
            <strong><?= $respuestaTraslados['ok'] ? $totalTraslados : '-' ?></strong>
        </span>
    </div>

    <div class="sap-kpi-card">
        <span class="sap-kpi-icon icon-orange"><i class="bi bi-box-seam"></i></span>
        <span class="sap-kpi-body">
            <span>Picking pendiente</span>
            <strong><?= $totalPicking === null ? '-' : $totalPicking ?></strong>
        </span>
    </div>

    <div class="sap-kpi-card">
        <span class="sap-kpi-icon icon-blue"><i class="bi bi-truck"></i></span>
        <span class="sap-kpi-body">
            <span>Recepciones hoy</span>
            <strong><?= $totalRecepcionesHoy === null ? '-' : $totalRecepcionesHoy ?></strong>
        </span>
    </div>

    <a class="sap-kpi-card" href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>#antiguedad">
        <span class="sap-kpi-icon icon-purple"><i class="bi bi-hourglass-split"></i></span>
        <span class="sap-kpi-body">
            <span>Lotes con más de 90 días</span>
            <strong><?= $totalAntiguos === null ? '-' : $totalAntiguos ?></strong>
        </span>
    </a>
</div>

<?php

// Bodega: traslados pendientes + picking pendiente combinados en una sola lista
// con ícono por tipo. Se arma una vez y se reutiliza en el tab "Todos" y en el
// tab "Bodega" para no duplicar el markup.
ob_start();

if (!$respuestaTraslados['ok'] || !$respuestaPicking['ok']) {
    ?>
    <div class="card">
        <h2>Error de conexión con apifaret</h2>
        <p>
            No se pudo obtener el listado completo de bodega.
            <?php if (!$respuestaTraslados['ok']): ?><?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaTraslados)) ?><?php endif; ?>
            <?php if (!$respuestaPicking['ok']): ?><?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaPicking)) ?><?php endif; ?>
        </p>
    </div>
    <?php
} elseif (count($trasladosPendientes) === 0 && count($pickingPendiente) === 0) {
    ?>
    <p style="color:var(--muted);padding:8px 6px;">No hay traslados ni picking pendientes en <?= htmlspecialchars($empresa) ?>.</p>
    <?php
} else {
    // Vista previa acotada (el KPI de arriba ya muestra el total real) — evita
    // una lista larguísima que descalce la columna del widget de stock al lado.
    $vistaBodega = array_merge(
        array_map(fn($t) => ['tipo' => 'traslado', 'item' => $t], $trasladosPendientes),
        array_map(fn($pk) => ['tipo' => 'picking', 'item' => $pk], $pickingPendiente)
    );
    $totalBodega = count($vistaBodega);
    $vistaBodegaPreview = array_slice($vistaBodega, 0, 8);
    $destino = $verLogistica ? '/modules/sap/logistica/?empresa=' . rawurlencode($empresa) : null;
    $tag = $destino ? 'a' : 'div';

    foreach ($vistaBodegaPreview as $fila) {
        if ($fila['tipo'] === 'traslado') {
            $t = $fila['item'];
            ?>
            <<?= $tag ?> class="sap-list-row" <?= $destino ? 'href="' . htmlspecialchars($destino) . '"' : '' ?>>
                <span class="sap-list-icon"><i class="bi bi-arrow-left-right"></i></span>
                <span class="sap-list-main">
                    <span class="sap-list-title">Traslado #<?= htmlspecialchars($t['docNum'] ?? $t['docEntry'] ?? '-') ?></span><br>
                    <span class="sap-list-sub"><?= htmlspecialchars($t['almacenOrigen'] ?? '-') ?> → <?= htmlspecialchars($t['almacenDestino'] ?? '-') ?></span>
                </span>
                <span class="sap-list-chip"><?= htmlspecialchars($empresa) ?></span>
                <span class="badge badge-warning">Pendiente</span>
                <span class="sap-list-date"><?= htmlspecialchars(formatoFechaSap($t['fecha'] ?? null)) ?></span>
                <?php if ($destino): ?><i class="bi bi-chevron-right sap-list-chevron"></i><?php endif; ?>
            </<?= $tag ?>>
            <?php
        } else {
            $pk = $fila['item'];
            ?>
            <<?= $tag ?> class="sap-list-row" <?= $destino ? 'href="' . htmlspecialchars($destino) . '"' : '' ?>>
                <span class="sap-list-icon"><i class="bi bi-box-seam"></i></span>
                <span class="sap-list-main">
                    <span class="sap-list-title">Picking #<?= htmlspecialchars($pk['absEntry'] ?? '-') ?></span><br>
                    <span class="sap-list-sub"><?= count($pk['lineas'] ?? []) ?> línea(s)</span>
                </span>
                <span class="sap-list-chip"><?= htmlspecialchars($empresa) ?></span>
                <span class="badge badge-warning"><?= htmlspecialchars($pk['estado'] ?? '-') ?></span>
                <span class="sap-list-date"><?= htmlspecialchars(formatoFechaSap($pk['fecha'] ?? null)) ?></span>
                <?php if ($destino): ?><i class="bi bi-chevron-right sap-list-chevron"></i><?php endif; ?>
            </<?= $tag ?>>
            <?php
        }
    }

    if ($totalBodega > count($vistaBodegaPreview)) {
        ?>
        <p style="text-align:center;padding:10px;color:var(--muted);font-size:13px;">
            Mostrando <?= count($vistaBodegaPreview) ?> de <?= $totalBodega ?>.
            <?php if ($destino): ?><a href="<?= htmlspecialchars($destino) ?>" style="color:var(--sap-accent);font-weight:700;">Ver todo en Logística →</a><?php endif; ?>
        </p>
        <?php
    }
}

$htmlBodega = ob_get_clean();

// Ventas/Compras: apifaret exige docNum y/o cliente-proveedor exacto para buscar
// (VentasController.cs/ComprasController.cs) — no hay forma de listar "todo lo
// abierto" sin ese filtro, así que este tab explica la limitación en vez de
// simular una lista vacía.
ob_start();
?>
<div class="sap-guide">
    <span class="sap-guide-icon"><i class="bi bi-graph-up"></i></span>
    <div>
        <h3>Ventas</h3>
        <p>
            SAP no permite listar todas las notas de venta/cotizaciones/facturas abiertas sin indicar
            un N° de documento o un cliente puntual.
        </p>
        <?php if ($verVentas): ?>
            <a href="/modules/sap/ventas/?empresa=<?= rawurlencode($empresa) ?>">Ir a Ventas para buscar por documento o cliente →</a>
        <?php else: ?>
            <p>Pide acceso al área Ventas para consultar documentos puntuales.</p>
        <?php endif; ?>
    </div>
</div>
<?php
$htmlVentasAviso = ob_get_clean();

ob_start();
?>
<div class="sap-guide">
    <span class="sap-guide-icon"><i class="bi bi-cart"></i></span>
    <div>
        <h3>Compras</h3>
        <p>
            SAP no permite listar todos los pedidos de compra abiertos sin indicar un N° de documento
            o un proveedor puntual.
        </p>
        <?php if ($verCompras): ?>
            <a href="/modules/sap/compras/?empresa=<?= rawurlencode($empresa) ?>">Ir a Compras para buscar por documento o proveedor →</a>
        <?php else: ?>
            <p>Pide acceso al área Compras para consultar documentos puntuales.</p>
        <?php endif; ?>
    </div>
</div>
<?php
$htmlComprasAviso = ob_get_clean();

?>

<div class="sap-columns">
    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Pendientes operativos</h2>
                <p>En <?= htmlspecialchars($empresa) ?>.</p>
            </div>
        </div>

        <div class="sap-tabs">
            <button type="button" class="pendientes-tab-btn active" data-tab="todos">Todos</button>
            <button type="button" class="pendientes-tab-btn" data-tab="bodega">Bodega</button>
            <button type="button" class="pendientes-tab-btn" data-tab="ventas">Ventas</button>
            <button type="button" class="pendientes-tab-btn" data-tab="compras">Compras</button>
        </div>

        <div class="pendientes-tab-panel" data-panel="todos">
            <?= $htmlBodega ?>
            <div style="margin-top:16px;"><?= $htmlVentasAviso ?></div>
            <div style="margin-top:16px;"><?= $htmlComprasAviso ?></div>
        </div>

        <div class="pendientes-tab-panel" data-panel="bodega" style="display:none;">
            <?= $htmlBodega ?>
        </div>

        <div class="pendientes-tab-panel" data-panel="ventas" style="display:none;">
            <?= $htmlVentasAviso ?>
        </div>

        <div class="pendientes-tab-panel" data-panel="compras" style="display:none;">
            <?= $htmlComprasAviso ?>
        </div>

        <script>
            (function () {
                var botones = document.querySelectorAll('.pendientes-tab-btn');
                var paneles = document.querySelectorAll('.pendientes-tab-panel');

                botones.forEach(function (boton) {
                    boton.addEventListener('click', function () {
                        var tab = boton.getAttribute('data-tab');

                        paneles.forEach(function (panel) {
                            panel.style.display = panel.getAttribute('data-panel') === tab ? '' : 'none';
                        });

                        botones.forEach(function (b) {
                            b.classList.toggle('active', b === boton);
                        });
                    });
                });
            })();
        </script>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Consulta rápida de stock</h2>
            </div>
        </div>

        <form method="GET" style="display:flex;gap:8px;margin-bottom:16px;">
            <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
            <?php if ($buscar !== ''): ?><input type="hidden" name="buscar" value="<?= htmlspecialchars($buscar) ?>"><?php endif; ?>
            <input type="text" name="stockItem" maxlength="100" placeholder="Código de artículo..." value="<?= htmlspecialchars($stockItem) ?>" style="flex:1;padding:10px 14px;border-radius:10px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--text);">
            <button type="submit" class="btn-primary"><i class="bi bi-search"></i></button>
        </form>

        <?php if ($stockItem === ''): ?>
            <p style="color:var(--muted);">Escribe un código de artículo para ver su stock total y por almacén.</p>
        <?php elseif (!$respuestaStockFicha['ok'] && !$respuestaStockDatos['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo consultar el artículo. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaStockFicha)) ?></p>
            </div>
        <?php elseif (count($stockDatos) === 0): ?>
            <p>El artículo <?= htmlspecialchars($stockItem) ?> no existe en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>
            <?php $stockTotal = array_sum(array_column($stockDatos, 'stockTotal')); ?>
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:14px;">
                <div>
                    <strong style="font-size:18px;"><?= htmlspecialchars($stockItem) ?></strong><br>
                    <span style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($stockFicha[0]['itemName'] ?? $stockDatos[0]['itemName'] ?? '') ?></span>
                </div>
                <div style="text-align:right;">
                    <span style="display:block;font-size:12px;color:var(--muted);">Stock total</span>
                    <strong style="font-size:22px;"><?= formatoCantidad($stockTotal) ?></strong>
                </div>
            </div>

            <?php foreach ($stockDatos as $s): ?>
                <?php foreach (($s['porAlmacen'] ?? []) as $alm): ?>
                    <div style="display:flex;justify-content:space-between;padding:8px 4px;border-bottom:1px solid var(--border);font-size:13px;">
                        <span><?= htmlspecialchars($alm['almacen'] ?? '-') ?></span>
                        <span><?= formatoCantidad($alm['disponible'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <p style="margin-top:14px;">
                <a href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>&item=<?= rawurlencode($stockItem) ?>" style="color:var(--sap-accent);font-weight:700;">
                    Ver lotes y ubicaciones <i class="bi bi-arrow-right"></i>
                </a>
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="table-card" style="margin-top:0;">
    <div class="table-header">
        <div>
            <h2>Accesos frecuentes</h2>
        </div>
    </div>

    <div class="sap-tiles">
        <a href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>#buscarArticulo" class="sap-tile">
            <span class="sap-tile-icon"><i class="bi bi-boxes"></i></span>
            <span>Artículos</span>
        </a>

        <a href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>#buscarLote" class="sap-tile">
            <span class="sap-tile-icon"><i class="bi bi-upc-scan"></i></span>
            <span>Lotes</span>
        </a>

        <a href="/modules/sap/clientes/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
            <span class="sap-tile-icon"><i class="bi bi-people"></i></span>
            <span>Clientes</span>
        </a>

        <a href="/modules/sap/proveedores/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
            <span class="sap-tile-icon"><i class="bi bi-truck"></i></span>
            <span>Proveedores</span>
        </a>

        <a href="/modules/sap/almacenes/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
            <span class="sap-tile-icon"><i class="bi bi-building"></i></span>
            <span>Almacenes</span>
        </a>
    </div>
</div>

<?php endif; // $verBase ?>

<?php if ($verVentas || $verCompras || $verLogistica || $verCalidad || $verPrecios): ?>

    <div class="table-card" style="margin-top:32px;">
        <div class="table-header">
            <div>
                <h2>Áreas</h2>
            </div>
        </div>

        <div class="sap-tiles">
            <?php if ($verVentas): ?>
                <a href="/modules/sap/ventas/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
                    <span class="sap-tile-icon"><i class="bi bi-graph-up"></i></span>
                    <span>Ventas</span>
                </a>
            <?php endif; ?>

            <?php if ($verCompras): ?>
                <a href="/modules/sap/compras/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
                    <span class="sap-tile-icon"><i class="bi bi-cart"></i></span>
                    <span>Compras</span>
                </a>
            <?php endif; ?>

            <?php if ($verLogistica): ?>
                <a href="/modules/sap/logistica/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
                    <span class="sap-tile-icon"><i class="bi bi-signpost-split"></i></span>
                    <span>Logística</span>
                </a>
            <?php endif; ?>

            <?php if ($verCalidad): ?>
                <a href="/modules/sap/calidad/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
                    <span class="sap-tile-icon"><i class="bi bi-patch-check"></i></span>
                    <span>Calidad</span>
                </a>
            <?php endif; ?>

            <?php if ($verPrecios): ?>
                <a href="/modules/sap/precios/?empresa=<?= rawurlencode($empresa) ?>" class="sap-tile">
                    <span class="sap-tile-icon"><i class="bi bi-tag"></i></span>
                    <span>Precios</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
