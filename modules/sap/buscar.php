<?php

// Proxy JSON server-side para el autocomplete en vivo de Clientes/Proveedores/Inventario.
// Existe porque la API key de apifaret debe seguir siendo server-only (ApiFaretClient no puede
// llamarse desde el navegador) — reutiliza exactamente los mismos endpoints /buscar que ya usan
// clientes/proveedores/inventario, solo que con un top acotado para una lista de sugerencias.

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('portal_sap');

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';

header('Content-Type: application/json; charset=utf-8');

const TOP_SUGERENCIAS = 8;

$endpointsPorTipo = [
    'clientes' => 'clientes/buscar',
    'proveedores' => 'proveedores/buscar',
    'articulos' => 'articulos/buscar',
];

$tipo = $_GET['tipo'] ?? '';
$texto = trim($_GET['texto'] ?? '');
$empresa = ApiFaretClient::empresaActual();

if (!isset($endpointsPorTipo[$tipo]) || mb_strlen($texto) < 3) {
    echo json_encode(['ok' => true, 'data' => []]);
    exit;
}

$respuesta = ApiFaretClient::get($endpointsPorTipo[$tipo] . '?texto=' . rawurlencode($texto) . '&top=' . TOP_SUGERENCIAS, $empresa);

if (!$respuesta['ok']) {
    echo json_encode(['ok' => false, 'data' => []]);
    exit;
}

$items = $respuesta['data']['data'] ?? [];
$sugerencias = [];

foreach ($items as $item) {
    if ($tipo === 'articulos') {
        $sugerencias[] = [
            'valor' => $item['itemCode'] ?? '',
            'titulo' => $item['itemName'] ?? '-',
            'subtitulo' => $item['itemCode'] ?? '',
        ];
    } else {
        $sub = $item['cardCode'] ?? '';

        if (!empty($item['ciudad'])) {
            $sub .= ' · ' . $item['ciudad'];
        }

        $sugerencias[] = [
            'valor' => $item['cardCode'] ?? '',
            'titulo' => $item['cardName'] ?? '-',
            'subtitulo' => $sub,
        ];
    }
}

echo json_encode(['ok' => true, 'data' => $sugerencias]);
