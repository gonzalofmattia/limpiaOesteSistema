# INFORME COMPLETO DEL SISTEMA — LIMPIA OESTE

**Fecha de generación:** 12 de mayo de 2026  
**Versión del sistema:** Sistema ABM de productos y precios — PHP 8.1+  
**Entorno local:** Laragon (Windows) | **Producción:** DonWeb (sistema.limpiaoeste.com.ar)

---

## SECCIÓN 1: ARQUITECTURA GENERAL

### 1.1 Estructura de carpetas

```
limpiaOesteSistema/
├── app/
│   ├── config/
│   │   ├── app.php              # Timezone, session name
│   │   ├── database.php         # Credenciales DB (generado por install.php)
│   │   └── routes.php           # Definición de TODAS las rutas
│   ├── Controllers/             # 16 controladores
│   │   ├── AccountController.php
│   │   ├── ApiController.php
│   │   ├── AttachmentController.php
│   │   ├── AuthController.php
│   │   ├── CategoryController.php
│   │   ├── ClientController.php
│   │   ├── ComboController.php
│   │   ├── DashboardController.php
│   │   ├── MailController.php
│   │   ├── MercadoLibreController.php
│   │   ├── PriceListController.php
│   │   ├── ProductController.php
│   │   ├── QuoteController.php
│   │   ├── SaleController.php
│   │   ├── SearchController.php
│   │   ├── SeiqOrderController.php
│   │   ├── SettingsController.php
│   │   ├── StockController.php
│   │   ├── SyncController.php
│   │   └── ToolsController.php
│   ├── Core/
│   │   ├── App.php              # Bootstrap (env, session, router)
│   │   ├── Controller.php       # Clase base abstracta
│   │   └── Router.php           # Router propio con regex
│   ├── Helpers/
│   │   ├── CategoryHierarchy.php
│   │   ├── ClientMarkupResolver.php
│   │   ├── ClientReceivableSummary.php
│   │   ├── DatabaseSynchronizer.php
│   │   ├── Env.php
│   │   ├── ImageUploader.php
│   │   ├── InvoiceMailHtml.php
│   │   ├── MailHelper.php
│   │   ├── PricingEngine.php     # Motor de precios central
│   │   ├── QuoteDeliveryStock.php # Lógica de stock
│   │   ├── QuoteLinePricing.php  # Resolución de precios por línea
│   │   ├── SeiqOrderBuilder.php  # Generación automática de pedidos
│   │   ├── SettingsCache.php
│   │   ├── SupplierResolver.php
│   │   └── functions.php         # Helpers globales
│   ├── Models/
│   │   └── Database.php          # Singleton PDO
│   └── Views/                    # ~50 vistas PHP
│       ├── auth/, categories/, clients/, combos (en products/)
│       ├── cuenta-corriente/, dashboard/, layout/, mail/
│       ├── pdf/, pedido-seiq/, presupuestos (quotes/)
│       ├── pricelists/, products/, sales/, search/
│       ├── settings/, stock/, sync/, ventas-ml/
│       └── components/
├── database/
│   └── migrations/               # ~35 archivos SQL incrementales
├── public/
│   ├── index.php                 # Front controller
│   ├── .htaccess                 # Rewrite rules
│   └── assets/
│       ├── css/app.css
│       ├── js/app.js
│       └── img/
├── storage/
│   ├── attachments/
│   ├── logs/
│   ├── pdfs/
│   └── products/ (originals/, thumbs/)
├── vendor/                       # Composer autoload
├── .env                          # Variables de entorno (NO en repo)
├── .gitignore
├── composer.json
├── deploy.php                    # Deploy FTP a producción
├── install.php                   # Instalación inicial
└── index.php                     # Redirect a public/
```

### 1.2 Patrón MVC propio

El sistema implementa un MVC **sin framework**, con las siguientes características:

**Entry Point** (`public/index.php`):
- Define constantes: `BASE_PATH`, `APP_PATH`, `PUBLIC_PATH`, `STORAGE_PATH`
- Carga `.env` con `Env::load()`
- Configura error_reporting según `APP_DEBUG`
- Registra exception handler, error handler y shutdown function
- Calcula `BASE_URL` dinámicamente desde `SCRIPT_NAME`
- Carga Composer autoload si existe
- Ejecuta `App::run()`

**App::run()** (`app/Core/App.php`):
- Carga `.env` de nuevo (redundante pero seguro)
- Configura timezone (America/Argentina/Buenos_Aires)
- Inicia sesión con nombre configurable (`limpia_oeste_session`)
- Carga helpers globales (`functions.php`)
- Instancia `Router`, carga rutas desde `routes.php`, despacha

**Router** (`app/Core/Router.php`):
- Convierte patrones como `presupuestos/{id}/editar` a regex con named groups
- Parámetro `{id}` matchea `[0-9]+`, parámetro `{slug}` matchea `[a-z0-9]+(?:-[a-z0-9]+)*`
- Soporta rutas `public` (sin auth) y protegidas
- Verifica `$_SESSION['admin_user_id']` para rutas protegidas
- Si no hay match, devuelve 404

**Controller base** (`app/Core/Controller.php`):
- Método `view()`: captura output con ob_start, inyecta en layout
- Método `viewRaw()`: renderiza sin layout
- Método `json()`: respuesta JSON
- Métodos `input()` y `query()`: acceso a `$_POST` y `$_GET`

**Database** (`app/Models/Database.php`):
- Singleton PDO con `ERRMODE_EXCEPTION` y `FETCH_ASSOC`
- Métodos: `query()`, `fetch()`, `fetchAll()`, `fetchColumn()`, `insert()`, `update()`, `delete()`, `count()`
- Todos usan prepared statements internamente
- No hay ORM ni modelos por tabla; las queries SQL están inline en los controladores

### 1.3 Rutas

El archivo `app/config/routes.php` define **148 rutas** organizadas en secciones:

| Módulo | Rutas | Métodos |
|--------|-------|---------|
| Auth | 3 | GET/POST login, GET logout |
| Categorías | 6 | CRUD + toggle |
| Productos | 12 | CRUD + toggle + imágenes + importación |
| Combos | 7 | CRUD + toggle + API |
| Stock | 2 | Listar + ajustar |
| Listas de precios | 6 | CRUD + preview + PDF |
| Clientes | 6 | CRUD + API crear |
| Presupuestos | 13 | CRUD + status + PDF + entrega parcial + crédito + mail |
| Ventas | 5 | Listar + reportes + exportar |
| Ventas ML | 6 | CRUD |
| Adjuntos | 4 | Upload + download + ver + eliminar |
| Pedidos proveedor | 16 | CRUD + status + PDF + markDelivered (rutas duplicadas pedido-seiq y pedidos-proveedor) |
| Settings | 2 | Listar + guardar |
| Sync | 4 | Index + run + export + import |
| Cuenta corriente | 13 | Index + clientes + detalle + cobros + pagos + ajustes + PDF |
| API interna | 11 | Catálogo, búsqueda, precios, combos, clientes, pricing preview |
| Dashboard | 2 | Index + detalle |
| Otros | 4 | Búsqueda, fix-stock, tools |

**Rutas públicas** (sin autenticación): login, API catálogo, API imágenes productos, API items explotados.

### 1.4 Autenticación y sesiones

