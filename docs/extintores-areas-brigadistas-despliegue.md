# Extintores — procedimiento de despliegue y rollback

**Estado: preparado, NO ejecutado.** Sin credenciales en este documento. Probado solo en local (LocalDB ficticia).
Paquete: `tools/despliegue-extintores/armar-paquete.ps1` → `..\_deploy_extintores\` (`api/`, `sql/`, `web/`, `herramientas/`, `MANIFIESTO_SHA256.txt`).

## Qué cambia
- **Público** (`inspeccion/`): sin login; usa solo `api/prevencion/extintores/publico/*`; tope por IP (150 envíos/10 min) y bloqueo de reenvío del mismo extintor en 2 min.
- **Administración**: el navegador llama a `modules/prevencion/extintores/api.php` (sesión del portal; valida login, usuario activo, nivel `ver`/`gestionar`, lista blanca de rutas y origen). `api.php` firma (HMAC-SHA256) y la API vuelve a verificar firma y nivel. Ya no existe `X-Admin-Key` en Extintores.
- **TLS**: `api.php` verifica siempre certificado y nombre de host (`VERIFYPEER=true`, `VERIFYHOST=2`, almacén de Windows vía `CURLSSLOPT_NATIVE_CA`; PHP 8.3.36 en .70 lo soporta). Opcional: `API_PREVENCION_CAFILE` en `secrets.php` para confiar en una CA interna sin tocar `php.ini`. Los demás módulos (`ApiClient.php`) no cambian.
- Solo cambian archivos de Extintores + `Program.cs` (1 línea de configuración) y los 4 `.cs` de Extintores de la API. Otros controladores/módulos intactos. `admin-formularios.css` no se despliega (idéntico al vivo); `formularios.css` solo agrega `.pr-crit.is-na`.

## Fase 0 — Verificaciones previas (solo lectura)
Ejecutar como Administrador y enviar la salida completa:
- En .70: `powershell -File verificar-solo-lectura.ps1 -Rol web`
- En .161: `powershell -File verificar-solo-lectura.ps1 -Rol api`

Criterios para continuar:
1. Desfase de hora entre servidores **< 60 s** (máximo tolerado por la firma: 300 s). Medición HTTP del 29-10-2026: ≤ 1 s.
2. En .70, la prueba TLS con las opciones de `api.php` da **`errno=0`** (cualquier `http=` 401/404 es normal sin firma). Si da `errno=60`: la cadena de `api.faret.cl` no es de confianza en .70 → importar la CA emisora en `LocalMachine\Root` de .70 (o `API_PREVENCION_CAFILE`) **antes** del despliegue. Nunca desactivar la verificación.
3. .161 con `Microsoft.AspNetCore.App 8.x` (hoy 8.0.27) y `PrevencionSecurity` ausente (se agrega en Fase 3).
4. `secrets.php` define `API_ADMIN_DELETE_KEY` (no se toca) y aún no define `API_PREVENCION_SIGNING_KEY`.

**Fase 0 ejecutada el 29-10-2026 (solo lectura, ejecutada realmente en .70 y .161):**
- TLS desde .70: certificado de `api.faret.cl` emitido por Let's Encrypt (YR2 → ISRG Root X1, vigente hasta 2027-01-24), cadena válida y raíz en `LocalMachine\Root`. PHP 8.3.36 con curl, openssl, `NATIVE_CA` y `curl.cainfo` existente: la prueba con las opciones de `api.php` da `errno=0`. (El certificado «Kaspersky» visto desde el PC de desarrollo era inspección TLS local, no el de producción.)
- Hora: ambos servidores sincronizan con `ad1.ad.faret.cl` (mismo origen), UTC coincidente al segundo. `w32tm /stripchart` entre servidores da 0x800705B4 (NTP entre ellos filtrado): no afecta.
- IIS: .70 sitio `workspace` + pool `workspace` Started; .161 app `/formularios` → `FormulariosPool` Started (24 pools Started), binding HTTPS `api.faret.cl` con certificado asignado, .NET 8.0.27, `uploads` presente. `api.faret.cl` resuelve a .161 (hosts de .70 sin entradas).

## Fase 1 — Respaldos (con hash)
- .161: copiar `Formularios.Api.dll`, `.pdb` y `appsettings.json` a `C:\API WEB\api\formularios\_backup_deploy_extintores_<fecha>`.
- .70: copiar `modules\prevencion`, `assets\js\prevencion`, `assets\css\formularios\formularios.css` y `config\secrets.php` a `C:\Paginas Web\_backup_deploy_extintores_<fecha>` (fuera del sitio).
- `Get-FileHash` de cada copia contra su original.

## Fase 2 — SQL (lo ejecuta el usuario en SSMS, `formularios_db`)
Orden en SSMS: `sql/00_respaldo_formularios_db.sql` (respaldo COPY_ONLY + VERIFYONLY; ajustar la ruta) → `sql/01_verificacion_extintores.sql` (guardar salida «ANTES») → `2026-10-29_prevencion_areas_brigadistas.sql` → `01_verificacion_extintores.sql` otra vez («DESPUÉS»: las filas HISTORICO deben ser idénticas; 3 tablas nuevas, 17 áreas, 19 brigadistas).
`sql/2026-10-29_prevencion_areas_brigadistas.sql`: aditivo e idempotente (probado 2 veces: 17 áreas / 19 brigadistas), no asigna nada y es **compatible con la API actual**, por eso va antes. Sin transacciones largas ni bloqueos relevantes.

## Fase 3 — Clave HMAC (inerte hasta el corte)
La versión actual ignora esta configuración, así que no afecta nada todavía.
1. En .70: `aplicar-clave.ps1 -Destino web -Generar` (genera 64 caracteres, la agrega a `secrets.php` y la deja en el portapapeles sin mostrarla).
2. En .161: `aplicar-clave.ps1 -Destino api` y pegar (Ctrl+V + Enter). Inserta `PrevencionSecurity` en `appsettings.json` sin tocar el resto; respalda y valida JSON.
3. Limpiar portapapeles (`Set-Clipboard -Value ' '`). No quedan claves en scripts, logs ni repositorio. Si la clave ya existe, el script no hace nada.

## Fase 4 — Preparación (sin cortar nada)
- .161: copiar `api/*` a `C:\API WEB\api\formularios\_staging_extintores_<fecha>`; .70: copiar `web/*` a `C:\Paginas Web\_staging_extintores_<fecha>`. Verificar hashes contra `MANIFIESTO_SHA256.txt`.

## Fase 5 — Corte coordinado (ventana de ~1 min, fuera de horario de inspecciones)
**Hay una ventana inevitable**: API nueva y web nueva se necesitan mutuamente (la API nueva rechaza a la web vieja y viceversa). Se reduce a segundos preparando todo antes y ejecutando ambas copias seguidas. Solo Extintores se ve afectado.
1. .161: crear `app_offline.htm` → esperar ~3 s → copiar `Formularios.Api.dll/.pdb` desde staging → renombrar `app_offline.htm` (usar `Rename-Item`, no `Remove-Item`).
2. Inmediatamente, .70: copiar el staging `web/*` sobre `C:\Paginas Web\workspace\` (un solo `Copy-Item -Recurse -Force`). En Windows con CRLF no es necesario convertir (PHP no lo requiere).
3. Verificar hashes de lo copiado (ambos servidores).

## Fase 6 — Pruebas de humo (solo lectura; sin enviar inspecciones ni usar cuentas reales)
- `GET .../publico/catalogos` → 200 con `brigadistas`, `areas`, `plantas`, `criterios`.
- `GET .../areas` sin firma → **401**; con la `X-Admin-Key` antigua → **401**.
- `modules/prevencion/extintores/api.php?r=areas` sin sesión → **401**.
- Páginas públicas `inspeccion/` → 200 sin login; `configuracion/`, `admin/`, `inventario/` sin sesión → 302 a login.
- Otros módulos: `modules/operacion/` y `modules/formularios/` siguen respondiendo (302/200 como antes).

## Fase 7 — Validación de permisos (la hace Miguel/TI con sus propias cuentas)
Usuario `gestionar`: Brigadistas y áreas, Inventario y Panel cargan y guardan. Usuario `ver`: Panel y consolidado cargan; Brigadistas y áreas → 403. Excel del panel incluye columna Área.

## Fase 8 — Errores y rendimiento (primeros 30 min)
Revisar `error_log` de PHP (mensajes `[extintores/api.php] fallo TLS` = problema de certificado), Event Viewer / logs del pool `FormulariosPool`, y que `publico/catalogos` y `consolidado` respondan en < 2 s. Un 502 «rechazó la llamada» en el panel indica clave distinta o reloj desfasado.

## Fase 9 — Reversión inmediata (si falla algo crítico)
1. .161: `app_offline.htm` → restaurar `Formularios.Api.dll/.pdb` (y `appsettings.json` solo si hace falta) desde el respaldo → reactivar.
2. .70: restaurar `modules\prevencion`, `assets\js\prevencion`, `formularios.css` (y `secrets.php` si se desea) desde el respaldo.
3. El SQL no se revierte (aditivo; la versión anterior lo ignora). Tiempo estimado: < 3 min. Restaurar siempre ambos lados juntos.

## Configura Miguel (Prevención → Brigadistas y áreas / Inventario)
1. Revisar ortografía de los 19 brigadistas y 17 áreas (vienen de su Google Form).
2. Asignar cada extintor a un área (Inventario → columna Área).
3. Asignar áreas a cada brigadista (sin áreas = ve todos; con áreas = solo esas).
4. Quien administre necesita nivel `gestionar` en `prevencion_riesgos` (Admin → Usuarios).

## Riesgos conocidos
- Ventana de incompatibilidad de ~1 min en el corte (solo Extintores).
- Reloj desfasado > 5 min entre servidores → panel con 502.
- La firma no cubre el cuerpo y una petición firmada puede repetirse dentro de 300 s (se asume TLS entre servidores).
- Tope por IP en memoria (se reinicia al reciclar la app); varios trabajadores tras un NAT comparten IP (límite alto a propósito).
- El formulario público no autentica personas (decisión de negocio): cualquiera con la URL puede registrar una inspección a nombre de un brigadista de la lista.
- Si .70 no confía en la CA de `api.faret.cl`, el panel falla (502) hasta importar la CA; el público y el resto de los módulos no se afectan.

## Pruebas (solo local)
`tests/extintores/api_extintores_test.py` (API), `php_gate_setup.ps1` + `php_gate_seed.php` + `php_gate_test.py` (proxy PHP) y `php_tls_test.py` (TLS: certificado no confiable → 502, CA configurada → 200, nombre de host incorrecto → 502).
