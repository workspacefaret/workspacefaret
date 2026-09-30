<?php

// Pruebas del helper de presentación de Portal SAP (modules/sap/_ui.php).
// Uso (desde la raíz del repo): php tests/sap_ui_test.php
// Sin dependencias: no llama a apifaret ni a SAP, usa respuestas sintéticas.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Santiago');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiFaretClient.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/sap/_ui.php';

$fallos = 0;
$total = 0;

function probar(string $nombre, $obtenido, $esperado): void
{
    global $fallos, $total;
    $total++;

    if ($obtenido !== $esperado) {
        $fallos++;
        echo "FALLA: $nombre\n  esperado: " . var_export($esperado, true) . "\n  obtenido: " . var_export($obtenido, true) . "\n";
    }
}

function respuesta(bool $ok, int $status, ?array $cuerpo): array
{
    return ['ok' => $ok, 'status' => $status, 'error' => null, 'data' => $cuerpo];
}

// Fechas: la parte de fecha no se desplaza por zona horaria.
probar('fecha OData UTC no retrocede un día', sapFecha('2026-01-22T00:00:00Z'), '22-01-2026');
probar('fecha SQLQuery yyyyMMdd', sapFecha('20221111'), '11-11-2022');
probar('fecha vacía', sapFecha(''), '-');
probar('fecha null', sapFecha(null), '-');
probar('fecha ilegible se muestra tal cual', sapFecha('no-es-fecha'), 'no-es-fecha');
probar('fecha inválida (30 feb)', sapFechaParse('2026-02-30'), null);
probar('días desde hoy = 0', sapDiasDesde(date('Y-m-d') . 'T00:00:00Z'), 0);
probar('días desde hace 10 días', sapDiasDesde(date('Ymd', strtotime('-10 days'))), 10);
probar('días fecha futura = 0', sapDiasDesde(date('Ymd', strtotime('+5 days'))), 0);
probar('días fecha ilegible', sapDiasDesde('x'), null);

// Cantidades.
probar('cantidad entera', sapCantidad(12400), '12.400');
probar('cantidad decimal', sapCantidad(1.5), '1,50');
probar('cantidad null', sapCantidad(null), '-');

// Estados: solo traducciones confirmadas; el resto se muestra tal cual.
probar('estado abierto', strip_tags(sapBadgeEstado('bost_Open')), 'Abierta');
probar('estado cerrado', strip_tags(sapBadgeEstado('bost_Close')), 'Cerrada');
probar('picking liberado', strip_tags(sapBadgeEstado('ps_Released')), 'Liberado');
probar('estado desconocido sin inventar', strip_tags(sapBadgeEstado('bost_Paid')), 'bost_Paid');
probar('estado conserva valor técnico en title', str_contains(sapBadgeEstado('bost_Open'), 'title="Estado en SAP: bost_Open"'), true);
probar('estado escapa HTML', str_contains(sapBadgeEstado('<b>x</b>'), '<b>'), false);

// Empresa: CompanyDB crudo -> código del portal.
probar('empresa CompanyDB', sapEmpresa('FARET_PRODUCCION'), 'FARET');
probar('empresa ya normalizada', sapEmpresa('innpack'), 'INNPACK');
probar('empresa desconocida usa fallback', sapEmpresa('OTRA_PRODUCCION', 'FARET'), 'FARET');
probar('etiqueta empresa desconocida', sapEmpresaEtiqueta('OTRA_DB'), 'OTRA_DB');

// Conteos honestos.
probar('conteo exacto bajo el tope', sapConteo(7, 50), '7');
probar('conteo en el máximo de apifaret', sapConteo(20, 50), '20+');
probar('conteo en el top pedido', sapConteo(10, 10), '10+');
probar('cero es exacto', sapConteo(0, 20), '0');
probar('sin nota si no está truncado', sapNotaTruncado(5, 50), '');
probar('nota si está truncado', str_contains(sapNotaTruncado(20, 50), 'Puede haber más en SAP'), true);

// Resultados: ok / parcial / error.
$ok = sapResultado(respuesta(true, 200, ['ok' => true, 'total' => 1, 'data' => [['lote' => 'A']]]), true);
probar('resultado ok', [$ok['estado'], count($ok['filas'])], ['ok', 1]);

$parcial = sapResultado(respuesta(false, 200, [
    'ok' => false, 'total' => 1, 'data' => [['lote' => 'A']],
    'errors' => [['empresa' => 'LENIAN_PRODUCCION', 'status' => 500, 'mensaje' => 'x']],
]), true);
probar('parcial conserva datos', [$parcial['estado'], count($parcial['filas']), $parcial['empresasFallidas']], ['parcial', 1, ['LENIAN']]);

$parcialVacio = sapResultado(respuesta(false, 200, [
    'ok' => false, 'total' => 0, 'data' => [],
    'errors' => [['empresa' => 'LENIAN_PRODUCCION', 'status' => 500, 'mensaje' => 'x']],
]), true);
probar('parcial sin filas sigue siendo parcial', $parcialVacio['estado'], 'parcial');

$todasFallan = sapResultado(respuesta(false, 200, [
    'ok' => false, 'total' => 0, 'data' => [],
    'errors' => array_map(fn($e) => ['empresa' => $e . '_PRODUCCION'], ApiFaretClient::EMPRESAS_VALIDAS),
]), true);
probar('fallan las 4 empresas = error', $todasFallan['estado'], 'error');

$monoempresa = sapResultado(respuesta(false, 200, ['ok' => false, 'data' => [], 'errors' => [['empresa' => 'FARET_PRODUCCION']]]), false);
probar('consulta de una empresa con error = error', $monoempresa['estado'], 'error');

probar('sin conexión = error', sapResultado(respuesta(false, 0, null), true)['estado'], 'error');
probar('HTTP 500 = error', sapResultado(respuesta(false, 500, ['errors' => [['empresa' => 'FARET']]]), true)['estado'], 'error');

probar('aviso parcial 1 empresa', strip_tags(sapAvisoParcial(['LENIAN'])), 'No se pudo consultar LENIAN. El resto de los resultados está disponible.');
probar('aviso parcial 2 empresas', strip_tags(sapAvisoParcial(['LENIAN', 'PHARPACK'])), 'No se pudieron consultar LENIAN y PHARPACK. El resto de los resultados está disponible.');
probar('sin aviso si no hay fallas', sapAvisoParcial([]), '');

// Mensajes de error sin detalle técnico.
probar('error sin conexión', sapMensajeError(respuesta(false, 0, null)), 'SAP no respondió a tiempo o no está disponible. Intenta nuevamente en unos minutos.');
probar('error por límite de consultas', str_contains(sapMensajeError(respuesta(false, 429, null)), 'Espera un minuto'), true);
probar('error de credencial', str_contains(sapMensajeError(respuesta(false, 401, null)), 'Avisa a TI'), true);
probar('tarjeta de error no incluye cuerpo crudo', str_contains(sapErrorCard('X', ['ok' => false, 'status' => 500, 'error' => 'SECRETO-INTERNO', 'data' => null]), 'SECRETO-INTERNO'), false);

// Texto corto.
probar('texto corto vacío', sapTextoCorto(''), '-');
probar('texto corto sin recorte', sapTextoCorto('Hola'), 'Hola');
probar('texto corto recorta y conserva completo en title', str_contains(sapTextoCorto(str_repeat('a', 80), 10), 'title="' . str_repeat('a', 80) . '"'), true);

echo "\n$total pruebas, $fallos fallas\n";
exit($fallos === 0 ? 0 : 1);
