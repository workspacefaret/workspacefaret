<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireLogin();

ob_start();

$secciones = [
    [
        'titulo' => 'Perfiles y Moldes',
        'descripcion' => 'Asigna correlativos, registra y consulta el histórico de perfiles y moldes.',
        'url' => '/modules/planificacion/',
        'icono' => 'bi-rulers',
        'modulo' => 'planificacion'
    ],
    [
        'titulo' => 'Registro de Molde',
        'descripcion' => 'Registra y consulta el avance del proceso de fabricación de cada molde por NP.',
        'url' => '/modules/planificacion/registro-molde/',
        'icono' => 'bi-clipboard-check',
        'modulo' => 'planificacion'
    ],
    [
        'titulo' => 'Control de Moldes',
        'descripcion' => 'Seguimiento del estado de layout y fabricación de moldes.',
        'url' => '/modules/planificacion/control-moldes/',
        'icono' => 'bi-diagram-3',
        'modulo' => 'control_moldes'
    ],
    [
        'titulo' => 'Stock de Moldes',
        'descripcion' => 'Inventario de moldes por rack físico, organizado por categoría.',
        'url' => '/modules/planificacion/stock-moldes/',
        'icono' => 'bi-archive',
        'modulo' => 'stock_moldes'
    ]
];

$secciones = array_filter($secciones, fn($s) => hasModuleAccess($s['modulo']));
?>

<section class="hero">
    <h1>Moldes</h1>
    <p>Acceso centralizado a los procesos de moldes: perfiles, registro, control y stock.</p>
</section>

<section class="section">
    <div class="grid-3">

        <?php foreach ($secciones as $seccion): ?>
            <a class="action-card" href="<?= htmlspecialchars($seccion['url']) ?>">
                <div class="action-card-icon">
                    <i class="bi <?= htmlspecialchars($seccion['icono']) ?>"></i>
                </div>
                <h3><?= htmlspecialchars($seccion['titulo']) ?></h3>
                <p><?= htmlspecialchars($seccion['descripcion']) ?></p>
            </a>
        <?php endforeach; ?>

        <a class="action-card" href="/modules/operacion/">
            <div class="action-card-icon">
                <i class="bi bi-arrow-left-circle-fill"></i>
            </div>
            <h3>Volver</h3>
            <p>Regresar al módulo de operación.</p>
        </a>

    </div>
</section>

<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
