# Diagnóstico Completo del Sistema Limpia Oeste

- Fecha de generación: 2026-05-05T11:47:19+00:00
- Base de datos relevada: `limpia_oeste_abm`
- Objetivo: referencia técnica-operativa exhaustiva para onboarding y planificación de mejoras.

## 1. Arquitectura y stack técnico

### Stack base

- Backend: PHP >= 8.1 (CLI relevado: 8.3.16), aplicación MVC custom sin framework full-stack.
- Base de datos: MySQL/MariaDB (acceso por PDO).
- Renderizado: vistas PHP server-side en `app/Views`.
- Front: JS vanilla + Alpine (en formularios complejos), assets en `public/assets`.
- Librerías Composer:

| Dependencia | Uso principal |
|---|---|
| `php` | Runtime principal del sistema |
| `dompdf/dompdf (^2.0)` | Generación de PDFs (presupuestos, pedidos, cuenta corriente). |
| `phpoffice/phpspreadsheet (^2.0 || ^3.0 || ^4.0)` | Importación/exportación de Excel y listas. |
| `phpmailer/phpmailer (^7.0)` | Envío de mails transaccionales. |

### Estructura de carpetas

| Carpeta | Rol |
|---|---|
| `app/Controllers` | Casos de uso y orquestación de negocio por módulo. |
| `app/Helpers` | Reglas de negocio reutilizables (pricing, stock, pedidos, utilidades). |
| `app/Models` | Acceso a datos (`Database` wrapper sobre PDO). |
| `app/Core` | Bootstrap y enrutador (`App`, `Router`, `Controller`). |
| `app/Views` | Vistas HTML/PDF de cada módulo. |
| `app/config` | Configuración de app, DB y rutas. |
| `database/migrations` | Evolución incremental del esquema. |
| `public/` | Front controller y assets públicos. |
| `storage/` | Adjuntos, imágenes y artefactos de ejecución. |

### Patrón MVC, rutas y autenticación

- Front controller: `public/index.php` (define `BASE_PATH`, carga `.env`, autoload y `App::run()`).
- Bootstrap: `app/Core/App.php` inicia sesión, timezone y despacha rutas.
- Router: `app/Core/Router.php` mapea `METHOD + patrón` a `Controller@action` con parámetros nombrados (`{id}`, `{slug}`).
- Rutas: `app/config/routes.php` declara rutas de módulos web + APIs internas.
- Auth: `AuthController` valida contra `admin_users.password_hash` (`password_verify`), guarda `$_SESSION[admin_user_id]` y protege toda ruta no pública.
- CSRF: validación en formularios críticos vía helper `verifyCsrf()`.

### Deploy y operación

- Script de deploy: `deploy.php` (FTP), con modos `--dry-run`, `--only=app`, `--only=public`, `--no-vendor`.
- Seguridad de deploy: bloquea por defecto subir a `/public_html` raíz salvo `--force-root`.
- Export/sync DB: scripts `db_export.php`, `db_import.php`, `sync_database.php`.
- Entorno local usa `.env` para DB/MAIL/FTP; en producción se requiere `.env` específico.

## 2. Base de datos — esquema completo

### Inventario de tablas existentes

Tablas detectadas (19): `account_transactions`, `admin_users`, `categories`, `clients`, `combo_products`, `combos`, `mail_log`, `price_list_items`, `price_lists`, `product_images`, `products`, `quote_attachments`, `quote_items`, `quotes`, `seiq_order_items`, `seiq_orders`, `settings`, `stock_adjustments`, `suppliers`.

### Tabla `account_transactions`

- Propósito: Movimientos de cuenta corriente de clientes/proveedores.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `account_type` | `enum('client','supplier')` | NO | NULL | - | `MUL` |
| `account_id` | `int` | NO | NULL | - | - |
| `transaction_type` | `enum('invoice','payment','adjustment')` | NO | NULL | - | `MUL` |
| `reference_type` | `varchar(50)` | YES | NULL | - | `MUL` |
| `reference_id` | `int` | YES | NULL | - | - |
| `amount` | `decimal(12,2)` | NO | NULL | - | - |
| `payment_method` | `enum('efectivo','transferencia','otro')` | YES | NULL | - | - |
| `payment_reference` | `varchar(255)` | YES | NULL | - | - |
| `description` | `varchar(255)` | NO | NULL | - | - |
| `notes` | `text` | YES | NULL | - | - |
| `transaction_date` | `date` | NO | NULL | - | `MUL` |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |

**Índices**

- `idx_account` (NON UNIQUE): `account_type`, `account_id`
- `idx_date` (NON UNIQUE): `transaction_date`
- `idx_reference` (NON UNIQUE): `reference_type`, `reference_id`
- `idx_type` (NON UNIQUE): `transaction_type`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- Sin claves foráneas.

### Tabla `admin_users`

- Propósito: Usuarios administradores del sistema (autenticación).

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `username` | `varchar(50)` | NO | NULL | - | `UNI` |
| `password_hash` | `varchar(255)` | NO | NULL | - | - |
| `last_login` | `timestamp` | YES | NULL | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |

**Índices**

- `PRIMARY` (UNIQUE): `id`
- `username` (UNIQUE): `username`

**Foreign keys**

- Sin claves foráneas.

### Tabla `categories`

- Propósito: Jerarquía de categorías y defaults (descuento/markup/proveedor).

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `parent_id` | `int` | YES | NULL | - | `MUL` |
| `supplier_id` | `int` | YES | NULL | - | `MUL` |
| `name` | `varchar(100)` | NO | NULL | - | - |
| `slug` | `varchar(100)` | NO | NULL | - | `UNI` |
| `description` | `text` | YES | NULL | - | - |
| `default_discount` | `decimal(5,2)` | NO | `0.00` | - | - |
| `default_markup` | `decimal(5,2)` | YES | NULL | - | - |
| `markup_override` | `decimal(5,2)` | YES | NULL | - | - |
| `presentation_info` | `varchar(255)` | YES | NULL | - | - |
| `sort_order` | `int` | YES | `0` | - | - |
| `is_active` | `tinyint(1)` | YES | `1` | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `fk_supplier_category` (NON UNIQUE): `supplier_id`
- `idx_parent` (NON UNIQUE): `parent_id`
- `PRIMARY` (UNIQUE): `id`
- `slug` (UNIQUE): `slug`

**Foreign keys**

- `fk_categories_parent`: `parent_id` -> `categories.id` (ON UPDATE NO ACTION, ON DELETE SET NULL)
- `fk_supplier_category`: `supplier_id` -> `suppliers.id` (ON UPDATE NO ACTION, ON DELETE SET NULL)

### Tabla `clients`

- Propósito: Maestro de clientes y datos comerciales.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `name` | `varchar(255)` | NO | NULL | - | - |
| `business_name` | `varchar(255)` | YES | NULL | - | - |
| `contact_person` | `varchar(255)` | YES | NULL | - | - |
| `phone` | `varchar(50)` | YES | NULL | - | - |
| `email` | `varchar(255)` | YES | NULL | - | - |
| `address` | `text` | YES | NULL | - | - |
| `city` | `varchar(100)` | YES | NULL | - | - |
| `notes` | `text` | YES | NULL | - | - |
| `balance` | `decimal(12,2)` | YES | `0.00` | - | - |
| `is_active` | `tinyint(1)` | YES | `1` | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- Sin claves foráneas.

### Tabla `combo_products`

- Propósito: Composición interna de cada combo (producto + cantidad).

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `combo_id` | `int` | NO | NULL | - | `MUL` |
| `product_id` | `int` | NO | NULL | - | `MUL` |
| `quantity` | `int` | NO | `1` | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |

**Índices**

- `idx_combo_products_combo` (NON UNIQUE): `combo_id`
- `idx_combo_products_product` (NON UNIQUE): `product_id`
- `PRIMARY` (UNIQUE): `id`
- `uq_combo_product` (UNIQUE): `combo_id`, `product_id`

**Foreign keys**

- `fk_combo_products_combo`: `combo_id` -> `combos.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)
- `fk_combo_products_product`: `product_id` -> `products.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)

### Tabla `combos`

- Propósito: Definición de combos comercializables.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `name` | `varchar(255)` | NO | NULL | - | - |
| `description` | `text` | YES | NULL | - | - |
| `markup_percentage` | `decimal(5,2)` | NO | `90.00` | - | - |
| `subtotal_override` | `decimal(12,2)` | YES | NULL | - | - |
| `discount_percentage` | `decimal(5,2)` | NO | `0.00` | - | - |
| `is_active` | `tinyint(1)` | NO | `1` | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- Sin claves foráneas.

### Tabla `mail_log`

