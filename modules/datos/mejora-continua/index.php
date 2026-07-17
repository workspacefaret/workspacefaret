<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('mejora_continua');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

ob_start();

$respuesta = ApiClient::getMejoraContinua('no-conformidades');

$registros = [];

if ($respuesta['ok'] && is_array($respuesta['data'])) {
    $registros = $respuesta['data'];
}

$totalNc = count($registros);

$abiertas = count(array_filter($registros, function ($r) {
    return strtoupper($r['estado'] ?? '') === 'ABIERTA';
}));

$cerradas = count(array_filter($registros, function ($r) {
    return strtoupper($r['estado'] ?? '') === 'CERRADA';
}));

$filtroEstado = strtoupper($_GET['estado'] ?? '');

$registrosMostrados = $registros;

if ($filtroEstado !== '') {
    $registrosMostrados = array_filter($registros, function ($r) use ($filtroEstado) {
        return strtoupper($r['estado'] ?? '') === $filtroEstado;
    });
}

function formatoFecha($fecha)
{
    if (!$fecha) {
        return '-';
    }

    return date('d-m-Y H:i', strtotime($fecha));
}

?>

<div class="hero">
    <h1>Mejora Continua</h1>
    <p>Gestión de no conformidades y acciones correctivas.</p>
</div>

<?php if (!$respuesta['ok']): ?>

<div class="card">
    <h2>Error API</h2>
    <p>No fue posible obtener las no conformidades.</p>
</div>

<?php else: ?>

<div class="kpi-grid">

<div class="kpi-card">
    <span>Total NC</span>
    <strong><?= $totalNc ?></strong>
</div>

<div class="kpi-card">
    <span>Abiertas</span>
    <strong><?= $abiertas ?></strong>
</div>

<div class="kpi-card">
    <span>Cerradas</span>
    <strong><?= $cerradas ?></strong>
</div>

</div>

<div class="table-card">
<div class="table-header">
    <div>
        <h2>No Conformidades</h2>
        <p>
            Listado general.
            <?php if ($filtroEstado !== ''): ?>
                Mostrando solo estado <strong><?= htmlspecialchars($filtroEstado) ?></strong> —
                <a href="/modules/datos/mejora-continua/">quitar filtro</a>.
            <?php endif; ?>
        </p>
    </div>

    <a href="crear.php" class="btn-primary">
        <i class="bi bi-plus-circle-fill"></i>
        Nueva No Conformidad
    </a>
</div>

<div class="table-responsive">

    <table class="data-table">

        <thead>
            <tr>
                <th>Código</th>
                <th>Título</th>
                <th>Proceso</th>
                <th>Severidad</th>
                <th>Estado</th>
                <th>Norma</th>
                <th>Fecha</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($registrosMostrados as $r): ?>

            <tr>
                <td><?= htmlspecialchars($r['codigo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['titulo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['proceso'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['severidad'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['estado'] ?? '-') ?></td>
                <td><?= htmlspecialchars($r['norma'] ?? '-') ?></td>
                <td><?= htmlspecialchars(formatoFecha($r['fechaCreacion'] ?? null)) ?></td>
            </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

</div>

</div>

<?php endif; ?>

<?php

$contenido = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
?>

