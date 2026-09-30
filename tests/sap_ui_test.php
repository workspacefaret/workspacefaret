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

// Paginación: lectura estricta de parámetros.
function conGet(array $get, callable $fn)
{
    $anterior = $_GET;
    $_GET = $get;
    $resultado = $fn();
    $_GET = $anterior;

    return $resultado;
}

probar('página por defecto', conGet([], fn() => sapLeerPagina()), 1);
probar('página válida', conGet(['pagina' => '3'], fn() => sapLeerPagina()), 3);
probar('página con otro parámetro', conGet(['paginaNV' => '7'], fn() => sapLeerPagina('paginaNV')), 7);
probar('página 0 = 1', conGet(['pagina' => '0'], fn() => sapLeerPagina()), 1);
probar('página negativa = 1', conGet(['pagina' => '-2'], fn() => sapLeerPagina()), 1);
probar('página con texto = 1', conGet(['pagina' => '2abc'], fn() => sapLeerPagina()), 1);
probar('página decimal = 1', conGet(['pagina' => '2.5'], fn() => sapLeerPagina()), 1);
probar('página arreglo = 1', conGet(['pagina' => ['2']], fn() => sapLeerPagina()), 1);
probar('página enorme = 1', conGet(['pagina' => '99999999999'], fn() => sapLeerPagina()), 1);
probar('porPagina por defecto', conGet([], fn() => sapLeerPorPagina()), 25);
probar('porPagina 50', conGet(['porPagina' => '50'], fn() => sapLeerPorPagina()), 50);
probar('porPagina 100', conGet(['porPagina' => '100'], fn() => sapLeerPorPagina()), 100);
probar('porPagina fuera de opciones = 25', conGet(['porPagina' => '30'], fn() => sapLeerPorPagina()), 25);
probar('porPagina sobre 100 = 25', conGet(['porPagina' => '500'], fn() => sapLeerPorPagina()), 25);
probar('porPagina con texto = 25', conGet(['porPagina' => '50x'], fn() => sapLeerPorPagina()), 25);
probar('query de página', sapQueryPagina(2, 50), 'pagina=2&porPagina=50');
probar('params omiten valores por defecto', sapParamsPaginacion(['pagina' => 1, 'paginaNV' => 3], 25), ['paginaNV' => 3]);
probar('params incluyen porPagina distinto', sapParamsPaginacion(['pagina' => 1], 100), ['porPagina' => 100]);
probar('input porPagina solo si no es default', [sapInputPorPagina(25), str_contains(sapInputPorPagina(50), 'value="50"')], ['', true]);

// Paginación: lectura de la respuesta de apifaret.
function paginada(int $pagina, int $porPagina, int $devueltos, ?int $total, bool $hayMas): array
{
    return respuesta(true, 200, [
        'ok' => true, 'total' => $devueltos, 'data' => [],
        'paginacion' => ['pagina' => $pagina, 'porPagina' => $porPagina, 'devueltos' => $devueltos, 'totalDisponible' => $total, 'hayMas' => $hayMas, 'maxPorPagina' => 100],
    ]);
}

$pInter = sapPaginacion(paginada(2, 25, 25, 4022, true));
probar('total de páginas', [$pInter['total'], $pInter['totalPaginas']], [4022, 161]);
probar('sin bloque paginacion (legacy) = null', sapPaginacion(respuesta(true, 200, ['ok' => true, 'data' => []])), null);
probar('total disponible para conteo', sapTotalDisponible(paginada(1, 1, 1, 317, true)), 317);
probar('total disponible cero', sapTotalDisponible(paginada(1, 1, 0, 0, false)), 0);
probar('total disponible con error = null', sapTotalDisponible(respuesta(false, 500, null)), null);
probar('número con miles', sapNumero(4022), '4.022');

