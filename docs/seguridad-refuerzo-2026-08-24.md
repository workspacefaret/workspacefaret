# Refuerzo de seguridad — 2026-08-24

## 1. Contexto

Este documento registra la auditoría de seguridad y el refuerzo aplicado a `workspace.faret.cl` el 2026-08-24, para que quede como referencia de qué se hizo, qué se descartó, qué quedó pendiente, y cómo revertir cada cambio si hiciera falta. Todo el trabajo se hizo en modo seguro (plan → aprobación → un paso a la vez → prueba → despliegue verificado), sin dependencias nuevas y sin tocar `Formularios.Api` ni ningún otro repositorio/servidor.

Alcance: solo este repositorio (`workspace.faret.cl`, servidor `192.168.1.70`, sitio IIS "workspace"). No se modificó ninguna API ni base de datos externa.

## 2. Auditoría inicial — hallazgos

Auditoría de solo lectura del código fuente (autenticación, control de acceso, manejo de datos, configuración de servidor, superficie pública):

| Severidad | Hallazgo | Estado |
|---|---|---|
| Alto | `exports/guardias/recorridos-excel.php` sin autenticación — exponía datos de guardias (usuario, planta, horarios) a cualquiera | ✅ Corregido |
| Alto | `services/ApiClient.php` con `CURLOPT_SSL_VERIFYPEER`/`VERIFYHOST` en `false` — todas las llamadas salientes vulnerables a MITM | 🟡 Pendiente (Fase 2) |
| Medio | Sin `session_regenerate_id()` tras login — riesgo de session fixation | ✅ Corregido |
| Medio | Sin headers de seguridad HTTP (`X-Frame-Options`, `X-Content-Type-Options`, etc.) | ✅ Corregido (básicos) |
| Medio | HTML crudo sin sanitizar en Documentación Técnica — XSS almacenado si se compromete una cuenta `admin_ti` | ✅ Corregido |
| Medio | Carpetas `_backup_*/` con `.php` ejecutables sin guardia de autenticación, alcanzables por URL | ✅ Corregido |
| Medio | `auth/setup.php` sigue accesible (se autodesactiva si ya existe un usuario) | 🟡 Pendiente (decisión de producto) |
| Bajo | `includes/sidebar.php.bak` servido como texto plano | ✅ Corregido (mismo fix que las carpetas backup) |
| Bajo | Rate limiting de login por cuenta, no por IP | 🟡 Pendiente |
| Bajo | Cookie `secure` depende de `$_SERVER['HTTPS']` — no verificado detrás de proxy/terminación TLS | 🟡 Pendiente (verificación) |
| Bajo | Formularios públicos sin CAPTCHA/rate-limit en esta capa | 🟡 Pendiente (mayormente responsabilidad de `Formularios.Api`) |

Lo que ya estaba bien (confirmado por auditoría, no se tocó): SQL 100% parametrizado (cero inyección SQL), bcrypt para contraseñas, bloqueo temporal tras 5 intentos fallidos, `htmlspecialchars()` consistente en el resto del sitio, `data/` bloqueado y gitignorado, control de acceso por módulo sin escalación de privilegios, cookies `HttpOnly`+`SameSite=Lax`, cero `include`/`require` con input de usuario.

## 3. Fase 1 — Refuerzo base (implementado y desplegado)

### 3.1 Exportación de Guardias protegida

**Archivo:** `exports/guardias/recorridos-excel.php`

Se agregó al inicio:
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
requireModuleAccess('rrhh');
```

Mismo patrón ya usado en 25+ páginas del sitio. Sin impacto para usuarios con acceso a `rrhh` (siempre entraban logueados desde el botón "Exportar Excel"); bloquea solo el acceso directo por URL sin sesión.

### 3.2 Carpetas y archivos huérfanos bloqueados

**Archivo:** `web.config`

```xml
<hiddenSegments>
    <add segment="data" />
    <add segment="_backup_logistica" />
    <add segment="_backup_orden_rrhh" />
    <add segment="sidebar.php.bak" />
