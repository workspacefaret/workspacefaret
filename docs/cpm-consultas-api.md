# Consultas a la base de datos CPM (Correlativos Perfiles y Moldes) vía API

## 1. Contexto

Este documento describe cómo consultar, a través de `Formularios.Api`, los datos migrados desde el Excel `CORRELATIVOS PERFILES _ MOLDES (PLA-DES-CPM-S).xlsx` (ver `docs/`) hacia las nuevas tablas `cpm_perfiles`, `cpm_moldes` y `cpm_historial` en la base de datos MySQL de `Formularios.Api`.

**No existe acceso directo a la base de datos desde `workspacefaret`.** Toda consulta debe pasar por el API REST expuesto en `https://api.faret.cl/formularios/api/cpm/*`. Este es el mismo patrón "browser-direct" ya usado por Desgaje y Desarrollo (ver `CLAUDE.md` del repo): el frontend PHP no hace `SELECT` contra la BD, siempre llama al endpoint HTTP correspondiente.

Módulo relacionado en el portal: `modules/planificacion/index.php` (requiere login + módulo `planificacion`). Este documento cubre el **API subyacente**, útil tanto para depurar ese módulo como para cualquier consumo externo (scripts, reportes, otra integración).

## 2. Base URL

```
https://api.faret.cl/formularios/api/cpm/
```

No requiere autenticación a nivel de API (mismo esquema que el resto de `Formularios.Api`: sin JWT/API key). El control de acceso vive en `workspacefaret` (login + módulo `planificacion`), no en el API. Cualquier cliente con la URL puede consultar — tenerlo presente si se expone esta URL fuera del portal.

## 3. Endpoints de solo lectura (consultas)

### 3.1 Catálogos (valores distintos para filtros)

```
GET /cpm/catalogos
```

Devuelve, para poblar selects/autocompletados, los valores únicos actualmente en uso (no un catálogo maestro fijo — se recalculan en cada llamada con `SELECT DISTINCT`):

```json
{
  "perfilesRubros": ["..."],
  "perfilesOperadores": ["..."],
  "perfilesStatus": ["..."],
  "perfilesEstados": ["..."],
  "moldesRubros": ["..."],
  "moldesOperadores": ["..."],
  "moldesListoEstados": ["..."]
}
```

### 3.2 Perfiles — listado filtrado y paginado

```
GET /cpm/perfiles?cliente=&rubro=&operador=&estado=&status=&fechaDesde=&fechaHasta=&buscar=&pagina=1&porPagina=50
```

Todos los parámetros son opcionales salvo `pagina`/`porPagina` (traen default `1`/`50` si se omiten).

| Parámetro | Tipo | Comportamiento |
|---|---|---|
| `cliente` | string | `LIKE '%valor%'` (parcial) |
| `rubro` | string | igualdad exacta |
| `operador` | string | igualdad exacta |
| `estado` | string | igualdad exacta |
| `status` | string | igualdad exacta |
| `fechaDesde` / `fechaHasta` | `yyyy-MM-dd` | rango cerrado sobre columna `fecha` |
| `buscar` | string | `LIKE '%valor%'` contra `numero_perfil`, `cliente`, `descripcion`, `numero_desarrollo`, `rubro`, `operador`, `perfil_nuevo` (búsqueda libre multi-columna) |
| `pagina` | int | 1-indexed |
| `porPagina` | int | tamaño de página |

Orden fijo: `numero_perfil DESC, id DESC`.

Respuesta:
```json
{
  "items": [ { "id": 1, "numeroPerfil": "P000001", "cliente": "...", "medidas": "...", "descripcion": "...",
               "numeroDesarrollo": "...", "fecha": "2024-01-10T00:00:00", "fechaRaw": "...", "rubro": "...",
               "operador": "...", "numeroCaja": "...", "unidadesPorCaja": "...", "status": "...", "estado": "...",
               "perfilNuevo": "...", "sourceSheet": "PERFILES", "sourceRow": 5, "importBatch": "...",
               "createdAt": "...", "createdBy": "...", "updatedAt": null, "updatedBy": null } ],
  "total": 8068,
  "pagina": 1,
  "porPagina": 50
}
```

