<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('desgaje');

ob_start();
?>

<div class="hero">
    <h1>Desgaje</h1>
    <p>Registro y seguimiento de la producción de desgaje.</p>
</div>

<div class="module-grid">

    <a href="/modules/desgaje/registro/" class="module-card">
        <div class="module-icon">
            <i class="bi bi-scissors"></i>
        </div>

        <h2>Registro de Desgaje</h2>
        <p>Registro de producción de desgaje con checklist y firma del operador.</p>
    </a>

    <a href="/modules/desgaje/admin/" class="module-card">
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
