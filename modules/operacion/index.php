<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireLogin();

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

ob_start();

$hoy = date('Y-m-d');

$respuestaRecorridos = ApiClient::get('recorridos');
$recorridosHoy = 0;

if ($respuestaRecorridos['ok'] && is_array($respuestaRecorridos['data'])) {
    foreach ($respuestaRecorridos['data'] as $r) {
        $fechaInicio = $r['fechaInicio'] ?? null;
        if ($fechaInicio && substr($fechaInicio, 0, 10) === $hoy) {
            $recorridosHoy++;
        }
    }
}

$respuestaNc = ApiClient::getMejoraContinua('no-conformidades');
$ncAbiertas = 0;

if ($respuestaNc['ok'] && is_array($respuestaNc['data'])) {
    $ncAbiertas = count(array_filter($respuestaNc['data'], function ($r) {
        return strtoupper($r['estado'] ?? '') === 'ABIERTA';
    }));
}

$areas = [
    [
        'titulo' => 'Logística',
        'descripcion' => 'Formularios, registros, reportes y recursos del área logística.',
        'url' => '/modules/operacion/logistica/',
        'icono' => 'bi-truck'
    ],
    [
        'titulo' => 'Desarrollo',
        'descripcion' => 'Formularios, registros y seguimiento de solicitudes de desarrollo gráfico.',
        'url' => '/modules/formularios/desarrollo/',
        'icono' => 'bi-palette-fill'
    ],
    [
        'titulo' => 'Contabilidad',
        'descripcion' => 'Solicitudes administrativas, registros financieros y documentación.',
        'url' => '/modules/en-proceso/?modulo=Contabilidad',
        'icono' => 'bi-calculator-fill'
    ],
    [
        'titulo' => 'RRHH',
        'descripcion' => 'Gestión de módulos de Recursos Humanos, guardias y administración interna.',
        'url' => '/modules/rrhh/',
        'icono' => 'bi-people-fill'
    ]
];
?>

<div class="page">

    <section class="hero">
        <h1>Operación</h1>
        <p>Selecciona un área de trabajo para acceder a sus formularios, registros, reportes y recursos.</p>
    </section>

    <section class="section">
        <div class="grid-4">

            <?php foreach ($areas as $area): ?>

                <a class="action-card"
                    href="<?= htmlspecialchars($area['url']) ?>">

                    <div class="action-card-icon">
                        <i class="bi <?= htmlspecialchars($area['icono']) ?>"></i>
                    </div>

                    <h3><?= htmlspecialchars($area['titulo']) ?></h3>

                    <p><?= htmlspecialchars($area['descripcion']) ?></p>

                </a>

            <?php endforeach; ?>

        </div>
    </section>

    <section class="section">

        <div class="panel">

            <div class="section-header">
                <div class="section-title">
                    <h2>Pendientes que requieren atención</h2>
                    <p>Resumen en vivo de lo que está pendiente de gestión en los módulos activos.</p>
                </div>
            </div>

            <div class="grid-4">

                <a class="stat-card stat-card-link" href="/modules/formularios/desarrollo/">
                    <span>Solicitudes Desarrollo pendientes</span>
                    <strong id="pendDesarrolloPendientes">-</strong>
                    <small>Gráfico + Estructural, en Recibido / En edición / Pendiente información.</small>
                </a>

                <a class="stat-card stat-card-link" href="/modules/formularios/desarrollo/admin/?prioridad=URGENTE">
                    <span>Urgentes (Gráfico)</span>
                    <strong id="pendDesarrolloUrgentes">-</strong>
                    <small>Solicitudes con prioridad URGENTE.</small>
                </a>

                <a class="stat-card stat-card-link" href="/modules/datos/mejora-continua/?estado=ABIERTA">
                    <span>No conformidades abiertas</span>
                    <strong><?= $ncAbiertas ?></strong>
                    <small>Mejora Continua, estado Abierta.</small>
                </a>

                <a class="stat-card stat-card-link"
                    href="/modules/rrhh/guardias/registros/?fecha_desde=<?= htmlspecialchars($hoy) ?>&amp;fecha_hasta=<?= htmlspecialchars($hoy) ?>">
                    <span>Recorridos guardia hoy</span>
                    <strong><?= $recorridosHoy ?></strong>
                    <small>Registros del día en curso.</small>
                </a>

            </div>

        </div>

    </section>

</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
</script>
<script src="/assets/js/operacion-pendientes.js"></script>

<?php
$contenido = ob_get_clean();

include '../../layouts/app.php';
?>
