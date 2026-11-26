# Estructural: foto en Perfil, dos responsables y reapertura a En Proceso

Fecha: 2026-11-26. Estado: **PUBLICADO en producción el 2026-11-26 con autorización del usuario. OPERATIVO CON OBSERVACIONES.**

**Validación visual en la plataforma:** confirmada por el usuario el 2026-11-26, después de publicar. No informó pruebas de creación, guardado o reapertura con datos reales.

## Registro de la publicación (2026-11-26, hora local)

| Paso | Resultado |
|---|---|
| Respaldo BD (ejecutado por el usuario en SAP_NEW 192.168.1.230, AD\Administrador) | 16:10, verificado, 42,1 MB |
| Migración (ejecutada por el usuario) | OK; 828 registros antes y después; huella -1555199965 sin cambios |
| API actual con base migrada | Sin cambios respecto del estado inicial |
| API `.161` (solo DLL y PDB) | Instalada 16:13:49. SHA256 DLL `9D6B2324B99654F085245C3CD27B4C08C456B67E76ADCEDA13D6DE6829F8E98B`, PDB `FC85E82DEB98C4048E7ED099A8F7D9CAC6833414C6FD8C367F7DC25E75A62FDE` |
| Portal `.70` (7 archivos, CRLF) | 16:15, copiados y verificados por hash; ningún archivo vecino modificado |

**Incidente durante la API:** el primer intento no pudo copiar la DLL. Se conservaron los originales y se reactivó la API (fuera de línea 16:12:27–16:12:38, ~11 s). Causa probable: archivo aún bloqueado y reintentos demasiado seguidos (el mensaje de error no se capturó). El segundo intento, con espera y reactivación garantizada, funcionó (16:13:28–16:13:49, ~21 s). Afecta a todos los formularios de Formularios.Api durante esos segundos. **Para la próxima vez:** esperar al menos 4 s, reintentar con pausas, capturar el error y reactivar en un `finally`.

**Respaldos disponibles** (conservar):
- BD: SAP_NEW `C:\Program Files\Microsoft SQL Server\MSSQL13.MSSQLSERVER\MSSQL\Backup\formularios_db_pre_estructural_20261126_161027.bak`, SHA256 `D5BC8BEF78DA53499A58E8DCDF22BE2D393BB73F73851529287DD8751A505875` (releído por red: coincide).
- API: `\\192.168.1.161\C$\API WEB\api\formularios\_backup_deploy_estructural_20261126_155440` (`SHA256SUMS.txt`). DLL original `478C3830DEA8439FA0F1475FEFC63350099D247FA206FED186B426851BE3FD68`, PDB `E5A37D0E4599FB1A967D07C478C972BCBBCDD102B4562B9CA2E4AF182BCFD949`.
- Portal: `\\192.168.1.70\C$\Paginas Web\_backup_deploy_estructural_20261126_155440` (`SHA256SUMS.txt`).
- En `.161` quedaron `app_offline.htm.retirado_20261126_155440` y `..._2` (inertes; se pueden borrar).

**Validación en producción (sin escrituras):**
- API: 29 GET (los 2 `401` de Prevención son esperados), 828 estructurales y 551 gráficas sin registros perdidos, modificados ni nuevos respecto del estado inicial; 40 detalles con adjuntos e historial sin cambios; PDF y descarga correctos; `POST` vacío a las rutas de creación → 400.
- Formulario público real: 15/15, con escrituras bloqueadas en el navegador.
- Panel: 18/19 usando el **código publicado en la copia local** con datos reales por GET (no se inició sesión en producción). El caso restante es el 405 preexistente de Gráfico (abajo).
- Ventana de novedades: vista en la copia local.

**Pendiente en producción (requiere escribir datos o una sesión real; no se hizo):**
1. Primera solicitud de Perfil real: guardado de la foto, descarga, historial y correo (confirma permisos de `wwwroot/uploads` por la ruta nueva).
2. Rechazo del **servidor** a un Perfil sin foto (probado solo en local con la misma DLL).
3. Creación de una solicitud normal real, guardado de responsables y reapertura real.

La revisión visual con sesión real ya fue confirmada por el usuario (ver arriba).

Scripts SQL usados: `Formularios.Api/sql/2026-11-26_publicacion_paso1_respaldo.sql` y `..._paso2_migracion.sql`. **No volver a ejecutar la migración.**

## Alcance

