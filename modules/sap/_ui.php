<?php

// Helpers de presentación compartidos por Portal SAP (modules/sap/**): fechas,
// cantidades, estados, empresa, paginación y avisos de error/parcial. Solo formato y semántica — no llama a apifaret ni decide permisos.
// Requiere services/ApiFaretClient.php cargado antes (usa EMPRESAS_VALIDAS).

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

// Traducciones solo para estados cuyo significado está confirmado. Cualquier otro
// valor se muestra tal cual, sin inventar una traducción.
const SAP_ESTADOS = [
    'bost_Open' => ['Abierta', 'status-pending'],
    'bost_Close' => ['Cerrada', 'status-ok'],
    'ps_Released' => ['Liberado', 'status-pending'],
];

// Fechas: apifaret entrega "2026-01-22T00:00:00Z" (OData) o "20260122" (SQLQueries).
// Se toma solo la parte de fecha: pasar el valor completo por strtotime() lo
// interpretaba como medianoche UTC y en America/Santiago mostraba el día anterior.
function sapFechaParse($valor): ?DateTimeImmutable
{
    $valor = trim((string) $valor);

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) || preg_match('/^(\d{4})(\d{2})(\d{2})$/', $valor, $m)) {
        if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return new DateTimeImmutable($m[1] . '-' . $m[2] . '-' . $m[3]);
        }
    }

    return null;
}

function sapFecha($valor): string
{
    $fecha = sapFechaParse($valor);

    if ($fecha !== null) {
        return $fecha->format('d-m-Y');
    }

    return ($valor === null || $valor === '') ? '-' : (string) $valor;
}

// Días completos desde una fecha SAP hasta hoy. null si la fecha no se puede leer.
function sapDiasDesde($valor): ?int
{
    $fecha = sapFechaParse($valor);

    if ($fecha === null) {
        return null;
    }

    $dias = (int) $fecha->diff(new DateTimeImmutable('today'))->format('%r%a');

    return max(0, $dias);
}

function sapCantidad($n): string
{
    if ($n === null || $n === '') {
        return '-';
    }

    $n = (float) $n;
    $decimales = floor($n) == $n ? 0 : 2;

    return number_format($n, $decimales, ',', '.');
}

function sapBadgeEstado($estado): string
{
    $estado = (string) $estado;

    if ($estado === '') {
        return '<span class="status-badge">-</span>';
    }

    [$texto, $clase] = SAP_ESTADOS[$estado] ?? [$estado, ''];

    return '<span class="status-badge ' . $clase . '" title="Estado en SAP: ' . htmlspecialchars($estado) . '">' . htmlspecialchars($texto) . '</span>';
}

// Las filas de lotes/* e inventario/antiguedad traen el CompanyDB de SAP
// ("FARET_PRODUCCION"), no el código que usa Portal SAP ("FARET").
function sapEmpresa($valor, ?string $fallback = null): ?string
{
    $codigo = strtoupper(preg_replace('/_PRODUCCION$/i', '', trim((string) $valor)));

    return in_array($codigo, ApiFaretClient::EMPRESAS_VALIDAS, true) ? $codigo : $fallback;
}

function sapEmpresaEtiqueta($valor): string
{
    return sapEmpresa($valor) ?? ((string) $valor !== '' ? (string) $valor : '-');
}

// Clasifica una respuesta de ApiFaretClient: 'ok', 'parcial' (consulta multiempresa
// con datos válidos donde fallaron solo algunas compañías) o 'error'.
function sapResultado(array $respuesta, bool $multiempresa = false): array
{
    $cuerpo = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];
    $filas = is_array($cuerpo['data'] ?? null) ? $cuerpo['data'] : [];

    if ($respuesta['ok']) {
        return ['estado' => 'ok', 'filas' => $filas, 'empresasFallidas' => []];
    }

    $status = (int) ($respuesta['status'] ?? 0);
    $errores = is_array($cuerpo['errors'] ?? null) ? $cuerpo['errors'] : [];
    $fallidas = array_values(array_unique(array_map(fn($e) => sapEmpresaEtiqueta($e['empresa'] ?? ''), $errores)));

    if ($multiempresa && $status >= 200 && $status < 300 && count($fallidas) > 0
        && count($fallidas) < count(ApiFaretClient::EMPRESAS_VALIDAS)) {
        return ['estado' => 'parcial', 'filas' => $filas, 'empresasFallidas' => $fallidas];
    }

    return ['estado' => 'error', 'filas' => [], 'empresasFallidas' => $fallidas];
}

