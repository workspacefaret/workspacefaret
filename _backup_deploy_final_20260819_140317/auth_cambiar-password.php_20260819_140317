<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

requireLogin();

$usuario = currentUser();

if (empty($usuario['debe_cambiar_password'])) {
    header('Location: /modules/operacion/');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if ($password === '' || $confirmar === '') {
        $error = 'Completa ambos campos.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $pdo = getAuthPdo();

        $stmt = $pdo->prepare('
            UPDATE usuarios
            SET password_hash = ?,
                debe_cambiar_password = 0,
                intentos_fallidos = 0,
                bloqueado_hasta = NULL
            WHERE id = ?
        ');

        $stmt->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $usuario['id'],
        ]);

        $_SESSION['usuario']['debe_cambiar_password'] = false;

        session_regenerate_id(true);

        header('Location: /modules/operacion/');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña — Workspace Faret</title>
    <link rel="stylesheet" href="/assets/css/auth/auth.css">
</head>

<body>

    <div class="auth-card">

        <div class="auth-logos">
            <img src="/assets/img/logo-faret.png" alt="Faret">
            <img src="/assets/img/logo-innpack.png" alt="Innpack">
        </div>

        <h1>Cambiar contraseña</h1>

        <p class="auth-instructions">
    Por seguridad, debes cambiar la contraseña temporal antes de continuar.
</p>
        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/auth/cambiar-password.php">

            <div class="auth-field">
                <label for="password">Nueva contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                    autofocus
                >
            </div>

            <div class="auth-field">
                <label for="confirmar">Confirmar contraseña</label>
                <input
                    type="password"
                    id="confirmar"
                    name="confirmar"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="btn-auth">
                Guardar nueva contraseña
            </button>

        </form>

    </div>

</body>

</html>