# SOLO LECTURA. Ejecutar como Administrador EN CADA servidor antes del despliegue:
#   .70  (web):  powershell -File verificar-solo-lectura.ps1 -Rol web
#   .161 (api):  powershell -File verificar-solo-lectura.ps1 -Rol api
# No modifica nada, no imprime valores de secretos (solo si existen). Envia el resultado completo a quien prepara el despliegue.
param(
    [Parameter(Mandatory)][ValidateSet('web', 'api')][string]$Rol,
    [string]$SitioWeb = 'C:\Paginas Web\workspace',
    [string]$SitioApi = 'C:\API WEB\api\formularios',
    [string]$Php = 'C:\PHP83\php.exe',
    [string]$HostApi = 'api.faret.cl'
)
$ErrorActionPreference = 'Continue'
function Seccion($t) { Write-Host "`n=== $t" -ForegroundColor Cyan }

Seccion 'Equipo y hora (la firma HMAC tolera +-300 s entre .70 y .161)'
"Equipo: $env:COMPUTERNAME   Hora local: $((Get-Date).ToString('s'))   UTC: $([DateTime]::UtcNow.ToString('s'))   Zona: $((Get-TimeZone).Id)"
w32tm /query /status 2>&1 | Select-String 'Origen|Source|Estrato|Stratum|Última sincronización|Last Successful|Desfase|Phase' | ForEach-Object { $_.Line.Trim() }
$otro = if ($Rol -eq 'web') { '192.168.1.161' } else { '192.168.1.70' }
"Desfase contra ${otro} (3 muestras; si da error 0x800705B4 el NTP entre servidores esta filtrado: basta con que ambos tengan el mismo Origen y la hora UTC coincida):"
w32tm /stripchart /computer:$otro /samples:3 /dataonly 2>&1 | Select-Object -Last 4

Seccion "Certificado de $HostApi tal como lo ve ESTE servidor"
try {
    $tcp = New-Object Net.Sockets.TcpClient($HostApi, 443)
    $ssl = New-Object Net.Security.SslStream($tcp.GetStream(), $false, { $true })
    $ssl.AuthenticateAsClient($HostApi)
    $c = New-Object Security.Cryptography.X509Certificates.X509Certificate2($ssl.RemoteCertificate)
    $cadena = New-Object Security.Cryptography.X509Certificates.X509Chain
    $ok = $cadena.Build($c)
    "Sujeto: $($c.Subject)"; "Emisor: $($c.Issuer)"; "Vigencia: $($c.NotBefore.ToString('yyyy-MM-dd')) -> $($c.NotAfter.ToString('yyyy-MM-dd'))"
    "Cadena valida en este servidor: $ok  $(($cadena.ChainStatus | ForEach-Object { $_.Status }) -join ',')"
    $raiz = $cadena.ChainElements[$cadena.ChainElements.Count - 1].Certificate
    "Raiz de la cadena: $($raiz.Subject)  | en LocalMachine\Root: $([bool](Get-ChildItem Cert:\LocalMachine\Root | Where-Object Thumbprint -eq $raiz.Thumbprint))"
    $tcp.Close()
} catch { "ERROR TLS: $($_.Exception.Message)" }

if ($Rol -eq 'web') {
    Seccion 'PHP (.70)'
    if (Test-Path $Php) {
        & $Php -v | Select-Object -First 1
        Write-Host 'Prueba TLS con las MISMAS opciones que api.php (GET solo lectura, sin firma: 401/404 es normal, lo que importa es errno=0):'
        # Se escribe un .php temporal (PowerShell 5.1 corrompe las comillas de php -r) y se borra al terminar.
        $tmp = Join-Path $env:TEMP 'verif_tls.php'
        $plantilla = @'
<?php
echo "curl: ", (extension_loaded("curl") ? "si" : "NO"), "  NATIVE_CA: ", (defined("CURLSSLOPT_NATIVE_CA") ? "si" : "NO"), "  curl.cainfo: ", (ini_get("curl.cainfo") ?: "(vacio)"), "  existe: ", (is_file((string)ini_get("curl.cainfo")) ? "si" : "no"), "\n";
$ch = curl_init("https://__HOST__/formularios/api/prevencion/extintores/publico/catalogos");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
if (defined("CURLSSLOPT_NATIVE_CA")) curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
curl_exec($ch);
echo "TLS como api.php: http=", curl_getinfo($ch, CURLINFO_RESPONSE_CODE), " errno=", curl_errno($ch), " ", curl_error($ch), "\n";
'@
        Set-Content -Path $tmp -Encoding Ascii -Value $plantilla.Replace('__HOST__', $HostApi)
        & $Php $tmp
        Remove-Item $tmp -Force -ErrorAction SilentlyContinue
    } else { "No se encontro $Php (ajustar -Php)" }
    Seccion 'Sitio web'
    foreach ($r in "$SitioWeb\modules\prevencion", "$SitioWeb\assets\js\prevencion", "$SitioWeb\config\secrets.php", "$SitioWeb\data") { "{0}: {1}" -f $r, (Test-Path $r) }
    "secrets.php define API_ADMIN_DELETE_KEY: $([bool](Select-String -Path "$SitioWeb\config\secrets.php" -Pattern 'API_ADMIN_DELETE_KEY' -Quiet))"
    "secrets.php YA define API_PREVENCION_SIGNING_KEY: $([bool](Select-String -Path "$SitioWeb\config\secrets.php" -Pattern 'API_PREVENCION_SIGNING_KEY' -Quiet))"
    $appcmd = "$env:windir\system32\inetsrv\appcmd.exe"
    if (Test-Path $appcmd) { & $appcmd list site workspace; & $appcmd list apppool workspace }
    "Lectura de secrets.php por el pool (ACL, solo nombres):"; (Get-Acl "$SitioWeb\config\secrets.php").Access | ForEach-Object { "  $($_.IdentityReference) $($_.FileSystemRights)" }
} else {
    Seccion '.NET / API (.161)'
    dotnet --list-runtimes 2>&1 | Select-String 'AspNetCore.App 8'
    foreach ($r in "$SitioApi\Formularios.Api.dll", "$SitioApi\appsettings.json", "$SitioApi\wwwroot\uploads") { "{0}: {1}" -f $r, (Test-Path $r) }
    $j = Get-Content "$SitioApi\appsettings.json" -Raw | ConvertFrom-Json
    "appsettings: PrevencionSecurity presente: $($null -ne $j.PrevencionSecurity)  | AdminSecurity.DeleteKey definida: $([bool]$j.AdminSecurity.DeleteKey)"
    Seccion 'Sitio y pool'
    Import-Module WebAdministration -ErrorAction SilentlyContinue
    Get-WebApplication -Site 'API' -Name 'formularios' | Select-Object Path, PhysicalPath, ApplicationPool | Format-List
    Get-ChildItem IIS:\AppPools | Select-Object Name, State | Format-Table -AutoSize
    Seccion 'Bindings HTTPS del sitio API (certificado asignado)'
    Get-WebBinding -Name 'API' -Protocol https | Select-Object bindingInformation, sslFlags, @{n = 'Huella'; e = { $_.certificateHash } } | Format-Table -AutoSize
}
Write-Host "`nFin. Nada fue modificado." -ForegroundColor Green