// Mensaje para el usuario, sin detalle técnico (el detalle queda en error_log vía ApiFaretClient).
function sapMensajeError(array $respuesta): string
{
    $status = (int) ($respuesta['status'] ?? 0);

    if ($status === 429) {
        return 'Hay muchas consultas a SAP en este momento. Espera un minuto e intenta de nuevo.';
    }

    if (in_array($status, [401, 403, 503], true)) {
        return 'Portal SAP no pudo conectarse al servicio de consulta. Avisa a TI.';
    }

    return 'SAP no respondió a tiempo o no está disponible. Intenta nuevamente en unos minutos.';
}

function sapErrorCard(string $titulo, array $respuesta): string
{
    return '<div class="sap-aviso"><i class="bi bi-exclamation-triangle"></i><div><strong>'
        . htmlspecialchars($titulo) . '</strong><br>' . htmlspecialchars(sapMensajeError($respuesta)) . '</div></div>';
}

function sapAvisoParcial(array $empresasFallidas): string
{
    if (count($empresasFallidas) === 0) {
        return '';
    }

    $lista = count($empresasFallidas) === 1
        ? $empresasFallidas[0]
        : implode(', ', array_slice($empresasFallidas, 0, -1)) . ' y ' . end($empresasFallidas);
    $texto = count($empresasFallidas) === 1
        ? 'No se pudo consultar ' . $lista . '. El resto de los resultados está disponible.'
        : 'No se pudieron consultar ' . $lista . '. El resto de los resultados está disponible.';

    return '<div class="sap-aviso"><i class="bi bi-exclamation-triangle"></i><div>' . htmlspecialchars($texto) . '</div></div>';
}

// Texto largo (ej. comentarios) recortado en tablas, con el texto completo en el tooltip.
function sapTextoCorto($texto, int $max = 60): string
{
    $texto = trim((string) $texto);

    if ($texto === '') {
        return '-';
    }

    if (mb_strlen($texto) <= $max) {
        return htmlspecialchars($texto);
    }

    return '<span title="' . htmlspecialchars($texto) . '">' . htmlspecialchars(rtrim(mb_substr($texto, 0, $max - 1))) . '…</span>';
}

// Ayuda breve junto a un título/columna (tooltip nativo).
function sapAyuda(string $texto): string
{
    return '<i class="bi bi-question-circle sap-ayuda" title="' . htmlspecialchars($texto) . '" aria-label="' . htmlspecialchars($texto) . '"></i>';
}

// Paginación pública de apifaret (A4): con pagina/porPagina la API devuelve el bloque
// "paginacion" {pagina, porPagina, devueltos, totalDisponible, hayMas} con el total real
// según los filtros. Exige empresa, no se combina con top y porPagina ≤ 100. Los listados
// de documentos paginados traen solo la cabecera (sin "lineas"): las líneas van en la ficha.
const SAP_POR_PAGINA_OPCIONES = [25, 50, 100];
const SAP_POR_PAGINA_DEFAULT = 25;
const SAP_PAGINA_MAX = 100000;

// Número de página desde $_GET[$param]: solo dígitos, entre 1 y SAP_PAGINA_MAX; si no, 1.
function sapLeerPagina(string $param = 'pagina'): int
{
    $valor = $_GET[$param] ?? '';

    if (!is_string($valor) || !ctype_digit($valor) || strlen($valor) > 6) {
        return 1;
    }

    $pagina = (int) $valor;

    return ($pagina >= 1 && $pagina <= SAP_PAGINA_MAX) ? $pagina : 1;
}