**Ejemplo:**
```
GET /cpm/perfiles?rubro=Cosmetica&buscar=P0044&pagina=1&porPagina=20
```

### 3.3 Perfil por id

```
GET /cpm/perfiles/{id}
```
`404` si no existe. Devuelve el mismo shape de item que el listado.

### 3.4 Historial de un perfil

```
GET /cpm/perfiles/{id}/historial
```
Lista de cambios (`CREAR`/`EDITAR`), más reciente primero. `valoresAnteriores`/`valoresNuevos` vienen como **string JSON** (no objeto) — hay que hacer `JSON.parse()` en el cliente para inspeccionarlos. En `CREAR`, `valoresAnteriores` es `null`.

### 3.5 Moldes — listado filtrado y paginado

```
GET /cpm/moldes?tipoMolde=REPETITIVO&incluirObsoletos=false&cliente=&rubro=&operador=&listoEstado=&fechaDesde=&fechaHasta=&buscar=&pagina=1&porPagina=50
```

`tipoMolde` es **obligatorio**: `REPETITIVO` o `NO_REPETITIVO` (cualquier otro valor devuelve `400`). No existe un tercer valor consultable directamente para `HISTORICO` — esos 171 registros migrados solo aparecen si se agrega `incluirObsoletos=true` a una consulta de `REPETITIVO` o `NO_REPETITIVO` (el filtro real pasa a ser `tipo_molde IN (tipoMolde, 'HISTORICO')`).

| Parámetro | Tipo | Comportamiento |
|---|---|---|
| `tipoMolde` | string, obligatorio | `REPETITIVO` \| `NO_REPETITIVO` |
| `incluirObsoletos` | bool | agrega `HISTORICO` al filtro de tipo (ver arriba) |
| `cliente` | string | `LIKE '%valor%'` |
| `rubro` / `operador` / `listoEstado` | string | igualdad exacta |
| `fechaDesde` / `fechaHasta` | `yyyy-MM-dd` | rango sobre `fecha_ingreso` |
| `buscar` | string | `LIKE '%valor%'` contra `codigo`, `cliente`, `producto`, `perfil`, `np_primera_entrada`, `operador`, `comentarios` |
| `pagina` / `porPagina` | int | paginación |

Orden fijo: `id DESC` (a diferencia de perfiles, no ordena por código).

Respuesta: mismo shape `{ items, total, pagina, porPagina }`, cada item con `esObsoleto` (bool) — **display-only**, no es el filtro real (ver tabla arriba).

**Ejemplo — traer históricos junto con repetitivos:**
```
GET /cpm/moldes?tipoMolde=REPETITIVO&incluirObsoletos=true&pagina=1&porPagina=100
```

### 3.6 Molde por id

```
GET /cpm/moldes/{id}
```
`404` si no existe.

### 3.7 Historial de un molde

```
GET /cpm/moldes/{id}/historial
```
Mismo formato que 3.4.

## 4. Endpoints de escritura (referencia rápida, no son "consultas")

Incluidos solo para completar el panorama del vertical — el resto de este documento se enfoca en lectura.

| Método | Ruta | Body mínimo | Nota |
|---|---|---|---|
| `POST` | `/cpm/perfiles` | `CrearCpmPerfilDto` + `usuario` (obligatorio) | Asigna correlativo `P######` automáticamente |
| `PUT` | `/cpm/perfiles/{id}` | igual + `usuario` | No cambia `numeroPerfil` |
| `POST` | `/cpm/moldes` | `CrearCpmMoldeDto` (`tipoMolde` obligatorio) + `usuario` | Asigna `M######` o `NR######` según `tipoMolde`; no permite crear `HISTORICO` |
| `PUT` | `/cpm/moldes/{id}` | igual + `usuario` | No cambia `codigo` ni `tipoMolde` |

