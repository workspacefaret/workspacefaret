<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('documentacion');

ob_start();

$pdo = getAuthPdo();

$sistemaFiltro = trim($_GET['sistema'] ?? '');

$sql = 'SELECT d.id, d.sistema, d.titulo, d.creado_en, d.actualizado_en, u.nombre AS autor
        FROM documentos_tecnicos d
        LEFT JOIN usuarios u ON u.id = d.creado_por
        WHERE d.activo = 1';
$params = [];

if ($sistemaFiltro !== '') {
    $sql .= ' AND d.sistema = ?';
    $params[] = $sistemaFiltro;
}

$sql .= ' ORDER BY d.sistema, d.titulo';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sistemas = obtenerSistemasDocumentacion();

$porSistema = [];
foreach ($documentos as $doc) {
    $porSistema[$doc['sistema']][] = $doc;
}

?>

<div class="hero">
    <h1>Documentación Técnica</h1>
    <p>Documentación interna de los sistemas de Faret: arquitectura, módulos y criterios de uso.</p>
</div>

<?php if (count($sistemas) > 1): ?>
    <form method="GET" class="filter-card">
        <div class="filter-group">
            <label>Sistema</label>
            <select name="sistema" onchange="this.form.submit()">
                <option value="">Todos los sistemas</option>
                <?php foreach ($sistemas as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $s === $sistemaFiltro ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
<?php endif; ?>

<?php if (count($documentos) === 0): ?>
    <div class="card">
        <p>Todavía no hay documentos publicados<?= $sistemaFiltro !== '' ? ' para "' . htmlspecialchars($sistemaFiltro) . '"' : '' ?>.</p>
    </div>
<?php endif; ?>

<?php foreach ($porSistema as $sistema => $docs): ?>
    <div class="table-card" style="margin-bottom: 24px;">
        <div class="table-header">
            <div>
                <h2><?= htmlspecialchars($sistema) ?></h2>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Autor</th>
                        <th>Última actualización</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['titulo']) ?></td>
                            <td><?= htmlspecialchars($d['autor'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($d['actualizado_en'] ?? $d['creado_en']) ?></td>
                            <td>
                                <a href="/modules/documentacion/detalle.php?id=<?= (int) $d['id'] ?>" class="btn-secondary">
                                    <i class="bi bi-file-earmark-text"></i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php

$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