- Propósito: Registro de envíos de email del sistema.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `quote_id` | `int` | NO | NULL | - | `MUL` |
| `attachment_id` | `int` | YES | NULL | - | - |
| `to_email` | `varchar(255)` | NO | NULL | - | - |
| `to_name` | `varchar(255)` | YES | NULL | - | - |
| `subject` | `varchar(255)` | NO | NULL | - | - |
| `status` | `enum('sent','failed')` | NO | NULL | - | - |
| `error_message` | `text` | YES | NULL | - | - |
| `sent_at` | `datetime` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |

**Índices**

- `idx_quote` (NON UNIQUE): `quote_id`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- `mail_log_ibfk_1`: `quote_id` -> `quotes.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)

### Tabla `price_list_items`

- Propósito: Snapshot de precios por producto de cada lista.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `price_list_id` | `int` | NO | NULL | - | `MUL` |
| `product_id` | `int` | NO | NULL | - | `MUL` |
| `precio_base_usado` | `decimal(12,2)` | NO | NULL | - | - |
| `costo_limpia_oeste` | `decimal(12,2)` | NO | NULL | - | - |
| `precio_venta` | `decimal(12,2)` | NO | NULL | - | - |
| `precio_venta_iva` | `decimal(12,2)` | YES | NULL | - | - |
| `markup_applied` | `decimal(5,2)` | NO | NULL | - | - |
| `discount_applied` | `decimal(5,2)` | NO | NULL | - | - |
| `price_field_used` | `varchar(50)` | YES | NULL | - | - |

**Índices**

- `idx_pricelist` (NON UNIQUE): `price_list_id`
- `PRIMARY` (UNIQUE): `id`
- `product_id` (NON UNIQUE): `product_id`

**Foreign keys**

- `price_list_items_ibfk_1`: `price_list_id` -> `price_lists.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)
- `price_list_items_ibfk_2`: `product_id` -> `products.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)

### Tabla `price_lists`

- Propósito: Versiones históricas de listas de precios generadas.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `name` | `varchar(255)` | NO | NULL | - | - |
| `description` | `text` | YES | NULL | - | - |
| `custom_markup` | `decimal(5,2)` | YES | NULL | - | - |
| `include_iva` | `tinyint(1)` | YES | `0` | - | - |
| `category_filter` | `text` | YES | NULL | - | - |
| `status` | `enum('draft','active','archived')` | YES | `draft` | - | - |
| `generated_at` | `timestamp` | YES | NULL | - | - |
| `pdf_path` | `varchar(255)` | YES | NULL | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- Sin claves foráneas.

### Tabla `product_images`

- Propósito: Imágenes de productos para catálogo y vistas.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int unsigned` | NO | NULL | `auto_increment` | `PRI` |
| `product_id` | `int` | NO | NULL | - | `MUL` |
| `filename` | `varchar(255)` | NO | NULL | - | - |
| `original_name` | `varchar(255)` | NO | NULL | - | - |
| `mime_type` | `varchar(50)` | NO | NULL | - | - |
| `file_size` | `int unsigned` | NO | NULL | - | - |
| `sort_order` | `tinyint unsigned` | NO | `0` | - | - |
| `is_cover` | `tinyint(1)` | NO | `0` | - | - |
| `alt_text` | `varchar(255)` | YES | NULL | - | - |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |

**Índices**

- `idx_product_cover` (NON UNIQUE): `product_id`, `is_cover`
- `idx_product_sort` (NON UNIQUE): `product_id`, `sort_order`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- `product_images_ibfk_1`: `product_id` -> `products.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)

### Tabla `products`

- Propósito: Maestro de productos con costos, precios de lista, stock y reglas de pricing.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `category_id` | `int` | NO | NULL | - | `MUL` |
| `code` | `varchar(50)` | NO | NULL | - | `MUL` |
| `name` | `varchar(255)` | NO | NULL | - | - |
| `slug` | `varchar(255)` | YES | NULL | - | - |
| `short_name` | `varchar(100)` | YES | NULL | - | - |
| `description` | `text` | YES | NULL | - | - |
| `short_description` | `varchar(255)` | YES | NULL | - | - |
| `full_description` | `text` | YES | NULL | - | - |
| `content` | `varchar(100)` | YES | NULL | - | - |
| `presentation` | `varchar(100)` | YES | NULL | - | - |
| `presentacion_minorista` | `varchar(50)` | YES | NULL | - | - |
| `content_volume` | `varchar(50)` | YES | NULL | - | - |
| `units_per_box` | `int` | YES | `1` | - | - |
| `stock_units` | `int` | NO | `0` | - | - |
| `stock_committed_units` | `int` | NO | `0` | - | - |
| `unit_volume` | `varchar(50)` | YES | NULL | - | - |
| `equivalence` | `varchar(100)` | YES | NULL | - | - |
| `ean13` | `varchar(13)` | YES | NULL | - | - |
| `sale_unit_type` | `enum('caja','unidad')` | NO | `caja` | - | - |
| `sale_unit_label` | `varchar(50)` | NO | `Caja` | - | - |
| `sale_unit_description` | `varchar(150)` | YES | NULL | - | - |
| `precio_lista_unitario` | `decimal(12,2)` | YES | NULL | - | - |
| `precio_lista_caja` | `decimal(12,2)` | YES | NULL | - | - |
| `precio_lista_bidon` | `decimal(12,2)` | YES | NULL | - | - |
| `precio_lista_litro` | `decimal(12,2)` | YES | NULL | - | - |
| `precio_lista_bulto` | `decimal(12,2)` | YES | NULL | - | - |
| `precio_lista_sobre` | `decimal(12,2)` | YES | NULL | - | - |
| `discount_override` | `decimal(5,2)` | YES | NULL | - | - |
| `markup_override` | `decimal(5,2)` | YES | NULL | - | - |
| `dilution` | `varchar(100)` | YES | NULL | - | - |
| `usage_cost` | `decimal(12,2)` | YES | NULL | - | - |
| `pallet_info` | `varchar(50)` | YES | NULL | - | - |
| `is_active` | `tinyint(1)` | YES | `1` | - | `MUL` |
| `is_featured` | `tinyint(1)` | YES | `0` | - | - |
| `is_published` | `tinyint(1)` | NO | `0` | - | - |
| `sort_order` | `int` | YES | `0` | - | - |
| `notes` | `text` | YES | NULL | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `idx_active` (NON UNIQUE): `is_active`
- `idx_category` (NON UNIQUE): `category_id`
- `idx_code` (NON UNIQUE): `code`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- `products_ibfk_1`: `category_id` -> `categories.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)

### Tabla `quote_attachments`

- Propósito: Adjuntos de presupuestos (facturas, comprobantes, etc.).

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `quote_id` | `int` | NO | NULL | - | `MUL` |
| `type` | `enum('remito','factura')` | NO | NULL | - | - |
| `original_filename` | `varchar(255)` | NO | NULL | - | - |
| `stored_filename` | `varchar(255)` | NO | NULL | - | - |
| `mime_type` | `varchar(100)` | NO | NULL | - | - |
| `file_size` | `int` | NO | NULL | - | - |
| `notes` | `text` | YES | NULL | - | - |
| `created_at` | `datetime` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |

**Índices**

- `idx_quote_type` (NON UNIQUE): `quote_id`, `type`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- `quote_attachments_ibfk_1`: `quote_id` -> `quotes.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)

### Tabla `quote_items`

- Propósito: Líneas de productos/combos por presupuesto, cantidades y precios.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `quote_id` | `int` | NO | NULL | - | `MUL` |
| `product_id` | `int` | YES | NULL | - | `MUL` |
| `combo_id` | `int` | YES | NULL | - | `MUL` |
| `quantity` | `int` | NO | `1` | - | - |
| `qty_delivered` | `decimal(10,2)` | NO | `0.00` | - | - |
| `unit_type` | `varchar(50)` | YES | NULL | - | - |
| `unit_label` | `varchar(50)` | YES | NULL | - | - |
| `unit_description` | `varchar(150)` | YES | NULL | - | - |
| `unit_price` | `decimal(12,2)` | NO | NULL | - | - |
| `individual_unit_price` | `decimal(12,2)` | YES | NULL | - | - |
| `subtotal` | `decimal(12,2)` | NO | NULL | - | - |
| `price_field_used` | `varchar(50)` | YES | NULL | - | - |
| `discount_applied` | `decimal(5,2)` | YES | NULL | - | - |
| `markup_applied` | `decimal(5,2)` | YES | NULL | - | - |
| `cost_unit_snapshot` | `decimal(12,2)` | YES | NULL | - | - |
| `cost_subtotal_snapshot` | `decimal(12,2)` | YES | NULL | - | - |
| `notes` | `varchar(255)` | YES | NULL | - | - |
| `sort_order` | `int` | YES | `0` | - | - |

**Índices**

- `idx_quote` (NON UNIQUE): `quote_id`
- `idx_quote_items_combo` (NON UNIQUE): `combo_id`
- `PRIMARY` (UNIQUE): `id`
- `product_id` (NON UNIQUE): `product_id`

**Foreign keys**

- `fk_quote_items_combo`: `combo_id` -> `combos.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)
- `quote_items_ibfk_1`: `quote_id` -> `quotes.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)
- `quote_items_ibfk_2`: `product_id` -> `products.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)

