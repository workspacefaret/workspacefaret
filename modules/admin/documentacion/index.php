<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireAdminTi();

ob_start();

$pdo = getAuthPdo();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $sistema = trim($_POST['sistema'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');

        if ($sistema === '' || $titulo === '' || $contenido === '') {
            $error = 'Sistema, título y contenido son obligatorios.';
        } else {
            $pdo->prepare('INSERT INTO documentos_tecnicos (sistema, titulo, contenido, creado_por, creado_en) VALUES (?, ?, ?, ?, ?)')
                ->execute([$sistema, $titulo, $contenido, currentUser()['id'], nowLocal()]);

            $mensaje = 'Documento publicado correctamente.';
        }
    }

    if ($accion === 'editar') {
        $id = (int) ($_POST['id'] ?? 0);
        $sistema = trim($_POST['sistema'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');

        if ($sistema === '' || $titulo === '' || $contenido === '') {
            $error = 'Sistema, título y contenido son obligatorios.';
        } else {
            $pdo->prepare('UPDATE documentos_tecnicos SET sistema = ?, titulo = ?, contenido = ?, actualizado_en = ? WHERE id = ?')
                ->execute([$sistema, $titulo, $contenido, nowLocal(), $id]);

            $mensaje = 'Documento actualizado correctamente.';
        }
    }

    if ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE documentos_tecnicos SET activo = 1 - activo WHERE id = ?')->execute([$id]);
        $mensaje = 'Estado del documento actualizado.';
    }
}

$editando = null;

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM documentos_tecnicos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$documentos = $pdo->query('
    SELECT d.*, u.nombre AS autor
    FROM documentos_tecnicos d
    LEFT JOIN usuarios u ON u.id = d.creado_por
    ORDER BY d.sistema, d.titulo
')->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="hero">
    <h1>Documentación Técnica</h1>
    <p>Biblioteca interna de documentación de todos los sistemas de Faret. Solo Admin TI puede publicar, editar u ocultar documentos. El contenido se guarda como HTML y se muestra tal cual a quien tenga el permiso "Documentación Técnica" — revísalo antes de publicar.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="border-color:#16a34a;">
        <strong><?= htmlspecialchars($mensaje) ?></strong>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="card" style="border-color:#dc2626;">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="table-card" style="margin-bottom: 28px;">
    <div class="table-header">
        <div>
            <h2>Documentos publicados</h2>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sistema</th>
                    <th>Título</th>
                    <th>Autor</th>
                    <th>Publicado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($documentos as $d): ?>
                    <?php $activo = (int) $d['activo'] === 1; ?>

                    <tr>
                        <td><?= htmlspecialchars($d['sistema']) ?></td>
                        <td><?= htmlspecialchars($d['titulo']) ?></td>
                        <td><?= htmlspecialchars($d['autor'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($d['actualizado_en'] ?? $d['creado_en']) ?></td>
                        <td>
                            <span class="status-badge <?= $activo ? 'status-ok' : 'status-pending' ?>">
                                <?= $activo ? 'Visible' : 'Oculto' ?>
                            </span>
                        </td>
                        <td>
                            <a href="/modules/documentacion/detalle.php?id=<?= (int) $d['id'] ?>" class="btn-secondary" target="_blank">Ver</a>
                            <a href="/modules/admin/documentacion/?editar=<?= (int) $d['id'] ?>" class="btn-secondary">Editar</a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                <button type="submit" class="btn-secondary">
                                    <?= $activo ? 'Ocultar' : 'Activar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($documentos) === 0): ?>
                    <tr>
                        <td colspan="6">No hay documentos publicados todavía.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<details style="margin-top: 8px;" <?= $editando ? 'open' : '' ?>>
    <summary class="btn-secondary" style="display:inline-flex; cursor:pointer; user-select:none;">
        <i class="bi <?= $editando ? 'bi-pencil-square' : 'bi-plus-lg' ?>"></i>
        <?= $editando ? 'Editando: ' . htmlspecialchars($editando['titulo']) : 'Agregar nueva documentación' ?>
    </summary>

    <div class="table-card" style="margin-top: 12px;">
        <div class="table-header">
            <div>
                <h2><?= $editando ? 'Editar documento' : 'Publicar documento' ?></h2>
                <p>
                    Indica el sistema al que pertenece (ej. "Workspace Faret", "Formularios.Api", "fps-api")
                    y el contenido en HTML. El documento se muestra en su propia página, con la plantilla
                    de Documentación Técnica (no con el estilo del portal) — divide el contenido en bloques
                    <code>&lt;section&gt;&lt;h2&gt;Título&lt;/h2&gt;...&lt;/section&gt;</code>, uno por tema:
                    cada uno se numera solo y aparece automáticamente en el índice lateral.
                    Clases disponibles dentro de una sección: <code>callout</code> (nota destacada, agregar <code>warn</code> para advertencia),
                    <code>table-wrap</code> + <code>table</code> (tablas), <code>pill</code>/<code>status-pill</code> (etiquetas, con
                    <code>pill-ok</code>/<code>pill-warn</code>/<code>pill-info</code>), <code>grid-2</code> + <code>card</code> (tarjetas en dos columnas),
                    <code>kpi-row</code> + <code>kpi</code> (indicadores numéricos).
                </p>
            </div>
        </div>

        <form method="POST" class="filter-card" style="grid-template-columns: 1fr;">
            <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'crear' ?>">
            <?php if ($editando): ?>
                <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
            <?php endif; ?>

            <div class="filter-group">
                <label>Sistema</label>
                <input type="text" name="sistema" list="sistemas-existentes" required
                    value="<?= htmlspecialchars($editando['sistema'] ?? '') ?>">
                <datalist id="sistemas-existentes">
                    <?php foreach (array_unique(array_column($documentos, 'sistema')) as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="filter-group">
                <label>Título</label>
                <input type="text" name="titulo" required value="<?= htmlspecialchars($editando['titulo'] ?? '') ?>">
            </div>

            <div class="filter-group">
                <label>Contenido (HTML)</label>
                <textarea name="contenido" rows="16" required
                    style="font-family:'Courier New',monospace; font-size:13px;"><?= htmlspecialchars($editando['contenido'] ?? '') ?></textarea>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="bi bi-journal-check"></i>
                    <?= $editando ? 'Guardar cambios' : 'Publicar' ?>
                </button>
                <?php if ($editando): ?>
                    <a href="/modules/admin/documentacion/" class="btn-secondary">Cancelar edición</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</details>

<?php

$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