// Filas por página desde $_GET['porPagina']: solo 25, 50 o 100; si no, 25.
function sapLeerPorPagina(): int
{
    $valor = $_GET['porPagina'] ?? '';

    if (!is_string($valor) || !ctype_digit($valor) || strlen($valor) > 3) {
        return SAP_POR_PAGINA_DEFAULT;
    }

    return in_array((int) $valor, SAP_POR_PAGINA_OPCIONES, true) ? (int) $valor : SAP_POR_PAGINA_DEFAULT;
}

// Fragmento de query para apifaret. Un endpoint que lleva esto nunca lleva también "top".
function sapQueryPagina(int $pagina, int $porPagina): string
{
    return 'pagina=' . $pagina . '&porPagina=' . $porPagina;
}

// Parámetros de paginación que viajan en la URL de la página, solo los distintos del
// valor por defecto (URLs limpias). $paginas = ['paramDePagina' => n, ...].
function sapParamsPaginacion(array $paginas, int $porPagina): array
{
    $params = [];

    foreach ($paginas as $param => $pagina) {
        if ($pagina > 1) {
            $params[$param] = $pagina;
        }
    }

    if ($porPagina !== SAP_POR_PAGINA_DEFAULT) {
        $params['porPagina'] = $porPagina;
    }

    return $params;
}

// Campo oculto para que un formulario de filtros conserve el tamaño de página elegido.
// Los números de página no se incluyen: cambiar un filtro vuelve siempre a la página 1.
function sapInputPorPagina(int $porPagina): string
{
    return $porPagina !== SAP_POR_PAGINA_DEFAULT
        ? '<input type="hidden" name="porPagina" value="' . $porPagina . '">'
        : '';
}

// Bloque "paginacion" normalizado de una respuesta, o null si no vino (error o modo legacy).
function sapPaginacion(array $respuesta): ?array
{
    $bloque = is_array($respuesta['data'] ?? null) ? ($respuesta['data']['paginacion'] ?? null) : null;

    if (!is_array($bloque) || !isset($bloque['pagina'], $bloque['porPagina'])) {
        return null;
    }

    $porPagina = max(1, (int) $bloque['porPagina']);
    $total = isset($bloque['totalDisponible']) && is_numeric($bloque['totalDisponible']) ? (int) $bloque['totalDisponible'] : null;

    return [
        'pagina' => max(1, (int) $bloque['pagina']),
        'porPagina' => $porPagina,
        'devueltos' => (int) ($bloque['devueltos'] ?? 0),
        'total' => $total,
        'hayMas' => !empty($bloque['hayMas']),
        'totalPaginas' => $total !== null ? max(1, (int) ceil($total / $porPagina)) : null,
    ];
}

// Total real de una consulta hecha solo para contar (pagina=1&porPagina=1). null si no vino.
function sapTotalDisponible(array $respuesta): ?int
{
    if (!($respuesta['ok'] ?? false)) {
        return null;
    }

    return sapPaginacion($respuesta)['total'] ?? null;
}

function sapNumero(int $n): string
{
    return number_format($n, 0, ',', '.');
}

