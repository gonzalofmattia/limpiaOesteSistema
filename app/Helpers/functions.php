<?php

declare(strict_types=1);

use App\Helpers\SettingsCache;

/**
 * URL absoluta del sitio (esquema + host + prefijo de la app), desde APP_URL en .env.
 */
function baseUrl(string $path = ''): string
{
    return rtrim(\App\Helpers\Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
}

/**
 * Ruta relativa al dominio (incluye subcarpeta /public si existe).
 * Ej.: url('/login') → /limpiaOesteSistema/public/login
 */
function url(string $path = '/'): string
{
    $base = defined('BASE_URL_PATH') ? (string) BASE_URL_PATH : (defined('BASE_URL') ? (string) BASE_URL : '');
    $base = rtrim($base, '/');
    $path = trim($path, '/');
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function redirect(string $pathOrUrl): void
{
    if (preg_match('#^https?://#i', $pathOrUrl)) {
        header('Location: ' . $pathOrUrl);
        exit;
    }
    header('Location: ' . url($pathOrUrl));
    exit;
}

function formatPrice(?float $amount): string
{
    if ($amount === null) {
        return '$ 0,00';
    }
    $negative = $amount < 0;
    $amount = abs($amount);
    $formatted = number_format($amount, 2, ',', '.');
    return ($negative ? '- ' : '') . '$ ' . $formatted;
}

function formatPercent(?float $value): string
{
    if ($value === null) {
        return '—';
    }
    return number_format($value, 1, ',', '.') . '%';
}

function parseArgentineAmount(string $raw): float
{
    $normalized = trim($raw);
    if ($normalized === '') {
        return 0.0;
    }
    $normalized = str_replace(['$', ' '], '', $normalized);
    $negative = false;
    if (str_starts_with($normalized, '-')) {
        $negative = true;
        $normalized = ltrim(substr($normalized, 1));
    }

    if (str_contains($normalized, ',')) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (str_contains($normalized, '.')) {
        $normalized = str_replace('.', '', $normalized);
    }

    if (!is_numeric($normalized)) {
        return 0.0;
    }

    $value = (float) $normalized;
    if ($negative) {
        $value *= -1;
    }

    return round($value, 2);
}

/**
 * Estados de cobro simplificados (sin “parcial”): al día vs pendiente.
 *
 * @return array{status:string,label:string,badge:string,paid:float,pending:float}
 */
function quotePaymentStatus(float $quoteTotal, float $paidAmount): array
{
    $total = round(max(0, $quoteTotal), 2);
    $paid = round(max(0, $paidAmount), 2);
    $pending = round(max(0, $total - $paid), 2);

    if ($total > 0 && $pending <= 0.009) {
        return [
            'status' => 'paid',
            'label' => 'Cobrado (cliente al día)',
            'badge' => 'bg-emerald-100 text-emerald-800',
            'paid' => $paid,
            'pending' => 0.0,
        ];
    }

    return [
        'status' => 'pending',
        'label' => 'Pendiente (saldo cliente)',
        'badge' => 'bg-rose-100 text-rose-800',
        'paid' => $paid,
        'pending' => $pending,
    ];
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['_flash'])) {
        return null;
    }
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfField(): string
{
    $t = e(csrfToken());
    return '<input type="hidden" name="_csrf" value="' . $t . '">';
}

function verifyCsrf(): bool
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf'], $token);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Texto de presentación para listas de precios / PDF (el operador ?? no sustituye strings vacíos).
 *
 * @param array<string, mixed> $product Fila producto; puede incluir category_presentation_info (join a categories).
 */
function productListPresentation(array $product): string
{
    foreach (['presentation', 'content', 'sale_unit_description'] as $k) {
        $t = trim((string) ($product[$k] ?? ''));
        if ($t !== '') {
            return $t;
        }
    }
    $c = trim((string) ($product['category_presentation_info'] ?? ''));

    return $c !== '' ? $c : '—';
}

/**
 * Texto de presentación para PDF lista minorista: columna dedicada, luego estándar.
 *
 * @param array<string, mixed> $product Fila producto (p. ej. join a categories).
 */
function productMinoristaPresentation(array $product): string
{
    $m = trim((string) ($product['presentacion_minorista'] ?? ''));
    if ($m !== '') {
        return $m;
    }
    $std = trim((string) ($product['presentation'] ?? ''));
    if ($std !== '') {
        return $std;
    }

    return productListPresentation($product);
}

/**
 * Cobros/pagos/ajustes cargados a mano (o registros viejos sin reference_type).
 * No permite tocar facturas del sistema (presupuesto / pedido proveedor).
 *
 * @param array<string, mixed> $row Fila account_transactions
 */
function accountMovementIsEditable(array $row): bool
{
    $type = (string) ($row['transaction_type'] ?? '');
    if (!in_array($type, ['payment', 'adjustment'], true)) {
        return false;
    }
    $ref = $row['reference_type'] ?? null;
    $refStr = $ref === null || $ref === '' ? '' : (string) $ref;
    $refId = (int) ($row['reference_id'] ?? 0);

    if ($refStr === 'manual') {
        return true;
    }
    if ($refStr === '' && $refId === 0) {
        return true;
    }

    return false;
}

function isActive(string $path): bool
{
    $current = $_SERVER['REQUEST_URI'] ?? '/';
    if (($q = strpos($current, '?')) !== false) {
        $current = substr($current, 0, $q);
    }
    $base = defined('BASE_URL_PATH') ? rtrim((string) BASE_URL_PATH, '/') : (defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '');
    if ($base !== '' && str_starts_with($current, $base)) {
        $current = substr($current, strlen($base)) ?: '/';
    }
    $path = '/' . ltrim(trim($path, '/'), '/');
    if ($path === '/' || $path === '') {
        return $current === '/' || $current === '';
    }
    return str_starts_with($current, $path);
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower((string) $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: 'item';
}

function setting(string $key, ?string $default = null): ?string
{
    return SettingsCache::get($key, $default);
}

/**
 * Texto de remanente para pedidos Seiq (un., sobres, etc.).
 *
 * @param array<string, mixed> $row Consolidado o ítem con units_remainder, sale_unit_label, category_slug
 */
function seiqRemainderLabel(array $row): string
{
    $n = (int) ($row['units_remainder'] ?? 0);
    if ($n <= 0) {
        return '0 un.';
    }
    $slug = strtolower((string) ($row['category_slug'] ?? ''));
    $label = strtolower(trim((string) ($row['sale_unit_label'] ?? '')));
    if ($slug === 'sobres' || str_contains($label, 'sobre')) {
        return $n . ' sobres';
    }

    return $n . ' un.';
}

/**
 * Texto columna «Detalle» en presupuestos (snapshot o fallback).
 *
 * @param array<string, mixed> $it Fila quote_items con joins (category_slug, content, sale_unit_description, etc.)
 */
function quoteItemDetalleDisplay(array $it): string
{
    if ((int) ($it['combo_id'] ?? 0) > 0) {
        return 'Combo';
    }
    return \App\Helpers\QuoteLinePricing::fallbackDetalleDisplay($it);
}

/**
 * Precio de venta de 1 unidad suelta (referencia en presupuesto). Usa snapshot o recalcula si falta.
 *
 * @param array<string, mixed> $it Fila quote_items con joins de producto/categoría
 * @param array<string, mixed> $quote Fila quotes
 */
/** Leyenda para vistas/PDF según si el documento incluye IVA en los importes. */
function priceIvaLegendLine(bool $includeIva): string
{
    return $includeIva
        ? '* Precios con IVA incluido'
        : '* Los precios expresados no incluyen IVA';
}

function statusLabel(string $status): string
{
    return match (strtolower(trim($status))) {
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
        'expired' => 'Vencido',
        'delivered' => 'Entregado',
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'pending' => 'Pendiente',
        'received' => 'Recibido',
        'cancelled' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', trim($status))),
    };
}

function statusBadgeClass(string $status): string
{
    return match (strtolower(trim($status))) {
        'draft', 'inactive' => 'bg-gray-100 text-gray-700',
        'sent' => 'bg-blue-100 text-blue-800',
        'accepted', 'active', 'received' => 'bg-green-100 text-green-800',
        'rejected', 'cancelled' => 'bg-red-100 text-red-800',
        'expired', 'pending' => 'bg-amber-100 text-amber-800',
        'delivered' => 'bg-emerald-100 text-emerald-800',
        default => 'bg-gray-100 text-gray-700',
    };
}

function quoteItemIndividualUnitPrice(array $it, array $quote): float
{
    if ((int) ($it['combo_id'] ?? 0) > 0) {
        return (float) ($it['unit_price'] ?? 0);
    }
    if (isset($it['individual_unit_price']) && $it['individual_unit_price'] !== null && $it['individual_unit_price'] !== '') {
        return (float) $it['individual_unit_price'];
    }
    $mark = $quote['custom_markup'] ?? null;
    $mark = $mark !== null && $mark !== '' ? (float) $mark : null;
    $includeIva = !empty($quote['include_iva']);

    return \App\Helpers\QuoteLinePricing::individualUnitSellingPrice(
        $it,
        (string) ($it['category_slug'] ?? ''),
        $mark,
        $includeIva
    );
}