- Tabla `admin_users` con `username` y `password_hash`
- Login usa `password_verify()` contra hash bcrypt
- Sesión nativa PHP con nombre `limpia_oeste_session`
- `$_SESSION['admin_user_id']` y `$_SESSION['admin_username']`
- Verificación en el Router: si ruta no es `public` y no hay `admin_user_id`, redirect a `/login`
- Logout: `unset()` de variables de sesión

### 1.5 Assets

- **CSS**: `public/assets/css/app.css` (archivo propio mínimo)
- **JS**: `public/assets/js/app.js` (lógica UI personalizada)
- **CDN**: Tailwind CSS (vía cdn.tailwindcss.com), Alpine.js 3.13.5, Lucide Icons, Chart.js 4.4.1
- **Fuentes**: Poppins (Google Fonts)
- **Imágenes**: Logo en `public/assets/img/`, portada PDF en `public/assets/img/portadaPDF.jpg`
- Imágenes de producto en `storage/products/originals/{id}/` y `storage/products/thumbs/{id}/`

### 1.6 Dependencias PHP (composer.json)

```json
{
    "require": {
        "php": ">=8.1",
        "dompdf/dompdf": "^2.0",
        "phpoffice/phpspreadsheet": "^2.0 || ^3.0 || ^4.0",
        "phpmailer/phpmailer": "^7.0"
    }
}
```

- **dompdf**: Generación de PDFs (presupuestos, pedidos, listas de precios, estados de cuenta)
- **phpspreadsheet**: Importación/exportación Excel de productos
- **phpmailer**: Envío de emails (presupuestos por correo)

---

## SECCIÓN 2: BASE DE DATOS

### 2.1 Listado de tablas y propósito

| Tabla | Propósito |
|-------|-----------|
| `admin_users` | Usuarios del sistema (admin) |
| `settings` | Configuración global (key-value) |
| `categories` | Categorías de productos (jerárquicas) |
| `suppliers` | Proveedores (SEIQ, Higienik, etc.) |
| `products` | Catálogo de productos |
| `product_images` | Imágenes de productos (multi-imagen) |
| `combos` | Combos de productos |
| `combo_products` | Productos que componen un combo (pivot) |
| `clients` | Clientes |
| `client_segment_config` | Configuración de segmentos de clientes |
| `quotes` | Presupuestos/cotizaciones |
| `quote_items` | Líneas de cada presupuesto |
| `quote_attachments` | Adjuntos de presupuestos (remito, factura) |
| `seiq_orders` | Pedidos a proveedores |
| `seiq_order_items` | Ítems de cada pedido a proveedor |
| `account_transactions` | Movimientos de cuenta corriente (clientes y proveedores) |
| `stock_adjustments` | Auditoría de cambios de stock |
| `price_lists` | Listas de precios generadas |
| `price_list_items` | Ítems de cada lista de precios |
| `sales` | Registro de ventas (módulo adicional) |
| `mail_log` | Log de emails enviados |

### 2.2 Relaciones principales (foreign keys)

```
categories.parent_id → categories.id        (jerarquía padre/hijo)
categories.supplier_id → suppliers.id       (proveedor por categoría)
products.category_id → categories.id
product_images.product_id → products.id
combo_products.combo_id → combos.id
combo_products.product_id → products.id
quotes.client_id → clients.id
quote_items.quote_id → quotes.id
quote_items.product_id → products.id
quote_items.combo_id → combos.id
quote_attachments.quote_id → quotes.id
seiq_orders.supplier_id → suppliers.id
seiq_order_items.seiq_order_id → seiq_orders.id
seiq_order_items.product_id → products.id
price_list_items.price_list_id → price_lists.id
account_transactions: polimórfica (account_type + account_id → clients|suppliers)
```

### 2.3 Campos clave por tabla

**products** (campos relevantes para lógica de negocio):
- `precio_lista_caja`, `precio_lista_unitario`, `precio_lista_bidon`, `precio_lista_litro`, `precio_lista_bulto`, `precio_lista_sobre` — Precios lista proveedor por presentación
- `discount_override`, `markup_override` — Override de descuento/markup a nivel producto
- `units_per_box` — Unidades por caja/bulto
- `sale_unit_type`, `sale_unit_label` — Tipo y etiqueta de unidad de venta
- `stock_units` — Stock físico (unidades)
- `stock_committed_units` — Stock comprometido por presupuestos aceptados
- `is_active` — Flag activo/inactivo

**categories**:
- `parent_id` — Jerarquía (padre/hijo)
- `supplier_id` — Proveedor asociado
- `default_discount`, `default_markup` — Descuento/markup por defecto de la categoría
- `markup_override` — Override de markup
- `markup_locked` — Si el markup está bloqueado (no se puede cambiar desde presupuesto)
- `markup_minorista` — Markup para presupuestos minoristas cuando está locked
- `slug` — Identificador para reglas de pricing (aerosoles, bidones, sobres, etc.)

**quotes**:
- `status` — Estado: draft, sent, accepted, partially_delivered, delivered, rejected, expired
- `delivery_stock_applied` — Guard: 1 si ya se descontó stock por entrega
- `credit_applied`, `credit_transaction_id` — Saldo a favor del cliente aplicado
- `is_mercadolibre` — Flag para ventas MercadoLibre
- `ml_net_amount`, `ml_sale_total` — Montos específicos ML
- `include_iva` — Si los precios incluyen IVA
- `custom_markup` — Override de markup global para este presupuesto
- `discount_percentage`, `discount_amount` — Descuento global del presupuesto
- `sale_number` — Número de venta (asignado al aceptar)

**quote_items**:
- `qty_delivered` — Cantidad ya entregada (para entregas parciales)
- `unit_type` — 'caja' o 'unidad'
- `cost_unit_snapshot`, `cost_subtotal_snapshot` — Snapshot de costo al momento de creación
- `markup_applied`, `discount_applied` — Valores de markup/descuento aplicados

**seiq_orders**:
- `status` — draft, sent, received
- `receipt_stock_applied` — Guard: 1 si ya se sumó stock por recepción
- `included_quotes` — JSON con IDs de presupuestos incluidos

**account_transactions**:
- `account_type` — 'client' o 'supplier'
- `transaction_type` — 'invoice', 'payment', 'adjustment'
- `reference_type` — 'quote', 'seiq_order', 'manual', 'quote_credit'
- `payment_method` — 'efectivo', 'transferencia', 'mercadopago', 'otro'

### 2.4 Flags y state machines

| Campo | Tabla | Valores | Propósito |
|-------|-------|---------|-----------|
| `status` | `quotes` | draft→sent→accepted→partially_delivered→delivered; rejected; expired | Ciclo de vida del presupuesto |
| `status` | `seiq_orders` | draft→sent→received | Ciclo de vida del pedido proveedor |
| `delivery_stock_applied` | `quotes` | 0/1 | Guard idempotencia: evita doble descuento de stock |
| `receipt_stock_applied` | `seiq_orders` | 0/1 | Guard idempotencia: evita doble suma de stock |
| `is_active` | `products`, `categories`, `combos`, `clients`, `suppliers` | 0/1 | Soft-delete / visibilidad |
| `is_mercadolibre` | `quotes` | 0/1 | Distingue presupuestos ML |
| `include_iva` | `quotes` | 0/1 | Si los precios son con IVA |
| `markup_locked` | `categories` | 0/1 | Bloquea override de markup desde presupuesto |

### 2.5 Índices faltantes potenciales

