<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php';

$respuesta = ApiClient::get('recorridos');

$registros = [];

if ($respuesta['ok'] && is_array($respuesta['data'])) {
    $registros = $respuesta['data'];
}

$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$planta = $_GET['planta'] ?? '';

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

function formatoFechaExcel($fecha)
{
    if (!$fecha) {
        return '';
    }

    return date('d-m-Y H:i', strtotime($fecha));
}

$filename = 'reporte_guardias_recorridos_' . date('Ymd_His') . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
?>

<html>
<head>
<meta charset="UTF-8">

<style>
    table {
        border-collapse: collapse;
        width: 100%;
        font-family: Arial, sans-serif;
        font-size: 12px;
    }

    .title {
        background: #0f172a;
        color: #ffffff;
        font-size: 20px;
        font-weight: bold;
        text-align: center;
        padding: 14px;
        border: 1px solid #0f172a;
    }

    .subtitle {
        background: #e5e7eb;
        color: #111827;
        font-weight: bold;
        padding: 8px;
        border: 1px solid #94a3b8;
    }

    th {
        background: #1f2937;
        color: #ffffff;
        font-weight: bold;
        text-align: center;
        border: 1px solid #94a3b8;
        padding: 8px;
    }

    td {
        border: 1px solid #cbd5e1;
        padding: 7px;
        vertical-align: middle;
    }

    .estado-finalizado {
        background: #dcfce7;
        color: #166534;
        font-weight: bold;
        text-align: center;
    }

    .estado-proceso {
        background: #fef3c7;
        color: #92400e;
        font-weight: bold;
        text-align: center;
    }

    .center {
        text-align: center;
    }
</style>

</head>

<body>

<table>
    <tr>
        <td colspan="8" class="title">
            Reporte de Recorridos Guardias - Workspace Faret
        </td>
    </tr>

    <tr>
        <td colspan="8" class="subtitle">
            Fecha de exportación: <?= date('d-m-Y H:i') ?>
        </td>
    </tr>

    <tr>
        <td colspan="8" class="subtitle">
            Filtros:
            Desde <?= htmlspecialchars($fechaDesde ?: 'Todos') ?> |
            Hasta <?= htmlspecialchars($fechaHasta ?: 'Todos') ?> |
            Usuario <?= htmlspecialchars($usuario ?: 'Todos') ?> |
            Planta <?= htmlspecialchars($planta ?: 'Todas') ?>
        </td>
    </tr>

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

    <?php foreach ($registrosFiltrados as $r): ?>

        <?php
            $estado = strtoupper($r['estado'] ?? '');
            $estadoClass = $estado === 'FINALIZADO'
                ? 'estado-finalizado'
                : 'estado-proceso';
        ?>

        <tr>
            <td class="center"><?= htmlspecialchars($r['recorridoId'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['username'] ?? '') ?></td>
            <td class="<?= $estadoClass ?>"><?= htmlspecialchars($r['estado'] ?? '') ?></td>
            <td><?= htmlspecialchars(formatoFechaExcel($r['fechaInicio'] ?? null)) ?></td>
            <td><?= htmlspecialchars(formatoFechaExcel($r['fechaFin'] ?? null)) ?></td>
            <td><?= htmlspecialchars($r['planta'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['ubicacion'] ?? '') ?></td>
            <td><?= htmlspecialchars(formatoFechaExcel($r['fechaHora'] ?? null)) ?></td>
        </tr>

    <?php endforeach; ?>

</table>

</body>
</html>
