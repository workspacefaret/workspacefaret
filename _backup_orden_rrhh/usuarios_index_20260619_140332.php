<?php
ob_start();
?>

<div class="hero">
    <h1>Administración de usuarios</h1>
    <p>Gestión de usuarios autorizados para la aplicación Guardias.</p>
</div>

<div class="card">
    <h2>Módulo en construcción</h2>
    <p>Próximo paso: conectar esta pantalla con la API de usuarios de Guardias.</p>
</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
