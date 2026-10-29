# Prepara el entorno LOCAL para php_gate_test.py: docroot temporal con SQLite de usuarios propio, API (Release) y php -S.
# Uso:  powershell -File tests/extintores/php_gate_setup.ps1 -Conn "<cadena SQL Server local>" -Php "<php.exe>" -Base "<carpeta temporal>"
# Con -Https (y -ConCa) prueba TLS con un certificado autofirmado local; -HostApi localhost prueba el nombre de host incorrecto.
# Escribe los puertos y pids en <Base>\gate_estado.json. Para terminar: php_gate_setup.ps1 -Detener -Base "<carpeta>".
param(
    [string]$Conn,
    [string]$Php = 'php',
    [string]$Base = $env:TEMP,
    [switch]$Detener,
    [switch]$Https,      # API local por HTTPS con certificado autofirmado (prueba de TLS)
    [switch]$ConCa,      # con -Https: confiar en ese certificado via API_PREVENCION_CAFILE
    [string]$HostApi = '127.0.0.1'   # nombre usado para llegar a la API (para probar nombre de host incorrecto)
)
$ErrorActionPreference = 'Stop'
$estadoPath = Join-Path $Base 'gate_estado.json'

if ($Detener) {
    if (Test-Path $estadoPath) {
        $e = Get-Content $estadoPath -Raw | ConvertFrom-Json
        foreach ($id in @($e.pidApi, $e.pidWeb)) { try { Stop-Process -Id $id -Force -ErrorAction Stop } catch {} }
        Start-Sleep -Seconds 1
        Remove-Item -Recurse -Force $e.docroot -ErrorAction SilentlyContinue
        Remove-Item -Force $estadoPath
        'detenido y limpiado'
    }
    return
}

$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$dll = (Resolve-Path (Join-Path $repo '..\Formularios.Api\bin\Release\net8.0\Formularios.Api.dll')).Path
$key = 'clave-firma-local-de-prueba'

function Puerto-Libre {
    $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    $l.Start(); $p = $l.LocalEndpoint.Port; $l.Stop(); $p
}
$pa = Puerto-Libre
$pw = Puerto-Libre

$doc = Join-Path $Base ('wf_gate_' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $doc | Out-Null
foreach ($d in 'includes', 'layouts', 'assets', 'config', 'auth', 'services') {
    Copy-Item -Recurse (Join-Path $repo $d) (Join-Path $doc $d)
}
Remove-Item (Join-Path $doc 'config\secrets.php') -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path (Join-Path $doc 'modules') | Out-Null
Copy-Item -Recurse (Join-Path $repo 'modules\prevencion') (Join-Path $doc 'modules\prevencion')
New-Item -ItemType Directory -Path (Join-Path $doc 'data') | Out-Null

$esquema = 'http'
$pfxPass = ''
$lineaCa = ''
if ($Https) {
    $esquema = 'https'
    $cert = New-SelfSignedCertificate -Subject 'CN=127.0.0.1' -TextExtension @('2.5.29.17={text}IPAddress=127.0.0.1') -CertStoreLocation Cert:\CurrentUser\My -NotAfter (Get-Date).AddDays(2)
    $pfxPass = [guid]::NewGuid().ToString('N')
    Export-PfxCertificate -Cert $cert -FilePath (Join-Path $doc 'api_test.pfx') -Password (ConvertTo-SecureString $pfxPass -AsPlainText -Force) | Out-Null
    $pem = "-----BEGIN CERTIFICATE-----`n" + [Convert]::ToBase64String($cert.RawData, 'InsertLineBreaks') + "`n-----END CERTIFICATE-----`n"
    [IO.File]::WriteAllText((Join-Path $doc 'api_test.pem'), $pem)
    Remove-Item $cert.PSPath
    if ($ConCa) { $lineaCa = "define('API_PREVENCION_CAFILE','" + ((Join-Path $doc 'api_test.pem') -replace '\\', '/') + "');" }
}
Set-Content -Encoding Ascii (Join-Path $doc 'config\api.php') @"
<?php
define('API_GUARDIAS','x');define('API_MEJORA_CONTINUA','x');define('API_APIFARET','x');
define('API_FORMULARIOS','${esquema}://${HostApi}:${pa}/api/');
"@
Set-Content -Encoding Ascii (Join-Path $doc 'config\secrets.php') @"
<?php
define('API_PREVENCION_SIGNING_KEY','$key');
define('API_ADMIN_DELETE_KEY','clave-admin-vieja');
$lineaCa
"@

# Siembra de usuarios: ejecutar aparte  php tests/extintores/php_gate_seed.php <docroot>  (docroot en gate_estado.json)

$env:ASPNETCORE_ENVIRONMENT = 'Production'; $env:DOTNET_ENVIRONMENT = 'Production'
$env:ConnectionStrings__SqlServerConnection = $Conn
$env:PrevencionSecurity__SigningKey = $key
$env:AdminSecurity__DeleteKey = 'clave-admin-vieja'
$env:Cors__AllowedOrigins__0 = 'http://localhost:8080'
$env:Smtp__Host = '127.0.0.1'; $env:Smtp__Port = '1'; $env:FpsApi__BaseUrl = ''; $env:FpsApi__ApiKey = ''
if ($Https) {
    $env:Kestrel__Certificates__Default__Path = (Join-Path $doc 'api_test.pfx')
    $env:Kestrel__Certificates__Default__Password = $pfxPass
}
$api = Start-Process dotnet -ArgumentList @($dll, '--urls', "${esquema}://127.0.0.1:${pa}") -WorkingDirectory (Split-Path (Split-Path (Split-Path (Split-Path $dll)))) -PassThru -WindowStyle Hidden
$web = Start-Process $Php -ArgumentList @('-S', "127.0.0.1:$pw", '-t', $doc) -WorkingDirectory $doc -PassThru -WindowStyle Hidden

for ($i = 0; $i -lt 60; $i++) {
    try { if ((curl.exe -sk -o NUL -w "%{http_code}" "${esquema}://127.0.0.1:${pa}/api/prevencion/extintores/publico/catalogos") -eq "200") { break } } catch {}; Start-Sleep 1
}
@{ puertoApi = $pa; puertoWeb = $pw; pidApi = $api.Id; pidWeb = $web.Id; docroot = $doc } | ConvertTo-Json | Set-Content $estadoPath
"listo: web $pw / api $pa"
