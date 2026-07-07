# CLAUDE.md — Sistema interno Limpia Oeste

Este archivo le da contexto a Claude Code sobre este repo puntual (backend/admin).
Para contexto de negocio (productos, MercadoLibre, WhatsApp, etc.) ver el CLAUDE.md
de la raíz del proyecto (`C:\laragon\www\limpiaoeste\CLAUDE.md`), que aplica a
ambos repos (este y el storefront).

## Stack

- PHP 8.1+ MVC custom (sin framework)
- MySQL/PDO, singleton en `app/Models/Database.php`
- Tailwind CDN + Alpine.js 3.13.5 (CDN)
- Lucide Icons (CDN, `window.rebuildLucideIcons()` tras cambios dinámicos del DOM)
- DomPDF (PDFs), PhpSpreadsheet (import/export Excel), PHPMailer
- Local: Laragon, `http://sistema.limpiaOeste.test`, DB `limpia_oeste_abm`

## Estructura

```
app/
  Controllers/   Un controlador por dominio, métodos públicos = acciones de ruta
  Core/          Controller (base), Router, App (bootstrap)
  Helpers/       Lógica de negocio reutilizable + functions.php (helpers globales)
  Models/        Solo Database.php (singleton PDO; no hay ORM, SQL inline en controllers)
  Views/         Una carpeta por módulo; layout/main.php es el layout compartido
  config/        routes.php, database.php, app.php, etc.
database/
  migrations/    SQL incremental, nombrado YYYY_MM_DD_descripcion.sql (o NNN_descripcion.sql legacy)
storage/logs/    Logs de la app (file_put_contents, nunca error_log)
public/          Document root, index.php bootstrap
```

## Convenciones

- Clases PascalCase, métodos camelCase, tablas/columnas snake_case, rutas kebab-case.
- `declare(strict_types=1);` en todo archivo PHP.
- Prepared statements siempre — nunca interpolar valores de usuario en SQL.
- Sin ORM: queries inline en controladores vía `Database::getInstance()`.
- Vistas: `Controller::view('modulo/nombre', $data)` renderiza con `layout/main` por
  defecto (`viewRaw()` o `$layout=null` para omitirlo).
- CSRF: `verifyCsrf()` al inicio de todo POST + `csrfField()` en los forms.
- Flash messages: `flash('success'|'error', 'mensaje')` + `getFlash()` en el layout.
- Listados: patrón page/per_page/search con `LIMIT/OFFSET`, tope `per_page` a 100,
  total_pages recalculado, componente compartido `layout/pagination.php`.
- UI: `lo-card`, `lo-table-wrap`/`lo-table`, `lo-mobile-card-list`/`lo-mobile-card`
  (definidas en `public/assets/css/app.css`) para mantener look consistente; tablas
  en desktop + cards apiladas en mobile (`hidden md:block` / `md:hidden`).
- Botones: partials en `app/Views/layout/partials/ui-btn-*.php`.
- Eliminar registros: modal de confirmación compartido (`layout/delete_modal.php`,
  disparado con `openDeleteModal(formId, label)` desde Alpine, no `confirm()` nativo).
- Logging: siempre `file_put_contents(ruta, contenido, FILE_APPEND | LOCK_EX)` a
  `storage/logs/`. Nunca `error_log()` (no accesible en Ferozo).

## Git — workflow obligatorio

```
git checkout main
git pull
git checkout -b tipo/nombre-descriptivo
```

Nomenclatura: `fix/`, `feature/`, `refactor/`, `hotfix/`. Commits descriptivos por
bloque funcional. Al terminar: push. Merge a main lo puede hacer Claude Code una vez
verificada la branch (tests/checks corridos, sin romper nada). El **deploy a
producción NO es automático** — lo corre Gonzalo manualmente con
`php deploy.php --no-vendor --changed-only` (ver CLAUDE.md raíz para detalle completo
de flags de deploy).

## Antes de tocar código sensible

No modificar sin entender bien el porqué (ver CLAUDE.md raíz para el detalle):
`QuoteDeliveryStock.php`, `SeiqOrderBuilder.php`, `PricingEngine.php`, lógica de
stock/cuenta corriente/ML.

## Módulo Outreach (Prospección) — Fase 1

CRM de prospección B2B por WhatsApp. Vive bajo `/prospeccion`, controlador
`ProspectController`. Pensado para una Fase 2 (no implementada todavía) que agregue
cola de envíos + worker conectado al script externo de automatización de WhatsApp
(`C:\Users\gonza\whatsapp-limpia-oeste`, fuera de este repo).

### Schema (`database/migrations/2026_07_07_*`)

- **`prospects`**: negocio prospecto. `phone` normalizado y único (mismo criterio
  de normalización de números AR que usa el script de WhatsApp: agrega `+54` o
  `+549` según longitud/prefijo). `status` es una máquina de estados simple
  (`nuevo` → ... → `cliente` | `no_interesado` | `sin_respuesta`). Al pasar a
  `no_interesado` se setea `blacklisted=1` automáticamente (controller, no trigger).
  `client_id` linkea al prospecto que efectivamente se convirtió en cliente
  (tabla `clients`, ya existente — no se toca).
- **`outreach_templates`**: plantillas de mensaje por rubro + etapa, con variables
  `{{nombre}}`/`{{ciudad}}` que se resuelven client-side (preview Alpine) y
  server-side al momento de enviar (Fase 2).
- **`prospect_events`**: bitácora de eventos por prospecto (cambios de estado,
  notas manuales, futuros envíos). Es el historial que alimenta el dashboard
  ("respuestas pendientes" = último evento no es de tipo respuesta, etc.).

### Decisiones

- No se creó tabla de cola/worker en esta fase — el schema de `prospect_events`
  ya deja lugar para loguear envíos cuando se implemente la Fase 2, sin migrar de nuevo.
- El importador reusa el patrón de `ProductController::import*` (PhpSpreadsheet,
  headers tolerantes a mayúsculas/acentos) pero como métodos propios en
  `ProspectController`, no una clase compartida — el volumen de casos especiales
  de productos (multi-hoja, categorías) no aplica acá.
