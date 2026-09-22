<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$secretsPath = $_SERVER['DOCUMENT_ROOT'] . '/config/secrets.php';
if (file_exists($secretsPath)) {
    require_once $secretsPath;
}

class ApiFaretClient
{
    const EMPRESAS_VALIDAS = ['FARET', 'INNPACK', 'PHARPACK', 'LENIAN'];

    private static function request($endpoint, $method = 'GET')
    {
        if (!defined('API_APIFARET_KEY') || API_APIFARET_KEY === '') {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'API_APIFARET_KEY no configurada en config/secrets.php',
                'data' => null
            ];
        }

        $url = API_APIFARET . ltrim($endpoint, '/');

        $headers = [
            'Accept: application/json',
            'X-Api-Key: ' . API_APIFARET_KEY
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            error_log('ApiFaretClient: error de conexión en ' . $endpoint . ' - ' . $error);

            return [
                'ok' => false,
                'status' => 0,
                'error' => $error,
                'data' => null
            ];
        }

        if ($statusCode >= 400) {
            error_log('ApiFaretClient: HTTP ' . $statusCode . ' en ' . $endpoint);
        }

        $data = json_decode($response, true);

        // apifaret puede responder HTTP 200 con {ok:false, errors:[...]} cuando la
        // consulta a SAP falló para la compañía pedida (login vencido, Service Layer
        // caído, etc.) — ApiListResponseDto.Exitoso() en apifaret siempre devuelve
        // 200, el fallo real solo se ve en el cuerpo. Sin este chequeo, esos casos se
        // mostraban como "sin resultados" en vez del error real.
        $okHttp = $statusCode >= 200 && $statusCode < 300;
        $okCuerpo = is_array($data) && array_key_exists('ok', $data) ? (bool) $data['ok'] : true;

        if ($okHttp && !$okCuerpo) {
            error_log('ApiFaretClient: HTTP 200 con ok:false en ' . $endpoint);
        }

        return [
            'ok' => $okHttp && $okCuerpo,
            'status' => $statusCode,
            'error' => ($statusCode >= 400 || !$okCuerpo) ? $response : null,
            'data' => $data
        ];
    }

    public static function get($endpoint, $empresa = null)
    {
        if ($empresa !== null) {
            if (!in_array($empresa, self::EMPRESAS_VALIDAS, true)) {
                return [
                    'ok' => false,
                    'status' => 0,
                    'error' => 'Empresa inválida: ' . $empresa,
                    'data' => null
                ];
            }

            $separador = strpos($endpoint, '?') === false ? '?' : '&';
            $endpoint .= $separador . 'empresa=' . rawurlencode($empresa);
        }

        return self::request($endpoint, 'GET');
    }

    // Lee ?empresa= de la URL, valida contra la lista real de compañías de apifaret
    // y cae a $default si falta o es inválida. Fuente de verdad: la query string,
    // igual que el resto de los filtros del proyecto.
    public static function empresaActual($default = 'FARET')
    {
        $empresa = strtoupper(trim($_GET['empresa'] ?? ''));

        return in_array($empresa, self::EMPRESAS_VALIDAS, true) ? $empresa : $default;
    }

    // Mensaje para mostrar al usuario cuando $respuesta['ok'] es false, sin exponer
    // el cuerpo crudo de SAP. Centralizado (antes duplicado por página) para que el
    // caso "HTTP 200 con ok:false" (ver request()) se explique igual en todo Portal SAP.
    public static function mensajeError($respuesta)
    {
        if ($respuesta['status'] === 0) {
            return 'Sin respuesta de apifaret (timeout o conexión rechazada).';
        }

        $errores = is_array($respuesta['data'] ?? null) ? ($respuesta['data']['errors'] ?? null) : null;

        if (is_array($errores) && count($errores) > 0) {
            $primero = $errores[0];
            $empresaTxt = isset($primero['empresa']) ? ' en ' . $primero['empresa'] : '';

            return 'SAP no respondió correctamente' . $empresaTxt . ': ' . ($primero['mensaje'] ?? 'error desconocido') . '.';
        }

        return 'apifaret respondió con código HTTP ' . $respuesta['status'] . '.';
    }
}
