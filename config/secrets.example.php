<?php
// Plantilla de config/secrets.php (gitignorado, no se versiona).
// Copiar este archivo como config/secrets.php en cada servidor y completar los valores reales.

define('API_ADMIN_DELETE_KEY', ''); // debe coincidir con AdminSecurity:DeleteKey en el appsettings.json de Formularios.Api

define('API_APIFARET_KEY', ''); // key del consumidor "Workspace" en Security:ApiKeys del appsettings.Production.json de apifaret

// Extintores (Prevencion de Riesgos): clave de FIRMA entre este servidor y Formularios.Api para las llamadas administrativas.
// Debe ser IGUAL a PrevencionSecurity:SigningKey en el appsettings.json de Formularios.Api. Aleatoria, minimo 32 caracteres.
// Nunca llega al navegador (la usa solo modules/prevencion/extintores/api.php). Generar p. ej. con: php -r "echo bin2hex(random_bytes(32));"
define('API_PREVENCION_SIGNING_KEY', '');

// Opcional (Extintores): ruta a un certificado de CA (PEM) si la API usa una CA interna que este servidor no reconoce.
// Sigue verificandose cadena y nombre de host; esto solo agrega esa CA como de confianza. Dejar comentado si no hace falta.
// define('API_PREVENCION_CAFILE', 'C:\PHP83\extras\ssl\ca-interna-faret.pem');
