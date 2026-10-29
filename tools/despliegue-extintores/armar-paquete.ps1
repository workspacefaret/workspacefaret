# Arma el paquete de despliegue de Extintores en una carpeta LOCAL. No despliega nada, no toca servidores ni bases de datos,
# y NO incluye credenciales (ni config/secrets.php ni appsettings).
# Uso:  powershell -File tools/despliegue-extintores/armar-paquete.ps1 [-Salida <carpeta>]
param([string]$Salida = (Join-Path $PSScriptRoot '..\..\..\_deploy_extintores'))
$ErrorActionPreference = 'Stop'

$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$apiRepo = (Resolve-Path (Join-Path $repo '..\Formularios.Api')).Path

Write-Host 'Compilando Formularios.Api (Release)...'
& dotnet build $apiRepo -c Release --nologo -v q
if ($LASTEXITCODE -ne 0) { throw 'La compilacion fallo.' }
$bin = Join-Path $apiRepo 'bin\Release\net8.0'

if (Test-Path $Salida) { Remove-Item -Recurse -Force $Salida }
New-Item -ItemType Directory -Path $Salida | Out-Null
$salida = (Resolve-Path $Salida).Path

# API (.161): solo la DLL propia y su PDB (las dependencias no cambian).
New-Item -ItemType Directory -Path "$salida\api" | Out-Null
Copy-Item "$bin\Formularios.Api.dll", "$bin\Formularios.Api.pdb" "$salida\api"

# SQL (se ejecuta a mano en SSMS).
New-Item -ItemType Directory -Path "$salida\sql" | Out-Null
Copy-Item "$apiRepo\sql\2026-10-29_prevencion_areas_brigadistas.sql" "$salida\sql"
Copy-Item (Join-Path $PSScriptRoot 'sql\*.sql') "$salida\sql"   # 00 respaldo previo y 01 verificacion (solo lectura)

# Web (.70): rutas relativas a la raiz del sitio. Sin secrets.php.
# admin-formularios.css NO se incluye: es identico al de produccion (verificado 2026-10-29); formularios.css solo suma .pr-crit.is-na.
$web = @('assets\css\formularios\formularios.css')
$web += (Get-ChildItem (Join-Path $repo 'modules\prevencion') -Recurse -File | ForEach-Object { $_.FullName.Substring($repo.Length + 1) })
$web += (Get-ChildItem (Join-Path $repo 'assets\js\prevencion') -Recurse -File | ForEach-Object { $_.FullName.Substring($repo.Length + 1) })
foreach ($rel in $web) {
    $dest = Join-Path "$salida\web" $rel
    New-Item -ItemType Directory -Force -Path (Split-Path $dest) | Out-Null
    Copy-Item (Join-Path $repo $rel) $dest
}

# Utilidades que se ejecutan en los servidores (no son parte del sitio), configuracion de ejemplo y procedimiento.
$herr = Join-Path $repo 'tools\despliegue-extintores'
New-Item -ItemType Directory -Path "$salida\herramientas" | Out-Null
Copy-Item (Join-Path $herr 'verificar-solo-lectura.ps1'), (Join-Path $herr 'aplicar-clave.ps1') "$salida\herramientas"
Copy-Item (Join-Path $repo 'config\secrets.example.php') "$salida\herramientas"
Copy-Item (Join-Path $repo 'docs\extintores-areas-brigadistas-despliegue.md') "$salida\PROCEDIMIENTO.md"

# Manifiesto SHA256 (para comparar lo copiado en cada servidor).
Get-ChildItem $salida -Recurse -File | Where-Object { $_.Name -ne 'MANIFIESTO_SHA256.txt' } | ForEach-Object {
    '{0}  {1}' -f (Get-FileHash $_.FullName -Algorithm SHA256).Hash, $_.FullName.Substring($salida.Length + 1)
} | Set-Content -Encoding Ascii "$salida\MANIFIESTO_SHA256.txt"

Write-Host "Paquete en: $salida"