Los archivos de migración son SQL incrementales y no se observa creación explícita de índices compuestos. Potenciales índices faltantes:
- `quote_items(quote_id, product_id)` — Consulta frecuente
- `account_transactions(account_type, account_id, transaction_type)` — Consulta para balances
- `account_transactions(reference_type, reference_id)` — Consulta para buscar facturas de presupuestos
- `seiq_order_items(seiq_order_id)` — JOIN frecuente
- `quotes(status)` — Filtro frecuente
- `quotes(client_id)` — JOIN frecuente

---

## SECCIÓN 3: MÓDULO DE PRODUCTOS Y CATEGORÍAS

### 3.1 ABM de categorías

**Archivos**: `CategoryController.php`, `Views/categories/index.php`, `Views/categories/form.php`

- Jerarquía de dos niveles: categoría padre (parent_id=NULL) y subcategorías (parent_id>0)
- Cada categoría tiene: name, slug (auto-generado), parent_id, supplier_id, default_discount, default_markup, markup_override, markup_locked, markup_minorista
- Toggle activo/inactivo
- El slug se usa internamente para reglas de pricing (aerosoles, bidones, sobres, masivo, alimenticia, etc.)

### 3.2 ABM de productos

**Archivos**: `ProductController.php`, `Views/products/index.php`, `Views/products/form.php`, `Views/products/import.php`

- Campos principales: code, name, description, category_id, presentation, content
- 6 campos de precio lista proveedor (caja, unitario, bidón, litro, bulto, sobre)
- Campos de venta: sale_unit_type, sale_unit_label, sale_unit_description, presentacion_minorista
- Stock: stock_units, stock_committed_units, units_per_box
- Overrides: discount_override, markup_override
- Multi-imagen: upload, reorder, set cover, delete, alt text via `product_images`
- Importación masiva desde Excel (single sheet y multi-sheet)
- Template de importación descargable

### 3.3 units_per_box y sale_unit_type

`units_per_box` define cuántas unidades individuales contiene una caja/bulto. Es crucial para:
- **Stock**: se cuenta en unidades individuales (`stock_units`). Una venta de 1 caja de un producto con units_per_box=12 descuenta 12 del stock
- **Pedidos proveedor**: convierte demanda en unidades a cajas completas con `ceil(unidades / units_per_box)`
- **Pricing aerosoles**: precio caja = precio_unitario × units_per_box

`sale_unit_type` determina la unidad de venta por defecto; `sale_unit_label` es la etiqueta visible ("Caja", "Bidón x 5L", etc.)

### 3.4 Productos activos vs inactivos

- Campo `is_active` (0/1)
- Toggle vía POST `productos/{id}/toggle`
- Los productos inactivos no aparecen en búsquedas de presupuestos pero permanecen en históricos

### 3.5 Búsqueda de productos (API interna)

**Endpoint**: `GET /api/productos/buscar?q=...`  
**Controlador**: `ApiController@searchProducts`

Busca por code, name y description con `LIKE`. Devuelve JSON con id, code, name, precio, stock, category_slug.

---

## SECCIÓN 4: MOTOR DE PRECIOS (PricingEngine)

**Archivos**: `app/Helpers/PricingEngine.php`, `app/Helpers/QuoteLinePricing.php`

### 4.1 Jerarquía de descuentos

Resuelto por `PricingEngine::getEffectiveDiscount()`:

```
1. discount_override (producto)     → Si existe y no es null/vacío
2. default_discount (categoría)      → Si > 0
3. parent_discount (categoría padre) → Si existe y no es null/vacío
4. 0% (fallback)
```

### 4.2 Jerarquía de markups

Resuelto por `PricingEngine::resolveEffectiveMarkup()`:

**Sin override de operación (presupuesto sin custom_markup)**:
```
1. markup_override (producto)        → Si existe
2. category_markup_override          → Si existe
3. parent_markup_override            → Si existe
4. category_default_markup           → Si existe
5. parent_default_markup             → Si existe
6. setting('default_markup', 60)     → Global (default 60%)
```

**Con override de operación (presupuesto con custom_markup)**:
- Si la categoría tiene `markup_locked=1` Y `markup_minorista` definido → usa `markup_minorista` (source: `locked_minorista`)
- Si la categoría tiene `markup_locked=1` SIN `markup_minorista` → ignora override, sigue cascada normal
- Si NO está locked → usa el override del presupuesto (source: `override`)

### 4.3 Fórmulas

```
Costo        = Precio Lista Seiq × (1 - Descuento% / 100)
Precio Venta = Costo × (1 + Markup% / 100)
Precio + IVA = Precio Venta × (1 + IVA% / 100)    [IVA default: 21%]
Margen ($)   = Precio Venta - Costo
```

### 4.4 Casos especiales por categoría

Determinado por `QuoteLinePricing::resolveListaForQuote()` según el slug de categoría:

| Categoría slug | Modo CAJA (price field) | Modo UNIDAD (price field) |
|----------------|------------------------|--------------------------|
| aerosoles | unitario × units_per_box | unitario |
| bidones | caja | bidón |
| masivo | caja | unitario |
| sobres | caja | sobre |
| alimenticia | caja | bidón |
| default | caja (fallback: unitario × units_per_box) | unitario |

### 4.5 Precios en presupuestos

Los precios se calculan **dinámicamente** al crear/editar el presupuesto, pero se persisten como **snapshot** en `quote_items`:
- `unit_price` — Precio unitario calculado
- `individual_unit_price` — Precio de 1 unidad suelta
- `cost_unit_snapshot`, `cost_subtotal_snapshot` — Costo al momento
- `markup_applied`, `discount_applied` — Markup y descuento usados
- `price_field_used` — Campo de precio utilizado

Esto significa que cambios en precios de lista NO afectan presupuestos ya creados.

---

## SECCIÓN 5: PRESUPUESTOS (QuoteController)

**Archivos**: `app/Controllers/QuoteController.php`, `app/Views/quotes/form.php`, `app/Views/quotes/preview.php`, `app/Views/quotes/index.php`, `app/Views/pdf/quote.php`

### 5.1 Ciclo de vida completo

```
draft ──→ sent ──→ accepted ──→ delivered
  │         │         │            ↑
  │         │         ├──→ partially_delivered ──→ delivered
  │         │         │
  │         │         ├──→ rejected ──→ draft
  │         │         └──→ expired  ──→ draft
  │         ├──→ rejected ──→ draft
  │         └──→ expired  ──→ draft
  ├──→ accepted (directo)
  ├──→ rejected
  └──→ expired
```

### 5.2 Transiciones de estado (ALLOWED_TRANSITIONS)

```php
'draft'                 => ['sent', 'accepted', 'rejected', 'expired'],
'sent'                  => ['draft', 'accepted', 'rejected', 'expired'],
'accepted'              => ['draft', 'sent', 'delivered', 'partially_delivered', 'rejected', 'expired'],
'partially_delivered'   => ['delivered', 'rejected'],
'delivered'             => [],              // Terminal
'rejected'              => ['draft'],       // Reabrir
'expired'               => ['draft'],       // Reabrir
```

### 5.3 Qué pasa en CADA transición

