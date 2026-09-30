<?php

// Helpers de presentación compartidos por Portal SAP (modules/sap/**): fechas,
// cantidades, estados, empresa, conteos que pueden venir truncados y avisos de
// error/parcial. Solo formato y semántica — no llama a apifaret ni decide permisos.
// Requiere services/ApiFaretClient.php cargado antes (usa EMPRESAS_VALIDAS).

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

// apifaret devuelve como máximo 20 filas por empresa en cada consulta, aunque se
// pida un top mayor (verificado 2026-09-30 contra producción: top=50/200 → 20 filas,
// top=10 → 10). Su campo "total" es la cantidad descargada, no el total real en SAP.
const SAP_FILAS_MAX_POR_CONSULTA = 20;

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

// ¿Puede haber más filas en SAP que las recibidas? Sí cuando se llegó al tope
// pedido o al máximo que entrega apifaret por consulta.
function sapPuedeEstarTruncado(int $filas, int $topPedido): bool
{
    return $filas >= min($topPedido, SAP_FILAS_MAX_POR_CONSULTA);
}

// "7" cuando el conteo es exacto, "20+" cuando puede haber más.
function sapConteo(int $filas, int $topPedido): string
{
    return $filas . (sapPuedeEstarTruncado($filas, $topPedido) ? '+' : '');
}

// Nota bajo una lista que puede estar incompleta. $orden describe cómo vienen
// ordenadas: 'recientes' (documentos, DocEntry desc), 'coincidencias' (búsquedas
// por texto) o 'rango' (consultas por fechas sin un orden garantizado).
function sapNotaTruncado(int $filas, int $topPedido, string $orden = 'recientes'): string
{
    if (!sapPuedeEstarTruncado($filas, $topPedido)) {
        return '';
    }

    $textos = [
        'recientes' => 'Se muestran los ' . $filas . ' resultados más recientes. Puede haber más en SAP.',
        'coincidencias' => 'Se muestran los primeros ' . $filas . ' resultados. Si no ves lo que buscas, afina la búsqueda.',
        'rango' => 'Se muestran los primeros ' . $filas . ' resultados. Puede haber más en SAP: acota el rango de fechas para verlos.',
    ];
    $texto = $textos[$orden] ?? $textos['recientes'];

    return '<p class="sap-nota"><i class="bi bi-info-circle"></i> ' . htmlspecialchars($texto) . '</p>';
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
