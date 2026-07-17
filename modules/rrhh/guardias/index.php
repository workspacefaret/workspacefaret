<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('rrhh');

ob_start();
?>

<div class="hero">
    <h1>Guardias</h1>
    <p>Selecciona una opción del sistema de control de guardias.</p>
</div>

<div class="module-grid">

    <a href="/modules/rrhh/guardias/registros/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-clipboard-data"></i>
        </div>

        <h2>Registros</h2>
        <p>Revisión de recorridos, puntos de control, plantas, usuarios y fechas.</p>
    </a>

    <a href="/modules/rrhh/guardias/usuarios/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-person-gear"></i>
        </div>

        <h2>Administración de usuarios</h2>
        <p>Crear, activar o desactivar usuarios autorizados para usar la app Guardias.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