### Tabla `quotes`

- Propósito: Cabecera de presupuestos y su ciclo de vida comercial.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `quote_number` | `varchar(20)` | NO | NULL | - | `UNI` |
| `sale_number` | `varchar(20)` | YES | NULL | - | `UNI` |
| `client_id` | `int` | YES | NULL | - | `MUL` |
| `title` | `varchar(255)` | YES | NULL | - | - |
| `notes` | `text` | YES | NULL | - | - |
| `validity_days` | `int` | YES | `7` | - | - |
| `custom_markup` | `decimal(5,2)` | YES | NULL | - | - |
| `include_iva` | `tinyint(1)` | YES | `0` | - | - |
| `is_mercadolibre` | `tinyint(1)` | NO | `0` | - | - |
| `ml_net_amount` | `decimal(12,2)` | YES | NULL | - | - |
| `ml_sale_total` | `decimal(12,2)` | YES | NULL | - | - |
| `subtotal` | `decimal(12,2)` | YES | `0.00` | - | - |
| `discount_percentage` | `decimal(5,2)` | YES | NULL | - | - |
| `discount_amount` | `decimal(12,2)` | YES | NULL | - | - |
| `iva_amount` | `decimal(12,2)` | YES | `0.00` | - | - |
| `total` | `decimal(12,2)` | YES | `0.00` | - | - |
| `status` | `enum('draft','sent','accepted','rejected','expired','delivered','partially_delivered')` | YES | `draft` | - | `MUL` |
| `sent_at` | `timestamp` | YES | NULL | - | - |
| `pdf_path` | `varchar(255)` | YES | NULL | - | - |
| `delivery_stock_applied` | `tinyint(1)` | NO | `0` | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `client_id` (NON UNIQUE): `client_id`
- `idx_number` (NON UNIQUE): `quote_number`
- `idx_sale_number` (UNIQUE): `sale_number`
- `idx_status` (NON UNIQUE): `status`
- `PRIMARY` (UNIQUE): `id`
- `quote_number` (UNIQUE): `quote_number`

**Foreign keys**

- `quotes_ibfk_1`: `client_id` -> `clients.id` (ON UPDATE NO ACTION, ON DELETE SET NULL)

### Tabla `seiq_order_items`

- Propósito: Detalle por producto de cada pedido a proveedor.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `seiq_order_id` | `int` | NO | NULL | - | `MUL` |
| `product_id` | `int` | NO | NULL | - | `MUL` |
| `qty_units_sold` | `int` | NO | `0` | - | - |
| `qty_boxes_sold` | `int` | NO | `0` | - | - |
| `total_units_needed` | `int` | NO | `0` | - | - |
| `units_per_box` | `int` | NO | `1` | - | - |
| `boxes_to_order` | `int` | NO | `0` | - | - |
| `units_remainder` | `int` | NO | `0` | - | - |
| `sort_order` | `int` | YES | `0` | - | - |

**Índices**

- `idx_order` (NON UNIQUE): `seiq_order_id`
- `PRIMARY` (UNIQUE): `id`
- `product_id` (NON UNIQUE): `product_id`

**Foreign keys**

- `seiq_order_items_ibfk_1`: `seiq_order_id` -> `seiq_orders.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)
- `seiq_order_items_ibfk_2`: `product_id` -> `products.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)

### Tabla `seiq_orders`

- Propósito: Pedidos a proveedor (SEIQ y otros) con estado logístico.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `supplier_id` | `int` | YES | NULL | - | `MUL` |
| `order_number` | `varchar(20)` | NO | NULL | - | `UNI` |
| `notes` | `text` | YES | NULL | - | - |
| `included_quotes` | `text` | YES | NULL | - | - |
| `total_products` | `int` | YES | `0` | - | - |
| `total_boxes` | `int` | YES | `0` | - | - |
| `status` | `enum('draft','sent','received')` | YES | `draft` | - | - |
| `sent_at` | `timestamp` | YES | NULL | - | - |
| `received_at` | `timestamp` | YES | NULL | - | - |
| `receipt_stock_applied` | `tinyint(1)` | NO | `0` | - | - |
| `pdf_path` | `varchar(255)` | YES | NULL | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `fk_order_supplier` (NON UNIQUE): `supplier_id`
- `order_number` (UNIQUE): `order_number`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- `fk_order_supplier`: `supplier_id` -> `suppliers.id` (ON UPDATE NO ACTION, ON DELETE NO ACTION)

### Tabla `settings`

- Propósito: Parámetros globales de negocio y UI (IVA, markup global, etc.).

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `setting_key` | `varchar(100)` | NO | NULL | - | `UNI` |
| `setting_value` | `text` | NO | NULL | - | - |
| `description` | `varchar(255)` | YES | NULL | - | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `PRIMARY` (UNIQUE): `id`
- `setting_key` (UNIQUE): `setting_key`

**Foreign keys**

- Sin claves foráneas.

### Tabla `stock_adjustments`

- Propósito: Auditoría de ajustes manuales de stock.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `product_id` | `int` | NO | NULL | - | `MUL` |
| `previous_stock` | `int` | NO | NULL | - | - |
| `new_stock` | `int` | NO | NULL | - | - |
| `difference` | `int` | NO | NULL | - | - |
| `notes` | `varchar(255)` | YES | NULL | - | - |
| `created_by` | `varchar(100)` | YES | NULL | - | - |
| `created_at` | `datetime` | NO | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | `MUL` |

**Índices**

- `idx_stock_adjustments_created` (NON UNIQUE): `created_at`
- `idx_stock_adjustments_product` (NON UNIQUE): `product_id`
- `PRIMARY` (UNIQUE): `id`

**Foreign keys**

- `stock_adjustments_ibfk_1`: `product_id` -> `products.id` (ON UPDATE NO ACTION, ON DELETE CASCADE)

### Tabla `suppliers`

- Propósito: Proveedores para abastecimiento y cuenta corriente.

| Columna | Tipo | Null | Default | Extra | Key |
|---|---|---|---|---|---|
| `id` | `int` | NO | NULL | `auto_increment` | `PRI` |
| `name` | `varchar(100)` | NO | NULL | - | - |
| `slug` | `varchar(50)` | NO | NULL | - | `UNI` |
| `contact_name` | `varchar(255)` | YES | NULL | - | - |
| `phone` | `varchar(50)` | YES | NULL | - | - |
| `email` | `varchar(255)` | YES | NULL | - | - |
| `address` | `text` | YES | NULL | - | - |
| `notes` | `text` | YES | NULL | - | - |
| `cliente_id` | `varchar(50)` | YES | NULL | - | - |
| `cliente_nombre` | `varchar(255)` | YES | NULL | - | - |
| `condicion_pago` | `varchar(100)` | YES | NULL | - | - |
| `observaciones` | `varchar(255)` | YES | NULL | - | - |
| `is_active` | `tinyint(1)` | YES | `1` | - | - |
| `created_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED` | - |
| `updated_at` | `timestamp` | YES | `CURRENT_TIMESTAMP` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | - |

**Índices**

- `PRIMARY` (UNIQUE): `id`
- `slug` (UNIQUE): `slug`

**Foreign keys**

- Sin claves foráneas.

## 3. Módulos del sistema — funcionalidad detallada

### Dashboard
- Consolida KPIs comerciales/financieros/stock con filtros de período.
- Cruza presupuestos, pedidos proveedor y cuenta corriente para vista operativa diaria.

### Productos y categorías
- ABM completo de categorías (con jerarquía padre/hijo) y productos.
- Productos almacenan múltiple precio de lista por unidad comercial (caja, unitario, bidón, litro, sobre, etc.).
- Gestión de imágenes y carga masiva por Excel.

### Combos
- Combos se venden como ítem comercial único pero explotan a productos reales para stock y demanda.
- Cada componente en `combo_products` define cuántas unidades del producto consume por combo.

### Listas de precios
- Generación/preview de listas desde pricing vigente.
- Persistencia histórica en `price_lists` + `price_list_items`.

### Clientes
- ABM de clientes y datos de contacto/ubicación.
- Integración con presupuestos y cuenta corriente.

### Presupuestos
- Estados operativos: `draft`, `sent`, `accepted`, `partially_delivered`, `delivered`, `rejected`, `expired`.
- Transición a `accepted`: compromete stock (`stock_committed_units`).
- Transición a `delivered`: libera comprometido y descuenta físico (`stock_units`).
- Entrega parcial: incrementa `qty_delivered` por línea, libera compromiso sólo de lo entregado y descuenta físico parcial.
- Al completar todas las líneas, pasa automáticamente a `delivered`.
- Registro de deuda en cuenta corriente al aceptar/entregar (tipo `invoice`).

