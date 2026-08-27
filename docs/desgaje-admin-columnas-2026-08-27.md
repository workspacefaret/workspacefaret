# Panel Desgaje — columnas alineadas a Excel de referencia (2026-08-27)

## 1. Contexto

El área de RRHH usa un Excel de referencia ("Registro Producción Desgaje") con columnas específicas para revisar la producción por NP/operador. Se pidió alinear el listado del panel administrativo (`https://workspace.faret.cl/modules/rrhh/desgaje/admin/`) y su exportación a Excel con ese formato. La imagen de referencia usada como base quedó guardada en `docs/image (6).png`.

Trabajo hecho en modo seguro (plan → aprobación → un paso a la vez → prueba local → deploy verificado), sin tocar `Formularios.Api`, sin dependencias nuevas, sin tocar otros módulos.

## 2. Decisión tomada

Se optó por **espejar exactamente** las columnas de la imagen, en vez de agregarlas junto a las existentes. Esto significó **quitar** columnas que sí existían antes: `Código`, `Taller`, `Estado`, `Acciones`.

**Columnas finales** (tabla y export Excel, mismo orden):
`NP | Fecha | Operador | Cliente | Detalle trabajo | Cantidad de pliegos | Moldes | Cantidad | Valor`

Los datos de `Cantidad de pliegos` y `Moldes` ya existían en la respuesta de la API (`detalle.cantidadPliegos` / `detalle.numeroMoldes`) — se usaban en `admin/detalle.php` pero nunca se mostraban en el listado ni en su export. No hizo falta ningún cambio de backend.

## 3. Funcionalidad perdida y su mitigación

Quitar la columna `Acciones` significaba perder, desde el listado, el acceso directo a: ver detalle, abrir PDF, y **eliminar registro** (borrado definitivo). Se resolvió así, con aprobación explícita del usuario en ambos puntos:

- **Ver detalle / PDF / Validar / Anular**: se mitigó haciendo que **toda la fila sea clickeable** (`class="admin-row-clickable"`, click navega a `detalle.php?id=...`) — esas acciones ya existen dentro de esa página.
- **Eliminar registro (borrado definitivo)**: no tiene equivalente en `detalle.php`. El usuario decidió **quitarla del todo** (no se agregó reemplazo). El código de esa función (`eliminarRegistro()`, `X-Admin-Key`/`adminDeleteKey`) se eliminó como código muerto — si en el futuro se necesita recuperar el borrado desde el panel, se debe re-implementar desde cero (no quedó comentado ni oculto).

Filtros (`Estado`, `Taller`, `Cliente`, `NP`, fechas) siguen funcionando igual — viven en la barra de filtros, no en la tabla, así que no se perdió capacidad de filtrar, solo la columna visual.

## 4. Archivos modificados

| Archivo | Cambio |
|---|---|
| `modules/rrhh/desgaje/admin/index.php` | `<thead>` de la tabla a las 9 columnas del nuevo orden; `colspan` de las filas de estado (cargando/error) de 11 a 9 |
| `assets/js/rrhh/desgaje-admin.js` | `renderTabla()` reordenada + fila clickeable; `exportarExcel()` con las mismas 9 columnas; se eliminó `eliminarRegistro()`, la variable `adminDeleteKey` y `badgeEstado()` (código muerto tras quitar Estado/Acciones) |
| `assets/css/formularios/admin-formularios.css` | Etiquetas responsive (`::before`) de `.admin-table-desgaje` actualizadas al nuevo orden de 9 columnas; nueva regla `.admin-row-clickable` (cursor + hover), con su override en la sección "MODO CLARO" al final del archivo (convención additive-only del proyecto) |

Sin cambios en `modules/rrhh/desgaje/admin/detalle.php`, `Formularios.Api`, ni base de datos.

## 5. Prueba realizada

Antes de desplegar, se corrió el sitio con `php -S localhost:8080` (ver [[technique-local-dev-environment]]) contra la API real (solo lecturas — no se tocó Validar/Anular para no mutar datos reales), sesión con usuario local `admin_ti`:

- Headers de la tabla verificados por JS (`document.querySelectorAll('.admin-table-desgaje thead th')`) → coinciden exactamente con el orden pedido.
- Fila real inspeccionada (NP 22707, Pliego 1 · Industrial): Pliegos=600, Moldes=9, Cantidad=5400, Valor=$4.320,00 — todos los campos poblados correctamente.
- Click en fila → navegó a `detalle.php?id=144`, cargó el registro `RD-000136` correctamente.
- Botón "Exportar Excel" → `.xls` descargado con las mismas 9 columnas/orden/datos.
- Consola del navegador sin errores JS.

No se probó la vista mobile (`::before` labels) ni un registro en modo manual (sin `tipoDesgajeId`) por no haber uno disponible en los datos reales al momento de la prueba — el fallback (`!= null ? valor : '-'`) es de bajo riesgo.

## 6. Deploy

Desplegado a producción (`192.168.1.70`, sitio IIS "workspace") el 2026-08-27 siguiendo el ciclo completo de deploy seguro (ver [[technique-deploy-servers-161-70]]):

1. Hash-diff local vs. vivo → los 3 archivos estaban pendientes.
2. Backup de los 3 originales de producción con verificación de hash → `\\192.168.1.70\C$\Paginas Web\_backup_deploy_desgaje-columnas_2026-08-27_090727\` (se deja en el servidor, no se borra).
3. Staging desde el repo local al servidor, hash-verificado → `_staging_deploy_desgaje-columnas_2026-08-27_090727\` (también se deja).
4. Preflight contra `https://workspace.faret.cl` real.
5. Copia staging → rutas en vivo.
6. Postflight: hash de los 3 archivos en vivo coincide 100% con local; mismos códigos HTTP que el preflight; contenido nuevo (`cantidadPliegos`) confirmado servido en producción.

No fue necesario recargar el pool de IIS (PHP se interpreta por request).

**Validado en producción por el usuario el 2026-08-27.**

## 7. Si hay que revertir

Restaurar los 3 archivos desde el backup listado en el punto 6.2, copiándolos de vuelta a sus rutas originales bajo `C:\Paginas Web\workspace\`. No hay cambios de base de datos ni de `Formularios.Api` que revertir.