| De → A | Stock | Cuenta corriente | Otros |
|--------|-------|-----------------|-------|
| * → accepted | `commitStock()` | Crea invoice en account_transactions | Asigna sale_number |
| * → sent | — | — | Registra sent_at |
| accepted → delivered | `releaseCommitted()` + `applyDelivery()` | Sincroniza monto invoice | delivery_stock_applied=1, qty_delivered=quantity |
| accepted → partially_delivered | — (cambio de estado solo) | — | — |
| partially_delivered → delivered | `markRemainingDeliveredFromPartial()` | — | delivery_stock_applied=1 |
| accepted → draft/rejected | `releaseCommittedStock()` | Elimina invoice | Revierte crédito si aplica |
| partially_delivered → rejected | `releaseRemainingCommittedStock()` | — | — |
| delivered → * (revert) | `revertDeliveredStock()` | — | delivery_stock_applied=0 |
| * → rejected/expired | — | Elimina invoice, revierte crédito | Recalcula balance cliente |

### 5.4 Estados editables

```php
EDITABLE_STATUSES = ['draft', 'sent', 'accepted', 'partially_delivered']
```

- `delivered`, `rejected`, `expired` NO son editables
- En `partially_delivered`: no se pueden eliminar productos con entregas ni bajar cantidades por debajo de lo entregado

### 5.5 Creación/edición de quote_items

El método `persistQuote()` (privado, ~400 líneas):
1. Valida cliente y al menos un producto
2. Inicia transacción SQL
3. Si es edición en `accepted`: libera stock comprometido, borra ítems
4. Si es edición en `partially_delivered`: valida restricciones, libera stock pendiente, borra solo ítems sin entregas
5. Procesa líneas: productos normales y combos
6. Para cada producto: resuelve precio lista, calcula con PricingEngine, genera snapshot
7. Para cada combo: calcula subtotal de componentes, aplica markup y descuento del combo
8. Calcula totales: subtotal, IVA, descuento global, crédito aplicado
9. Si es `accepted`/`partially_delivered`: re-commitea stock
10. Si cambió el cliente: migra transacciones de CC
11. Commit

### 5.6 Entrega parcial

**Método**: `partialDelivery()` / `QuoteDeliveryStock::markPartialDelivery()`

- Solo disponible en estados `accepted` y `partially_delivered`
- UI envía cantidades por producto/componente de combo (formato "explotado")
- El controlador convierte cantidades explotadas a cantidades por línea de presupuesto
- Para combos: calcula el mínimo ratio de componentes entregados
- `markPartialDelivery()`: libera committed, aplica descuento físico, actualiza qty_delivered
- Si todas las líneas quedan completas → auto-transiciona a `delivered`
- Si quedan pendientes → estado `partially_delivered`

### 5.7 Entrega desde pedido proveedor

`SeiqOrderController::markQuotesDelivered()`:
- Toma los IDs de presupuestos del campo `included_quotes` del pedido
- Para cada presupuesto en `accepted` o `partially_delivered`:
  - Si `partially_delivered`: `markRemainingDeliveredFromPartial()`
  - Si `accepted`: `markDelivered()`
  - Actualiza status a `delivered`, delivery_stock_applied=1

### 5.8 Generación de PDF

Usa DomPDF con template `Views/pdf/quote.php`. Genera archivo en `storage/pdfs/` con nombre `presupuesto-{id}-{timestamp}.pdf`. Guarda path en `quotes.pdf_path`.

### 5.9 Presupuestos MercadoLibre

- Flag `is_mercadolibre=1`
- Campos específicos: `ml_net_amount` (neto que recibe la empresa), `ml_sale_total` (total de la venta)
- Al crear invoice en CC: usa `ml_net_amount` en lugar de `total`
- Controlador separado: `MercadoLibreController`

### 5.10 Descuento por presupuesto

- `discount_percentage`: porcentaje de descuento global
- `discount_amount`: monto absoluto de descuento
- Se aplica SOLO sobre productos (no sobre combos — los combos van a subtotalNoDiscount)
- Si vienen ambos del POST: mantiene porcentaje del frontend, acota monto
- Si viene solo monto: calcula porcentaje
- Si viene solo porcentaje: calcula monto

### 5.11 Notas y adjuntos

- `quotes.notes`: campo de texto libre
- Adjuntos via tabla `quote_attachments`: tipos 'remito' y 'factura'
- `AttachmentController`: upload, download, ver inline, eliminar

### 5.12 Crédito aplicado (saldo a favor)

- `quotes.credit_applied`: monto de saldo a favor aplicado
- `quotes.credit_transaction_id`: ID del movimiento en account_transactions
- Solo aplicable en estados `draft` y `sent`
- Verifica que el cliente tenga saldo negativo (a favor) en CC
- Crea/actualiza adjustment en account_transactions con reference_type='quote_credit'
- Se revierte automáticamente al rechazar/vencer

---

## SECCIÓN 6: STOCK (QuoteDeliveryStock)

**Archivo**: `app/Helpers/QuoteDeliveryStock.php` (~750 líneas)

### 6.1 Modelo de stock

- **Stock físico** (`stock_units`): unidades reales en depósito
- **Stock comprometido** (`stock_committed_units`): unidades reservadas por presupuestos aceptados
- **Stock disponible**: stock_units - stock_committed_units (calculado, no persistido)

Todo se mide en **unidades individuales** (no en cajas).

### 6.2 commitStock

```
Cuándo: al pasar a 'accepted' (o al re-editar un presupuesto aceptado)
Qué hace: suma stock_committed_units para cada producto del presupuesto
Combos: explota componentes via combo_products (qty × quantity_per_combo)
Conversión: cajas × units_per_box = unidades
```

### 6.3 releaseCommittedStock / releaseRemainingCommittedStock

- `releaseCommittedStock()`: libera TODO el comprometido del presupuesto (resta de stock_committed_units)
- `releaseRemainingCommittedStock()`: libera solo lo PENDIENTE (quantity - qty_delivered por línea)

### 6.4 markDelivered

```
1. Verifica guard delivery_stock_applied (si ya es 1, no hace nada)
2. releaseCommittedStock() — libera todo el comprometido
3. applyDelivery() — descuenta stock_units
4. qty_delivered = quantity para todas las líneas
```

### 6.5 deliverPartial / applyPartialDelivery / markPartialDelivery

`markPartialDelivery()` es el punto de entrada:
1. Para cada línea con entrega, calcula unidades por producto (explota combos)
2. `releaseCommittedUnitsForProducts()` — libera committed por las unidades entregadas
3. `applyPartialDelivery()` — descuenta stock_units y actualiza qty_delivered
4. Verifica si todas las líneas están completas → si sí, auto-entrega completa

### 6.6 applyProductDelta

Método central de modificación de stock:
```php
private static function applyProductDelta(Database $db, int $productId, int $stockDelta, int $committedDelta): void
```

- Lee stock actual
- Log warning si quedaría negativo
- Aplica `max(0, current + delta)` (clamp a 0)
- Registra en `stock_adjustments` si hay cambio físico

### 6.7 Auditoría (stock_adjustments)

Cada cambio de stock_units genera un registro con:
- product_id, previous_stock, new_stock, difference
- notes: "Auto: stock_delta=X committed_delta=Y"
- created_by: 'system'

### 6.8 Ajustes manuales de stock

**Controlador**: `StockController@adjust`
- UI en `Views/stock/index.php`
- Permite setear stock_units directamente por producto
- También registra en stock_adjustments

### 6.9 Explosión de combos

Para todas las operaciones de stock (commit, release, deliver), los combos se descomponen:
```
combo_products WHERE combo_id = ?
→ Para cada componente: product_id, quantity (units per combo)
→ Total units = qty_combo × qty_per_component
```

---

## SECCIÓN 7: PEDIDOS A PROVEEDOR (SeiqOrderBuilder + SeiqOrderController)

