<?php
// Puerta administrativa de Extintores hacia Formularios.Api (SOLO rutas de api/prevencion/extintores, no es un proxy general).
//
// El navegador llama a este archivo con la sesión del portal; aquí se valida en el servidor que el usuario esté logueado, activo
// y con acceso a «prevencion_riesgos» (nivel «ver» para consultar, «gestionar» para modificar), y luego se llama a la API
// firmando la petición con una clave que solo conocen este servidor y la API (config/secrets.php). Esa clave nunca llega al
// navegador y la API vuelve a verificar firma y nivel. El formulario público NO pasa por aquí (usa api/.../publico/* directo).
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/api.php';

$secretsPath = $_SERVER['DOCUMENT_ROOT'] . '/config/secrets.php';
if (file_exists($secretsPath)) {
    require_once $secretsPath;
}

const PR_MODULO = 'prevencion_riesgos';
const PR_MAX_CUERPO = 1048576;

function prFallar(int $status, string $mensaje): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $mensaje;
    exit;
}

// Rutas permitidas: [método, patrón de la ruta relativa, nivel mínimo]. Todo lo demás se rechaza.
$rutas = [
    ['GET', '#^(catalogos|consolidado|\d+|\d+/inspecciones|inspecciones/\d+)?$#', 'ver'],
    ['GET', '#^(areas|brigadistas)$#', 'gestionar'],
    ['POST', '#^(|inspecciones|areas|brigadistas)$#', 'gestionar'],
    ['PUT', '#^(\d+|areas/\d+|brigadistas/\d+)$#', 'gestionar'],
];

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ruta = $_GET['r'] ?? '';
if (!is_string($ruta) || strlen($ruta) > 60) {
    prFallar(404, 'No encontrado.');
}

$nivelRequerido = null;
foreach ($rutas as [$m, $patron, $nivel]) {
    if ($m === $metodo && preg_match($patron, $ruta)) {
        $nivelRequerido = $nivel;
        break;
    }
}
if ($nivelRequerido === null) {
    prFallar(404, 'No encontrado.');
}

// Query reenviada (filtros): sin la clave 'r', con alfabeto restringido.
$params = $_GET;
unset($params['r']);
$query = http_build_query($params);
if (strlen($query) > 512 || !preg_match('#^[A-Za-z0-9=&%._\-]*$#', $query)) {
    prFallar(400, 'Parámetros inválidos.');
}

