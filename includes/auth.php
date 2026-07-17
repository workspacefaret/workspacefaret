<?php

const MAX_INTENTOS_FALLIDOS = 5;
const BLOQUEO_MINUTOS = 15;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function getAuthPdo(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/data';

    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0770, true);
    }

    $pdo = new PDO('sqlite:' . $dataDir . '/auth.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            rol TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1,
            debe_cambiar_password INTEGER NOT NULL DEFAULT 1,
            intentos_fallidos INTEGER NOT NULL DEFAULT 0,
            bloqueado_hasta TEXT NULL,
            creado_en TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS usuario_modulos (
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
            modulo_clave TEXT NOT NULL,
            PRIMARY KEY (usuario_id, modulo_clave)
        )
    ');

    return $pdo;
}

function obtenerModulosCatalogo(): array
{
    return require $_SERVER['DOCUMENT_ROOT'] . '/config/modulos.php';
}

function obtenerRolesCatalogo(): array
{
    return [
        'admin_ti' => 'Admin TI',
        'rrhh' => 'RRHH',
        'diseno' => 'Diseño',
        'logistica' => 'Logística',
        'calidad' => 'Calidad',
        'administrativo' => 'Administrativo',
    ];
}

function currentUser(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function requireLogin(): void
{
    $usuario = currentUser();

    if ($usuario === null) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /auth/login.php?next=' . $next);
        exit;
    }

    $rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if (
        !empty($usuario['debe_cambiar_password'])
        && $rutaActual !== '/auth/cambiar-password.php'
        && $rutaActual !== '/auth/logout.php'
    ) {
        header('Location: /auth/cambiar-password.php');
        exit;
    }
}

function currentUserModules(): array
{
    $usuario = currentUser();

    if ($usuario === null) {
        return [];
    }

    $stmt = getAuthPdo()->prepare('SELECT modulo_clave FROM usuario_modulos WHERE usuario_id = ?');
    $stmt->execute([$usuario['id']]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function hasModuleAccess(string $clave): bool
{
    $usuario = currentUser();

    if ($usuario === null) {
        return false;
    }

    if ($usuario['rol'] === 'admin_ti') {
        return true;
    }

    return in_array($clave, currentUserModules(), true);
}

function mostrarAccesoDenegado(string $mensaje): void
{
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<title>Acceso no autorizado</title>'
        . '<link rel="stylesheet" href="/assets/css/auth/auth.css"></head><body>'
        . '<div class="auth-card"><h1>Acceso no autorizado</h1>'
        . '<div class="auth-error">' . htmlspecialchars($mensaje) . '</div>'
        . '<a href="/modules/operacion/" class="auth-link">Volver al inicio</a></div></body></html>';
    exit;
}

function requireModuleAccess(string $clave): void
{
    requireLogin();

    if (!hasModuleAccess($clave)) {
        mostrarAccesoDenegado('Tu usuario no tiene acceso a este módulo. Solicita el permiso al administrador TI.');
    }
}

function requireAdminTi(): void
{
    requireLogin();

    if (currentUser()['rol'] !== 'admin_ti') {
        mostrarAccesoDenegado('Esta sección es exclusiva del administrador TI.');
    }
}

function generarPasswordTemporal(int $longitud = 10): string
{
    $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';

    for ($i = 0; $i < $longitud; $i++) {
        $password .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }

    return $password;
}

function intentarLogin(string $username, string $password): array
{
    $pdo = getAuthPdo();

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE username = ?');
    $stmt->execute([$username]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }

    if ((int) $usuario['activo'] === 0) {
        return ['ok' => false, 'error' => 'Este usuario está deshabilitado.'];
    }

    if (!empty($usuario['bloqueado_hasta']) && $usuario['bloqueado_hasta'] > gmdate('Y-m-d H:i:s')) {
        return ['ok' => false, 'error' => 'Cuenta bloqueada temporalmente por intentos fallidos. Intenta nuevamente en unos minutos.'];
    }

    if (!password_verify($password, $usuario['password_hash'])) {
        $intentos = (int) $usuario['intentos_fallidos'] + 1;
        $bloqueadoHasta = null;

        if ($intentos >= MAX_INTENTOS_FALLIDOS) {
            $bloqueadoHasta = gmdate('Y-m-d H:i:s', time() + BLOQUEO_MINUTOS * 60);
            $intentos = 0;
        }

        $update = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id = ?');
        $update->execute([$intentos, $bloqueadoHasta, $usuario['id']]);

        return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }

    $reset = $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
    $reset->execute([$usuario['id']]);

    $_SESSION['usuario'] = [
        'id' => (int) $usuario['id'],
        'nombre' => $usuario['nombre'],
        'username' => $usuario['username'],
        'rol' => $usuario['rol'],
        'debe_cambiar_password' => (bool) $usuario['debe_cambiar_password'],
    ];

    return ['ok' => true];
}

function cerrarSesionUsuario(): void
{
    $_SESSION = [];
    session_destroy();
}