</hiddenSegments>
```

`_backup_logistica/`, `_backup_orden_rrhh/` (código previo al sistema de login, de jun-2026) e `includes/sidebar.php.bak` ya no responden a peticiones HTTP (404). No se eliminó ningún archivo — siguen en el repo/servidor, solo dejaron de ser accesibles por web.

### 3.3 Regeneración de ID de sesión tras login

**Archivo:** `auth/login.php`

```php
if ($resultado['ok']) {
    session_regenerate_id(true);
    $usuario = currentUser();
    // ...
```

Cierra un riesgo de session fixation: antes, el ID de sesión no cambiaba entre el estado anónimo y el autenticado. Mismo mecanismo que ya usaba `auth/cambiar-password.php`.

### 3.4 Headers de seguridad básicos (sin CSP)

**Archivo:** `web.config`

```xml
<httpProtocol>
    <customHeaders>
        <add name="X-Content-Type-Options" value="nosniff" />
        <add name="X-Frame-Options" value="SAMEORIGIN" />
        <add name="Referrer-Policy" value="strict-origin-when-cross-origin" />
    </customHeaders>
</httpProtocol>
```

Deliberadamente **sin** CSP (requiere inventario completo de recursos del sitio, queda en Fase 2) ni HSTS (no solicitado, añade su propio riesgo). Verificado: cero `<iframe>` y cero contenido `http://` mixto en todo el repo antes de aplicar.

## 4. Sanitización de Documentación Técnica (implementado y desplegado)

Motivación: `documentos_tecnicos.contenido` se renderiza como HTML crudo, sin escapar, por diseño (así funciona el "formato tipo" del módulo). Sin sanitización, una cuenta `admin_ti` comprometida (o un pegado descuidado de contenido no confiable) es XSS almacenado directo.

### 4.1 Sanitizador por allowlist (sin dependencias nuevas)

**Archivo:** `includes/auth.php` — funciones `sanitizarHtmlDocumentacion()` y `limpiarNodoDocumentacion()`, usando `DOMDocument` nativo de PHP.

El allowlist se calibró contra el vocabulario real ya usado en los documentos publicados (no es una lista genérica):

- **Etiquetas permitidas:** `section h2 h3 h4 p strong em b i ul ol li br hr code pre div span table thead tbody tr th td a`
- **Clases permitidas:** `callout warn callout-label`, `table-wrap`, `pill status-pill pill-ok pill-warn pill-info status-ok status-warn status-info`, `grid-2 card`, `kpi-row kpi k v`, `stack-list stack-item`, `flow node arrow`, `example-preview example-label`, `lead muted plain`
- **Atributos permitidos:** `class` (filtrado contra el allowlist), `href` en `<a>` (solo `http:`/`https:`/`mailto:`), `target="_blank"` en `<a>` (fuerza `rel="noopener noreferrer"` automático), `colspan`/`rowspan` en `td`/`th`
- **Todo lo demás se elimina:** `<script>`, `<iframe>`, `<object>`, `<form>`, `<img>` (no usado hoy), cualquier atributo `on*`, `style=`, `javascript:`/`data:` en URLs.

Se aplica en `modules/admin/documentacion/index.php`, al crear y al editar, justo antes del `INSERT`/`UPDATE` — la vista no cambia en absoluto.

### 4.2 CSP con nonce acotada a la página de documento

**Archivo:** `modules/documentacion/detalle.php`

Esta página ya renderiza HTML standalone (no pasa por `layouts/app.php`), así que puede llevar su propia política sin tocar `web.config` ni afectar el resto del sitio:

```php
$nonce = base64_encode(random_bytes(16));
```
```html
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'nonce-<?= $nonce ?>'; style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'none';">
```

El nonce se genera por request y se aplica también al único `<script>` inline de la página (detección de tema). Es la red de seguridad si algún día el sanitizador tuviera un bypass: aunque un `<script>` lograra colarse en el HTML guardado, el navegador no lo ejecuta sin el nonce correcto.

**Gap conocido:** los documentos publicados antes de este cambio no se limpian retroactivamente. Se sanitizan solos la próxima vez que se editen y guarden (no requiere migración aparte).

## 5. Despliegues realizados

Ambos siguieron el mismo protocolo: backup fechado fuera del document root con hash verificado → staging remoto fuera del sitio con hash verificado → preflight de lectura → copia solo de los archivos aprobados → verificación de hash post-copia → pruebas de lectura post-deploy. Ver la técnica general en la memoria `technique-deploy-servers-161-70`.

| Despliegue | Fecha/hora | Archivos | Backup | Staging |
|---|---|---|---|---|
| Fase 1 (refuerzo base) | 2026-08-24 15:53 | `auth/login.php`, `exports/guardias/recorridos-excel.php`, `web.config` | `_backup_deploy_security_hardening_20260824_155344` | `_staging_deploy_security_hardening_20260824_155344` |
| Sanitización Documentación Técnica | 2026-08-24 16:13 | `includes/auth.php`, `modules/admin/documentacion/index.php`, `modules/documentacion/detalle.php` | `_backup_deploy_doc_sanitizacion_20260824_161313` | `_staging_deploy_doc_sanitizacion_20260824_161313` |

Ambos backups/staging siguen en `C:\Paginas Web\` del servidor `192.168.1.70` (fuera del sitio, no interfieren) — pendiente decidir si se conservan o se eliminan.

### Rollback (si hiciera falta)

Restaurar el archivo correspondiente desde su carpeta de backup hacia `C:\Paginas Web\workspace\<ruta relativa>`, y verificar el hash contra el valor "produccion actual" documentado en el informe de despliegue de cada fase (ver historial de la conversación o pedir el hash de nuevo comparando contra el backup).

## 6. Backlog de seguridad — pendiente, no implementado

Registrado por decisión explícita de alcance, no por omisión:

1. **Verificación SSL en `ApiClient.php`** (`CURLOPT_SSL_VERIFYPEER`/`VERIFYHOST`) — requiere primero confirmar desde el servidor real que el certificado de `api.faret.cl` tiene una cadena de confianza válida, antes de activarla. Si se activa sin verificar y el certificado no es válido, se rompen las 3 integraciones (Guardias, Mejora Continua, y lo que dependa de ellas).
2. **CSP a nivel de todo el sitio, en modo Report-Only primero** — requiere inventariar todos los recursos externos, scripts inline y estilos inline del sitio completo antes de proponer una política.
3. **Rate limiting combinado por cuenta + IP** en el login (hoy es solo por cuenta).
4. **Verificación de la cookie `Secure` detrás de proxy/terminación TLS** — confirmar en el servidor real que `$_SERVER['HTTPS']` se setea correctamente.
5. **Protección antiabuso en formularios públicos** (`solicitud-grafica`, `solicitud-estructural`, `desgaje/registro`) — mayormente responsabilidad de `Formularios.Api`, fuera de este repo.
6. **Tratamiento definitivo de `auth/setup.php`** — hoy de bajo riesgo (se autodesactiva si ya existe un usuario), pendiente decidir si se elimina del despliegue.
7. **Logging/monitoreo de eventos sospechosos** (intentos de login fallidos, accesos denegados) — no existe hoy; es la pieza que falta para *detectar* ataques, no solo prevenirlos.
8. **Limpieza retroactiva opcional** de los documentos de Documentación Técnica publicados antes del sanitizador (se resuelve re-guardándolos desde el admin, sin código nuevo).

## 7. Verificación funcional

Confirmado por el propietario del sistema en producción el 2026-08-24: navegación general, login, exportación de Guardias (autorizada y no autorizada), y visualización de Documentación Técnica con el nuevo CSP — todo funcionando sin cambios visibles para el usuario final.
