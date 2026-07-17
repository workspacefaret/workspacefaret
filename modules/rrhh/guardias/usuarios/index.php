<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('rrhh');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

ob_start();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $body = [
            'username' => trim($_POST['username'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'fullName' => trim($_POST['full_name'] ?? ''),
            'roleName' => trim($_POST['role_name'] ?? 'Guardia'),
            'isActive' => isset($_POST['is_active'])
        ];

        $respuesta = ApiClient::post('users', $body);

        if ($respuesta['ok']) {
            $mensaje = 'Usuario creado correctamente.';
        } else {
            $error = $respuesta['error'] ?? 'No se pudo crear el usuario.';
        }
    }

    if ($accion === 'activar') {
        $id = (int)($_POST['id'] ?? 0);
        $respuesta = ApiClient::patch("users/$id/activar");

        if ($respuesta['ok']) {
            $mensaje = 'Usuario activado correctamente.';
        } else {
            $error = $respuesta['error'] ?? 'No se pudo activar el usuario.';
        }
    }

    if ($accion === 'desactivar') {
        $id = (int)($_POST['id'] ?? 0);
        $respuesta = ApiClient::patch("users/$id/desactivar");

        if ($respuesta['ok']) {
            $mensaje = 'Usuario desactivado correctamente.';
        } else {
            $error = $respuesta['error'] ?? 'No se pudo desactivar el usuario.';
        }
    }
}

$respuestaUsuarios = ApiClient::get('users');
$usuarios = [];

if ($respuestaUsuarios['ok'] && is_array($respuestaUsuarios['data'])) {
    $usuarios = $respuestaUsuarios['data'];
}

$totalUsuarios = count($usuarios);
$activos = 0;

foreach ($usuarios as $u) {
    if (!empty($u['isActive'])) {
        $activos++;
    }
}
$inactivos = $totalUsuarios - $activos;

?>

<div class="hero">
    <h1>Administración de usuarios</h1>
    <p>Gestión de usuarios autorizados para la aplicación Guardias.</p>
</div>

<?php if ($mensaje): ?>
    <div class="card" style="border-color:#16a34a;">
        <strong><?= htmlspecialchars($mensaje) ?></strong>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="card" style="border-color:#dc2626;">
        <strong>Error:</strong>
        <pre><?= htmlspecialchars($error) ?></pre>
    </div>
<?php endif; ?>

<?php if (!$respuestaUsuarios['ok']): ?>

    <div class="card">
        <h2>Error de conexión API</h2>
        <p>No se pudo obtener el listado de usuarios.</p>
        <p>Estado HTTP: <strong><?= htmlspecialchars($respuestaUsuarios['status']) ?></strong></p>
        <pre><?= htmlspecialchars($respuestaUsuarios['error'] ?? 'Sin detalle') ?></pre>
    </div>

<?php else: ?>

    <div class="kpi-grid">
        <div class="kpi-card">
            <span>Total usuarios</span>
            <strong><?= $totalUsuarios ?></strong>
        </div>

        <div class="kpi-card">
            <span>Activos</span>
            <strong><?= $activos ?></strong>
        </div>

        <div class="kpi-card">
            <span>Inactivos</span>
            <strong><?= $inactivos ?></strong>
        </div>

        <div class="kpi-card">
            <span>Sistema</span>
            <strong>Guardias</strong>
        </div>
    </div>

    <div class="table-card" style="margin-bottom: 28px;">
        <div class="table-header">
            <div>
                <h2>Crear usuario</h2>
                <p>Ingrese los datos del nuevo usuario para la app Guardias.</p>
            </div>
        </div>

        <form method="POST" class="filter-card" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 0;">
            <input type="hidden" name="accion" value="crear">

            <div class="filter-group">
                <label>Usuario</label>
                <input type="text" name="username" required>
            </div>

            <div class="filter-group">
                <label>Nombre completo</label>
                <input type="text" name="full_name" required>
            </div>

            <div class="filter-group">
                <label>Contraseña</label>
                <input type="text" name="password" required>
            </div>

            <div class="filter-group">
                <label>Rol</label>
                <select name="role_name">
                    <option value="Guardia">Guardia</option>
                    <option value="Supervisor">Supervisor</option>
                    <option value="Admin">Admin</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Activo</label>
                <select name="is_active">
                    <option value="1" selected>Sí</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-primary">
                    <i class="bi bi-person-plus-fill"></i>
                    Crear
                </button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-header">
            <div>
                <h2>Usuarios Guardias</h2>
                <p>Listado de usuarios registrados en la base de datos guardias_app.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <?php
                        $activo = !empty($u['isActive']);
                        $fecha = !empty($u['createdAt']) ? date('d-m-Y H:i', strtotime($u['createdAt'])) : '-';
                        ?>

                        <tr>
                            <td>#<?= htmlspecialchars($u['id'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['username'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['fullName'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['roleName'] ?? '-') ?></td>
                            <td>
                                <span class="status-badge <?= $activo ? 'status-ok' : 'status-pending' ?>">
                                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($fecha) ?></td>
                            <td>
                                <?php if ($activo): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="desactivar">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                        <button type="submit" class="btn-secondary">
                                            Desactivar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="activar">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                        <button type="submit" class="btn-primary">
                                            Activar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (count($usuarios) === 0): ?>
                        <tr>
                            <td colspan="7">No hay usuarios registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