**Perfil**
- Pregunta nueva "¿Es una solicitud de Perfil?" (Sí/No, por defecto No). No se deduce desde textos.
- Con Sí se exige al menos una fotografía real (JPG, PNG, WEBP, GIF o BMP). Mensaje: `NO SE PUEDE GENERAR LA SOLICITUD POR QUE FALTA LA FOTO`.
- Se crea por `POST solicitudes-estructural/perfil` (multipart: `solicitud` JSON, `claveEnvio` GUID, `archivos`). La solicitud, el historial y los adjuntos se confirman en una sola transacción; si algo falla, se borran los archivos escritos.
- `claveEnvio` evita duplicados al reintentar: la API devuelve la misma solicitud (`yaExistia: true`).
- `POST solicitudes-estructural` (JSON) sigue igual, pero rechaza `esPerfil: true`.
- No aplica a históricos, cambios de estado, ediciones ni reaperturas. Solicitudes normales y Gráfico no cambian.

**Responsables y reapertura**
- `asignador_plano` nuevo; `operador_edicion` se muestra como **Desarrollador estructural**. Ninguno es obligatorio. Cambios registrados como `ASIGNACION`.
- API: `asignadorPlano` omitido o `null` conserva el valor; `""` lo quita.
- Reabrir (solo Estructural) deja `En edición` ("En Proceso"). Gráfico sigue volviendo a Recibido.
- "Mis solicitudes asignadas" sigue contando solo el desarrollador, y solo en estados pendientes.

## Archivos

API (`.161`), requiere publish:
- `Helpers/ValidadorFotografia.cs` (nuevo)
- `Controllers/SolicitudesEstructuralController.cs`
- `Services/DesarrolloEstructuralService.cs`
- `Repositories/DesarrolloEstructuralRepository.cs`
- `Documents/DesarrolloEstructuralPdfDocument.cs`
- `DTOs/CrearSolicitudDesarrolloEstructuralDto.cs`, `DTOs/SolicitudDesarrolloEstructuralDto.cs`, `DTOs/SolicitudDesarrolloEstructuralDetalleDto.cs`
- `DTOs/ActualizarEstadoEstructuralDto.cs` (nuevo)

SQL manual (`Formularios.Api/sql/`):
- `2026-11-26_estructural_asignador_plano.sql`: agrega `asignador_plano`, `es_perfil` y `clave_envio` (las tres `NULL`). Idempotente.
- `2026-11-26_estructural_precheck.sql`: comprobación de solo lectura.

Portal (`.70`), 7 archivos:
- `modules/formularios/desarrollo/solicitud-estructural/index.php`
- `modules/formularios/desarrollo/solicitud-estructural/admin/index.php`
- `modules/formularios/desarrollo/solicitud-estructural/admin/detalle.php`
- `assets/js/formularios/solicitud-estructural.js`
- `assets/js/formularios/admin-estructural.js`
- `assets/css/formularios/admin-formularios.css`
- `config/novedades_version.php` (copiar al final)

## Publicación (orden obligatorio: SQL → API → portal)

### 0. Respaldos
- Base `formularios_db`: confirmar un respaldo vigente.
- API: copiar `Formularios.Api.dll`, `.pdb` (y `.deps.json` / `.runtimeconfig.json` si se reemplazan) a una carpeta `_backup_deploy_estructural_<fecha_hora>`.
- Portal: copiar los 7 archivos a una carpeta de respaldo, conservando las rutas.

### 1. SQL
Ejecutar la migración en SSMS sobre `formularios_db`. La API anterior sigue funcionando con las columnas creadas.

### 2. Comprobación obligatoria del esquema
Ejecutar el precheck. **Solo continuar si devuelve `OK - SE PUEDE PUBLICAR LA API`.** Si muestra un error, no publicar. Revisar además que `base_de_datos` sea la que usa la API.
Sin las columnas, la API nueva responde 500 en el listado y no puede crear solicitudes de Estructural.

### 3. API
- `Formularios.Api` no usa git. Comparación del 2026-11-26 (solo lectura): la DLL publicada (2026-11-05) tenía los mismos literales y nombres que el build de las fuentes originales, sin diferencias de tamaño en dependencias. Si pasan días o hay otro desarrollo, comparar de nuevo.
- Publicar con `app_offline.htm` (reactivar con `Rename-Item`). No tocar `wwwroot/uploads`, `appsettings.json` ni `web.config`.

