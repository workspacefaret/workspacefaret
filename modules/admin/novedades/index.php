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
        $titulo = trim($_POST['titulo'] ?? '');
        $cuerpo = trim($_POST['cuerpo'] ?? '');

        if ($titulo === '' || $cuerpo === '') {
            $error = 'Título y contenido son obligatorios.';
        } else {
            $pdo->prepare('INSERT INTO novedades (titulo, cuerpo, creado_por, creado_en) VALUES (?, ?, ?, ?)')
                ->execute([$titulo, $cuerpo, currentUser()['id'], nowLocal()]);

            $mensaje = 'Novedad publicada correctamente.';
        }
    }

    if ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE novedades SET activo = 1 - activo WHERE id = ?')->execute([$id]);
        $mensaje = 'Estado de la novedad actualizado.';
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM novedades WHERE id = ?')->execute([$id]);
        $mensaje = 'Novedad eliminada correctamente.';
    }
}

$novedades = $pdo->query('SELECT * FROM novedades ORDER BY creado_en DESC')->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="hero">
    <h1>Novedades del portal</h1>
    <p>Publica actualizaciones que se muestran a todos los usuarios en Operación.</p>
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
            <h2>Publicar novedad</h2>
            <p>Texto plano, sin formato — se muestra tal cual a todos los usuarios.</p>
        </div>
    </div>

    <form method="POST" class="filter-card" style="grid-template-columns: 1fr;">
        <input type="hidden" name="accion" value="crear">

        <div class="filter-group">
            <label>Título</label>
            <input type="text" name="titulo" required>
        </div>

        <div class="filter-group">
            <label>Contenido</label>
            <textarea name="cuerpo" rows="4" required></textarea>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-megaphone-fill"></i>
                Publicar
            </button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Novedades publicadas</h2>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Contenido</th>
                    <th>Publicado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($novedades as $n): ?>
                    <?php $activo = (int) $n['activo'] === 1; ?>

                    <tr>
                        <td><?= htmlspecialchars($n['titulo']) ?></td>
                        <td><?= htmlspecialchars($n['cuerpo']) ?></td>
                        <td><?= htmlspecialchars($n['creado_en']) ?></td>
                        <td>
                            <span class="status-badge <?= $activo ? 'status-ok' : 'status-pending' ?>">
                                <?= $activo ? 'Activa' : 'Oculta' ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="accion" value="toggle_activo">
                                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" class="btn-secondary">
                                    <?= $activo ? 'Ocultar' : 'Activar' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar definitivamente esta novedad? Esta acción no se puede deshacer.');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" class="btn-secondary" style="color:#dc2626;">
                                    Eliminar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($novedades) === 0): ?>
                    <tr>
                        <td colspan="5">No hay novedades publicadas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
