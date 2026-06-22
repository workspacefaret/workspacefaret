<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

class ApiClient
{
    private static function request($baseUrl, $endpoint, $method = 'GET', $body = null)
    {
        $url = $baseUrl . ltrim($endpoint, '/');

        $headers = [
            'Accept: application/json'
        ];

        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers
        ];

        if ($method === 'POST') {
            $payload = json_encode($body);

            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $payload;
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => $error,
                'data' => null
            ];
        }

        $data = json_decode($response, true);

        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status' => $statusCode,
            'error' => $statusCode >= 400 ? $response : null,
            'data' => $data
        ];
    }

    public static function get($endpoint)
    {
        return self::request(API_GUARDIAS, $endpoint);
    }

    public static function getMejoraContinua($endpoint)
    {
        return self::request(API_MEJORA_CONTINUA, $endpoint);
    }

    public static function postMejoraContinua($endpoint, $body)
    {
        return self::request(API_MEJORA_CONTINUA, $endpoint, 'POST', $body);
    }
}
