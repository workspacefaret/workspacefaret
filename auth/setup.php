<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$pdo = getAuthPdo();
$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

$error = null;
$exito = false;

if ($totalUsuarios === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if ($nombre === '' || $username === '' || $password === '') {
        $error = 'Completa todos los campos.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        // Reconfirma dentro de la misma solicitud para evitar una carrera si dos personas
        // envían el formulario de setup al mismo tiempo.
        $totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

        if ($totalUsuarios > 0) {
            $error = 'El setup ya fue completado por otra solicitud.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO usuarios (nombre, username, password_hash, rol, activo, debe_cambiar_password)
                VALUES (?, ?, ?, ?, 1, 0)
            ');
            $stmt->execute([$nombre, $username, password_hash($password, PASSWORD_DEFAULT), 'admin_ti']);

            $exito = true;
            $totalUsuarios = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración inicial — Workspace Faret</title>
    <link rel="stylesheet" href="/assets/css/auth/auth.css">
</head>

<body>

    <div class="auth-card">

        <div class="auth-logos">
            <img src="/assets/img/logo-faret.png" alt="Faret">
            <img src="/assets/img/logo-innpack.png" alt="Innpack">
        </div>

        <h1>Configuración inicial</h1>

        <?php if ($totalUsuarios > 0 && !$exito): ?>

            <div class="auth-error">El setup ya fue completado. Este formulario ya no está disponible.</div>
            <a href="/auth/login.php" class="auth-link">Ir a iniciar sesión</a>

        <?php elseif ($exito): ?>

            <div class="auth-success">Usuario administrador creado correctamente.</div>
            <a href="/auth/login.php" class="auth-link">Ir a iniciar sesión</a>

        <?php else: ?>

            <?php if ($error): ?>
                <div class="auth-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="/auth/setup.php">

                <div class="auth-field">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre" required autofocus>
                </div>

                <div class="auth-field">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" autocomplete="username" required>
                </div>

                <div class="auth-field">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8">
                </div>

                <div class="auth-field">
                    <label for="confirmar">Confirmar contraseña</label>
                    <input type="password" id="confirmar" name="confirmar" autocomplete="new-password" required minlength="8">
                </div>

                <button type="submit" class="btn-auth">Crear administrador</button>

            </form>

        <?php endif; ?>

    </div>

</body>

</html>