**Archivos**: `app/Helpers/SeiqOrderBuilder.php`, `app/Controllers/SeiqOrderController.php`, `app/Views/pedido-seiq/`

### 7.1 Generación automática

`SeiqOrderBuilder::buildFromDatabase()`:
1. `fetchOpenQuotes()`: trae presupuestos con status IN ('accepted', 'partially_delivered')
2. `fetchAcceptedQuotes()`: filtra los que NO están en `included_quotes` de pedidos existentes
3. `fetchQuoteItems()`: usa `pendingUnitsByProductForQuotes()` para obtener demanda pendiente (en partially_delivered descuenta qty_delivered)
4. `calculateRealCommitments()`: compromisos reales desde quote_items
5. `unitsInTransit()`: unidades ya pedidas al proveedor con status='sent'
6. `consolidate()`: agrupa por producto, calcula faltante

### 7.2 Query de presupuestos elegibles

```sql
SELECT * FROM quotes WHERE status IN ('accepted', 'partially_delivered')
```
Excluye los que ya están en `included_quotes` de algún seiq_order existente.

### 7.3 Cálculo de faltante

```
effectiveStock = stock_units + unitsInTransit
unitsToOrder = MAX(0, committedUnits - effectiveStock)
boxesToOrder = CEIL(unitsToOrder / units_per_box)
```

Usa compromisos **reales calculados** (no stock_committed_units que puede estar desincronizado).

### 7.4 Productos manuales

El formulario permite agregar productos manualmente (sin presupuesto asociado) con cantidad de cajas. Se procesan en `parseManualRowsBySupplier()`.

### 7.5 Multi-proveedor

- Cada categoría tiene `supplier_id` (directa o heredada del padre)
- `groupConsolidatedBySupplier()` separa los productos por proveedor
- Se genera un pedido separado por proveedor
- Números: PS-YYYY-NNNN (SEIQ) o PH-YYYY-NNNN (Higienik)

### 7.6 Estados del pedido

```
draft → sent → received
```

### 7.7 Recepción (stock)

Al cambiar a `received`:
- `applySupplierOrderStockDelta(db, orderId, +1)`: suma `boxes_to_order × units_per_box` a stock_units
- `receipt_stock_applied = 1` (guard)
- `registerSupplierInvoiceOnReceive()`: crea invoice en account_transactions para el proveedor
- Requiere monto del remito/factura como input

Al revertir de `received`:
- `applySupplierOrderStockDelta(db, orderId, -1)`: resta stock
- `receipt_stock_applied = 0`

### 7.8 Generación de PDF

Dos variantes:
- `downloadPdf()`: PDF sin precios (para enviar al proveedor)
- `downloadPdfWithPrices()`: PDF con precios de costo por caja y total

### 7.9 Eliminación

Solo pedidos en estado `draft`. Elimina ítems, PDF, transacciones CC y el pedido.

### 7.10 included_quotes

Campo JSON en `seiq_orders`. Ejemplo: `[42, 55, 67]`. Se usa para:
- Excluir presupuestos ya incluidos en pedidos al generar nuevos
- Identificar qué presupuestos marcar como entregados

### 7.11 markQuotesDelivered

Itera los IDs de `included_quotes`, para cada presupuesto accepted/partially_delivered:
- Ejecuta `markDelivered()` o `markRemainingDeliveredFromPartial()`
- Actualiza status a `delivered`, `delivery_stock_applied=1`

### 7.12 Mensaje WhatsApp

`buildWhatsAppMessage()` genera texto formateado con markdown de WhatsApp para enviar al proveedor.

---

## SECCIÓN 8: COMBOS

**Archivos**: `app/Controllers/ComboController.php`, `app/Views/products/combo_form.php`

### 8.1 Definición

Tabla `combos`: id, name, description, markup_percentage, subtotal_override, discount_percentage, is_active.
Tabla `combo_products`: combo_id, product_id, quantity (unidades de cada producto en el combo).

### 8.2 Cómo se agregan a presupuestos

En el formulario de presupuesto, se pueden agregar combos como líneas. Se guardan en `quote_items` con `combo_id` (product_id=NULL para combos).

### 8.3 Explosión para stock

Para TODAS las operaciones de stock, los combos se explotan a sus componentes:
```php
// En unitsByProductForLineQty():
$cps = $db->fetchAll('SELECT product_id, quantity FROM combo_products WHERE combo_id = ?', [$comboId]);
// units = lineSaleQty × quantity_per_component
```

### 8.4 Visualización

- En presupuesto: se muestra como una línea con unit_type='combo'
- En PDF: se muestra el nombre del combo y sus componentes debajo
- `comboComponentsMapForQuoteItems()` trae los componentes para cada línea combo

### 8.5 Pricing de combos

```
1. Para cada componente: calcular precio venta unitario con el markup del combo
2. subtotal = Σ(precio_componente × cantidad)
3. Si subtotal_override definido → usa ese valor
4. comboFinalUnit = subtotal × (1 - discount_percentage / 100)
5. lineTotal = comboFinalUnit × qty
```

Los combos NO se incluyen en el descuento global del presupuesto (van a `subtotalNetNoDiscount`).

### 8.6 Stock de combos

No hay concepto de "stock de combo". La disponibilidad se determina implícitamente por los componentes. No se calcula explícitamente "cuántos combos hay disponibles".

---

## SECCIÓN 9: CUENTA CORRIENTE

**Archivos**: `app/Controllers/AccountController.php`, `app/Helpers/ClientReceivableSummary.php`, `app/Views/cuenta-corriente/`

### 9.1 Modelo de datos

Tabla `account_transactions`:
- `account_type`: 'client' | 'supplier' (polimórfica)
- `account_id`: ID del cliente o proveedor
- `transaction_type`: 'invoice' | 'payment' | 'adjustment'
- `reference_type`: 'quote' | 'seiq_order' | 'manual' | 'quote_credit'
- `reference_id`: ID de la referencia
- `amount`: siempre positivo (el signo se determina por transaction_type)
- `payment_method`: efectivo, transferencia, mercadopago, otro
- `payment_reference`: referencia del pago (nro. transferencia, etc.)
- `transaction_date`, `description`, `notes`

### 9.2 Cuándo se crea un invoice

Al pasar a `accepted` O al pasar a `delivered` (lo que ocurra primero):
```php
if ($status === 'accepted' || $status === 'delivered') {
    // Busca si ya existe invoice para este presupuesto
    // Si no existe y clientId > 0 y amount > 0: crea invoice
    // Si existe: sincroniza monto
}
```

Para ventas ML: el monto del invoice es `ml_net_amount`.

### 9.3 Cuándo se revierte

Al pasar de accepted/delivered/partially_delivered a draft/rejected:
```sql
DELETE FROM account_transactions
WHERE reference_type = 'quote' AND reference_id = ? AND transaction_type = 'invoice'
```

### 9.4 Balance

```
Balance = Σ invoices - Σ payments + Σ adjustments
```

Se persiste en `clients.balance` y se recalcula con `recalculateClientBalance()` después de cada operación.

Existe un sistema "híbrido" (`ClientReceivableSummary`) que para clientes sin transacciones en CC usa la suma de quotes accepted/delivered como fallback.

### 9.5 Cobros y pagos

