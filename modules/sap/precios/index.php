<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap_precios');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

ob_start();

$empresa = ApiFaretClient::empresaActual();
$item = trim($_GET['item'] ?? '');

function formatoCantidad($n)
{
    if ($n === null || $n === '') {
        return '-';
    }

    $n = (float) $n;
    $decimales = floor($n) == $n ? 0 : 2;

    return number_format($n, $decimales, ',', '.');
}

$listas = [];
$respuestaListas = ApiFaretClient::get('listasprecios?soloActivas=true', $empresa);

if ($respuestaListas['ok']) {
    $listas = $respuestaListas['data']['data'] ?? [];
}

$fichaPrecios = [];
$respuestaPrecios = null;

if ($item !== '') {
    $respuestaPrecios = ApiFaretClient::get('articulos/' . rawurlencode($item) . '/precios', $empresa);

    if ($respuestaPrecios['ok']) {
        $fichaPrecios = $respuestaPrecios['data']['data'] ?? [];
    }
}

?>

<div class="hero">
    <h1>Precios SAP</h1>
    <p>Listas de precios y precio de un artículo por lista. Acceso restringido por permiso separado. Solo lectura.</p>
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

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Listas de precios en <?= htmlspecialchars($empresa) ?></h2>
            <p><?= count($listas) ?> listas activas.</p>
        </div>
    </div>

    <?php if (!$respuestaListas['ok']): ?>
        <div class="card">
            <h2>Error de conexión con apifaret</h2>
            <p>No se pudo obtener el catálogo de listas de precios. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaListas)) ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nombre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listas as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['numero'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($l['nombre'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($listas) === 0): ?>
                        <tr>
                            <td colspan="2">Sin listas de precios activas en <?= htmlspecialchars($empresa) ?>.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($item === ''): ?>
    <div class="sap-guide">
        <span class="sap-guide-icon"><i class="bi bi-tag"></i></span>
        <div>
            <h3>Precio de un artículo</h3>
            <p>Escribe el código exacto del artículo abajo. ¿No lo tienes? Búscalo por nombre en <a href="/modules/sap/inventario/?empresa=<?= rawurlencode($empresa) ?>">Inventario</a> y vuelve con el código.</p>
        </div>
    </div>
<?php endif; ?>

<form class="filter-card" method="GET">
    <input type="hidden" name="empresa" value="<?= htmlspecialchars($empresa) ?>">

    <div class="filter-group">
        <label>Ver precios de un artículo (código exacto)</label>
        <input type="text" name="item" maxlength="100" placeholder="Ej: 2001017008459" value="<?= htmlspecialchars($item) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn-primary">
            <i class="bi bi-search"></i>
            Buscar
        </button>

        <?php if ($item !== ''): ?>
            <a href="?empresa=<?= rawurlencode($empresa) ?>" class="btn-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($item !== ''): ?>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Precios de <?= htmlspecialchars($item) ?></h2>
                <p><?= htmlspecialchars($fichaPrecios[0]['itemName'] ?? '') ?></p>
            </div>
        </div>

        <?php if (!$respuestaPrecios['ok']): ?>
            <div class="card">
                <h2>Error de conexión con apifaret</h2>
                <p>No se pudo consultar el precio del artículo. <?= htmlspecialchars(ApiFaretClient::mensajeError($respuestaPrecios)) ?></p>
            </div>
        <?php elseif (count($fichaPrecios) === 0): ?>
            <p>El artículo no existe en <?= htmlspecialchars($empresa) ?>.</p>
        <?php else: ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lista</th>
                            <th>Precio</th>
                            <th>Moneda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($fichaPrecios[0]['precios'] ?? []) as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['listaNombre'] ?? $p['listaNumero'] ?? '-') ?></td>
                                <td><?= formatoCantidad($p['precio'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($p['moneda'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($fichaPrecios[0]['precios'] ?? []) === 0): ?>
                            <tr>
                                <td colspan="3">Sin precios registrados para este artículo.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
