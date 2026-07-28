<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireLogin();

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

ob_start();

$hoy = date('Y-m-d');

$verRecorridos = hasModuleAccess('rrhh') || hasModuleAccess('datos');
$verMejoraContinua = hasModuleAccess('mejora_continua');

$recorridosHoy = 0;

if ($verRecorridos) {
    $respuestaRecorridos = ApiClient::get('recorridos');

    if ($respuestaRecorridos['ok'] && is_array($respuestaRecorridos['data'])) {
        foreach ($respuestaRecorridos['data'] as $r) {
            $fechaInicio = $r['fechaInicio'] ?? null;
            if ($fechaInicio && substr($fechaInicio, 0, 10) === $hoy) {
                $recorridosHoy++;
            }
        }
    }
}

$ncAbiertas = 0;
$misNc = 0;
$nombreUsuarioActual = currentUser()['nombre'];

if ($verMejoraContinua) {
    $respuestaNc = ApiClient::getMejoraContinua('no-conformidades');

    if ($respuestaNc['ok'] && is_array($respuestaNc['data'])) {
        $ncAbiertas = count(array_filter($respuestaNc['data'], function ($r) {
            return strtoupper($r['estado'] ?? '') === 'ABIERTA';
        }));

        $misNc = count(array_filter($respuestaNc['data'], function ($r) use ($nombreUsuarioActual) {
            return strtoupper($r['estado'] ?? '') === 'ABIERTA'
                && trim(mb_strtolower($r['responsable'] ?? '')) === trim(mb_strtolower($nombreUsuarioActual));
        }));
    }
}

$alertas = [];

if ($verMejoraContinua && $misNc > 0) {
    $alertas[] = [
        'texto' => "No conformidades abiertas asignadas a ti: {$misNc}",
        'url' => '/modules/datos/mejora-continua/?estado=ABIERTA'
    ];
}

$areas = [
    [
        'titulo' => 'Logística',
        'descripcion' => 'Formularios, registros, reportes y recursos del área logística.',
        'url' => '/modules/operacion/logistica/',
        'icono' => 'bi-truck',
        'modulo' => 'logistica'
    ],
    [
        'titulo' => 'Desarrollo',
        'descripcion' => 'Formularios, registros y seguimiento de solicitudes de desarrollo gráfico.',
        'url' => '/modules/formularios/desarrollo/',
        'icono' => 'bi-palette-fill',
        'modulo' => 'desarrollo'
    ],
    [
        'titulo' => 'RRHH',
        'descripcion' => 'Gestión de módulos de Recursos Humanos, guardias y administración interna.',
        'url' => '/modules/rrhh/',
        'icono' => 'bi-people-fill',
        'modulo' => 'rrhh'
    ]
];

$areas = array_filter($areas, fn($area) => $area['modulo'] === null || hasModuleAccess($area['modulo']));

$verDesarrollo = hasModuleAccess('desarrollo');
$novedades = obtenerNovedadesActivas(5);
?>

<div class="page">

    <section class="hero">
        <h1>Hola, <?= htmlspecialchars(currentUser()['nombre']) ?></h1>
        <p>Selecciona un área de trabajo para acceder a sus formularios, registros, reportes y recursos.</p>
    </section>

    <?php if (!empty($novedades)): ?>
    <section class="section">
        <div class="panel">

            <div class="section-header">
                <div class="section-title">
                    <h2><i class="bi bi-megaphone-fill"></i> Novedades</h2>
                    <p>Últimas actualizaciones del portal.</p>
                </div>
            </div>

            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($novedades as $novedad): ?>
                <li style="padding:12px 16px; border-left:3px solid #2563eb; background:rgba(37,99,235,.08); border-radius:8px;">
                    <strong><?= htmlspecialchars($novedad['titulo']) ?></strong>
                    <small style="display:block; opacity:.7; margin:2px 0 6px;"><?= htmlspecialchars($novedad['creado_en']) ?></small>
                    <div><?= nl2br(htmlspecialchars($novedad['cuerpo'])) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>

        </div>
    </section>
    <?php endif; ?>

    <?php if ($verDesarrollo || $verMejoraContinua): ?>
    <section class="section" id="seccionAlertas" style="<?= empty($alertas) ? 'display:none;' : '' ?>">
        <div class="panel">

            <div class="section-header">
                <div class="section-title">
                    <h2><i class="bi bi-bell-fill"></i> Alertas</h2>
                    <p>Avisos que requieren tu atención según tu rol y accesos.</p>
                </div>
            </div>

            <ul id="listaAlertas" style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($alertas as $alerta): ?>
                <li style="display:flex; align-items:center; gap:10px; padding:12px 16px; border-left:3px solid #dc2626; background:rgba(220,38,38,.08); border-radius:8px;">
                    <i class="bi bi-exclamation-circle-fill" style="color:#dc2626;"></i>
                    <a href="<?= htmlspecialchars($alerta['url']) ?>" style="color:inherit; text-decoration:none; font-weight:500;">
                        <?= htmlspecialchars($alerta['texto']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

        </div>
    </section>
    <?php endif; ?>

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

    <?php if ($verDesarrollo || $verMejoraContinua || $verRecorridos): ?>
    <section class="section">

        <div class="panel">

            <div class="section-header">
                <div class="section-title">
                    <h2>Pendientes que requieren atención</h2>
                    <p>Resumen en vivo de lo que está pendiente de gestión en los módulos activos.</p>
                </div>
            </div>

            <div class="grid-4">

                <?php if ($verDesarrollo): ?>
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

                <a class="stat-card stat-card-link" id="cardMisPendientesDesarrollo" href="/modules/formularios/desarrollo/" style="display:none;">
                    <span>Mis solicitudes asignadas</span>
                    <strong id="misPendientesDesarrollo">-</strong>
                    <small>Gráfico + Estructural, con "Editor asignado" a tu nombre.</small>
                </a>
                <?php endif; ?>

                <?php if ($verMejoraContinua): ?>
                <a class="stat-card stat-card-link" href="/modules/datos/mejora-continua/?estado=ABIERTA">
                    <span>No conformidades abiertas</span>
                    <strong><?= $ncAbiertas ?></strong>
                    <small>Mejora Continua, estado Abierta.</small>
                </a>

                <?php if ($misNc > 0): ?>
                <a class="stat-card stat-card-link" href="/modules/datos/mejora-continua/?estado=ABIERTA">
                    <span>Mis no conformidades</span>
                    <strong><?= $misNc ?></strong>
                    <small>Abiertas, con "Responsable" a tu nombre.</small>
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($verRecorridos): ?>
                <a class="stat-card stat-card-link"
                    href="/modules/rrhh/guardias/registros/?fecha_desde=<?= htmlspecialchars($hoy) ?>&amp;fecha_hasta=<?= htmlspecialchars($hoy) ?>">
                    <span>Recorridos guardia hoy</span>
                    <strong><?= $recorridosHoy ?></strong>
                    <small>Registros del día en curso.</small>
                </a>
                <?php endif; ?>

            </div>

        </div>

    </section>
    <?php endif; ?>

</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
</script>
<script src="/assets/js/operacion-pendientes.js"></script>

<?php
$contenido = ob_get_clean();

include '../../layouts/app.php';
?>
