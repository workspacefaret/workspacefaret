<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('prevencion_riesgos');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$puedeGestionar = moduleAccessLevel('prevencion_riesgos') === 'gestionar';

// Pestañas del panel. Las que administran datos requieren nivel «Gestionar» (se vuelve a exigir en api.php en cada llamada).
$vistas = [
    'panel' => ['label' => 'Panel', 'icono' => 'bi-speedometer2', 'gestionar' => false],
    'extintores' => ['label' => 'Extintores', 'icono' => 'bi-boxes', 'gestionar' => true],
    'brigadistas' => ['label' => 'Brigadistas y áreas', 'icono' => 'bi-people', 'gestionar' => true],
];

$vista = $_GET['vista'] ?? 'panel';
if (!is_string($vista) || !isset($vistas[$vista])) {
    $vista = 'panel';
}
if ($vistas[$vista]['gestionar'] && !$puedeGestionar) {
    mostrarAccesoDenegado('Esta sección del panel requiere nivel «Gestionar» en Prevención de Riesgos.');
}

define('PR_VISTA', $vista);

ob_start();
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<section class="hero admin-hero">
    <div>
        <h1>Panel de administración</h1>
        <p>Inspecciones de extintores, inventario, brigadistas y áreas en un solo lugar.</p>
    </div>
    <div class="admin-hero-actions">
        <a href="/modules/prevencion/extintores/inspeccion/" class="admin-btn admin-btn-primary">
            <i class="bi bi-fire"></i>
            Inspeccionar
        </a>
        <a href="/modules/prevencion/" class="admin-btn admin-btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Volver
        </a>
    </div>
</section>

<section class="section">
    <nav class="admin-tabs pr-tabs" aria-label="Secciones del panel">
        <?php foreach ($vistas as $clave => $v): ?>
            <?php if ($v['gestionar'] && !$puedeGestionar) continue; ?>
            <a href="/modules/prevencion/extintores/admin/<?= $clave === 'panel' ? '' : '?vista=' . $clave ?>"
               class="admin-tab<?= $clave === $vista ? ' is-active' : '' ?>"<?= $clave === $vista ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= $v['icono'] ?>"></i><?= htmlspecialchars($v['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</section>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    // Las llamadas administrativas pasan por api.php (valida sesión y permiso en el servidor); no hay claves en el navegador.
    window.PR_ADMIN = true;
</script>
<script src="/assets/js/prevencion/extintores-comun.js"></script>

<?php
include __DIR__ . '/_vista_' . $vista . '.php';

$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