### 4. Verificación de la API (solo lectura)
- `GET solicitudes-estructural` → 200 y cada registro con `asignadorPlano`.
- `GET solicitudes-estructural/{id}/detalle` → incluye `esPerfil` (null en históricos).
- `GET solicitudes` (Gráfico) → 200.

### 5. Portal
Comparar otra vez con producción (ignorando CRLF/LF; el 2026-11-26 los 7 archivos eran iguales a `HEAD` `f811337`) y copiar. No requiere reinicio.

### 6. Verificación posterior
- Abrir el formulario: aparece la pregunta de Perfil; con Sí y sin foto muestra el mensaje y **no envía nada** (prueba sin efectos).
- Panel Estructural: listado de 14 columnas, detalle de un histórico sin "Solicitud de Perfil".
- Panel Gráfico carga normal.
- Primera solicitud de Perfil real: confirmar foto descargable, historial y correo. La prueba de creación envía correo a la lista real: hacerla solo con aviso previo.
- Revisar permisos de escritura si falla un Perfil con 500 (`wwwroot/uploads`).

## Reversión

Orden inverso: **portal y luego API**. No revertir la API dejando el portal nuevo (los Perfil fallarían con aviso, sin crearse).
- Portal: restaurar los 7 archivos.
- API: restaurar DLL/PDB respaldados.
- Columnas: dejarlas. La API anterior las ignora y así no se pierden datos. No ejecutar `DROP COLUMN`.
- Con la API anterior, reabrir vuelve a Recibido y no se exige foto.

Fuentes originales de la API: `Formularios.Api/deploy-estructural-responsables/original/`. Para revertir código, restaurarlas, quitar `Helpers/ValidadorFotografia.cs` y `DTOs/ActualizarEstadoEstructuralDto.cs`, y compilar.

## Pruebas (LocalDB aislada, SMTP falso, 2026-11-26)

- Compilación Release: 0 errores, 0 advertencias.
- API: 84/84 (precheck con y sin migración, migración dos veces, históricos, API anterior con base migrada, rechazos sin efectos, fallos de carga sin filas ni huérfanos, reintentos y 8 envíos simultáneos, formatos, correo, responsables, reapertura, Gráfico anterior vs nuevo).
- Navegador, formulario: 25/25.
- Navegador, panel: 35 de 36 en la primera corrida. El caso restante tenía mal la expectativa (el contador "Mis solicitudes" excluye Terminado); repetido: 4/4.
- Portal nuevo + API anterior: panel y solicitud normal funcionan; un Perfil avisa y no se crea.
- Móvil (390 px): formulario sin desplazamiento horizontal; etiquetas del listado correctas.

## Limitaciones

- La exigencia depende de que el cliente informe `esPerfil`. Un formulario antiguo abierto en el navegador, o un consumidor que no lo envíe, crea la solicitud sin foto (`es_perfil = NULL`).
- Si el usuario recarga la página después de un envío sin respuesta, se pierde la clave y reenviar puede duplicar. Igual si cambia de Sí a No en ese caso.
- Un corte del proceso o de energía a mitad de la carga, o un error justo en el commit, puede dejar archivos huérfanos (nunca una solicitud de Perfil confirmada sin sus adjuntos).
- La validación comprueba firma e imagen cargable; no juzga el contenido de la foto, y no garantiza detectar toda imagen parcialmente dañada. HEIC (iPhone) no se acepta.
- Un envío de Perfil admite hasta cerca de 100 MB en total (límite de IIS).
- La foto se puede borrar o perder después: no se vuelve a exigir.
- Solicitudes normales conservan el flujo anterior (crear y luego adjuntar), con sus mismas limitaciones.

## Pendientes independientes (no corregidos, preexistentes)

- El detalle de **Gráfico** pide `GET solicitudes/{id}/adjuntos`, ruta que la API no tiene (solo POST) → 405: ese panel no puede listar los adjuntos. No se modificó en esta entrega.

- Endpoints de estado, reapertura y adjuntos sin autenticación visible; `usuario` lo envía el navegador.
- "Guardar cambio" reemplaza la observación aunque el cuadro esté vacío.
- La exportación Excel muestra "En edición" en vez de "En Proceso".
- En el detalle, los campos de texto de responsables usan el estilo básico del navegador (ya ocurría con el campo original).
- Seguridad (TI): una credencial MySQL heredada de la configuración local de la API quedó visible en la salida de una sesión de asistente. TI revisará dependencias y eventual rotación. No se registra el valor.
