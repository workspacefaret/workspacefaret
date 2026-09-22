<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$texto = trim($_GET['texto'] ?? '');
$cliente = trim($_GET['cliente'] ?? '');

// Arma un link "?empresa=..&texto=..&cliente=.." combinando los filtros ya
// activos con el nuevo, mismo patrón que urlInventario() en inventario/index.php.
function urlClientes($empresa, $texto, $cliente, array $nuevo)
{
    $params = array_filter(
        $nuevo + ['empresa' => $empresa, 'texto' => $texto, 'cliente' => $cliente],
        fn($valor) => $valor !== ''
    );

    return '?' . http_build_query($params);
}

$clientes = [];
$respuestaBusqueda = null;

if ($texto !== '') {
    $respuestaBusqueda = ApiFaretClient::get('clientes/buscar?texto=' . rawurlencode($texto) . '&top=50', $empresa);

    if ($respuestaBusqueda['ok']) {
        $clientes = $respuestaBusqueda['data']['data'] ?? [];
    }
}

$fichaCliente = [];
$respuestaFicha = null;

if ($cliente !== '') {
    $respuestaFicha = ApiFaretClient::get('clientes/' . rawurlencode($cliente), $empresa);

    if ($respuestaFicha['ok']) {
        $fichaCliente = $respuestaFicha['data']['data'] ?? [];
    }
}

?>

<div class="hero">
    <h1>Clientes SAP</h1>
    <p>Buscar clientes por código o nombre. Datos mínimos, sin información financiera. Solo lectura.</p>
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

<form class="sap-search" method="GET">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">
    <span class="bi bi-search"></span>
    <input type="text" id="buscarClienteTexto" name="texto" maxlength="100" placeholder="Buscar cliente por código o nombre..." value="<?= htmlspecialchars($texto) ?>" data-sap-autocomplete="clientes" data-sap-target="cliente">
    <button type="submit">Buscar</button>
</form>

<?php if ($texto !== '' || $cliente !== ''): ?>
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
                <h2>Clientes para "<?= htmlspecialchars($texto) ?>"</h2>
                <p><?= count($clientes) ?> resultados en <?= htmlspecialchars($empresa) ?> (máximo 50).</p>
            </div>
        </div>

        <?php if (!$respuestaBusqueda['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo realizar la búsqueda de clientes. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusqueda)) ?></p>
            </div>
        <?php elseif (count($clientes) === 0): ?>
            <p style="color:var(--muted);">No se encontraron clientes.</p>
        <?php else: ?>

            <?php foreach ($clientes as $c): ?>
                <a class="sap-list-row" href="<?= htmlspecialchars(urlClientes($empresa, $texto, $cliente, ['cliente' => $c['cardCode'] ?? ''])) ?>" aria-label="Ver ficha de <?= htmlspecialchars($c['cardCode'] ?? '') ?>">
                    <span class="sap-list-icon"><i class="bi bi-person"></i></span>
                    <span class="sap-list-main">
                        <span class="sap-list-title"><?= htmlspecialchars($c['cardName'] ?? '-') ?></span><br>
                        <span class="sap-list-sub"><?= htmlspecialchars($c['cardCode'] ?? '-') ?> · <?= htmlspecialchars($c['grupoNombre'] ?? $c['grupoCodigo'] ?? '-') ?> · <?= htmlspecialchars($c['ciudad'] ?? '-') ?></span>
                    </span>
                    <i class="bi bi-chevron-right sap-list-chevron"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($cliente !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Cliente <?= htmlspecialchars($cliente) ?></h2>
                <p><?= htmlspecialchars($fichaCliente[0]['cardName'] ?? '') ?></p>
            </div>
        </div>

        <?php if (!$respuestaFicha['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo consultar el cliente. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaFicha)) ?></p>
            </div>
        <?php elseif (count($fichaCliente) === 0): ?>
            <p>El cliente no existe en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>

            <?php if (hasModuleAccess('portal_sap_ventas')): ?>
                <p>
                    <a class="btn-secondary" href="/modules/sap/ventas/?empresa=<?= rawurlencode($empresa) ?>&cliente=<?= rawurlencode($cliente) ?>">
                        <i class="bi bi-graph-up"></i>
                        Ver notas de venta, cotizaciones y facturas de este cliente
                    </a>
                </p>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Grupo</th>
                            <th>Vendedor</th>
                            <th>Ciudad</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fichaCliente as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['empresa'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['cardCode'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['cardName'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['grupoNombre'] ?? $f['grupoCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['vendedorCodigo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['ciudad'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['telefono'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['email'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <script>
                window.SapAutocomplete && window.SapAutocomplete.registrarReciente(
                    'clientes',
                    <?= json_encode($fichaCliente[0]['cardCode'] ?? '') ?>,
                    <?= json_encode($fichaCliente[0]['cardName'] ?? '') ?>,
                    <?= json_encode($fichaCliente[0]['cardCode'] ?? '') ?>
                );
            </script>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
