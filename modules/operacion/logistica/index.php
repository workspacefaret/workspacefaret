<?php
ob_start();
?>

<div class="hero">
    <h1>Logística</h1>
    <p>Selecciona una opción del área logística.</p>
</div>

<div class="module-grid">

    <a href="https://solicitudes.faret.cl/app/formularios/" target="_blank" class="module-card">
        <div class="module-icon">
            <i class="bi bi-clipboard2-check-fill"></i>
        </div>

        <h2>Formularios Logística</h2>
        <p>Acceso a formularios operacionales disponibles en solicitudes.faret.cl.</p>
    </a>

</div>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
