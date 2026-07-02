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

**Data layer (client-side pages).** `modules/formularios/desarrollo/**` breaks from the pattern above: instead of going through `ApiClient.php`, these pages render an empty shell and fetch directly from the browser against `API_FORMULARIOS` (`https://api.faret.cl/formularios/api/`) using JS `fetch()`. The base URL reaches the client via a `window.API_FORMULARIOS = '...'` inline `<script>` block set from the PHP constant (see `admin/index.php`, `desarrollo/index.php`) — except `admin/detalle.php`, which hardcodes the URL directly in its inline script instead of using the constant; keep that in mind if the API host ever changes. This is a real architectural fork (server-proxied vs. browser-direct API calls) — don't assume `ApiClient.php` is involved when touching `modules/formularios/**`.

**Filtering/pagination convention.** List pages (e.g. `modules/datos/guardias/index.php`, `modules/rrhh/guardias/registros/index.php`) fetch the *entire* collection from the API, then filter/paginate in PHP using `$_GET` params (`fecha_desde`, `fecha_hasta`, `usuario`, `planta`, `page`), with a fixed `$porPagina = 20`. Filter state round-trips through the query string via `http_build_query()`. Excel export pages under `exports/` (e.g. `exports/guardias/recorridos-excel.php`) mirror this same fetch+filter logic independently and stream an `.xls`-labeled HTML table with `Content-Disposition: attachment` headers rather than reusing the list page's code — there's no shared filtering helper, so a change to filter logic in a list page must be mirrored in its export sibling.

**Module structure:**
- `modules/operacion/` — operations hub, links out to `modules/operacion/logistica/*` and `modules/formularios/desarrollo/` (Desarrollo), plus external forms at `solicitudes.faret.cl`.
- `modules/rrhh/` — HR hub → `modules/rrhh/guardias/` (guard shift management: `registros/` for shift records, `usuarios/` for user admin against the Guardias API).
- `modules/datos/` — "Centro de Control": `dashboard/`, `guardias/` (same recorridos data as rrhh but read-only reporting view), `mejora-continua/` (non-conformance tracking against the Mejora Continua API), `reportes/`, `exportaciones/` (links to the `exports/` Excel downloads).
- `modules/formularios/` — "Formularios Corporativos" hub. Currently one live area, `desarrollo/` (Desarrollo Gráfico), which is client-side/API-driven (see the data layer note above): `desarrollo/solicitud-grafica/` (new-request form), `desarrollo/admin/` (filterable list + KPIs), `desarrollo/admin/detalle.php?id=` (single-request detail, status change, history, attachments). `desarrollo/admin/exportar.php` is currently an **empty stub** — the "Exportar Excel" button in `admin/index.php` already links to it, so treat that export as not-yet-implemented, not broken.
- `modules/en-proceso/index.php` — generic "coming soon" placeholder page, parameterized by a `?modulo=` query string; linked to from the sidebar for unbuilt areas (Contabilidad, Comercial, Registros Formularios) instead of building stub modules.
- `modules/dashboard/index.php` — a simplified two-card home hub (Operación / Administración-Registros). Not linked from the sidebar, `welcome`, or anywhere else in the app as of this writing — treat it as orphaned/experimental, not a live route, unless you find a new link to it.
- `_backup_logistica/`, `_backup_orden_rrhh/` — timestamped snapshots of previous file versions (filenames end in `_YYYYMMDD_HHMMSS`), kept in-repo rather than relying on git history. Don't treat these as live code paths.
- `includes/sidebar.php.bak` — a stray backup file that doesn't follow the `_backup_*/name_YYYYMMDD_HHMMSS.ext` convention above. Don't treat it as live code; flag it rather than silently deleting it.

**Navigation.** `includes/sidebar.php` is the single source of truth for the nav menu; it's hand-maintained HTML with commented-out `<a>` blocks marking not-yet-enabled areas. When adding a module, add its link here.

## Security notes specific to this repo

- `services/ApiClient.php` sets `CURLOPT_SSL_VERIFYPEER` / `CURLOPT_SSL_VERIFYHOST` to `false` — be aware of this when debugging TLS-related API issues; don't silently "fix" it as a drive-by change without flagging it, since it affects all outbound API calls.
- API base URLs in `config/api.php` are public API hosts, not secrets, but no credentials should ever be added to that file directly per the project's own README guidance — use environment-excluded config for anything sensitive.
- User-supplied output is escaped with `htmlspecialchars()` throughout existing pages — keep doing this for any new dynamic output.