// Controles bajo una tabla paginada: "Mostrando 26–50 de 4.022", Anterior | Página 2
// de 161 | Siguiente y el selector 25 | 50 | 100. $params es el estado actual de la URL
// (empresa, filtros y páginas de otras tablas de la misma pantalla); $paramPagina es el
// parámetro de esta tabla y $ancla el id al que vuelve la pantalla tras navegar.
function sapPaginador(array $respuesta, array $params, string $paramPagina = 'pagina', string $ancla = ''): string
{
    $p = sapPaginacion($respuesta);

    if ($p === null || ($p['devueltos'] === 0 && $p['pagina'] === 1)) {
        return '';
    }

    $sufijo = $ancla !== '' ? '#' . rawurlencode($ancla) : '';
    $url = function (array $cambios) use ($params, $sufijo): string {
        foreach ($cambios as $clave => $valor) {
            if ($valor === null) {
                unset($params[$clave]);
            } else {
                $params[$clave] = $valor;
            }
        }

        return '?' . http_build_query($params) . $sufijo;
    };
    $urlPagina = fn(int $n) => $url([$paramPagina => $n > 1 ? $n : null]);

    $html = '<nav class="sap-paginacion" aria-label="Paginación">';

    if ($p['devueltos'] === 0) {
        // Página fuera de rango (ej. URL vieja o editada a mano): solo el aviso y el
        // link a la última página que sí tiene resultados.
        $destino = $p['totalPaginas'] !== null && $p['total'] > 0 ? $p['totalPaginas'] : 1;

        return $html . '<span class="sap-paginacion-resumen">No hay resultados en la página ' . sapNumero($p['pagina']) . '. '
            . '<a href="' . htmlspecialchars($urlPagina($destino)) . '">Ir a la página ' . sapNumero($destino) . '</a></span></nav>';
    }

    $desde = ($p['pagina'] - 1) * $p['porPagina'] + 1;
    $hasta = $desde + $p['devueltos'] - 1;
    $html .= '<span class="sap-paginacion-resumen">Mostrando ' . sapNumero($desde) . '–' . sapNumero($hasta)
        . ($p['total'] !== null ? ' de ' . sapNumero($p['total']) : '') . '</span>';

    $hayAnterior = $p['pagina'] > 1;
    $haySiguiente = $p['totalPaginas'] !== null ? $p['pagina'] < $p['totalPaginas'] : $p['hayMas'];

    if ($hayAnterior || $haySiguiente) {
        $html .= '<span class="sap-paginacion-nav">';
        $html .= $hayAnterior
            ? '<a class="btn-secondary" rel="prev" href="' . htmlspecialchars($urlPagina($p['pagina'] - 1)) . '"><i class="bi bi-chevron-left"></i> Anterior</a>'
            : '<span class="btn-secondary sap-paginacion-off" aria-disabled="true"><i class="bi bi-chevron-left"></i> Anterior</span>';
        $html .= '<span class="sap-paginacion-actual">Página ' . sapNumero($p['pagina'])
            . ($p['totalPaginas'] !== null ? ' de ' . sapNumero($p['totalPaginas']) : '') . '</span>';
        $html .= $haySiguiente
            ? '<a class="btn-secondary" rel="next" href="' . htmlspecialchars($urlPagina($p['pagina'] + 1)) . '">Siguiente <i class="bi bi-chevron-right"></i></a>'
            : '<span class="btn-secondary sap-paginacion-off" aria-disabled="true">Siguiente <i class="bi bi-chevron-right"></i></span>';
        $html .= '</span>';
    }

    // El selector solo aparece si hay más filas que la opción más chica (o no se sabe).
    if ($p['total'] === null || $p['total'] > SAP_POR_PAGINA_OPCIONES[0]) {
        // Cambiar el tamaño vuelve a la página 1 en todas las tablas de la pantalla.
        $cambiosBase = [];

        foreach (array_keys($params) as $clave) {
            if (str_starts_with((string) $clave, 'pagina')) {
                $cambiosBase[$clave] = null;
            }
        }

        $html .= '<span class="sap-paginacion-tamano">Por página:';

        foreach (SAP_POR_PAGINA_OPCIONES as $opcion) {
            if ($opcion === $p['porPagina']) {
                $html .= ' <strong aria-current="true">' . $opcion . '</strong>';
            } else {
                $cambios = $cambiosBase + [$paramPagina => null, 'porPagina' => $opcion === SAP_POR_PAGINA_DEFAULT ? null : $opcion];
                $html .= ' <a href="' . htmlspecialchars($url($cambios)) . '">' . $opcion . '</a>';
            }
        }

        $html .= '</span>';
    }

    return $html . '</nav>';
}

// Nota cuando la propia API avisa que el resultado quedó limitado ("truncado": true).
// No muestra las advertencias técnicas de apifaret: $texto explica qué hacer.
function sapNotaTruncadoApi(array $respuesta, string $texto): string
{
    $cuerpo = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];

    if (($cuerpo['truncado'] ?? false) !== true) {
        return '';
    }

    return '<p class="sap-nota"><i class="bi bi-info-circle"></i> ' . htmlspecialchars($texto) . '</p>';
}
