<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireLogin();

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

ob_start();

$usuario = currentUser();
$nombreUsuarioActual = $usuario['nombre'];
$hoy = date('Y-m-d');

$hora = (int) date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

$verDesarrollo = hasModuleAccess('desarrollo');
$verMejoraContinua = hasModuleAccess('mejora_continua');
$verRecorridos = hasModuleAccess('rrhh') || hasModuleAccess('datos');
$verRrhh = hasModuleAccess('rrhh');
$verCpmPrm = hasModuleAccess('planificacion');
$verControlMoldes = hasModuleAccess('control_moldes');
$verStockMoldes = hasModuleAccess('stock_moldes');
$verMoldes = $verCpmPrm || $verControlMoldes || $verStockMoldes;
$verLogistica = hasModuleAccess('logistica');

// ---------------- Mejora Continua: 1 sola llamada a la API, reutilizada para KPIs + alerta + donut ----------------
$ncTotal = 0;
$ncAbiertas = 0;
$ncCerradas = 0;
$misNc = 0;
$ncPorEstado = [];
$ncPorSeveridad = [];

if ($verMejoraContinua) {
    $respuestaNc = ApiClient::getMejoraContinua('no-conformidades');

    if ($respuestaNc['ok'] && is_array($respuestaNc['data'])) {
        $ncTotal = count($respuestaNc['data']);

        foreach ($respuestaNc['data'] as $r) {
            $estado = strtoupper(trim($r['estado'] ?? '')) ?: 'SIN DATO';
            $ncPorEstado[$estado] = ($ncPorEstado[$estado] ?? 0) + 1;

            $severidad = trim($r['severidad'] ?? '') ?: 'Sin dato';
            $ncPorSeveridad[$severidad] = ($ncPorSeveridad[$severidad] ?? 0) + 1;

            if ($estado === 'ABIERTA') {
                $ncAbiertas++;

                if (trim(mb_strtolower($r['responsable'] ?? '')) === trim(mb_strtolower($nombreUsuarioActual))) {
                    $misNc++;
                }
            }

            if ($estado === 'CERRADA') {
                $ncCerradas++;
            }
        }
    }
}

// ---------------- Guardias: 1 sola llamada a la API, deduplicada por recorridoId (la API trae 1 fila por punto de control) ----------------
$recorridosHoy = 0;
$recorridosPorDia = [];
$recorridosPorEstado = [];