### Pedidos SEIQ / Proveedor
- Se generan desde demanda pendiente de presupuestos `accepted` y `partially_delivered`.
- Excluyen presupuestos ya incluidos en pedidos previos (`included_quotes`).
- Estados: `draft` -> `sent` -> `received`.
- En `received` se incrementa stock físico por `boxes_to_order * units_per_box`.

### Cuenta corriente (clientes y proveedores)
- Ledger unificado en `account_transactions` con `transaction_type`: `invoice`, `payment`, `adjustment`.
- Permite cobros, pagos a proveedor, ajustes y emisión de extractos PDF.

### Stock
- Modelo en unidades base: físico (`stock_units`), comprometido (`stock_committed_units`) y disponible derivado.
- Ajustes manuales auditables en `stock_adjustments`.

### Configuración
- Parámetros globales en `settings` (ej. IVA, markup global, toggles).

### Sincronización
- Módulo `sincronizacion` para export/import SQL local y sincronización entre entornos.

## 4. Motor de precios (`PricingEngine`)

### Jerarquía de descuentos
1. `products.discount_override`
2. `categories.default_discount` (categoría directa)
3. `parent_discount` (categoría padre)
4. fallback `0%`

### Jerarquía de markups
1. Override explícito de operación (`overrideMarkup` en runtime).
2. `products.markup_override`.
3. `categories.markup_override`.
4. `parent_markup_override`.
5. `categories.default_markup`.
6. `parent_default_markup`.
7. `settings.default_markup` (fallback 60%).

### Fórmulas
```text
costo = precio_lista_seiq * (1 - descuento/100)
precio_venta = costo * (1 + markup/100)
precio_con_iva = precio_venta * (1 + iva_rate/100)
margen_pesos = precio_venta - costo
```

### Casos especiales por categoría (selección de campo primario)

| Categoría slug | Campo principal | Campos habilitados |
|---|---|---|
| `aerosoles` | `precio_lista_unitario` | unitario, caja |
| `sobres` | `precio_lista_sobre` | sobre, caja |
| `bidones` | `precio_lista_bidon` | bidón, caja, litro |
| `masivo` | `precio_lista_unitario` | unitario, caja |
| `alimenticia` | `precio_lista_caja` | caja, bidón |
| `hig-toallas-intercaladas`, `fac-toallas-intercaladas` | `precio_lista_unitario` | regla específica por unidad |
| Otras | `precio_lista_caja` | set completo de campos de lista |

## 5. Lógica de stock

- `stock_units`: stock físico real en depósito (unidades sueltas).
- `stock_committed_units`: unidades reservadas por presupuestos vigentes.
- `stock_available_units` (derivado): `stock_units - stock_committed_units` (o stock efectivo si se suma tránsito en contexto de pedido).

### Flujo completo presupuesto -> entrega
1. `draft/sent`: no compromete stock.
2. `accepted`: incrementa `stock_committed_units` (directo o por explosión de combos).
3. `partially_delivered`: por cada entrega parcial, libera compromiso parcial y baja físico parcial.
4. `delivered`: libera remanente comprometido y descuenta físico pendiente.
5. Reversiones de estado: reponen/liberan según corresponda (incluye rollback de entregas).

### Combos y stock
- Cada combo se explota a productos componentes (`combo_products`) para comprometer/liberar/descontar stock.
- No existe stock separado de combo; siempre impacta stock de productos base.

## 6. Módulo Pedido SEIQ — lógica de cálculo

- Fuente de demanda: presupuestos `accepted` y `partially_delivered` no incluidos antes.
- Compromiso real en tiempo real: se recalcula desde `quote_items` y `qty_delivered` (no depende ciegamente de `stock_committed_units`).
- Stock considerado: `stock_units` + unidades en tránsito de pedidos `sent`.
- Faltante real: `max(0, committed_units - effective_stock)`.
- Conversión a cajas: `boxes_to_order = ceil(units_shortage / units_per_box)`.
- Exclusión de duplicados: presupuestos ya presentes en `seiq_orders.included_quotes` no vuelven a incluirse.

## 7. Catálogo de productos actual

Total productos: 252.

