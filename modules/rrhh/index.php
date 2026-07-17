<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('rrhh');

ob_start();
?>

<div class="hero">
    <h1>RRHH</h1>
    <p>Selecciona un módulo del área de Recursos Humanos.</p>
</div>

<div class="module-grid">

    <a href="/modules/rrhh/guardias/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-shield-check"></i>
        </div>

        <h2>Guardias</h2>
        <p>Registros de recorridos y administración de usuarios del sistema Guardias.</p>
    </a>

    <a href="/modules/rrhh/desgaje/registro/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-scissors"></i>
        </div>

        <h2>Registro de Desgaje</h2>
        <p>Registro de producción de desgaje con checklist y firma del operador.</p>
    </a>

    <a href="/modules/rrhh/desgaje/admin/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-clipboard-data"></i>
        </div>

        <h2>Panel Administrativo Desgaje</h2>
        <p>Filtra, valida y anula los registros de producción de desgaje.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