if ($verRecorridos) {
    for ($i = 6; $i >= 0; $i--) {
        $recorridosPorDia[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    }

    $respuestaRecorridos = ApiClient::get('recorridos');

    if ($respuestaRecorridos['ok'] && is_array($respuestaRecorridos['data'])) {
        $recorridosUnicos = [];

        foreach ($respuestaRecorridos['data'] as $r) {
            $id = $r['recorridoId'] ?? null;

            if ($id === null || isset($recorridosUnicos[$id])) {
                continue;
            }

            $recorridosUnicos[$id] = true;

            $fecha = substr($r['fechaInicio'] ?? '', 0, 10);
            $estado = strtoupper(trim($r['estado'] ?? '')) ?: 'SIN DATO';

            $recorridosPorEstado[$estado] = ($recorridosPorEstado[$estado] ?? 0) + 1;

            if ($fecha === $hoy) {
                $recorridosHoy++;
            }

            if (isset($recorridosPorDia[$fecha])) {
                $recorridosPorDia[$fecha]++;
            }
        }
    }
}

// ---------------- KPIs personalizados (solo módulos autorizados) ----------------
$kpis = [];

if ($verDesarrollo) {
    $kpis[] = ['id' => 'pendDesarrolloPendientes', 'valor' => null, 'titulo' => 'Solicitudes pendientes', 'desc' => 'Desarrollo Gráfico + Estructural', 'icono' => 'bi-hourglass-split', 'color' => '#f59e0b', 'url' => '/modules/formularios/desarrollo/'];
    $kpis[] = ['id' => 'pendDesarrolloUrgentes', 'valor' => null, 'titulo' => 'Urgentes (Gráfico)', 'desc' => 'Prioridad URGENTE', 'icono' => 'bi-exclamation-triangle-fill', 'color' => '#ef4444', 'url' => '/modules/formularios/desarrollo/admin/?prioridad=URGENTE'];
}

if ($verMejoraContinua) {
    $kpis[] = ['id' => null, 'valor' => $ncAbiertas, 'titulo' => 'No conformidades abiertas', 'desc' => 'Mejora Continua', 'icono' => 'bi-clipboard-x', 'color' => '#ef4444', 'url' => '/modules/datos/mejora-continua/?estado=ABIERTA'];

    if ($misNc > 0) {
        $kpis[] = ['id' => null, 'valor' => $misNc, 'titulo' => 'Mis no conformidades', 'desc' => 'Responsable: tú', 'icono' => 'bi-person-check-fill', 'color' => '#f59e0b', 'url' => '/modules/datos/mejora-continua/?estado=ABIERTA'];
    }
}

if ($verRecorridos) {
    $kpis[] = ['id' => null, 'valor' => $recorridosHoy, 'titulo' => 'Recorridos guardia hoy', 'desc' => 'Registros del día en curso', 'icono' => 'bi-shield-check', 'color' => '#2563eb', 'url' => "/modules/rrhh/guardias/registros/?fecha_desde={$hoy}&fecha_hasta={$hoy}"];
}

// ---------------- Alertas ----------------
$alertas = [];

if ($verMejoraContinua && $misNc > 0) {
    $alertas[] = ['nivel' => 'atencion', 'texto' => "No conformidades abiertas asignadas a ti: {$misNc}", 'url' => '/modules/datos/mejora-continua/?estado=ABIERTA'];
}

// ---------------- Accesos rápidos ----------------
$areas = [
    ['titulo' => 'Moldes', 'descripcion' => 'Perfiles, registro, control y stock de moldes.', 'url' => '/modules/planificacion/moldes/', 'icono' => 'bi-box-seam', 'ver' => $verMoldes],
    ['titulo' => 'Logística', 'descripcion' => 'Formularios y recursos del área logística.', 'url' => '/modules/operacion/logistica/', 'icono' => 'bi-truck', 'ver' => $verLogistica],
    ['titulo' => 'Desarrollo', 'descripcion' => 'Solicitudes y seguimiento gráfico y estructural.', 'url' => '/modules/formularios/desarrollo/', 'icono' => 'bi-palette-fill', 'ver' => $verDesarrollo],
    ['titulo' => 'RRHH', 'descripcion' => 'Guardias, desgaje y administración de RRHH.', 'url' => '/modules/rrhh/', 'icono' => 'bi-people-fill', 'ver' => $verRrhh],
];

$areas = array_filter($areas, fn($a) => $a['ver']);

$novedades = obtenerNovedadesActivas(5);
?>

<link rel="stylesheet" href="/assets/css/formularios/admin-formularios.css">

<div class="page">

    <section class="home-greeting">
        <div>
            <h1><?= htmlspecialchars($saludo) ?>, <?= htmlspecialchars($nombreUsuarioActual) ?></h1>
            <p>Este es el resumen de tus módulos y pendientes.</p>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="bi bi-megaphone-fill"></i> Novedades del Portal</h2>
                <p>Comunicados y actualizaciones publicadas por administración.</p>
            </div>
        </div>

        <?php if (!empty($novedades)): ?>
            <div class="novedades-grid">
                <?php foreach ($novedades as $i => $novedad):
                    $destacada = $i === 0;
                    $cuerpo = $novedad['cuerpo'];
                    $limite = $destacada ? 320 : 160;
                    $esLarga = mb_strlen($cuerpo) > $limite;
                    $preview = $esLarga ? mb_substr($cuerpo, 0, $limite) . '…' : $cuerpo;
                    $resto = $esLarga ? mb_substr($cuerpo, $limite) : '';
                ?>
                    <article class="novedad-card<?= $destacada ? ' novedad-card-destacada' : '' ?>">
                        <?php if ($destacada): ?>
                            <span class="novedad-card-badge"><i class="bi bi-stars"></i> Más reciente</span>
                        <?php endif; ?>
                        <div class="novedad-card-icono"><i class="bi bi-megaphone-fill"></i></div>
                        <div class="novedad-card-meta">
                            <i class="bi bi-calendar3"></i>
                            <?= htmlspecialchars(date('d-m-Y H:i', strtotime($novedad['creado_en']))) ?>
                            <?php if (!empty($novedad['autor'])): ?>
                                · <?= htmlspecialchars($novedad['autor']) ?>
                            <?php endif; ?>
                        </div>
                        <h4><?= htmlspecialchars($novedad['titulo']) ?></h4>
                        <p><?= nl2br(htmlspecialchars($preview)) ?></p>
                        <?php if ($esLarga): ?>
                            <details>
                                <summary>Leer más</summary>
                                <p><?= nl2br(htmlspecialchars($resto)) ?></p>
                            </details>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="novedades-empty">
                <i class="bi bi-megaphone"></i>
                <span>Sin novedades</span>
            </div>
        <?php endif; ?>
    </section>

    <section class="section" id="seccionAlertas" style="<?= empty($alertas) ? 'display:none;' : '' ?>">
        <div class="section-header">
            <div class="section-title">
                <h2><i class="bi bi-bell-fill"></i> Requiere tu atención</h2>
            </div>
        </div>
        <div class="home-alerts" id="listaAlertas">
            <?php foreach ($alertas as $alerta): ?>
                <a class="home-alert home-alert-<?= htmlspecialchars($alerta['nivel']) ?>" href="<?= htmlspecialchars($alerta['url']) ?>">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($alerta['texto']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!empty($kpis) || $verDesarrollo): ?>
    <section class="section">
        <div class="admin-kpi-grid">
            <?php foreach ($kpis as $kpi): ?>
                <a class="admin-kpi-card admin-kpi-card-link" style="--kpi-color:<?= htmlspecialchars($kpi['color']) ?>;" href="<?= htmlspecialchars($kpi['url']) ?>">
                    <span><i class="bi <?= htmlspecialchars($kpi['icono']) ?>"></i><?= htmlspecialchars($kpi['titulo']) ?></span>
                    <strong<?= $kpi['id'] ? ' id="' . htmlspecialchars($kpi['id']) . '"' : '' ?>><?= $kpi['valor'] !== null ? (int) $kpi['valor'] : '-' ?></strong>
                    <small><?= htmlspecialchars($kpi['desc']) ?></small>
                </a>
            <?php endforeach; ?>

            <?php if ($verDesarrollo): ?>
                <a class="admin-kpi-card admin-kpi-card-link" id="cardMisPendientesDesarrollo" href="/modules/formularios/desarrollo/" style="--kpi-color:#2563eb; display:none;">
                    <span><i class="bi bi-person-check-fill"></i>Mis solicitudes asignadas</span>
                    <strong id="misPendientesDesarrollo">-</strong>
                    <small>Editor asignado: tú</small>
                </a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($verDesarrollo || $verMejoraContinua || $verRecorridos || $verMoldes): ?>
    <section class="section">
        <div class="section-header">
            <div class="section-title">
                <h2>Mis módulos</h2>
                <p>Resumen ejecutivo de los módulos a los que tienes acceso.</p>
            </div>
        </div>

        <?php if ($verDesarrollo): ?>
        <div class="home-modulo">
            <div class="home-modulo-header">
                <h3><i class="bi bi-palette-fill"></i> Desarrollo</h3>
                <a href="/modules/formularios/desarrollo/">Ver dashboard completo →</a>
            </div>
            <div class="dashboard-charts-grid">
                <div class="chart-card">
                    <h3>Estados (Gráfico)</h3>
                    <div id="homeChartDesarrolloEstados">Cargando...</div>
                </div>
                <div class="chart-card">
                    <h3>Solicitantes con más pendientes</h3>
                    <div id="homeChartDesarrolloSolicitantes">Cargando...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($verMejoraContinua): ?>
        <div class="home-modulo">
            <div class="home-modulo-header">
                <h3><i class="bi bi-clipboard-check-fill"></i> Mejora Continua</h3>
                <a href="/modules/datos/mejora-continua/">Ver todas →</a>
            </div>
            <div class="dashboard-charts-grid">
                <div class="chart-card">
                    <h3>Estado de las no conformidades</h3>
                    <div id="homeChartNcEstado">Cargando...</div>
                </div>
                <div class="chart-card">
                    <h3>Severidad</h3>
                    <div id="homeChartNcSeveridad">Cargando...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($verRecorridos): ?>
        <div class="home-modulo">
            <div class="home-modulo-header">
                <h3><i class="bi bi-shield-check"></i> Guardias</h3>
                <a href="/modules/rrhh/guardias/registros/">Ver registros →</a>
            </div>
            <div class="dashboard-charts-grid">
                <div class="chart-card">
                    <h3>Estado de los recorridos</h3>
                    <div id="homeChartRecorridosEstado">Cargando...</div>
                </div>
                <div class="chart-card">
                    <h3>Recorridos — últimos 7 días</h3>
                    <div id="homeChartRecorridos7d">Cargando...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($verMoldes): ?>
        <div class="home-modulo">
            <div class="home-modulo-header">
                <h3><i class="bi bi-box-seam"></i> Moldes</h3>
                <a href="/modules/planificacion/moldes/">Ver módulo →</a>
            </div>
            <div class="admin-kpi-grid">
                <?php if ($verCpmPrm): ?>
                    <div class="admin-kpi-card"><span>Perfiles</span><strong id="homeMoldesPerfiles">-</strong></div>
                    <div class="admin-kpi-card"><span>Moldes (CPM)</span><strong id="homeMoldesCpm">-</strong></div>
                <?php endif; ?>
                <?php if ($verControlMoldes): ?>
                    <div class="admin-kpi-card"><span>Control de Moldes</span><strong id="homeMoldesControl">-</strong></div>
                <?php endif; ?>
                <?php if ($verStockMoldes): ?>
                    <div class="admin-kpi-card"><span>Stock de Moldes</span><strong id="homeMoldesStock">-</strong></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </section>
    <?php endif; ?>

    <?php if (!empty($areas)): ?>
    <section class="section">
        <div class="section-header">
            <div class="section-title">
                <h2>Accesos rápidos</h2>
            </div>
        </div>
        <div class="home-quicklinks">
            <?php foreach ($areas as $area): ?>
                <a class="home-quicklink" href="<?= htmlspecialchars($area['url']) ?>">
                    <div class="home-quicklink-icon"><i class="bi <?= htmlspecialchars($area['icono']) ?>"></i></div>
                    <div>
                        <strong><?= htmlspecialchars($area['titulo']) ?></strong>
                        <span><?= htmlspecialchars($area['descripcion']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<script>
    window.API_FORMULARIOS = '<?= htmlspecialchars(API_FORMULARIOS) ?>';
    window.currentUserNombre = <?= json_encode($nombreUsuarioActual) ?>;
    window.homeVerDesarrollo = <?= $verDesarrollo ? 'true' : 'false' ?>;
    window.homeVerMoldesCpmPrm = <?= $verCpmPrm ? 'true' : 'false' ?>;
    window.homeVerControlMoldes = <?= $verControlMoldes ? 'true' : 'false' ?>;
    window.homeVerStockMoldes = <?= $verStockMoldes ? 'true' : 'false' ?>;
    window.homeNcPorEstado = <?= json_encode($ncPorEstado) ?>;
    window.homeNcPorSeveridad = <?= json_encode($ncPorSeveridad) ?>;
    window.homeRecorridosPorEstado = <?= json_encode($recorridosPorEstado) ?>;
    window.homeRecorridosDias = <?= json_encode(array_keys($recorridosPorDia)) ?>;
    window.homeRecorridosValores = <?= json_encode(array_values($recorridosPorDia)) ?>;
</script>
<script src="/assets/js/dashboard-charts.js"></script>
<script src="/assets/js/operacion-pendientes.js"></script>

<?php
$contenido = ob_get_clean();

include '../../layouts/app.php';
?>