| ID | Código | Nombre | Categoría | Presentación | Stock (u) | Units/Box |
|---:|---|---|---|---|---:|---:|
| 16 | ECOAAI01 | ECOMAX ABRILLANTADOR DE ACERO INOXIDABLE | Aerosoles ECOMAX | PACK X 12u | 8 | 12 |
| 22 | ECOALM07 | ECOMAX ACEITE LUBRICANTE MULTIUSO | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 29 | ECOAE14 | ECOMAX ANTIESCARCHA EXPRESS | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 24 | ECOA09 | ECOMAX APRESTO | Aerosoles ECOMAX | PACK X 12u | 1 | 12 |
| 27 | ECOAAFL12 | ECOMAX AROMATIZANTE DE AMBIENTES FLORAL | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 25 | ECOAAFR10 | ECOMAX AROMATIZANTE DE AMBIENTES FRUTAL | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 28 | ECOAALA13 | ECOMAX AROMATIZANTE DE AMBIENTES LAVANDA | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 26 | ECOAALI11 | ECOMAX AROMATIZANTE DE AMBIENTES LIMON | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 23 | ECODAS08 | ECOMAX DESINFECTANTE DE AMBIENTES Y SUPERFICIES | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 18 | ECOELM03 | ECOMAX ESPUMA LIMPIADORA MULTIUSO | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 20 | ECOLH05 | ECOMAX LIMPIA HORNOS | Aerosoles ECOMAX | PACK X 12u | 8 | 12 |
| 21 | ECOLTA06 | ECOMAX LIMPIA TAPIZADOS Y ALFOMBRAS | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 19 | ECOLM04 | ECOMAX LUSTRA MUEBLES | Aerosoles ECOMAX | PACK X 12u | 2 | 12 |
| 17 | ECOSP02 | ECOMAX SECUESTRANTE DE POLVO | Aerosoles ECOMAX | PACK X 12u | 0 | 12 |
| 242 | BB20300 | Bobina Industrial SH 300m x 20cm Beige | Bobinas Industriales Beige | Pack x2 | 0 | 2 |
| 243 | BB25300 | Bobina Industrial SH 300m x 25cm Beige | Bobinas Industriales Beige | Pack x2 | 0 | 2 |
| 264 | BBF20400 | Bobina Beige Factory 20cm SH | Bobinas Industriales Factory | Pack x2 | 0 | 2 |
| 265 | BBF25400 | Bobina Beige Factory 25cm SH | Bobinas Industriales Factory | Pack x2 | 0 | 2 |
| 262 | BF20400 | Bobina Premium Factory 20cm DH | Bobinas Industriales Factory | Pack x2 | 0 | 2 |
| 263 | BF25400 | Bobina Premium Factory 25cm DH | Bobinas Industriales Factory | Pack x2 | 0 | 2 |
| 238 | B20300 | Bobina Industrial DH 300m x 20cm | Bobinas Industriales Premium | Pack x2 | 0 | 2 |
| 239 | B25300 | Bobina Industrial DH 300m x 25cm | Bobinas Industriales Premium | Pack x2 | 0 | 2 |
| 240 | B20400 | Bobina Industrial DH 400m x 20cm | Bobinas Industriales Premium | Pack x2 | 0 | 2 |
| 241 | B25400 | Bobina Industrial DH 400m x 25cm | Bobinas Industriales Premium | Pack x2 | 0 | 2 |
| 125 | 240011 | ALLWAX ACRILICA (natural) | Ceras |  | 0 | 4 |
| 127 | 240021 | ALLWAX ACRILICA (negro) | Ceras |  | 0 | 4 |
| 126 | 240031 | ALLWAX ACRILICA (rojo) | Ceras |  | 0 | 4 |
| 122 | 14001B | ALLWAX Natural (cera p/pisos de mosaicos) | Ceras |  | 0 | 4 |
| 124 | 14002A | ALLWAX Negro (cera p/pisos de mosaicos) | Ceras |  | 0 | 4 |
| 123 | 14003 | ALLWAX Rojo (cera p/pisos de mosaicos) | Ceras |  | 0 | 4 |
| 118 | 861200 | LEICHT (autobrillo y autolimpiante) | Ceras |  | 0 | 4 |
| 129 | 262215 | LIMPIADOR DE PISOS PLASTIFICADOS | Ceras |  | 0 | 4 |
| 128 | 861250 | LUSTRAMUEBLES (lustrador y protect. c/ siliconas) | Ceras |  | 0 | 4 |
| 119 | 861204 | SHIVER Natural (cera autobrillante Extra) | Ceras |  | 0 | 4 |
| 121 | 861206 | SHIVER Negro (cera autobrillante Extra) | Ceras |  | 0 | 4 |
| 120 | 861205 | SHIVER Rojo (cera autobrillante Extra) | Ceras |  | 0 | 4 |
| 149 | 262120 | BRILLO FINAL | Cosmética del Automotor |  | 0 | 4 |
| 146 | 861620 | CH 140 (shampoo para el automotor) | Cosmética del Automotor |  | 0 | 4 |
| 154 | 262175 | DESENG. DE CARROCERIAS P/ CAMIONES | Cosmética del Automotor |  | 0 | 4 |
| 151 | 26159 | EASY REMOVE (desengrasa sin frotar) | Cosmética del Automotor |  | 0 | 4 |
| 152 | 452710 | ECOWASH (lavado de autos sin agua) | Cosmética del Automotor |  | 0 | 4 |
| 147 | 1609.40 | ESPUMA ACTIVA | Cosmética del Automotor |  | 0 | 4 |
| 153 | 162910 | LAVA CARROCERÍA CON TERPENO DE NARANJA | Cosmética del Automotor |  | 0 | 4 |
| 148 | 262100 | LAVA MOTOR | Cosmética del Automotor |  | 0 | 4 |
| 150 | 262140 | SILICONA GEL | Cosmética del Automotor |  | 0 | 4 |
| 267 | 262140b | SILICONA PARA RUEDAS | Cosmética del Automotor |  | 0 | 4 |
| 73 | 262200 | ABRILL. DE SUP. DE ACERO INOXIDABLE | Cuidado de la Cocina |  | 0 | 4 |
| 78 | 250065 | ABRILLANTADOR P/ MAQ. LAVAVAJILLA | Cuidado de la Cocina |  | 0 | 4 |
| 64 | 861009 | BIODET (deterg. desinfectante hidro alcoholico) | Cuidado de la Cocina |  | 0 | 4 |
| 67 | 398120 | CLEAN OUTLET LAVAVAJILLAS | Cuidado de la Cocina |  | 0 | 4 |
| 68 | 861018 | CP 130 (detergente al 15%) | Cuidado de la Cocina |  | 0 | 4 |
| 77 | 250060 | DETERGENTE P/ MAQUINA LAVAVAJILLA | Cuidado de la Cocina |  | 0 | 4 |
| 61 | 861017 | DX 110 (detergente concentrado) | Cuidado de la Cocina |  | 0 | 4 |
| 62 | 2026F | DX 111 (detergente concent. con glicerina) | Cuidado de la Cocina |  | 0 | 4 |
| 69 | 861020 | ECOL 100 (detergente ecológico multiuso) | Cuidado de la Cocina |  | 0 | 4 |
| 74 | 260065 | FAJINADO DE COPAS (desinf. y abrillantador) | Cuidado de la Cocina |  | 0 | 4 |
| 70 | 861024 | LA 111 (desengrasante líquido concentrado) | Cuidado de la Cocina |  | 0 | 4 |
| 76 | 262210 | LIMP. Y ABRILL. DE ELEMENT. DE PLATA | Cuidado de la Cocina |  | 0 | 4 |
| 71 | 261011 | LIMPIA HORNOS en GEL | Cuidado de la Cocina |  | 0 | 4 |
| 75 | 262205 | RECUPERADOR DE VAJILLA | Cuidado de la Cocina |  | 0 | 4 |
| 72 | 28014 | SAHNING (Limpiador cremoso) | Cuidado de la Cocina |  | 2 | 4 |
| 63 | 861008 B | STRONG (desengrasante p/ hornos y parrillas) | Cuidado de la Cocina |  | 0 | 4 |
| 65 | 861007 A | TIPSY F 21 (limpiador m/ uso y removedor) | Cuidado de la Cocina |  | 0 | 4 |
| 66 | 220060 | TIPSY F22 (limpiador m/ uso con oxígeno) | Cuidado de la Cocina |  | 0 | 4 |
| 42 | 861008 A | EWER 2000 (shampoo de tocador p/manos) | Cuidado de Manos |  | 0 | 4 |
| 49 | 260000 | GEL ALCOHOLICO (para manos) | Cuidado de Manos |  | 0 | 4 |
| 47 | 260001 | LAVA MANO CON TERPENO DE NARANJA | Cuidado de Manos |  | 0 | 4 |
| 48 | 100000 | LAVA MANOS BACTERICIDA | Cuidado de Manos |  | 0 | 4 |
| 44 | 861023 | SW 111 (shampoo de tocador) Celeste | Cuidado de Manos |  | 0 | 4 |
| 46 | 291920 | SW 111 (shampoo de tocador) Fragancia Coco | Cuidado de Manos |  | 0 | 4 |
| 45 | 861023 A | SW 111 (shampoo de tocador) Rosa | Cuidado de Manos |  | 0 | 4 |
| 43 | 861022 | SW 112 (shampoo de alto poder desengrasante) | Cuidado de Manos |  | 0 | 4 |
| 116 | 861901 | Zieguel H 20 (curador siliconado p/ pisos natural) | Curadores Hidrofugos |  | 0 | 4 |
| 115 | 861900 | Zieguel H 20 (curador siliconado p/ pisos negro) | Curadores Hidrofugos |  | 0 | 4 |
| 117 | 861902 | Zieguel H 20 (curador siliconado p/ pisos rojo) | Curadores Hidrofugos |  | 0 | 4 |
| 157 | 861650 | AT 700 (insecticida poderoso, rastreros y volad.) | Insecticidas |  | 0 | 4 |
| 158 | 861652 | CD 720 (insecticida residual, rastreros y vol.) | Insecticidas |  | 0 | 4 |
| 159 | 7002 | AT 711 (insecticida concentrado) | Insecticidas Concentrados | POR UNIDAD | 0 | 1 |
| 160 | 7004 | CD 722 (insecticida concentrado) | Insecticidas Concentrados | POR UNIDAD | 0 | 1 |
| 95 | 250050 | APRESTO | Lavandería |  | 0 | 4 |
| 87 | 261030 | AROMATIZANTE (para ropa) BABY & KID | Lavandería |  | 0 | 4 |
| 88 | 261021 | AROMATIZANTE (para ropa) TINA | Lavandería |  | 0 | 4 |
| 92 | 250020 | BLANQUEADOR P/ ROPA BLANCA | Lavandería |  | 3 | 4 |
| 93 | 250010 | BLANQUEADOR P/ ROPA COLOR | Lavandería |  | 0 | 4 |
| 94 | 250015 | PLANCHA FRESH (agua de plancha perfumada) | Lavandería |  | 0 | 4 |
| 91 | 250040 | PRELAVADO | Lavandería |  | 0 | 4 |
| 90 | 260046 | SEIQ (deterg. sintético para ropa fina) | Lavandería |  | 0 | 4 |
| 89 | 1001 | SEIQ (Jabón líquido para ropa) | Lavandería |  | 2 | 4 |
| 81 | 28186A | SUAVIZANTE SEIQ | Lavandería |  | 0 | 4 |
| 82 | 28186Z | SUAVIZANTE SOFNER (blanco) | Lavandería |  | 0 | 4 |
| 80 | 18187 | SUAVIZANTE TINA | Lavandería |  | 0 | 4 |
| 79 | 861080 | WHELL 10 (suavizante p/ ropa) | Lavandería |  | 0 | 4 |
| 83 | 8116A | WHELL ESSENCE (perfume p/ ropa) | Lavandería |  | 0 | 4 |
| 84 | 8116D | WHELL EXCEPCIONAL (perfume p/ ropa) | Lavandería |  | 0 | 4 |
| 85 | 8116E | WHELL FLORAL (perfume p/ ropa) | Lavandería |  | 0 | 4 |
| 86 | 8116B | WHELL VAINILLA (perfume p/ ropa) | Lavandería |  | 0 | 4 |
| 55 | 8256 | DESCAC 90 (derivado de los ésteres de sacarosa) | Limpiadores Desengrasantes |  | 0 | 4 |
| 53 | 2045 | DISOLVEX F (desengrasante ind. baja espuma) | Limpiadores Desengrasantes |  | 0 | 4 |
| 52 | 861401 | DLX 500 (limpia grasas minerales pesadas) | Limpiadores Desengrasantes |  | 0 | 4 |
| 50 | 861013 | DLX 600 (desengrasante sin solvente) | Limpiadores Desengrasantes |  | 0 | 4 |
| 56 | 2048 | FAST CLEANER (desengrasa sup. duras) | Limpiadores Desengrasantes |  | 0 | 4 |
| 58 | 861015 | GEL CLORADO (limpiad. deseng. c/ hipoclorito) | Limpiadores Desengrasantes |  | 0 | 4 |
| 57 | 250070 | LIMPIADOR C/ OXIGENO ACTIVO (limp. gral.) | Limpiadores Desengrasantes |  | 0 | 4 |
| 54 | 2046 | MC 500 (limpiador biodegradable con terpeno) | Limpiadores Desengrasantes |  | 0 | 4 |
| 60 | 250067 | PEROXIL 100 (con fragancia, limpiador multiuso c/ peróxido) | Limpiadores Desengrasantes |  | 0 | 4 |
| 59 | 250066 | PEROXIL 100 (sin fragancia, limpiador multiuso c/ peróxido) | Limpiadores Desengrasantes |  | 0 | 4 |
| 51 | 382018 | TOP FORCE (Limpiador desengrasante) | Limpiadores Desengrasantes |  | 0 | 4 |
| 140 | 464656 | ALCOHOL 70% | Limpiadores Desinfectantes |  | 0 | 4 |
| 138 | 2062 | BAC SAN PLUS (desin hidroalcoholico clorado) | Limpiadores Desinfectantes |  | 0 | 4 |
| 135 | 861621 | BC 201 (limpiador, bactericida, sanitizante, desinfect.) | Limpiadores Desinfectantes |  | 0 | 4 |
| 133 | 861016 | BENTOL 10 (limpiador desodor y desinfectante) | Limpiadores Desinfectantes |  | 1 | 4 |
| 134 | 861019 | BENTOL CITRICO (limp, desodorizante y desinf) | Limpiadores Desinfectantes |  | 0 | 4 |
| 143 | 260073 | BIO BAC (limpiador germicida, bactericida y desinf) | Limpiadores Desinfectantes |  | 0 | 4 |
| 131 | 260089 | BIOCLOR (desinf. a base de amonio cuaternario) | Limpiadores Desinfectantes |  | 0 | 4 |
| 136 | 861122 | CL 202 (desengrasante, sanitizante, neutralizador olores) | Limpiadores Desinfectantes |  | 0 | 4 |
| 139 | 261000 | DESINFECTANTE (limpiador líquido) | Limpiadores Desinfectantes |  | 0 | 4 |
| 145 | ECHL1 | ECOMAX CHLOR | Limpiadores Desinfectantes |  | 0 | 4 |
| 132 | 861000 | KIEFER (limpiador bactereostático desod. gel) | Limpiadores Desinfectantes |  | 0 | 4 |
| 141 | 260071 | SANIGEN (desengrasante neutralizador de olores) | Limpiadores Desinfectantes |  | 0 | 4 |
| 142 | 260072 | SANITY PRO (limpiador odorizante bactericida) | Limpiadores Desinfectantes |  | 0 | 4 |
| 144 | 260070 | SEIQ DESTAPA CAÑERIAS | Limpiadores Desinfectantes |  | 0 | 4 |
| 137 | 861012 | SX 185 (limpiador desincrustante y bactericida) | Limpiadores Desinfectantes |  | 0 | 4 |
| 39 | 399329 | ACEITE PARA ANTORCHAS (base citronela) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 34 | 861006 | DUFT SWEET (cherry Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 38 | 334345 | DUFT SWEET (Cítrico Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 37 | 329201 | DUFT SWEET (Citronela espanta moscas/mosquitos) | Limpiadores y Aromatizantes |  | 1 | 4 |
| 30 | 861002 | DUFT SWEET (colonia Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 33 | 861005 | DUFT SWEET (jazmín Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 31 | 861003 | DUFT SWEET (lavanda Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 32 | 861004 | DUFT SWEET (marina Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 36 | 861008 | DUFT SWEET (papaya Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 35 | 861007 | DUFT SWEET (pino Limpiador Desodorante) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 41 | 861600 | FM 301 (desodorante germicida de alto poder) | Limpiadores y Aromatizantes |  | 0 | 4 |
| 40 | 861010 | WIPER (limpiavidrios) | Limpiadores y Aromatizantes |  | 3 | 4 |
| 130 | 861009 A | RUNAX D 45 (limpialfombras) | Limpieza de Alfombras |  | 0 | 4 |
| 105 | 861102 | BLAST (cera acrílica p/ alto tránsito) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 102 | 26034 | BRILLO ACRÍLICO (emulsión acríl. c/cera carnaúba) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 104 | 26035 A | BRILLO ACRÍLICO NEGRO | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 103 | 26035 | BRILLO ACRÍLICO ROJO | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 107 | 861104 | CLEM (removedor de ceras acrílicas) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 106 | 861103 | CROSS (restaurador acrílico) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 112 | 861014 | DN 120 (limpiador neutro en pasta) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 97 | 2060 | FLOOR CRIS (cristalizador para mármol y terrazo) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 101 | 260033 | LÍQUIDO P/ LAMPAZO (base acuosa) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 100 | 260032 | LIQUIDO P/ LAMPAZO ESPECIAL | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 109 | 861106 | MIRAGE 2000 (emulsión acrílica) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 110 | 861145 | NEUTRAL 50 (limpiador neutro c/brillo residual) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 114 | 861107 | PARQUET GLOSSY (emulsión acrílica p/ madera) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 108 | 861105 | PAYL (limpiador acrílico p/ pisos) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 96 | 861100 | SAPP (sellador acrílico p/ pisos) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 113 | 861017 A | STRIPER (limpiador neutro) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 98 | 861101 | TESEP (secuestrante de polvo) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 99 | 2061 | TESEP SR (secuestrante de polvo secado rápido) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 111 | 262190 | WET (emulsión acrílica) | Limpieza y Tratamiento de Pisos |  | 0 | 4 |
| 208 | ALACD6 | ECOMAX ACID (espumígeno desincrustante ácido) | Línea Alimenticia |  | 0 | 4 |
| 209 | ALACD61 | ECOMAX ACID (espumígeno desincrustante ácido) | Línea Alimenticia |  | 0 | 1 |
| 200 | ALALC1 | ECOMAX ALCAL (espuma clorada alcalina) | Línea Alimenticia |  | 0 | 4 |
| 201 | ALALC11 | ECOMAX ALCAL (espuma clorada alcalina) | Línea Alimenticia |  | 0 | 1 |
| 206 | ALDESG5 | ECOMAX DESGRASS (limpiador desengrasante multiuso baja espuma) | Línea Alimenticia |  | 0 | 4 |
| 207 | ALDESG51 | ECOMAX DESGRASS (limpiador desengrasante multiuso baja espuma) | Línea Alimenticia |  | 0 | 1 |
| 202 | ALFRC2 | ECOMAX FORCE (detergente 15% de materia activa) | Línea Alimenticia |  | 0 | 4 |
| 203 | ALPLUS3 | ECOMAX PLUS ULTRA (limpiador desengrasante p/grasas carbonizadas) | Línea Alimenticia |  | 0 | 4 |
| 204 | ALPLUS31 | ECOMAX PLUS ULTRA (limpiador desengrasante p/grasas carbonizadas) | Línea Alimenticia |  | 0 | 1 |
| 205 | ALPWR4 | ECOMAX POWER PLUS (detergente en pasta c/30% de materia activa) | Línea Alimenticia |  | 0 | 4 |
| 162 | CMAWN2 | ALL WAX, CERA LÍQUIDA (NATURAL) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 161 | CMAWNG1 | ALL WAX, CERA LÍQUIDA (NEGRO) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 163 | CMAWR3 | ALL WAX, CERA LÍQUIDA (ROJO) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 170 | CMC10 | CLEM, REMOVEDOR DE CERAS Y ACRÍLICOS | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 176 | ECODPC16 | DESODORANTE DE PISOS (CHERRY) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 175 | ECODPLV15 | DESODORANTE DE PISOS (LAVANDA) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 174 | ECODPLI14 | DESODORANTE DE PISOS (LIMÓN) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 177 | ECODPP17 | DESODORANTE DE PISOS (PINO) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 172 | CMD12 | DESTAPA CAÑERÍAS, LIMPIADOR CONCENTRADO | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 179 | ECOLAV19 | LAVAVAJILLAS (ALOE VERA) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 178 | ECOLL18 | LAVAVAJILLAS (LIMÓN) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 168 | CML8 | LEICHT, ACONDICIONADOR DE PISOS | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 167 | CMPG7 | PARQUET GLOSSY, CERA PARA MADERAS | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 171 | CMQS11 | QUITA SARROS, LIMPIEZA Y DESINFECCIÓN DE SANITARIOS | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 1 | 12 |
| 173 | CMSH13 | SAHNNING, CREMA MULTIUSO | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 4 | 12 |
| 169 | CMSPP9 | SAPP, SELLADOR ACRÍLICO PARA PISOS | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 165 | CMZN5 | ZIEGEL - H20, CURADOR DE PISOS SILICONADO (NATURAL) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 164 | CMZNG4 | ZIEGEL - H20, CURADOR DE PISOS SILICONADO (NEGRO) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 166 | CMZR6 | ZIEGEL - H20, CURADOR DE PISOS SILICONADO (ROJO) | Masivo - Consumo Masivo | CAJA X 12 UNIDADES | 0 | 12 |
| 266 | RCF | Rollo Camillero Factory | Paños de Limpieza Factory | Pack x2 | 0 | 2 |
| 230 | H8200A | Higiénico 200m ANTÁRTICO | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 232 | H8200AG | Higiénico 200m ANTÁRTICO Cono Grande | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 229 | H8300A | Higiénico 300m ANTÁRTICO | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 231 | H8300AG | Higiénico 300m ANTÁRTICO Cono Grande | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 233 | H8300A ECO | Higiénico 300m ECOLÓGICO | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 234 | H8300AG ECO | Higiénico 300m ECOLÓGICO Cono Grande | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 226 | H8200P | Higiénico PREMIUM 200m | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 228 | H8200PG | Higiénico PREMIUM 200m Cono Grande | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 225 | H8300P | Higiénico PREMIUM 300m | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 227 | H8300PG | Higiénico PREMIUM 300m Cono Grande | Papel Higiénico Altometraje | Pack x8 | 0 | 8 |
| 251 | HPACC | Higiénico 300m ANTÁRTICO Factory CC | Papel Higiénico Altometraje Factory | Pack x8 | 0 | 8 |
| 252 | HPACG | Higiénico 300m ANTÁRTICO Factory CG | Papel Higiénico Altometraje Factory | Pack x8 | 0 | 8 |
| 254 | HPACC ECO | Higiénico 300m ECOLÓGICO Factory CC | Papel Higiénico Altometraje Factory | Pack x8 | 0 | 8 |
| 255 | HPACG ECO | Higiénico 300m ECOLÓGICO Factory CG | Papel Higiénico Altometraje Factory | Pack x8 | 0 | 8 |
| 249 | HPFCC | Higiénico 300m PREMIUM Factory CC | Papel Higiénico Altometraje Factory | Pack x8 | 0 | 8 |
| 250 | HPFCG | Higiénico 300m PREMIUM Factory CG | Papel Higiénico Altometraje Factory | Pack x8 | 0 | 8 |
| 253 | HF5000 | Higiénico 500m ANTÁRTICO Factory CG | Papel Higiénico Altometraje Factory | Pack x4 | 0 | 4 |
| 236 | H3080A | Higiénico 80m Antártico x30 | Papel Higiénico Cortometraje | Bolsón x30 | 0 | 30 |
| 237 | HB100 | Higiénico PREMIUM 100m Bolsón x12 | Papel Higiénico Cortometraje | Bolsón x12 | 0 | 12 |
| 235 | H3080P | Higiénico PREMIUM 80m x30 | Papel Higiénico Cortometraje | Bolsón x30 | 0 | 30 |
| 256 | HF1100 | Higiénico 110m Antártico Factory | Papel Higiénico Cortometraje Factory | Bolsón x30 | 0 | 30 |
| 259 | HF1100 ECO | Higiénico 110m Ecológico Factory | Papel Higiénico Cortometraje Factory | Bolsón x30 | 0 | 30 |
| 258 | HF600 | Higiénico 60m Antártico Factory | Papel Higiénico Cortometraje Factory | Bolsón x30 | 0 | 30 |
| 261 | HF600 ECO | Higiénico 60m Ecológico Factory | Papel Higiénico Cortometraje Factory | Bolsón x30 | 0 | 30 |
| 257 | HF800 | Higiénico 80m Antártico Factory | Papel Higiénico Cortometraje Factory | Bolsón x30 | 0 | 30 |
| 260 | HF800 ECO | Higiénico 80m Ecológico Factory | Papel Higiénico Cortometraje Factory | Bolsón x30 | 0 | 30 |
| 199 | 391741 | DISPENSER EN SPRAY SEIQ | Pouches y Dispenser | POR UNIDAD | 0 | 1 |
| 198 | 391745 | POUCH JABON MANOS SPRAY 400 cc Fragancia Aloe Vera | Pouches y Dispenser | CAJA 12 POUCH | 0 | 12 |
| 197 | 391740 | POUCH JABON MANOS SPRAY 400 cc Fragancia Frutos Rojos | Pouches y Dispenser | CAJA 12 POUCH | 0 | 12 |
| 196 | 391742 | POUCH SANITIZANTE EN SPRAY 400 cc | Pouches y Dispenser | CAJA 12 POUCH | 0 | 12 |
| 155 | 200055 | ALGUICIDA (alguicida y bactericida p/ piletas) | Productos para Piletas |  | 0 | 4 |
| 156 | 260050 | CLARIFICADOR (clarificante-floculante-coagulante) | Productos para Piletas |  | 0 | 4 |
| 181 | 391739 | DESENGRASANTE ECOMAX | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 188 | 391736 A | DESINFECTANTE ECOMAX | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 183 | 391731 A | DESODORANTE ECOMAX CHERRY | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 186 | 391728 | DESODORANTE ECOMAX CITRICO | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 187 | 391735 | DESODORANTE ECOMAX CITRONELLA | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 182 | 391730 | DESODORANTE ECOMAX LAVANDA | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 185 | 391733 | DESODORANTE ECOMAX PAPAYA | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 184 | 391732 | DESODORANTE ECOMAX PINO | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 192 | 391747 | ECOMAX JABON LIQUIDO PARA ROPA | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 191 | 391746 | ECOMAX LAVAVAJILLAS (CITRICO) | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 195 | 391750 | ECOMAX LIMP. PISOS PLASTIFICADOS Y PORCELANATO | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 194 | 391749 | ECOMAX PRELAVADO | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 193 | 391748 | ECOMAX SUAVIZANTE PARA ROPA | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 180 | 391792 | LAVANDINA ECOMAX | Sobres Concentrados | 10 cajas x 8 Sobres | 68 | 80 |
| 190 | 391738 | LIMPIA VIDRIOS ECOMAX | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 189 | 391737 | LIMPIADOR ECOLÓGICO ECOMAX | Sobres Concentrados | 10 cajas x 4 Sobres | 0 | 40 |
| 219 | R4x150A | Toalla en Rollo 150m Antártico | Toallas en Rollo | Pack x4 | 0 | 4 |
| 222 | R4x150B | Toalla en Rollo 150m BEIGE | Toallas en Rollo | Pack x4 | 0 | 4 |
| 216 | R4x150P | Toalla en Rollo 150m PREMIUM | Toallas en Rollo | Pack x4 | 0 | 4 |
| 220 | R4x200A | Toalla en Rollo 200m Antártico | Toallas en Rollo | Pack x4 | 0 | 4 |
| 223 | R4x200B | Toalla en Rollo 200m BEIGE | Toallas en Rollo | Pack x4 | 0 | 4 |
| 217 | R4x200P | Toalla en Rollo 200m PREMIUM | Toallas en Rollo | Pack x4 | 0 | 4 |
| 221 | R2x250A | Toalla en Rollo 250m Antártico | Toallas en Rollo | Pack x2 | 0 | 2 |
| 224 | R2x250B | Toalla en Rollo 250m BEIGE | Toallas en Rollo | Pack x2 | 0 | 2 |
| 218 | R2x250P | Toalla en Rollo 250m PREMIUM | Toallas en Rollo | Pack x2 | 0 | 2 |
| 247 | TRAF | Toalla en Rollo 200m Antártico Factory | Toallas en Rollo Factory | Pack x4 | 0 | 4 |
| 248 | TRBF | Toalla en Rollo 200m Beige Factory | Toallas en Rollo Factory | Pack x4 | 0 | 4 |
| 246 | TRPF | Toalla en Rollo 200m PREMIUM Factory | Toallas en Rollo Factory | Pack x4 | 0 | 4 |
| 214 | E1500 | Toalla Blanca PREMIUM 1500u | Toallas Intercaladas | Bolsón | 0 | 1 |
| 212 | IB2000 | Toalla Blanca PREMIUM 2000u | Toallas Intercaladas | Bolsón | 0 | 1 |
| 210 | IB2500 | Toalla Blanca PREMIUM 2500u | Toallas Intercaladas | Bolsón | 0 | 1 |
| 215 | EB1500 | Toalla Natural Beige 1500u | Toallas Intercaladas | Bolsón | 0 | 1 |
| 213 | IN2000 | Toalla Natural Beige 2000u | Toallas Intercaladas | Bolsón | 0 | 1 |
| 211 | IN2500 | Toalla Natural Beige 2500u | Toallas Intercaladas | Bolsón | 0 | 1 |
| 245 | TBF | Toalla BEIGE Factory 1500u | Toallas Intercaladas Factory | Bolsón | 0 | 1 |
| 244 | TPF | Toalla PREMIUM Factory 1500u | Toallas Intercaladas Factory | Bolsón | 0 | 1 |

### Categorías y jerarquía

Total categorías: 34.

| ID | Nombre | Slug | Parent ID | Categoría padre |
|---:|---|---|---:|---|
| 1 | Aerosoles ECOMAX | aerosoles | - | - |
| 2 | Bidones - Línea Institucional | bidones | - | - |
| 14 | Ceras | bidones-ceras | 2 | Bidones - Línea Institucional |
| 18 | Cosmética del Automotor | bidones-automotor | 2 | Bidones - Línea Institucional |
| 11 | Cuidado de la Cocina | bidones-cocina | 2 | Bidones - Línea Institucional |
| 9 | Cuidado de Manos | bidones-cuidado-manos | 2 | Bidones - Línea Institucional |
| 15 | Curadores Hidrofugos | bidones-curadores | 2 | Bidones - Línea Institucional |
| 20 | Insecticidas | bidones-insecticidas | 2 | Bidones - Línea Institucional |
| 12 | Lavandería | bidones-lavanderia | 2 | Bidones - Línea Institucional |
| 10 | Limpiadores Desengrasantes | bidones-desengrasantes | 2 | Bidones - Línea Institucional |
| 17 | Limpiadores Desinfectantes | bidones-desinfectantes | 2 | Bidones - Línea Institucional |
| 8 | Limpiadores y Aromatizantes | bidones-limpiadores-aromatizantes | 2 | Bidones - Línea Institucional |
| 16 | Limpieza de Alfombras | bidones-alfombras | 2 | Bidones - Línea Institucional |
| 13 | Limpieza y Tratamiento de Pisos | bidones-pisos | 2 | Bidones - Línea Institucional |
| 19 | Productos para Piletas | bidones-piletas | 2 | Bidones - Línea Institucional |
| 6 | Insecticidas Concentrados | insecticidas-concentrados | - | - |
| 5 | Línea Alimenticia | alimenticia | - | - |
| 3 | Masivo - Consumo Masivo | masivo | - | - |
| 33 | Bobinas Industriales Factory | fac-bobinas | 28 | Papelera Factory |
| 34 | Paños de Limpieza Factory | fac-panos | 28 | Papelera Factory |
| 31 | Papel Higiénico Altometraje Factory | fac-ph-altometraje | 28 | Papelera Factory |
| 32 | Papel Higiénico Cortometraje Factory | fac-ph-cortometraje | 28 | Papelera Factory |
| 28 | Papelera Factory | papelera-factory | - | - |
| 30 | Toallas en Rollo Factory | fac-toallas-rollo | 28 | Papelera Factory |
| 29 | Toallas Intercaladas Factory | fac-toallas-intercaladas | 28 | Papelera Factory |
| 27 | Bobinas Industriales Beige | hig-bobinas-beige | 21 | Papelera Higienik |
| 26 | Bobinas Industriales Premium | hig-bobinas-premium | 21 | Papelera Higienik |
| 24 | Papel Higiénico Altometraje | hig-ph-altometraje | 21 | Papelera Higienik |
| 25 | Papel Higiénico Cortometraje | hig-ph-cortometraje | 21 | Papelera Higienik |
| 21 | Papelera Higienik | papelera-higienik | - | - |
| 23 | Toallas en Rollo | hig-toallas-rollo | 21 | Papelera Higienik |
| 22 | Toallas Intercaladas | hig-toallas-intercaladas | 21 | Papelera Higienik |
| 7 | Pouches y Dispenser | pouches-y-dispenser | - | - |
| 4 | Sobres Concentrados | sobres | - | - |

## 8. Combos definidos

Total combos: 3.

### Combo #1 - Combo Cocina

| Producto ID | Código | Producto | Cantidad en combo |
|---:|---|---|---:|
| 67 | 398120 | CLEAN OUTLET LAVAVAJILLAS | 1 |
| 16 | ECOAAI01 | ECOMAX ABRILLANTADOR DE ACERO INOXIDABLE | 1 |
| 20 | ECOLH05 | ECOMAX LIMPIA HORNOS | 1 |
| 173 | CMSH13 | SAHNNING, CREMA MULTIUSO | 1 |
| 63 | 861008 B | STRONG (desengrasante p/ hornos y parrillas) | 1 |

### Combo #3 - Combo Hogar

| Producto ID | Código | Producto | Cantidad en combo |
|---:|---|---|---:|
| 133 | 861016 | BENTOL 10 (limpiador desodor y desinfectante) | 1 |
| 34 | 861006 | DUFT SWEET (cherry Limpiador Desodorante) | 1 |
| 23 | ECODAS08 | ECOMAX DESINFECTANTE DE AMBIENTES Y SUPERFICIES | 1 |
| 19 | ECOLM04 | ECOMAX LUSTRA MUEBLES | 1 |
| 171 | CMQS11 | QUITA SARROS, LIMPIEZA Y DESINFECCIÓN DE SANITARIOS | 1 |
| 173 | CMSH13 | SAHNNING, CREMA MULTIUSO | 1 |
| 40 | 861010 | WIPER (limpiavidrios) | 1 |

### Combo #2 - Combo Lavanderia

| Producto ID | Código | Producto | Cantidad en combo |
|---:|---|---|---:|
| 24 | ECOA09 | ECOMAX APRESTO | 1 |
| 89 | 1001 | SEIQ (Jabón líquido para ropa) | 1 |
| 81 | 28186A | SUAVIZANTE SEIQ | 1 |

## 9. Clientes activos

Se listan clientes presentes en la BD con saldo calculado desde `account_transactions`.

| ID | Nombre | Ciudad | Balance actual |
|---:|---|---|---:|
| 2 | Ana María Tomassini | Vill Sarmiento | -3.012,07 |
| 8 | Belen SPL | Lujan | 7.682,34 |
| 15 | Cafecito Lujan | Luján, Provincia de Buenos Aires | 84.889,68 |
| 12 | Carla Giampa | Ramos Mejia | -21,41 |
| 11 | Eduardo Motoni HSP | Haras San Pablo | -115,00 |
| 1 | Gonzalo Mattia | General Rodriguez | 0,00 |
| 9 | Jimena Gonzalez | Moreno | 58.262,34 |
| 14 | La Artesanal Ramos Mejia | Ramos Mejia | 29.446,42 |
| 3 | Maria Gloria | Moreno | 0,00 |
| 7 | MercadoLibre |  | 55.680,00 |
| 5 | Romina Galetto | Lujan, Provincia de Buenos Aires | -387,42 |
| 4 | San Patricio de Luján | Luján, Buenos Aires | 0,00 |
| 10 | Sofia SPL Miss | Lujan - San Patricio de Lujan | 37.848,00 |
| 13 | Susana Tomassini |  | 0,00 |

## 10. Estado operativo actual

### Presupuestos por estado

| Estado | Cantidad |
|---|---:|
| `accepted` | 4 |
| `delivered` | 10 |
| `partially_delivered` | 1 |

### Pedidos SEIQ por estado

| Estado | Cantidad |
|---|---:|
| `received` | 5 |
| `sent` | 1 |

### Financieros y stock

- Saldo proveedores (CC): **968.902,18**.
- Stock valorizado estimado a costo (aprox): **5.316.426,03**.
- Nota: valorización estimada con mejor precio de lista disponible por producto y descuento efectivo, sin costos logísticos/impositivos extra.

## 11. Bugs conocidos y limitaciones

- Hay evidencia de ajustes recientes en cálculo de descuento (`deploy_check.txt` y `debug_discount.txt`), señalando sensibilidad en entradas extremas de descuento.
- Riesgo de desincronización histórica de `stock_committed_units`; por eso el módulo de pedidos recalcula compromisos en tiempo real desde `quote_items`.
- Dependencia de campos JSON (`included_quotes`) para exclusión de presupuestos en pedidos: dificulta trazabilidad SQL pura y validaciones relacionales.
- No se observa suite de tests automáticos integrada en CI para regresiones de reglas críticas (pricing/stock).
- Deploy por FTP scriptado: simple y efectivo, pero sin estrategia de releases atómicos ni rollback automatizado.

## 12. Decisiones de diseño relevantes

- Diseño centrado en **operación comercial real**: unidades físicas como base única para stock y compromisos.
- Modelo sin capa de warehouse compleja: stock agregado por producto (sin multi-depósito).
- Venta por caja/unidad configurable por línea, con conversión explícita por `units_per_box`.
- Combos como abstracción comercial: no duplican inventario, sólo explotan componentes.
- Cuenta corriente unificada (clientes/proveedores) en una sola tabla para simplificar reportes y conciliación.
- Cálculo de demanda para compras basado en compromisos pendientes + tránsito para evitar sobrecompra.
- Priorización de reglas de negocio en helpers (`PricingEngine`, `QuoteDeliveryStock`, `SeiqOrderBuilder`) para reuso transversal.

## Apéndice: referencias de código clave

- Rutas: `app/config/routes.php`
- Router: `app/Core/Router.php`
- Autenticación: `app/Controllers/AuthController.php`
- Pricing: `app/Helpers/PricingEngine.php`
- Stock entrega/compromisos: `app/Helpers/QuoteDeliveryStock.php`
- Pedido proveedor: `app/Helpers/SeiqOrderBuilder.php`, `app/Controllers/SeiqOrderController.php`
- Presupuestos: `app/Controllers/QuoteController.php`
- Deploy: `deploy.php`
