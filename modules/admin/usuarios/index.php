<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireAdminTi();

ob_start();

$pdo = getAuthPdo();
$rolesCatalogo = obtenerRolesCatalogo();
$modulosCatalogo = obtenerModulosCatalogo();

$mensaje = '';
$passwordTemporal = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $rol = $_POST['rol'] ?? '';
        $modulosSeleccionados = $_POST['modulos'] ?? [];

        if ($nombre === '' || $username === '') {
            $error = 'Nombre y usuario son obligatorios.';
        } elseif (!array_key_exists($rol, $rolesCatalogo)) {
            $error = 'Rol inválido.';
        } else {
            $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE username = ?');
            $existe->execute([$username]);

            if ((int) $existe->fetchColumn() > 0) {
                $error = 'Ya existe un usuario con ese nombre de usuario.';
            } else {
                $passwordTemporal = generarPasswordTemporal();

                $stmt = $pdo->prepare('
                    INSERT INTO usuarios (nombre, username, password_hash, rol, activo, debe_cambiar_password)
                    VALUES (?, ?, ?, ?, 1, 1)
                ');
                $stmt->execute([$nombre, $username, password_hash($passwordTemporal, PASSWORD_DEFAULT), $rol]);

                $nuevoId = (int) $pdo->lastInsertId();

                if ($rol !== 'admin_ti' && is_array($modulosSeleccionados)) {
                    $insertModulo = $pdo->prepare('INSERT INTO usuario_modulos (usuario_id, modulo_clave) VALUES (?, ?)');

                    foreach ($modulosSeleccionados as $clave) {
                        if (array_key_exists($clave, $modulosCatalogo)) {
                            $insertModulo->execute([$nuevoId, $clave]);
                        }
                    }
                }

                $mensaje = "Usuario \"$username\" creado. Contraseña temporal (entrégala solo una vez, se pedirá cambiarla al ingresar):";
            }
        }
    }

    if ($accion === 'toggle_activo') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === currentUser()['id']) {
            $error = 'No puedes activar/desactivar tu propio usuario.';
        } else {
            $pdo->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
            $mensaje = 'Estado del usuario actualizado.';
        }
    }
}

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);

$modulosPorUsuario = [];
$stmtModulos = $pdo->prepare('SELECT modulo_clave FROM usuario_modulos WHERE usuario_id = ?');

foreach ($usuarios as $u) {
    if ($u['rol'] === 'admin_ti') {
        continue;
    }

    $stmtModulos->execute([$u['id']]);
    $modulosPorUsuario[$u['id']] = $stmtModulos->fetchAll(PDO::FETCH_COLUMN);
}

?>

<div class="hero">
    <h1>Administración de usuarios</h1>
    <p>Crea usuarios, asigna roles y controla el acceso a los módulos del portal.</p>
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
            <h2>Crear usuario</h2>
            <p>La contraseña inicial se genera automáticamente y se debe cambiar en el primer ingreso.</p>
        </div>
    </div>

    <form method="POST" class="filter-card" style="grid-template-columns: repeat(3, 1fr);">
        <input type="hidden" name="accion" value="crear">

        <div class="filter-group">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required>
        </div>

        <div class="filter-group">
            <label>Usuario</label>
            <input type="text" name="username" required>
        </div>

        <div class="filter-group">
            <label>Rol</label>
            <select name="rol" required>
                <?php foreach ($rolesCatalogo as $clave => $label): ?>
                    <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group" style="grid-column: 1 / -1;">
            <label>Módulos con acceso (no aplica para Admin TI, que ya ve todo)</label>
            <div style="display:flex; flex-wrap:wrap; gap:14px; margin-top:6px;">
                <?php foreach ($modulosCatalogo as $clave => $modulo): ?>
                    <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                        <input type="checkbox" name="modulos[]" value="<?= htmlspecialchars($clave) ?>">
                        <?= htmlspecialchars($modulo['label']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-person-plus-fill"></i>
                Crear usuario
            </button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Usuarios del portal</h2>
            <p>Listado de cuentas registradas.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Módulos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php $activo = (int) $u['activo'] === 1; ?>

                    <tr>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($rolesCatalogo[$u['rol']] ?? $u['rol']) ?></td>
                        <td>
                            <?php if ($u['rol'] === 'admin_ti'): ?>
                                <span class="badge badge-primary">Todos</span>
                            <?php elseif (empty($modulosPorUsuario[$u['id']])): ?>
                                <span class="status-badge status-pending">Sin asignar</span>
                            <?php else: ?>
                                <?php foreach ($modulosPorUsuario[$u['id']] as $clave): ?>
                                    <span class="badge"><?= htmlspecialchars($modulosCatalogo[$clave]['label'] ?? $clave) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $activo ? 'status-ok' : 'status-pending' ?>">
                                <?= $activo ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="display:flex; gap:8px;">
                            <a href="/modules/admin/usuarios/detalle.php?id=<?= (int) $u['id'] ?>" class="btn-secondary">
                                Editar
                            </a>

                            <?php if ((int) $u['id'] !== currentUser()['id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="toggle_activo">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn-secondary">
                                        <?= $activo ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($usuarios) === 0): ?>
                    <tr>
                        <td colspan="6">No hay usuarios registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
