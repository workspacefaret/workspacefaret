# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Reglas de trabajo (modo seguro)

El propietario de este repo trabaja en **modo seguro**. Respeta estas reglas en todo momento:

1. **No modifiques archivos sin aprobación explícita.**
2. Antes de cambiar algo, **analiza el problema**.
3. Entrega primero un **plan** con: archivos que cambiarías, motivo del cambio, riesgo y cómo probarlo.
4. **Espera la aprobación** antes de implementar.
5. Implementa **solo un paso a la vez**.
6. Después de cada cambio, **resume exactamente qué modificaste**.
7. **No agregues dependencias nuevas** sin aprobación.
8. **No hagas refactor general.**
9. **No renombres archivos ni carpetas** salvo que se apruebe.
10. **Mantén el estilo actual del proyecto.**

# Comunicación

- Responder siempre en español.
- Ser breve y directo.
- No hacer cambios sin aprobación.
- Explicar el plan antes de implementar.
- Implementar un paso a la vez.
- Mantener la arquitectura existente.
- No introducir sobreingeniería.

## Project overview

Workspace Faret is a server-rendered PHP portal (no framework, no build step, no package manager) that centralizes internal tools for operations, data, and HR at Faret. Pages are plain `.php` files that render HTML directly; there is no templating engine, router, autoloader, or ORM.

## Deployment

