<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$texto = trim($_GET['texto'] ?? '');
$proveedor = trim($_GET['proveedor'] ?? '');

// Arma un link "?empresa=..&texto=..&proveedor=.." combinando los filtros ya
// activos con el nuevo, mismo patrón que urlInventario() en inventario/index.php.
function urlProveedores($empresa, $texto, $proveedor, array $nuevo)
{
    $params = array_filter(
        $nuevo + ['empresa' => $empresa, 'texto' => $texto, 'proveedor' => $proveedor],
        fn($valor) => $valor !== ''
    );

    return '?' . http_build_query($params);
}

$proveedores = [];
$respuestaBusqueda = null;

if ($texto !== '') {
    $respuestaBusqueda = ApiFaretClient::get('proveedores/buscar?texto=' . rawurlencode($texto) . '&top=50', $empresa);

    if ($respuestaBusqueda['ok']) {
        $proveedores = $respuestaBusqueda['data']['data'] ?? [];
    }
}

$fichaProveedor = [];
$respuestaFicha = null;

if ($proveedor !== '') {
    $respuestaFicha = ApiFaretClient::get('proveedores/' . rawurlencode($proveedor), $empresa);

    if ($respuestaFicha['ok']) {
        $fichaProveedor = $respuestaFicha['data']['data'] ?? [];
    }
}

?>

<div class="hero">
    <h1>Proveedores SAP</h1>
    <p>Buscar proveedores por código o nombre. Datos mínimos, sin información financiera. Solo lectura.</p>
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
    <input type="text" id="buscarProveedorTexto" name="texto" maxlength="100" placeholder="Buscar proveedor por código o nombre..." value="<?= htmlspecialchars($texto) ?>" data-sap-autocomplete="proveedores" data-sap-target="proveedor">
    <button type="submit">Buscar</button>
</form>

<?php if ($texto !== '' || $proveedor !== ''): ?>
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
                <h2>Proveedores para "<?= htmlspecialchars($texto) ?>"</h2>
                <p><?= count($proveedores) ?> resultados en <?= htmlspecialchars($empresa) ?> (máximo 50).</p>
            </div>
        </div>

        <?php if (!$respuestaBusqueda['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo realizar la búsqueda de proveedores. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaBusqueda)) ?></p>
            </div>
        <?php elseif (count($proveedores) === 0): ?>
            <p style="color:var(--muted);">No se encontraron proveedores.</p>
        <?php else: ?>

            <?php foreach ($proveedores as $p): ?>
                <a class="sap-list-row" href="<?= htmlspecialchars(urlProveedores($empresa, $texto, $proveedor, ['proveedor' => $p['cardCode'] ?? ''])) ?>" aria-label="Ver ficha de <?= htmlspecialchars($p['cardCode'] ?? '') ?>">
                    <span class="sap-list-icon"><i class="bi bi-truck"></i></span>
                    <span class="sap-list-main">
                        <span class="sap-list-title"><?= htmlspecialchars($p['cardName'] ?? '-') ?></span><br>
                        <span class="sap-list-sub"><?= htmlspecialchars($p['cardCode'] ?? '-') ?> · <?= htmlspecialchars($p['grupoNombre'] ?? $p['grupoCodigo'] ?? '-') ?> · <?= htmlspecialchars($p['ciudad'] ?? '-') ?></span>
                    </span>
                    <i class="bi bi-chevron-right sap-list-chevron"></i>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php if ($proveedor !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Proveedor <?= htmlspecialchars($proveedor) ?></h2>
                <p><?= htmlspecialchars($fichaProveedor[0]['cardName'] ?? '') ?></p>
            </div>
        </div>

        <?php if (!$respuestaFicha['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo consultar el proveedor. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaFicha)) ?></p>
            </div>
        <?php elseif (count($fichaProveedor) === 0): ?>
            <p>El proveedor no existe en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>

            <?php if (hasModuleAccess('portal_sap_compras')): ?>
                <p>
                    <a class="btn-secondary" href="/modules/sap/compras/?empresa=<?= rawurlencode($empresa) ?>&proveedor=<?= rawurlencode($proveedor) ?>">
                        <i class="bi bi-cart"></i>
                        Ver pedidos de compra de este proveedor
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
                            <th>Ciudad</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fichaProveedor as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['empresa'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['cardCode'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['cardName'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($f['grupoNombre'] ?? $f['grupoCodigo'] ?? '-') ?></td>
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
                    'proveedores',
                    <?= json_encode($fichaProveedor[0]['cardCode'] ?? '') ?>,
                    <?= json_encode($fichaProveedor[0]['cardName'] ?? '') ?>,
                    <?= json_encode($fichaProveedor[0]['cardCode'] ?? '') ?>
                );
            </script>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