// Sesión y permiso, validados aquí en cada llamada (no se confía en lo que muestre la interfaz).
$usuario = currentUser();
if ($usuario === null) {
    prFallar(401, 'Tu sesión expiró. Vuelve a iniciar sesión.');
}
if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > SESSION_INACTIVIDAD_MINUTOS * 60) {
    prFallar(401, 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.');
}

$st = getAuthPdo()->prepare('SELECT activo, debe_cambiar_password FROM usuarios WHERE id = ?');
$st->execute([(int) $usuario['id']]);
$fila = $st->fetch(PDO::FETCH_ASSOC);
if (!$fila || (int) $fila['activo'] !== 1 || (int) $fila['debe_cambiar_password'] === 1) {
    prFallar(401, 'Tu sesión ya no es válida.');
}

$nivel = moduleAccessLevel(PR_MODULO);
if ($nivel === null || ($nivelRequerido === 'gestionar' && $nivel !== 'gestionar')) {
    prFallar(403, 'No tienes permiso para esta operación.');
}
$nivel = $nivel === 'gestionar' ? 'gestionar' : 'ver';

$_SESSION['ultimo_acceso'] = time();
session_write_close();   // no bloquear llamadas paralelas de la misma sesión

// Protección CSRF para escrituras: el navegador same-origin siempre envía Origin o Referer propios.
if ($metodo !== 'GET') {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $fuente = $origen !== '' ? $origen : $ref;
    $hostOrigen = $fuente !== '' ? parse_url($fuente, PHP_URL_HOST) : null;
    $puertoOrigen = $fuente !== '' ? parse_url($fuente, PHP_URL_PORT) : null;
    $declarado = is_string($hostOrigen) ? strtolower($hostOrigen . ($puertoOrigen ? ':' . $puertoOrigen : '')) : '';
    if ($declarado === '' || $declarado !== $host) {
        prFallar(403, 'Origen no permitido.');
    }
}

$clave = defined('API_PREVENCION_SIGNING_KEY') ? API_PREVENCION_SIGNING_KEY : '';
if ($clave === '') {
    prFallar(503, 'El servicio no está configurado.');
}

$cuerpo = '';
if ($metodo === 'POST' || $metodo === 'PUT') {
    $cuerpo = file_get_contents('php://input', false, null, 0, PR_MAX_CUERPO + 1);
    if ($cuerpo === false || strlen($cuerpo) > PR_MAX_CUERPO) {
        prFallar(413, 'La solicitud es demasiado grande.');
    }
}

$base = rtrim(API_FORMULARIOS, '/');   // .../formularios/api
$pathApi = '/api/prevencion/extintores' . ($ruta === '' ? '' : '/' . $ruta);
$pathYQuery = $pathApi . ($query === '' ? '' : '?' . $query);

$ts = (string) time();
$userB64 = rtrim(strtr(base64_encode((string) $usuario['username']), '+/', '-_'), '=');
$firma = rtrim(strtr(base64_encode(hash_hmac('sha256', $ts . "\n" . $metodo . "\n" . $pathYQuery . "\n" . $userB64 . "\n" . $nivel, $clave, true)), '+/', '-_'), '=');

$url = preg_replace('#/api$#', '', $base) . $pathYQuery;
$cabeceras = ['X-WF-Ts: ' . $ts, 'X-WF-User: ' . $userB64, 'X-WF-Nivel: ' . $nivel, 'X-WF-Sig: ' . $firma, 'Expect:'];
if ($cuerpo !== '') {
    $cabeceras[] = 'Content-Type: application/json';
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $metodo,
    CURLOPT_HTTPHEADER => $cabeceras,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    // TLS SIEMPRE verificado (cadena de confianza + nombre de host), a diferencia de services/ApiClient.php (no se toca).
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
// Windows: usar el almacén de certificados del sistema (no requiere curl.cainfo en php.ini). Si la constante no existe
// (PHP < 8.2), se usa el bundle configurado en php.ini (curl.cainfo).
if (defined('CURLSSLOPT_NATIVE_CA')) {
    curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
}
// Opcional: si la API usa una CA interna que el servidor no conoce, se puede indicar su certificado (PEM) en secrets.php
// en vez de tocar php.ini. Sigue verificándose cadena y nombre; esto solo agrega una CA de confianza.
if (defined('API_PREVENCION_CAFILE') && API_PREVENCION_CAFILE !== '' && is_file(API_PREVENCION_CAFILE)) {
    curl_setopt($ch, CURLOPT_CAINFO, API_PREVENCION_CAFILE);
}
if ($cuerpo !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
}

$respuesta = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$errno = curl_errno($ch);
curl_close($ch);

if ($respuesta === false || $status === 0) {
    if (in_array($errno, [35, 51, 58, 60, 77, 83], true)) {   // errores TLS/certificado de libcurl
        error_log('[extintores/api.php] fallo TLS hacia la API (curl ' . $errno . '): revisar certificado/cadena de confianza.');
    }
    prFallar(502, 'No se pudo comunicar con el servicio de inspecciones.');
}

// Una 401/403 de la API aquí indica un problema de configuración/reloj, no del usuario: se informa sin detalles internos.
if ($status === 401) {
    prFallar(502, 'El servicio de inspecciones rechazó la llamada (revisar configuración de la clave).');
}

http_response_code($status);
header('Content-Type: ' . ($tipo !== '' ? $tipo : 'application/json; charset=utf-8'));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $respuesta;