Confirmed 2026-07-15: `workspace.faret.cl` (this repo) and `api.faret.cl` (the sibling `Formularios.Api` .NET project, at `../Formularios.Api`) run on **separate servers** — neither is hosted on the local dev machine (no IIS installed there: `W3SVC` service doesn't exist, `inetpub/wwwroot` is empty).

- **This repo (workspace.faret.cl):** no build step — deploying an update means copying only the changed `.php`/`.js`/`.css` files to the same relative path under that server's document root. No restart needed; PHP is interpreted per-request.
- **Formularios.Api (api.faret.cl):** has its own separate build/publish/deploy process, unrelated to this repo's deploy — see that repo's own `CLAUDE.md` for details (`dotnet publish` to a local `_deploy/` folder, then manual copy to its IIS server, excluding `wwwroot/uploads` to avoid overwriting real production attachments, using an `app_offline.htm` swap to avoid file locks since it hosts in-process).

There are no automated tests, linter, or build/dev-server commands configured in this repo. To work on a page, run it through any PHP 8.x-capable web server (Apache/Nginx/IIS) with the repo root as document root, since code relies on `$_SERVER['DOCUMENT_ROOT']` pointing at the project root (e.g. `php -S localhost:8000` from the repo root works for quick checks).

## Architecture

**Request flow:** `index.php` → `modules/welcome/index.php` is the landing page. Each functional area lives under `modules/<area>/`, and deeper features nest further (e.g. `modules/rrhh/guardias/registros/index.php`). There's no routing table — the URL path *is* the file path, resolved by the web server.

**Page pattern.** Nearly every module page follows this exact shape:
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/services/ApiClient.php'; // if it needs data
ob_start();
// ... PHP data-prep logic (fetch, filter, paginate) ...
?>
<!-- HTML using shared CSS classes: hero, cards, card, kpi-grid, kpi-card,
     table-card, table-header, data-table, filter-card, filter-group,
     btn-primary/btn-secondary/btn-export, status-badge, badge -->
<?php
$contenido = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/layouts/app.php';
```
`layouts/app.php` wraps `$contenido` with the shared shell (`includes/sidebar.php` nav + `includes/topbar.php`), pulling in `assets/css/main.css` and Bootstrap Icons. When adding a new page, follow this exact `ob_start()` → build `$contenido` → `include .../layouts/app.php` pattern — don't echo HTML directly.

Note: `modules/operacion/index.php` includes the layout via a relative path (`../../layouts/app.php`) instead of `$_SERVER['DOCUMENT_ROOT']` like every other module — this is an inconsistency in the existing code, not a pattern to copy. Use the `$_SERVER['DOCUMENT_ROOT']` form for new pages.

**Data layer (server-side pages).** Most pages get external data from remote REST APIs via `services/ApiClient.php`, a static class wrapping cURL. Base URLs are defined as constants in `config/api.php` (`API_GUARDIAS`, `API_MEJORA_CONTINUA`, `API_FORMULARIOS`). `ApiClient::get/post/put/patch()` hit the Guardias API; `getMejoraContinua/postMejoraContinua()` hit the Mejora Continua API. `request()` always returns a uniform shape:
```php
['ok' => bool, 'status' => int, 'error' => ?string, 'data' => mixed]
```
Pages check `$respuesta['ok']` and render an inline error card on failure — always follow this contract for new API calls rather than throwing/catching exceptions.

**Data layer (client-side pages).** `modules/formularios/desarrollo/**` breaks from the pattern above: instead of going through `ApiClient.php`, these pages render an empty shell and fetch directly from the browser against `API_FORMULARIOS` (`https://api.faret.cl/formularios/api/`) using JS `fetch()`. The base URL reaches the client via a `window.API_FORMULARIOS = '...'` inline `<script>` block set from the PHP constant (see `admin/index.php`, `desarrollo/index.php`) — except Desarrollo Gráfico's `admin/detalle.php`, which hardcodes the URL directly in its inline script instead of using the constant (the Estructural `detalle.php` does use the PHP constant correctly). This is a real architectural fork (server-proxied vs. browser-direct API calls) — don't assume `ApiClient.php` is involved when touching `modules/formularios/**`.

**The `API_FORMULARIOS` backend is a separate .NET project, not part of this repo.** It lives at a sibling path (`../Formularios.Api` relative to this repo on the dev machine) — an ASP.NET Core 8 + Dapper/MySQL + MailKit + QuestPDF API. It is **not a generic forms engine**: each request type (Desarrollo Gráfico, Desarrollo Estructural) has its own hardcoded DB tables (`desarrollo_grafico_*` / `desarrollo_estructural_*`), DTOs, repository, service, and controller in that project — adding a new form type there means cloning that vertical, not extending a shared one. `catalogos/solicitantes` and `catalogos/clientes` are the only genuinely shared catalogs across form types. If a `workspacefaret` task needs new fields, new endpoints, or email/PDF changes for a formulario, that work happens in `Formularios.Api`, not here — this repo only ever calls it over HTTP.

**Filtering/pagination convention.** List pages (e.g. `modules/datos/guardias/index.php`, `modules/rrhh/guardias/registros/index.php`) fetch the *entire* collection from the API, then filter/paginate in PHP using `$_GET` params (`fecha_desde`, `fecha_hasta`, `usuario`, `planta`, `page`), with a fixed `$porPagina = 20`. Filter state round-trips through the query string via `http_build_query()`. Excel export pages under `exports/` (e.g. `exports/guardias/recorridos-excel.php`) mirror this same fetch+filter logic independently and stream an `.xls`-labeled HTML table with `Content-Disposition: attachment` headers rather than reusing the list page's code — there's no shared filtering helper, so a change to filter logic in a list page must be mirrored in its export sibling.

**Theming convention (added 2026-07-13).** `assets/css/main.css` defines the color tokens twice: once in `:root` (dark, the original/default theme) and once in `[data-theme="light"]` (light, same token names). All actual light-mode fixes live in **additive-only** override blocks appended at the very end of `main.css`, `assets/css/formularios/admin-formularios.css`, and `assets/css/formularios/formularios.css` — every light-mode rule is a new `[data-theme="light"] .selector { ... }` declaration, and no existing dark-mode rule was edited in place (kept low-risk/reviewable on purpose). **Consequence:** any new hardcoded color (`color: #fff`, `background: rgba(255,255,255,.08)`, etc.) added to any of these 3 files needs a matching override added to that file's end-of-file "MODO CLARO" section, or it silently breaks in light mode — this was already missed once for `.stat-card strong` / `.kpi-card strong` / `.card h2` / `.module-card h2` / `.field label` and had to be patched in a follow-up pass. `layouts/app.php` has a blocking inline `<script>` in `<head>` (before the stylesheet `<link>`) that reads `localStorage.getItem('workspace-theme')` and sets `data-theme` on `<html>` before first paint, to avoid a flash of the wrong theme on navigation (this is a full-page-reload app, not an SPA, so this runs on every page). `assets/js/theme.js` (loaded at the end of `<body>`, via `layouts/app.php`) wires the toggle button `#themeToggle` in `includes/topbar.php`, which flips `data-theme` and persists it to the same `localStorage` key. `modules/welcome/` is intentionally excluded from theming (separate landing page, own `welcome.css`, video background, always dark/branded).

**Module structure:**
- `modules/operacion/` — operations hub, links out to `modules/operacion/logistica/*` and `modules/formularios/desarrollo/` (Desarrollo), plus external forms at `solicitudes.faret.cl`. Since 2026-07-13 its "Pendientes que requieren atención" panel replaced the old hardcoded demo stats/table (`'Vista demo'` badge, static counts, a static 2-row forms table) with 4 real, clickable KPI cards (`.stat-card-link`): Desarrollo pendientes (Gráfico + Estructural combined, `estado` in Recibido/En edición/Pendiente información), Urgentes (Gráfico only, `prioridad === 'URGENTE'`), No conformidades abiertas, and Recorridos de guardia registrados hoy. The first two are fetched client-side by `assets/js/operacion-pendientes.js` against `API_FORMULARIOS` (same reasoning as the data-layer note below — that API is browser-direct only); the last two are fetched server-side in the page itself via `ApiClient::getMejoraContinua('no-conformidades')` and `ApiClient::get('recorridos')` (filtered to today's date in PHP). This also introduced the project's first URL-query-string deep-link-into-a-filtered-view pattern: `assets/js/formularios/admin-formularios.js` now reads `?estado=`/`?prioridad=` on load and pre-sets the matching filter `<select>` before rendering, and `modules/datos/mejora-continua/index.php` (previously unfiltered) now reads `?estado=` and filters the rendered table server-side, showing a "quitar filtro" link when active. Follow this same server-side/client-side split for any future cross-module KPI added here.
- `modules/rrhh/` — HR hub → `modules/rrhh/guardias/` (guard shift management: `registros/` for shift records, `usuarios/` for user admin against the Guardias API).
- `modules/datos/` — "Centro de Control": `dashboard/`, `guardias/` (same recorridos data as rrhh but read-only reporting view), `mejora-continua/` (non-conformance tracking against the Mejora Continua API), `reportes/`, `exportaciones/` (links to the `exports/` Excel downloads).
- `modules/formularios/` — "Formularios Corporativos" hub. `desarrollo/` (Desarrollo) holds two independent request types, each client-side/API-driven (see the data layer note above):
  - `desarrollo/solicitud-grafica/` (new-request form) + `desarrollo/admin/` (filterable list + KPIs) + `desarrollo/admin/detalle.php?id=` (detail, status change, history, attachments) — **Desarrollo Gráfico**. `desarrollo/admin/exportar.php` is currently an **empty stub** — the "Exportar Excel" button in `admin/index.php` already links to it, so treat that export as not-yet-implemented, not broken.
    - The "Cambiar Estado" panel in `admin/detalle.php` (2026-07-13) sends, alongside `estadoId`/`observacion`, two extra fields in the `PUT solicitudes/{id}/estado` call: **Editor asignado** (`operadorEdicion`, free text) and **Nivel de complejidad** (`nivelComplejidad`, hardcoded `<select>` with BAJA/MEDIA/ALTA — no dynamic catalog, same reasoning as `tipoProceso`). Both are columns on `desarrollo_grafico_solicitudes` (`operador_edicion`, `nivel_complejidad`), written by `Formularios.Api`'s `ActualizarEstadoAsync`, and read back via `GET solicitudes/{id}/detalle`. They are **not** part of the `*_historial` row — same pattern as `observacion` living on the solicitud itself.
    - Same panel also has a **"Reabrir solicitud"** button (2026-07-13), visible only when the current estado is Terminado/Rechazado/Anulado (ids 4/5/6). It just pre-selects "En edición" in the same `nuevoEstado` dropdown and reuses the normal "Guardar cambio" flow — there's no dedicated reopen endpoint; since `PUT solicitudes/{id}/estado` always `UPDATE`s the existing row, the id/código never change on reopen.
    - **OC** (`oc`, free-text purchase-order number, optional, 2026-07-13): present on the creation form (`solicitud-grafica/index.php`, "Datos principales"), the admin detail view, the admin list table + Excel export, and the PDF.
  - `desarrollo/solicitud-estructural/` (new-request form) + `desarrollo/solicitud-estructural/admin/` (filterable list + KPIs) + `desarrollo/solicitud-estructural/admin/detalle.php?id=` — **Desarrollo Estructural**. Hits a *different* set of API routes (`solicitudes-estructural`, not `solicitudes`) backed by its own DB tables — see the API note below. Notification email here only goes to the fixed corporate list (no per-request solicitante email field, unlike Gráfico).
    - Until 2026-07-13 this vertical had no status/history workflow at all (no `estado_id`, no historial table) — only creation + adjuntos + PDF. On that date the full "gestión de solicitudes" from Gráfico was replicated here: `estado_id` (defaults to "Recibido" on creation), a `desarrollo_estructural_historial` table, the same "Cambiar Estado" panel (estado + Editor asignado + Nivel de complejidad + Observación + Reabrir) in `admin/detalle.php`, an Estado column/filter in `admin/index.php` + Excel export, and an **OC** field (same shape as Gráfico's, above). The existing KPIs in `admin/index.php` (Con adjunto / Cliente nuevo / Últimos 7 días) were left as-is rather than swapped for Gráfico's estado-based KPIs.
  - Both request types share the `solicitantes` and `clientes` catalogs (`catalogos/solicitantes`, `catalogos/clientes?search=`), and since 2026-07-13 also the shared `estados_solicitud` catalog (`catalogos/estados`) for the state-management workflow described above. Both reuse the CSS in `assets/css/formularios/*` — no new CSS was added for Estructural, it reuses `formularios.css` / `admin-formularios.css` as-is.
- `modules/en-proceso/index.php` — generic "coming soon" placeholder page, parameterized by a `?modulo=` query string; linked to from the sidebar for unbuilt areas (Contabilidad, Comercial, Registros Formularios) instead of building stub modules.
- `modules/dashboard/index.php` — a simplified two-card home hub (Operación / Administración-Registros). Not linked from the sidebar, `welcome`, or anywhere else in the app as of this writing — treat it as orphaned/experimental, not a live route, unless you find a new link to it.
- `_backup_logistica/`, `_backup_orden_rrhh/` — timestamped snapshots of previous file versions (filenames end in `_YYYYMMDD_HHMMSS`), kept in-repo rather than relying on git history. Don't treat these as live code paths.
- `includes/sidebar.php.bak` — a stray backup file that doesn't follow the `_backup_*/name_YYYYMMDD_HHMMSS.ext` convention above. Don't treat it as live code; flag it rather than silently deleting it.

**Navigation.** `includes/sidebar.php` is the single source of truth for the nav menu; it's hand-maintained HTML with commented-out `<a>` blocks marking not-yet-enabled areas. When adding a module, add its link here.

## Security notes specific to this repo

- `services/ApiClient.php` sets `CURLOPT_SSL_VERIFYPEER` / `CURLOPT_SSL_VERIFYHOST` to `false` — be aware of this when debugging TLS-related API issues; don't silently "fix" it as a drive-by change without flagging it, since it affects all outbound API calls.
- API base URLs in `config/api.php` are public API hosts, not secrets, but no credentials should ever be added to that file directly per the project's own README guidance — use environment-excluded config for anything sensitive.
- User-supplied output is escaped with `htmlspecialchars()` throughout existing pages — keep doing this for any new dynamic output.