- **Cobro de cliente**: `registerCollection()` — Crea payment para client
- **Pago rápido**: `quickPayment()` — Pago rápido con opción de vincular a presupuesto
- **Pago a proveedor**: `registerSupplierPayment()` — Crea payment para supplier
- **Ajuste manual**: `registerAdjustment()` — Crea adjustment (positivo o negativo)

### 9.6 Métodos de pago

Efectivo, transferencia, mercadopago, otro.

### 9.7 Generación de PDF de estado de cuenta

`clientStatementPdf()` / `supplierStatementPdf()`:
- Lista todos los movimientos con running balance
- Incluye saldo inicial (opening balance) para clientes
- Genera PDF con DomPDF

### 9.8 Relación con presupuestos y pedidos

- Invoice de presupuesto: `reference_type='quote'`, `reference_id=quote_id`
- Invoice de pedido proveedor: `reference_type='seiq_order'`, `reference_id=order_id`
- Crédito aplicado: `reference_type='quote_credit'`, `reference_id=quote_id`

---

## SECCIÓN 10: LISTAS DE PRECIOS

**Archivos**: `app/Controllers/PriceListController.php`, `app/Views/pricelists/`, `app/Views/pdf/pricelist.php`, `app/Views/pdf/pricelist_minorista.php`

### 10.1 Generación

- Se selecciona un markup y si incluye IVA
- Preview muestra cálculo en tiempo real por producto
- Al confirmar, persiste en `price_lists` + `price_list_items`

### 10.2 Persistencia histórica

- `price_lists`: id, name, markup, include_iva, created_at
- `price_list_items`: price_list_id, product_id, unit_price, pack_price, etc.

### 10.3 Exportación a PDF

Dos templates:
- `pricelist.php`: Lista mayorista
- `pricelist_minorista.php`: Lista minorista (con presentacion_minorista)

### 10.4 Datos incluidos

Cada lista incluye por producto: nombre, código, presentación, precio unitario, precio caja/bulto, categoría.

---

## SECCIÓN 11: CLIENTES

**Archivos**: `app/Controllers/ClientController.php`, `app/Helpers/ClientMarkupResolver.php`, `app/Views/clients/`

### 11.1 ABM

CRUD completo. Eliminación protegida si tiene presupuestos o movimientos en CC.

### 11.2 Campos

- name, business_name, contact_person, phone, email, address, city, notes
- `client_type`: mayorista, minorista, barrio_cerrado, gastronomico, mercadolibre
- `default_markup`: markup personalizado por cliente (nullable)
- `balance`: saldo en cuenta corriente (persistido, recalculado)
- `is_active`: activo/inactivo

### 11.3 Segmentación de precios

- Tabla `client_segment_config`: segment_key, segment_label, default_markup, is_active, sort_order
- `ClientMarkupResolver`: resuelve el markup efectivo para un cliente según su segmento
- Los segmentos se configuran en Settings
- En el formulario de presupuesto, se puede usar el markup del segmento del cliente como base

### 11.4 API de creación rápida

`POST /api/clientes/crear`: permite crear un cliente desde el formulario de presupuesto (modal inline) sin salir de la página.

---

## SECCIÓN 12: DASHBOARD

**Archivo**: `app/Controllers/DashboardController.php`, `app/Views/dashboard/index.php`

### 12.1 KPIs

| KPI | Fuente | Cálculo |
|-----|--------|---------|
| Ventas hoy/semana/mes | quotes (accepted+delivered con sale_number) | COUNT y SUM(total o ml_net_amount) |
| Ticket promedio | quotes | SUM / COUNT del mes |
| Cuentas a cobrar | account_transactions + fallback quotes | Suma balances positivos de clientes |
| Clientes con deuda | clients + CC | COUNT con balance > tolerancia |
| Deuda proveedores | account_transactions (supplier) | invoices - payments + adjustments |
| Pendientes de entrega | quotes (accepted + partially_delivered) | COUNT y SUM |
| Ganancia estimada | quote_items.cost_subtotal_snapshot | ventas - costos estimados |
| Top 5 productos | quote_items explotados | unidades vendidas |
| Top 5 clientes | quotes | SUM total por cliente |

### 12.2 Filtros de período

Selector: 7 días, 30 días (default), 90 días, este mes, mes anterior, histórico.

### 12.3 Gráficos

- **Ventas mensuales** (últimos 6 meses): Chart.js bar chart con labels y montos
- **Entregas parciales pendientes**: listado detallado con productos faltantes

### 12.4 Detalle

Ruta `dashboard/detalle/{slug}` para: aceptados, cobrado, ganancia, pendiente. Muestra tabla detallada con explicación.

---

## SECCIÓN 13: CONFIGURACIÓN (Settings)

**Archivos**: `app/Controllers/SettingsController.php`, `app/Helpers/SettingsCache.php`, `app/Views/settings/index.php`

### 13.1 Parámetros globales

| Key | Propósito | Default |
|-----|-----------|---------|
| `empresa_nombre` | Nombre de la empresa | — |
| `empresa_tagline` | Slogan | — |
| `empresa_instagram` | Instagram | — |
| `empresa_whatsapp` | WhatsApp | — |
| `empresa_zona` | Zona de cobertura | — |
| `default_markup` | Markup por defecto | 60% |
| `iva_rate` | Tasa de IVA | 21% |
| `lista_seiq_numero` | Número de lista de precios del proveedor | — |
| `lista_seiq_fecha` | Fecha de la lista del proveedor | — |
| `moneda` | Moneda | ARS |
| `mostrar_iva` | Mostrar IVA por defecto | — |
| `quote_prefix` | Prefijo de presupuestos | LO |
| `quote_validity_days` | Días de validez | 7 |
| `sale_prefix` | Prefijo de ventas | V- |
| `catalog_markup_mayorista` | Markup catálogo mayorista | — |
| `catalog_markup_minorista` | Markup catálogo minorista | — |
| `balance_tolerance` | Tolerancia para considerar deuda | 800 |

### 13.2 Cómo se leen

`setting($key, $default)` → `SettingsCache::get()` → Singleton que carga toda la tabla `settings` en memoria al primer acceso. Se invalida con `SettingsCache::forget()` al guardar.

### 13.3 Proveedores y segmentos

Settings también muestra/edita:
- Datos de proveedores (cliente_id, cliente_nombre, condicion_pago, observaciones)
- Configuración de segmentos de clientes (default_markup, is_active por segmento)

---

## SECCIÓN 14: SINCRONIZACIÓN

**Archivos**: `app/Controllers/SyncController.php`, `app/Helpers/DatabaseSynchronizer.php`, `app/Views/sync/index.php`

### 14.1 Qué hace

Módulo para sincronizar base de datos entre entorno local (Laragon) y producción (DonWeb).

### 14.2 Export/import SQL

- **Export local**: genera un dump SQL de la base local
- **Import local**: importa un dump SQL a la base local
- **Run sync**: ejecuta sincronización automática (DatabaseSynchronizer)

### 14.3 Para qué se usa

El flujo típico es: desarrollo en local → deploy código con FTP → sincronización de datos de BD por separado.

---

## SECCIÓN 15: DEPLOY Y ENTORNO

### 15.1 Deploy (deploy.php)

Script PHP de ~430 líneas que sube archivos por FTP:

```bash
php deploy.php                  # Deploy completo
php deploy.php --dry-run        # Simular
php deploy.php --no-vendor      # Sin vendor/
php deploy.php --changed-only   # Solo archivos modificados
php deploy.php --only=app       # Solo carpeta app/
```

