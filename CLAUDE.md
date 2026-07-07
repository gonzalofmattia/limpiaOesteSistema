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
`ProspectController`.

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

## Módulo Outreach — Fase 2: motor de envíos (cola + worker)

El hosting (DonWeb) no puede mandar WhatsApp. El sistema arma y administra la cola;
un worker Python (`worker/whatsapp_worker.py`, **fuera del deploy** — ver
`deploy.php`) corre en una PC local con Chrome + WhatsApp Web y hace polling a la
API. Todo se controla desde el panel (`/prospeccion/campanas`, `/prospeccion/cola`,
card "Worker" en `/prospeccion`).

### Schema (`database/migrations/2026_07_07_outreach_*`)

- **`outreach_campaigns`**: filtros (rubro/ciudad/estado del prospecto) + plantilla
  + tope diario propio. Máquina de estados `borrador → activa ⇄ pausada → finalizada`
  (`CampaignController::ALLOWED_TRANSITIONS`).
- **`outreach_queue`**: una fila por mensaje a enviar. `uuid` es lo que el worker
  usa para reportar (nunca el `id` autoincremental). `rendered_body` se calcula
  una sola vez al encolar (no al enviar), así el historial queda igual aunque se
  edite la plantilla después.
- **`outreach_worker_status`**: fila única `id=1`, la pisa cada heartbeat.
- Settings nuevos en la tabla `settings` (prefijo `outreach_`): token de API,
  tope diario (clamp 1-25 en `SettingsController::update`), ventana horaria,
  fines de semana, pausa global, delays min/max, cooldown. Editables desde
  `/settings` (sección "Prospección / Envíos"); el token se muestra ahí de solo lectura.

### `OutreachScheduler` (app/Helpers/OutreachScheduler.php)

Es lazy: no hay cron, se dispara cuando el worker pide `next-batch`. En cada
llamada: recupera `claimed` viejos (>30 min sin reporte → vuelven a `queued`),
chequea pausa global y ventana horaria/fin de semana, y si la cola de HOY está
vacía la llena (round-robin entre campañas activas respetando el `daily_limit`
de cada una y el tope global). La exclusión de prospectos
(`OutreachScheduler::matchingProspects`) siempre descarta: blacklisted, dentro
del cooldown, con un mensaje `queued`/`claimed` pendiente, ya contactados antes
por esa misma campaña, y teléfonos que ya son de un cliente activo (comparación
por `ArgentinePhoneNormalizer`, igual que el importador de prospectos).
**Fase 3 extiende `fillTodayQueueIfNeeded()`** agregando seguimientos y
recontactos con más prioridad que los primeros contactos — no tocar el orden sin
revisar esa fase.

### API para el worker (`OutreachApiController`)

Rutas públicas (`public => true` en `routes.php`, bypasean sesión admin) pero
autenticadas por header `X-Outreach-Token` contra el setting `outreach_api_token`
(`hash_equals`). `next-batch` (GET), `report` (POST, idempotente por `uuid`),
`heartbeat` (POST). Todo queda logueado en `storage/logs/outreach_api.log`.
Al reportar `sent`: si el prospecto estaba en `nuevo` pasa a `contactado`, y
siempre se actualiza `last_contacted_at`/`contact_attempts` + evento
`mensaje_enviado`/`mensaje_fallido`.

### Worker Python (`worker/`)

No se deploya (agregado a `$exclude` en `deploy.php`). Perfil de Chrome
persistente (`chrome_profile_path` en `config.json`) para no re-escanear el QR.
Selectores de WhatsApp Web (`MESSAGE_BOX_SELECTOR`, `SENT_TICK_SELECTOR`) son el
punto más frágil — WhatsApp cambia su DOM seguido, revisar ahí primero si el
worker empieza a fallar todo. **Fase 3 va a agregar** la lectura de chats no
leídos al principio del loop (antes de enviar), reportando a
`/api/outreach/responses`.

### Decisiones

- `daily_limit` de una campaña es un tope propio que compite por el cupo global
  (`outreach_daily_cap`) vía round-robin; no es un tope independiente.
- El link prospecto↔cliente-activo se resuelve por teléfono normalizado, no por
  `prospects.client_id` — evita mandar mensajes a alguien que ya compró aunque
  nadie haya actualizado el estado del prospecto a mano.
- `rendered_body` se persiste en `outreach_queue` en vez de re-renderizar al
  enviar, para que el historial de la cola no cambie retroactivamente si se
  edita la plantilla.
