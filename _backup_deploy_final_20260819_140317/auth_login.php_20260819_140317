<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

function siguienteUrlSegura(?string $next): string
{
    if (!$next || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '://')) {
        return '/modules/operacion/';
    }

    return $next;
}

$siguiente = siguienteUrlSegura($_GET['next'] ?? null);

if (currentUser() !== null) {
    header('Location: ' . $siguiente);
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Ingresa usuario y contraseña.';
    } else {
        $resultado = intentarLogin($username, $password);

        if ($resultado['ok']) {
    $usuario = currentUser();

    if (!empty($usuario['debe_cambiar_password'])) {
        header('Location: /auth/cambiar-password.php');
        exit;
    }

    header('Location: ' . $siguiente);
    exit;
}

        $error = $resultado['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — Workspace Faret</title>
    <link rel="stylesheet" href="/assets/css/auth/auth.css">
</head>

<body>

    <div class="auth-card">

        <div class="auth-logos">
            <img src="/assets/img/logo-faret.png" alt="Faret">
            <img src="/assets/img/logo-innpack.png" alt="Innpack">
        </div>

        <h1>Iniciar sesión</h1>

        <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/auth/login.php?next=<?= urlencode($siguiente) ?>">

            <div class="auth-field">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus value="<?= htmlspecialchars($username) ?>">
            </div>

            <div class="auth-field">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-auth">Ingresar</button>

        </form>

        <a href="/modules/welcome/" class="auth-link">Volver al inicio</a>

    </div>

</body>

</html>
