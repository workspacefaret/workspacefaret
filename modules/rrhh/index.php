<?php
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

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