Características:
- Lee credenciales FTP de `.env` (FTP_HOST, FTP_USER, FTP_PASS, FTP_PATH)
- Soporte FTP y FTPS (auto-detección o forzar con FTP_MODE)
- Reintentos configurables (FTP_RETRIES, default 3)
- Excluye automáticamente: .git, .env, deploy.php, install.php, node_modules, storage/logs, etc.
- Protección: bloquea deploy a /public_html raíz (requiere --force-root)
- Modo `--changed-only`: compara tamaño y mtime antes de subir

### 15.2 Entorno local vs producción

| Aspecto | Local (Laragon) | Producción (DonWeb) |
|---------|-----------------|---------------------|
| URL | localhost/limpiaOesteSistema/public | sistema.limpiaoeste.com.ar |
| Base | BASE_URL = /limpiaOesteSistema/public | BASE_URL = (vacío) |
| Debug | APP_DEBUG=true | APP_DEBUG=false |
| DB | Local MySQL | MySQL DonWeb |

### 15.3 Archivos .env

Variables esperadas:
```
APP_DEBUG=true|false
APP_URL=https://sistema.limpiaoeste.com.ar
DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
FTP_HOST, FTP_USER, FTP_PASS, FTP_PATH, FTP_PORT, FTP_MODE, FTP_TIMEOUT, FTP_RETRIES
MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME
```

### 15.4 .htaccess

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

URL rewriting estándar: todo lo que no sea archivo o directorio va a `index.php?url=...`.

---

## SECCIÓN 16: SEGURIDAD

### 16.1 Autenticación

- Login con username/password contra tabla `admin_users`
- Hash con `password_verify()` (bcrypt)
- Sesión nativa PHP
- **No hay multi-usuario/roles**: un solo nivel de acceso (admin)
- **No hay bloqueo por intentos fallidos ni rate limiting**
- `last_login` se registra

### 16.2 Protección CSRF

**SÍ existe**. Implementado en `functions.php`:
- `csrfToken()`: genera token con `random_bytes(32)`, almacena en `$_SESSION['_csrf']`
- `csrfField()`: genera `<input type="hidden" name="_csrf" value="...">`
- `verifyCsrf()`: verifica con `hash_equals()`
- Todos los POST de formularios verifican CSRF
- **Nota**: el token NO se rota por request, se mantiene mientras dure la sesión

### 16.3 SQL Injection

**Protegido con prepared statements**. La clase `Database` usa `PDO::prepare()` + `execute()` en todos los métodos. Algunos queries construyen SQL dinámico (ORDER BY, LIMIT, OFFSET) pero con valores casteados a int:
```php
'LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
```
**No se detectaron vulnerabilidades de SQL injection.**

### 16.4 XSS

- Función helper `e()`: `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`
- Usado consistentemente en vistas: `<?= e($var) ?>`
- **Pero**: algunos outputs usan `<?= $content ?>` (el contenido de la vista) sin escapar — esto es intencional (es HTML generado por el sistema, no input del usuario)
- Los datos del usuario (nombres, notas, etc.) pasan por `e()` antes de renderizarse

### 16.5 Secretos

- `.env` está en `.gitignore` — no se commitea
- El deploy excluye `.env`
- **No hay secretos hardcodeados** en el código

### 16.6 Rate limiting

**No existe**. No hay protección contra fuerza bruta en login ni throttling en API endpoints.

### 16.7 Sesión

- **No se llama `session_regenerate_id(true)` tras login** — riesgo de session fixation
- **Cookie de sesión sin flags de seguridad**: no se configuran `cookie_httponly`, `cookie_secure`, `cookie_samesite`
- La cookie es accesible desde JavaScript (falta HttpOnly)

### 16.8 Otros

- **Headers de seguridad**: no se configuran headers como X-Frame-Options, Content-Security-Policy, X-Content-Type-Options, HSTS
- **CORS**: no se configuran headers CORS (las APIs públicas son de solo lectura)
- **Upload de archivos**: limitado a 5MB por archivo (en .htaccess), se valida extensión
- **Contraseña default**: `install.php` contiene `limpiaOeste2026` hardcodeada como password del usuario admin
- **CRITICO — `.env.example` contiene credenciales reales**: la contraseña FTP de producción está expuesta en `.env.example` que está versionado en Git. Cualquiera con acceso al repositorio puede conectarse por FTP al servidor

---

## SECCIÓN 17: CÓDIGO Y CALIDAD

### 17.1 Convenciones de naming

- **Clases**: PascalCase (`QuoteController`, `PricingEngine`)
- **Métodos**: camelCase (`changeStatus`, `persistQuote`)
- **Variables**: camelCase (`$clientId`, `$deliveredQtys`)
- **Tablas DB**: snake_case (`quote_items`, `stock_adjustments`)
- **Columnas DB**: snake_case (`delivery_stock_applied`)
- **Rutas URL**: kebab-case (`/pedidos-proveedor`, `/cuenta-corriente`)
- **Archivos**: PascalCase para clases, kebab-case para vistas

### 17.2 Manejo de errores

- **Excepciones**: try/catch en operaciones de BD críticas (transacciones)
- **Flash messages**: `flash('error', $msg)` + `flash('success', $msg)`
- **Error handler global**: en `public/index.php` con `set_exception_handler()`, `set_error_handler()`, `register_shutdown_function()`
- **Emergency log**: escribe en `storage/logs/php_fatal.log` cuando falla
- **No hay logging estructurado** (solo error_log nativo)

### 17.3 Duplicación de código

Duplicaciones detectadas:
- `recalculateClientBalance()` está implementada tanto en `QuoteController` como en `AccountController` (código idéntico)
- Queries de JOIN producto→categoría→categoría padre se repiten en múltiples métodos (show, edit, downloadPdf, persistQuote)
- Generación de PDF con DomPDF tiene el mismo boilerplate en 4+ controladores
- `fetchSupplierDebtsSameAsAccount()` en DashboardController es copia de `getSupplierDebts()` de AccountController

### 17.4 Funciones/métodos largos

- `QuoteController::persistQuote()`: ~400 líneas — demasiado largo, maneja creación y edición, estado parcial, crédito, CC, stock
- `QuoteController::show()`: ~100 líneas
- `QuoteController::changeStatus()`: ~140 líneas
- `SeiqOrderBuilder::consolidate()`: ~100 líneas
- `DashboardController::index()`: ~200 líneas con muchas queries

### 17.5 Tests automatizados

**No existen tests automatizados** (ni unitarios ni de integración ni end-to-end).

### 17.6 Documentación inline

- **PHPDoc**: presente en clases principales (`PricingEngine`, `QuoteDeliveryStock`, `SeiqOrderBuilder`)
- Tipo annotations en parámetros y retornos de métodos clave
- `declare(strict_types=1)` en todos los archivos PHP
- Comentario diagnóstico útil en `QuoteDeliveryStock` que lista dónde se modifica stock

---

## SECCIÓN 18: UI/UX

### 18.1 Layout general

**Archivo**: `app/Views/layout/main.php`

- Layout con sidebar fijo (220px) + contenido principal
- Sidebar colapsable en mobile (via Alpine.js `sidebarOpen`)
- Header sticky con título de página y barra de búsqueda
- Máximo ancho 1400px, padding responsive

### 18.2 Navegación