Todo `POST`/`PUT` exitoso deja una fila en `cpm_historial` en la misma transacción.

## 5. Particularidades del dato a tener en cuenta al consultar

- **`numero_perfil` y `codigo` NO son `UNIQUE`.** El Excel histórico tiene duplicados reales (p. ej. `P000002`, `P000010`, `P000118`, `P004449`, cada uno dos veces) que se preservaron intencionalmente en la migración. Una consulta por código puede devolver más de una fila — no asumir cardinalidad 1.
- **`fecha`/`fecha_ingreso`/`fecha_entrega` pueden ser `NULL`** aunque el Excel tuviera algo escrito en esa celda: si el valor original no era una fecha parseable, quedó `NULL` en la columna tipada y el texto original se preservó en la columna `*_raw` (`fecha_raw`, `fecha_ingreso_raw`, `fecha_entrega_raw`). Si se necesita el dato "tal cual estaba en el Excel", usar la columna `_raw`, no la tipada.
- **`es_obsoleto` es solo informativo**, no es el mecanismo de filtrado real — el filtro real es `tipo_molde` (ver 3.5). Un molde con `tipoMolde=HISTORICO` y `esObsoleto=false` puede existir (no se garantiza consistencia 1:1 entre ambos campos fuera de los 171 migrados).
- **Anomalías preservadas tal cual**, no corregidas: un código `NULO`, códigos versionados `-V2`/`-V3`, un caso `NR000165 (MICA)` (anotación dentro del propio código), y filas con `operador` conteniendo una fecha en vez de un nombre (rango `M000887`–`M000895`). Cualquier consulta agregada (conteos por operador, por ejemplo) puede verse afectada por estos casos.
- **`sourceSheet`/`sourceRow`/`importBatch`** identifican la fila y hoja exacta del Excel original de la que provino cada registro migrado — útiles para auditoría o para volver a cruzar contra el archivo fuente en `docs/`. En registros creados desde el portal (no migrados), estos tres campos quedan `NULL`.
- **Paginación:** `total` en la respuesta es el conteo real tras aplicar filtros (no el total de la tabla). Con filtros vacíos, `GET /cpm/perfiles` devuelve `total` sobre los ~8.068 perfiles; `GET /cpm/moldes?tipoMolde=REPETITIVO` sobre los ~1.002 repetitivos (738 no repetitivos, 171 históricos si se incluyen).

## 6. Ejemplos

**cURL:**
```bash
curl "https://api.faret.cl/formularios/api/cpm/perfiles?cliente=Ejemplo&pagina=1&porPagina=10"
curl "https://api.faret.cl/formularios/api/cpm/moldes?tipoMolde=NO_REPETITIVO&incluirObsoletos=true"
curl "https://api.faret.cl/formularios/api/cpm/perfiles/123/historial"
```

**JavaScript (mismo patrón usado en `assets/js/planificacion/cpm-perfiles.js`):**
```javascript
const resp = await fetch(`${window.API_FORMULARIOS}cpm/perfiles?rubro=${encodeURIComponent(rubro)}&pagina=${pagina}&porPagina=50`);
const data = await resp.json();
// data.items, data.total, data.pagina, data.porPagina
```

## 7. Referencias

- Esquema de tablas: `Formularios.Api/sql/2026-08-13_perfiles_moldes_estructura_inicial.sql`
- Controlador: `Formularios.Api/Controllers/CpmController.cs`
- Lógica de consulta/filtros: `Formularios.Api/Repositories/CpmRepository.cs`
- Frontend consumidor: `workspacefaret/modules/planificacion/index.php`, `assets/js/planificacion/cpm-perfiles.js`, `cpm-moldes.js`
- Contexto de la migración y bugs encontrados: memoria `project-cpm-perfiles-moldes` (sesión 2026-08-14)

## ACTUALMENTE SE ESTA TRABAJANDO EN SISTEMA DE PROTECCION DE APIS EXPUESTAS POR HTTP PARA REFORZAR SEGURIDAD DE LA EMPRESA.