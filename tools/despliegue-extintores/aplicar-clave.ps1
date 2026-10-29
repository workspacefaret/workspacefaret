# Agrega la clave de firma HMAC de Extintores SIN mostrarla ni sobrescribir el resto de la configuracion.
# Ejecutar como Administrador, una vez en cada servidor, con la MISMA clave:
#   .70  : powershell -File aplicar-clave.ps1 -Destino web -Generar     (genera la clave, la aplica y la deja en el portapapeles)
#   .161 : powershell -File aplicar-clave.ps1 -Destino api              (pide la clave pegada: Ctrl+V + Enter; no se ve en pantalla)
# Hace respaldo del archivo con sello de hora, inserta SOLO la entrada nueva y valida el resultado. Si ya existe, no hace nada.
param(
    [Parameter(Mandatory)][ValidateSet('web', 'api')][string]$Destino,
    [switch]$Generar,
    [string]$ArchivoWeb = 'C:\Paginas Web\workspace\config\secrets.php',
    [string]$ArchivoApi = 'C:\API WEB\api\formularios\appsettings.json',
    [string]$Php = 'C:\PHP83\php.exe'
)
$ErrorActionPreference = 'Stop'
$archivo = if ($Destino -eq 'web') { $ArchivoWeb } else { $ArchivoApi }
if (-not (Test-Path $archivo)) { throw "No existe $archivo" }
$texto = [IO.File]::ReadAllText($archivo)
$patron = if ($Destino -eq 'web') { 'API_PREVENCION_SIGNING_KEY' } else { '"PrevencionSecurity"' }
if ($texto -match $patron) { Write-Host 'La clave ya esta configurada en este archivo. No se hizo nada.'; return }

if ($Generar) {
    $bytes = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $clave = ([BitConverter]::ToString($bytes) -replace '-', '').ToLower()
    Set-Clipboard -Value $clave
    Write-Host 'Clave generada (64 caracteres) y copiada al portapapeles. No se muestra.'
} else {
    $seguro = Read-Host 'Pega la clave (no se mostrara)' -AsSecureString
    $clave = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguro))
}
if ($clave.Length -lt 32 -or $clave -notmatch '^[A-Za-z0-9]+$') { throw 'La clave debe ser alfanumerica de al menos 32 caracteres.' }

$respaldo = "$archivo.bak_extintores_$(Get-Date -Format yyyyMMdd_HHmmss)"
Copy-Item $archivo $respaldo
if ((Get-FileHash $archivo).Hash -ne (Get-FileHash $respaldo).Hash) { throw 'El respaldo no coincide; abortado.' }

if ($Destino -eq 'web') {
    $nuevo = $texto.TrimEnd() + "`r`n`r`n// Extintores: clave de firma HMAC con Formularios.Api (igual a PrevencionSecurity:SigningKey).`r`ndefine('API_PREVENCION_SIGNING_KEY', '$clave');`r`n"
    [IO.File]::WriteAllText($archivo, $nuevo, (New-Object Text.UTF8Encoding($false)))
    & $Php -l $archivo | Out-Null
    if ($LASTEXITCODE -ne 0) { Copy-Item $respaldo $archivo -Force; throw 'secrets.php quedo invalido; se restauro el respaldo.' }
} else {
    $i = $texto.IndexOf('{')
    $nuevo = $texto.Substring(0, $i + 1) + "`r`n  `"PrevencionSecurity`": { `"SigningKey`": `"$clave`" }," + $texto.Substring($i + 1)
    try { $null = $nuevo | ConvertFrom-Json } catch { throw 'El JSON resultante es invalido; no se escribio nada.' }
    [IO.File]::WriteAllText($archivo, $nuevo, (New-Object Text.UTF8Encoding($false)))
}
Write-Host "Listo. Respaldo: $respaldo"