Sidebar organizado en secciones:
- **Principal**: Dashboard
- **Catálogo**: Categorías, Productos, Stock actual
- **Comercial**: Listas de precios, Clientes, Presupuestos, Ventas, Ventas ML, Pedidos a Proveedores, Cuenta Corriente
- **Sistema**: Configuración, Sincronización

Indicador visual del ítem activo (fondo azul claro + borde izquierdo azul).

### 18.3 Stack tecnológico UI

- **Tailwind CSS** (vía CDN, no compilado)
- **Alpine.js 3.13.5** para interactividad (modales, dropdowns, búsqueda live)
- **Lucide Icons** para iconografía
- **Chart.js 4.4.1** para gráficos del dashboard
- **Fuente Poppins** (Google Fonts)
- Paleta de colores personalizada (lo-bg, lo-text, lo-muted, lo-border, lo-blue, lo-blueSoft)

### 18.4 Responsividad

- **Sí, es responsive**: sidebar se oculta en mobile, se muestra como overlay
- Grid layouts con Tailwind responsive (lg:, md:, etc.)
- Búsqueda global oculta en mobile (`hidden md:block`)
- Tablas con overflow-x para scroll horizontal en pantallas chicas

### 18.5 Feedback al usuario

- **Flash messages**: toast en la parte superior, auto-dismiss después de 5 segundos
- Colores por tipo: verde (success), rojo (error), azul (info)
- **Modal de confirmación de eliminación**: componente reutilizable (`delete_modal.php`)
- **Modal de pago rápido**: componente para registrar pagos desde cualquier vista
- **Búsqueda global**: live search con debounce de 300ms, muestra productos/clientes/presupuestos

### 18.6 Búsqueda global

Implementada con Alpine.js en el header:
- Fetch a `/api/buscar?q=...` con debounce 300ms
- Muestra resultados agrupados por tipo (Productos, Clientes, Presupuestos)
- Enter navega a página de resultados completa (`/buscar?q=...`)

---

## SECCIÓN 19: BUGS CONOCIDOS O POTENCIALES

### 19.1 Race conditions

- **Operación simultánea del mismo presupuesto**: si dos usuarios cambian el estado al mismo tiempo, no hay locking optimista. Podrían aplicarse dos veces las operaciones de stock.
- **Stock comprometido**: `stock_committed_units` puede desincronizarse si una transacción falla a mitad. El sistema mitiga esto recalculando desde `quote_items` en `SeiqOrderBuilder`, pero `stock_committed_units` persistido puede divergir.
- **Recepción de pedido proveedor simultánea**: el guard `receipt_stock_applied` protege, pero sin locking de fila, dos requests simultáneos podrían pasar el check antes de que uno actualice.

### 19.2 Operaciones sin transacción SQL

- `applySupplierOrderStockDelta()` en `SeiqOrderController` hace UPDATE por producto sin transacción interna (aunque el caller está en transacción)
- Los scripts de auditoría en `public/` (`audit_stock.php`, `fix_stock.php`) acceden a la BD sin las mismas protecciones

### 19.3 Valores hardcodeados

- Prefijo de número de pedido: `'PS'` (SEIQ) y `'PH'` (Higienik) en `SeiqOrderController::nextOrderNumber()` — deberían ser configurables o venir de la tabla suppliers
- Client types en `ClientController::validateClientType()`: `['mayorista', 'minorista', 'barrio_cerrado', 'gastronomico', 'mercadolibre']` — deberían venir de client_segment_config
- Slugs de categoría hardcodeados en `PricingEngine::getPrimaryPriceField()` y `QuoteLinePricing`: aerosoles, bidones, masivo, sobres, alimenticia
- Balance tolerance default 800 está en el código además de en settings

### 19.4 Campos no validados en formularios

- `QuoteController::persistQuote()`: no valida que el product_id exista antes de buscar (lo maneja con `if (!$p) continue`)
- No hay validación de tipo/extensión en algunos uploads
- `discount_percentage` se clampea a [0, 100] pero `discount_amount` no se valida contra un máximo absoluto (solo contra baseDiscountable)

### 19.5 Otras inconsistencias

- **Token CSRF no rota**: el mismo token se usa durante toda la sesión, lo que reduce la protección contra replay attacks
- **Doble carga de .env**: tanto `public/index.php` como `App::run()` cargan `.env`
- **SHOW TABLES LIKE** en cada request: se ejecuta `SHOW TABLES LIKE 'account_transactions'` y similares en múltiples controladores por cada request, en lugar de verificar una vez al inicio
- **`QuoteController` tiene ~1800 líneas**: demasiada lógica concentrada en un solo archivo
- **Rutas duplicadas**: pedido-seiq y pedidos-proveedor apuntan a los mismos controladores/métodos (parece una migración gradual de URLs)
- **Sin validación de unicidad** al crear categorías/productos con el mismo nombre

---

## SECCIÓN 20: OPORTUNIDADES DE MEJORA EVIDENTES

### Rápido (< 1 hora)

1. **Extraer `recalculateClientBalance()`** a un helper compartido — elimina duplicación entre QuoteController y AccountController
2. **Cachear resultado de SHOW TABLES** — evitar queries de schema en cada request
3. **Agregar índices compuestos** a account_transactions y quote_items (ver sección 2.5)
4. **Rotar token CSRF** por request o por formulario
5. **Eliminar rutas duplicadas** pedido-seiq (mantener solo pedidos-proveedor)
6. **Agregar rate limiting básico** al login (ej: bloqueo 5 min después de 5 intentos)

### Medio (1-4 horas)

7. **Refactorizar QuoteController** — extraer lógica de persistQuote() a un QuotePersistenceService o QuoteHelper
8. **Extraer generación PDF** a un PdfService genérico con métodos render(template, data)
9. **Compilar Tailwind** en build en lugar de CDN — reduce tamaño de CSS a ~10KB en producción
10. **Agregar validación de formularios client-side** con Alpine.js (campos obligatorios, formato)
11. **Mover queries repetitivas de JOIN** producto→categoría→padre a un método repository reutilizable
12. **Agregar headers de seguridad** (X-Frame-Options, CSP, X-Content-Type-Options)
13. **Implementar logging estructurado** con niveles (info, warning, error) y archivo rotado
14. **Agregar locking optimista** en cambios de estado de presupuesto (version column o SELECT FOR UPDATE)

### Complejo (> 4 horas)

15. **Implementar tests automatizados** — comenzar por PricingEngine (más testeable, lógica pura) y QuoteDeliveryStock
16. **Implementar modelo ORM liviano** — extraer queries a clases Repository por tabla (QuoteRepository, ProductRepository)
17. **Multi-usuario con roles** — roles admin/operador/vendedor con permisos granulares
18. **API REST formal** — documentar y separar endpoints API con autenticación token
19. **Sistema de notificaciones** — alertas de stock bajo, presupuestos por vencer, pagos pendientes
20. **Historial de cambios (audit trail)** — log de quién hizo qué cambio, cuándo, en qué entidad
21. **Queue/jobs** — para envío de emails y generación de PDFs pesados (no bloquear el request)
22. **Migrar de CDN a build pipeline** — Vite/Webpack para CSS + JS, tree-shaking, minificación
23. **Implementar stock calculado por componente para combos** — mostrar disponibilidad real de combos basada en el mínimo stock de sus componentes
24. **Reconciliación automática de stock** — job periódico que verifique `stock_committed_units` contra la suma real de quote_items committed

---

*Informe generado el 12 de mayo de 2026 mediante análisis exhaustivo del código fuente del proyecto.*