$htmlInter = sapPaginador(paginada(2, 25, 25, 4022, true), ['empresa' => 'FARET', 'desde' => '2026-01-01', 'paginaTr' => 2], 'paginaTr', 'traslados');
probar('resumen página intermedia', str_contains(strip_tags($htmlInter), 'Mostrando 26–50 de 4.022'), true);
probar('página N de M', str_contains(strip_tags($htmlInter), 'Página 2 de 161'), true);
probar('anterior vuelve a página 1 sin parámetro', str_contains($htmlInter, 'href="?empresa=FARET&amp;desde=2026-01-01#traslados"'), true);
probar('siguiente conserva filtros', str_contains($htmlInter, 'href="?empresa=FARET&amp;desde=2026-01-01&amp;paginaTr=3#traslados"'), true);
probar('cambiar tamaño vuelve a página 1', str_contains($htmlInter, 'href="?empresa=FARET&amp;desde=2026-01-01&amp;porPagina=50#traslados"'), true);
probar('tamaño actual marcado', str_contains($htmlInter, '<strong aria-current="true">25</strong>'), true);

$htmlPrimera = sapPaginador(paginada(1, 25, 25, 60, true), ['empresa' => 'FARET']);
probar('primera página: anterior deshabilitado', str_contains($htmlPrimera, 'aria-disabled="true"><i class="bi bi-chevron-left"></i> Anterior'), true);
probar('primera página: siguiente activo', str_contains($htmlPrimera, 'rel="next"'), true);

$htmlUltima = sapPaginador(paginada(3, 25, 10, 60, false), ['empresa' => 'FARET', 'pagina' => 3]);
probar('última página: resumen', str_contains(strip_tags($htmlUltima), 'Mostrando 51–60 de 60'), true);
probar('última página: siguiente deshabilitado', [str_contains($htmlUltima, 'rel="next"'), str_contains($htmlUltima, 'rel="prev"')], [false, true]);

$htmlUnica = sapPaginador(paginada(1, 25, 7, 7, false), ['empresa' => 'FARET']);
probar('una sola página: sin navegación ni selector', [str_contains($htmlUnica, 'Anterior'), str_contains($htmlUnica, 'Por página')], [false, false]);
probar('una sola página: resumen', strip_tags($htmlUnica), 'Mostrando 1–7 de 7');
probar('sin resultados: sin paginador', sapPaginador(paginada(1, 25, 0, 0, false), ['empresa' => 'FARET']), '');
probar('respuesta con error: sin paginador', sapPaginador(respuesta(false, 500, null), []), '');

$htmlFuera = sapPaginador(paginada(999, 25, 0, 60, false), ['empresa' => 'FARET', 'pagina' => 999]);
probar('página fuera de rango ofrece la última', str_contains($htmlFuera, 'href="?empresa=FARET&amp;pagina=3"'), true);
probar('página fuera de rango sin navegación', [str_contains($htmlFuera, 'Anterior'), str_contains($htmlFuera, 'Página 999 de')], [false, false]);

$htmlSinTotal = sapPaginador(paginada(2, 25, 25, null, true), ['empresa' => 'FARET', 'pagina' => 2]);
probar('sin total: usa hayMas', [str_contains(strip_tags($htmlSinTotal), 'Mostrando 26–50'), str_contains(strip_tags($htmlSinTotal), 'Página 2 de'), str_contains($htmlSinTotal, 'rel="next"')], [true, false, true]);

probar('paginador escapa parámetros', str_contains(sapPaginador(paginada(1, 25, 25, 60, true), ['texto' => '"><script>']), '<script>'), false);

// Aviso de truncado informado por la propia API.
probar('sin truncado no hay nota', sapNotaTruncadoApi(respuesta(true, 200, ['ok' => true, 'data' => []]), 'x'), '');
probar('truncado muestra nota sin detalle técnico', strip_tags(sapNotaTruncadoApi(respuesta(true, 200, ['ok' => true, 'truncado' => true, 'advertencias' => ['APIFARET_MAPA_ALMACEN TOP 300']]), 'Filtra por artículo.')), ' Filtra por artículo.');

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
