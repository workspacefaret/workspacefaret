<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireLogin();

$usuario = currentUser();
$pdo = getAuthPdo();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $area = trim($_POST['area'] ?? '');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo ingresado no es válido.';
    } else {
        $pdo->prepare('
            INSERT INTO usuario_perfil (usuario_id, email, telefono, cargo, area, actualizado_en)
            VALUES (?, ?, ?, ?, ?, datetime(\'now\'))
            ON CONFLICT(usuario_id) DO UPDATE SET
                email = excluded.email,
                telefono = excluded.telefono,
                cargo = excluded.cargo,
                area = excluded.area,
                actualizado_en = excluded.actualizado_en
        ')->execute([$usuario['id'], $email, $telefono, $cargo, $area]);

        $mensaje = 'Perfil actualizado correctamente.';
    }
}

$perfil = obtenerPerfilUsuario($usuario['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $perfil = ['email' => $email, 'telefono' => $telefono, 'cargo' => $cargo, 'area' => $area];
}

$rolesCatalogo = obtenerRolesCatalogo();

ob_start();
?>

<div class="hero">
    <h1>Mi perfil</h1>
    <p><?= htmlspecialchars($usuario['nombre']) ?> (<?= htmlspecialchars($usuario['username']) ?>)</p>
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
            <h2>Datos de cuenta</h2>
            <p>Rol: <span class="badge"><?= htmlspecialchars($rolesCatalogo[$usuario['rol']] ?? $usuario['rol']) ?></span></p>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <div>
            <h2>Información personal</h2>
            <p>Estos datos son visibles solo para ti y el administrador del sistema.</p>
        </div>
    </div>

    <form method="POST" class="filter-card" style="grid-template-columns: repeat(2, 1fr);">
        <div class="filter-group">
            <label>Correo electrónico</label>
            <input type="email" name="email" value="<?= htmlspecialchars($perfil['email'] ?? '') ?>">
        </div>

        <div class="filter-group">
            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?= htmlspecialchars($perfil['telefono'] ?? '') ?>">
        </div>

        <div class="filter-group">
            <label>Cargo</label>
            <input type="text" name="cargo" value="<?= htmlspecialchars($perfil['cargo'] ?? '') ?>">
        </div>

        <div class="filter-group">
            <label>Área</label>
            <input type="text" name="area" value="<?= htmlspecialchars($perfil['area'] ?? '') ?>">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-save-fill"></i>
                Guardar cambios
            </button>
        </div>
    </form>
</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
