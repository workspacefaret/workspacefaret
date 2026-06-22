<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

ob_start();

$respuesta = ApiClient::get('recorridos');

$registros = [];

if ($respuesta['ok'] && is_array($respuesta['data'])) {
    $registros = $respuesta['data'];
}

$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$planta = $_GET['planta'] ?? '';
$paginaActual = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 20;

$usuariosDisponibles = [];
$plantasDisponibles = [];

foreach ($registros as $r) {
    if (!empty($r['username'])) {
        $usuariosDisponibles[$r['username']] = $r['username'];
    }

    if (!empty($r['planta'])) {
        $plantasDisponibles[$r['planta']] = $r['planta'];
    }
}

sort($usuariosDisponibles);
sort($plantasDisponibles);

$registrosFiltrados = array_filter($registros, function ($r) use ($fechaDesde, $fechaHasta, $usuario, $planta) {

    if ($usuario !== '' && ($r['username'] ?? '') !== $usuario) {
        return false;
    }

    if ($planta !== '' && ($r['planta'] ?? '') !== $planta) {
        return false;
    }

    $fechaRegistro = $r['fechaInicio'] ?? null;

    if ($fechaDesde !== '' && $fechaRegistro) {
        if (strtotime($fechaRegistro) < strtotime($fechaDesde . ' 00:00:00')) {
            return false;
        }
    }

    if ($fechaHasta !== '' && $fechaRegistro) {
        if (strtotime($fechaRegistro) > strtotime($fechaHasta . ' 23:59:59')) {
            return false;
        }
    }

    return true;
});

$registrosFiltrados = array_values($registrosFiltrados);

$totalRegistros = count($registrosFiltrados);

$recorridosUnicos = [];
$finalizados = 0;
$enProceso = 0;

foreach ($registrosFiltrados as $r) {
    if (isset($r['recorridoId'])) {
        $recorridosUnicos[$r['recorridoId']] = true;
    }

    $estado = strtoupper($r['estado'] ?? '');

    if ($estado === 'FINALIZADO') {
        $finalizados++;
    }

    if ($estado === 'EN_PROCESO') {
        $enProceso++;
    }
}

$totalRecorridos = count($recorridosUnicos);

$totalPaginas = max(1, ceil($totalRegistros / $porPagina));

if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}

$inicio = ($paginaActual - 1) * $porPagina;
$registrosPagina = array_slice($registrosFiltrados, $inicio, $porPagina);

function formatoFecha($fecha)
{
    if (!$fecha) {
        return '-';
    }

    return date('d-m-Y H:i', strtotime($fecha));
}

$queryFiltros = http_build_query([
    'fecha_desde' => $fechaDesde,
    'fecha_hasta' => $fechaHasta,
    'usuario' => $usuario,
    'planta' => $planta
]);

?>

<div class="hero">
    <h1>Guardias</h1>
    <p>Revisión de recorridos, puntos de control y registros operacionales.</p>
</div>

<?php if (!$respuesta['ok']): ?>

    <div class="card">
        <h2>Error de conexión API</h2>
        <p>No se pudo obtener información desde la API de guardias.</p>
        <p>Estado HTTP: <strong><?= htmlspecialchars($respuesta['status']) ?></strong></p>
        <pre><?= htmlspecialchars($respuesta['error'] ?? 'Sin detalle') ?></pre>
    </div>

<?php else: ?>

    <form class="filter-card" method="GET">

        <div class="filter-group">
            <label>Desde</label>
            <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
        </div>

        <div class="filter-group">
            <label>Hasta</label>
            <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
        </div>

        <div class="filter-group">
            <label>Usuario</label>
            <select name="usuario">
                <option value="">Todos</option>
                <?php foreach ($usuariosDisponibles as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>" <?= $usuario === $u ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Planta</label>
            <select name="planta">
                <option value="">Todas</option>
                <?php foreach ($plantasDisponibles as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>" <?= $planta === $p ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-primary">
                <i class="bi bi-funnel-fill"></i>
                Filtrar
            </button>

            <a href="/modules/datos/guardias/" class="btn-secondary">
                Limpiar
            </a>
        </div>

    </form>

    <div class="kpi-grid">

        <div class="kpi-card">
            <span>Total registros</span>
            <strong><?= $totalRegistros ?></strong>
        </div>

        <div class="kpi-card">
            <span>Recorridos</span>
            <strong><?= $totalRecorridos ?></strong>
        </div>

        <div class="kpi-card">
            <span>Finalizados</span>
            <strong><?= $finalizados ?></strong>
        </div>

        <div class="kpi-card">
            <span>En proceso</span>
            <strong><?= $enProceso ?></strong>
        </div>

    </div>

    <div class="table-card">

        <div class="table-header">
            <div>
                <h2>Últimos registros</h2>
                <p>
                    Mostrando <?= count($registrosPagina) ?> de <?= $totalRegistros ?> registros filtrados.
                </p>
            </div>

            <a href="/exports/guardias/recorridos-excel.php?<?= htmlspecialchars($queryFiltros) ?>" class="btn-export">
                <i class="bi bi-file-earmark-excel-fill"></i>
                Exportar Excel
            </a>
        </div>

        <div class="table-responsive">

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Recorrido</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Planta</th>
                        <th>Ubicación</th>
                        <th>Hora punto</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($registrosPagina as $r): ?>

                        <?php
                            $estado = strtoupper($r['estado'] ?? '');
                            $estadoClass = $estado === 'FINALIZADO' ? 'status-ok' : 'status-pending';
                        ?>

                        <tr>
                            <td>#<?= htmlspecialchars($r['recorridoId'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['username'] ?? '-') ?></td>

                            <td>
                                <span class="status-badge <?= $estadoClass ?>">
                                    <?= htmlspecialchars($r['estado'] ?? '-') ?>
                                </span>
                            </td>

                            <td><?= htmlspecialchars(formatoFecha($r['fechaInicio'] ?? null)) ?></td>
                            <td><?= htmlspecialchars(formatoFecha($r['fechaFin'] ?? null)) ?></td>
                            <td><?= htmlspecialchars($r['planta'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['ubicacion'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(formatoFecha($r['fechaHora'] ?? null)) ?></td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (count($registrosPagina) === 0): ?>
                        <tr>
                            <td colspan="8">No hay registros para los filtros seleccionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>

        <div class="pagination">

            <?php if ($paginaActual > 1): ?>
                <a href="?<?= htmlspecialchars($queryFiltros) ?>&page=<?= $paginaActual - 1 ?>">
                    Anterior
                </a>
            <?php endif; ?>

            <span>
                Página <?= $paginaActual ?> de <?= $totalPaginas ?>
            </span>

            <?php if ($paginaActual < $totalPaginas): ?>
                <a href="?<?= htmlspecialchars($queryFiltros) ?>&page=<?= $paginaActual + 1 ?>">
                    Siguiente
                </a>
            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
