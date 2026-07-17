<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireAdminTi();

$pdo = getAuthPdo();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    mostrarAccesoDenegado('El usuario solicitado no existe.');
}

ob_start();

$rolesCatalogo = obtenerRolesCatalogo();
$modulosCatalogo = obtenerModulosCatalogo();

$mensaje = '';
$passwordTemporal = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar') {
        $nombre = trim($_POST['nombre'] ?? '');
        $rol = $_POST['rol'] ?? '';
        $modulosSeleccionados = $_POST['modulos'] ?? [];

        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } elseif (!array_key_exists($rol, $rolesCatalogo)) {
            $error = 'Rol inválido.';
        } else {
            $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ? WHERE id = ?')
                ->execute([$nombre, $rol, $id]);

            $pdo->prepare('DELETE FROM usuario_modulos WHERE usuario_id = ?')->execute([$id]);

            if ($rol !== 'admin_ti' && is_array($modulosSeleccionados)) {
                $insertModulo = $pdo->prepare('INSERT INTO usuario_modulos (usuario_id, modulo_clave) VALUES (?, ?)');

                foreach ($modulosSeleccionados as $clave) {
                    if (array_key_exists($clave, $modulosCatalogo)) {
                        $insertModulo->execute([$id, $clave]);
                    }
                }
            }

            $usuario['nombre'] = $nombre;
            $usuario['rol'] = $rol;
            $mensaje = 'Usuario actualizado correctamente.';
        }
    }

    if ($accion === 'resetear_password') {
        $passwordTemporal = generarPasswordTemporal();

        $pdo->prepare('
            UPDATE usuarios
            SET password_hash = ?, debe_cambiar_password = 1, intentos_fallidos = 0, bloqueado_hasta = NULL
            WHERE id = ?
        ')->execute([password_hash($passwordTemporal, PASSWORD_DEFAULT), $id]);

        $mensaje = 'Contraseña restablecida. Entrégasela al usuario, se le pedirá cambiarla en el próximo ingreso:';
    }

    if ($accion === 'toggle_activo') {
        if ($id === currentUser()['id']) {
            $error = 'No puedes activar/desactivar tu propio usuario.';
        } else {
            $pdo->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
            $usuario['activo'] = (int) $usuario['activo'] === 1 ? 0 : 1;
            $mensaje = 'Estado del usuario actualizado.';
        }
    }
}

$stmtModulos = $pdo->prepare('SELECT modulo_clave FROM usuario_modulos WHERE usuario_id = ?');
$stmtModulos->execute([$id]);
$modulosAsignados = $stmtModulos->fetchAll(PDO::FETCH_COLUMN);

$activo = (int) $usuario['activo'] === 1;

?>

<div class="hero">
    <h1>Editar usuario</h1>
    <p><?= htmlspecialchars($usuario['nombre']) ?> (<?= htmlspecialchars($usuario['username']) ?>)</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="border-color:#16a34a;">
        <strong><?= htmlspecialchars($mensaje) ?></strong>
        <?php if ($passwordTemporal): ?>
            <p style="font-size:1.4rem; font-weight:700; margin-top:8px; letter-spacing:1px;">
                <?= htmlspecialchars($passwordTemporal) ?>
            </p>
        <?php endif; ?>
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
            <h2>Datos y acceso</h2>
            <p>
                Estado:
                <span class="status-badge <?= $activo ? 'status-ok' : 'status-pending' ?>">
                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                </span>
                <?php if ((int) $usuario['debe_cambiar_password'] === 1): ?>
                    <span class="badge">Pendiente cambio de contraseña</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <form method="POST" class="filter-card" style="grid-template-columns: repeat(2, 1fr);">
        <input type="hidden" name="accion" value="actualizar">

        <div class="filter-group">
            <label>Nombre completo</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
        </div>

        <div class="filter-group">
            <label>Rol</label>
            <select name="rol" required>
                <?php foreach ($rolesCatalogo as $clave => $label): ?>
                    <option value="<?= htmlspecialchars($clave) ?>" <?= $usuario['rol'] === $clave ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="grid-column: 1 / -1;">
            <label>Módulos con acceso (no aplica para Admin TI, que ya ve todo)</label>
            <div style="display:flex; flex-wrap:wrap; gap:14px; margin-top:6px;">
                <?php foreach ($modulosCatalogo as $clave => $modulo): ?>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                        <input type="checkbox" name="modulos[]" value="<?= htmlspecialchars($clave) ?>"
                            <?= in_array($clave, $modulosAsignados, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($modulo['label']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-save-fill"></i>
                Guardar cambios
            </button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Acciones</h2>
        </div>
    </div>

    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <form method="POST" onsubmit="return confirm('¿Restablecer la contraseña de este usuario?');">
            <input type="hidden" name="accion" value="resetear_password">
            <button type="submit" class="btn-secondary">
                <i class="bi bi-key-fill"></i>
                Restablecer contraseña
            </button>
        </form>

        <?php if ($id !== currentUser()['id']): ?>
            <form method="POST">
                <input type="hidden" name="accion" value="toggle_activo">
                <button type="submit" class="btn-secondary">
                    <?= $activo ? 'Desactivar usuario' : 'Activar usuario' ?>
                </button>
            </form>
        <?php endif; ?>

        <a href="/modules/admin/usuarios/" class="btn-secondary">Volver al listado</a>
    </div>
</div>

<?php

$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
