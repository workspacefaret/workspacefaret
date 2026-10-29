<?php
// Siembra usuarios FICTICIOS en el SQLite del docroot de prueba (argv[1]). Solo para php_gate_test.py; nunca se despliega.
$_SERVER['DOCUMENT_ROOT'] = $argv[1];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
$pdo = getAuthPdo();
$h = password_hash('Local1234!', PASSWORD_BCRYPT);
$u = $pdo->prepare('INSERT INTO usuarios (nombre,username,password_hash,rol,activo,debe_cambiar_password) VALUES (?,?,?,?,?,0)');
$m = $pdo->prepare('INSERT INTO usuario_modulos (usuario_id,modulo_clave,nivel) VALUES (?,?,?)');
$usuarios = [
    ['Miguel G', 't_gest', 'diseno', 1, 'gestionar'],
    ['Solo Ver', 't_ver', 'diseno', 1, 'ver'],
    ['Sin Modulo', 't_none', 'diseno', 1, null],
    ['Otro Modulo', 't_otro', 'diseno', 1, '__otro'],
    ['Inactivo', 't_inac', 'diseno', 0, 'gestionar'],
    ['Admin TI', 't_admin', 'admin_ti', 1, null],
];
foreach ($usuarios as [$nombre, $user, $rol, $activo, $nivel]) {
    $u->execute([$nombre, $user, $h, $rol, $activo]);
    $id = (int) $pdo->lastInsertId();
    if ($nivel === '__otro') {
        $m->execute([$id, 'rrhh', 'gestionar']);
    } elseif ($nivel) {
        $m->execute([$id, 'prevencion_riesgos', $nivel]);
    }
}
echo "seed ok\n";
