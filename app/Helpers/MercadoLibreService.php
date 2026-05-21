<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Cliente de la API de MercadoLibre (publicar, sincronizar, categorías, órdenes).
 * No maneja HTML ni redirecciones; los errores se registran y retornan sin romper el flujo.
 */
final class MercadoLibreService
{
    private const API_BASE = 'https://api.mercadolibre.com';
    private const ML_COMMISSION = 0.12;
    private const ML_COMMISSION_PCT = 12;
    private const ML_FIXED_SHIPPING_FEE = 1275.0;
    private const MAX_PICTURES = 12;
    private const ML_BADGE_PICTURE_PATH = '/assets/img/ML.jpg';
    private const DESCRIPTION_FOOTER = 'Entrega en Zona Oeste GBA. Para pedidos frecuentes consultanos por mensajes de ML.';

    public static function calculateMlPrice(int $productId, ?float $mlMarkup = null): float
    {
        $breakdown = self::calculateMlPriceBreakdown($productId, $mlMarkup);

        return $breakdown !== null ? (float) $breakdown['precio_publicado'] : 0.0;
    }

    /**
     * @return array{
     *   costo_base: float,
     *   markup_aplicado: float,
     *   cargo_fijo_envio: float,
     *   comision_ml_pct: float,
     *   precio_publicado: float,
     *   comision_ml_monto: float,
     *   neto_recibido: float,
     *   ganancia_pesos: float,
     *   ganancia_pct: float,
     *   markup_calculado_automaticamente: bool
     * }|null
     */
    public static function calculateMlPriceBreakdown(
        int $productId,
        ?float $mlMarkup = null,
        ?float $precioObjetivo = null
    ): ?array {
        try {
            $product = self::fetchProduct($productId);
            if ($product === null) {
                return null;
            }

            $slug = PricingEngine::getEffectiveCategorySlug($product);
            $priceField = PricingEngine::getPrimaryPriceField($slug);
            $pricing = PricingEngine::calculate($product, $priceField);
            $costoBase = (float) ($pricing['costo'] ?? 0.0);
            if ($costoBase <= 0) {
                return null;
            }

            $cargoFijoML = self::ML_FIXED_SHIPPING_FEE;
            $comisionPct = self::ML_COMMISSION;
            $markupCalculado = false;

            if ($precioObjetivo !== null && $precioObjetivo > 0) {
                $precioPublicado = round($precioObjetivo, 2);
                $mlMarkup = self::calculateImplicitMarkup($costoBase, $precioPublicado);
                $markupCalculado = true;
            } else {
                if ($mlMarkup === null) {
                    $mlMarkup = (float) (setting('ml_default_markup', '75') ?? '75');
                }
                $precioPublicado = (($costoBase * (1 + $mlMarkup / 100)) + $cargoFijoML) / (1 - $comisionPct);
                $precioPublicado = round($precioPublicado, 2);
            }

            $comisionMonto = round($precioPublicado * $comisionPct, 2);
            $netoRecibido = round($precioPublicado - $comisionMonto - $cargoFijoML, 2);
            $gananciaPesos = round($netoRecibido - $costoBase, 2);
            $gananciaPct = $costoBase > 0 ? round(($gananciaPesos / $costoBase) * 100, 2) : 0.0;

            return [
                'costo_base' => round($costoBase, 2),
                'markup_aplicado' => round($mlMarkup, 2),
                'cargo_fijo_envio' => $cargoFijoML,
                'comision_ml_pct' => self::ML_COMMISSION_PCT,
                'precio_publicado' => $precioPublicado,
                'comision_ml_monto' => $comisionMonto,
                'neto_recibido' => $netoRecibido,
                'ganancia_pesos' => $gananciaPesos,
                'ganancia_pct' => $gananciaPct,
                'markup_calculado_automaticamente' => $markupCalculado,
            ];
        } catch (\Throwable $e) {
            self::logError('calculateMlPrice', "product_id={$productId}", 0, $e->getMessage());

            return null;
        }
    }

    public static function calculateImplicitMarkup(float $costoBase, float $precioObjetivo): float
    {
        if ($costoBase <= 0 || $precioObjetivo <= 0) {
            return 0.0;
        }

        return round(
            ((($precioObjetivo * (1 - self::ML_COMMISSION)) - self::ML_FIXED_SHIPPING_FEE) / $costoBase - 1) * 100,
            2
        );
    }

    public static function predictCategory(string $title): ?string
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        try {
            $siteId = self::getSiteId();
            $path = '/sites/' . rawurlencode($siteId) . '/domain_discovery/search?q=' . rawurlencode($title);
            $result = self::apiRequest('GET', $path, null, true);
            if (!$result['success']) {
                self::logError('predictCategory', 'title=' . self::truncate($title, 40), $result['http_code'], $result['error']);

                return null;
            }

            $data = $result['data'];
            if (!is_array($data) || $data === []) {
                return null;
            }

            $first = $data[0] ?? null;
            if (!is_array($first)) {
                return null;
            }

            $categoryId = trim((string) ($first['category_id'] ?? ''));
            if ($categoryId === '') {
                return null;
            }

            return $categoryId;
        } catch (\Throwable $e) {
            self::logError('predictCategory', 'title=' . self::truncate($title, 40), 0, $e->getMessage());

            return null;
        }
    }

    /** @return array{success: bool, ml_item_id: string, error: string} */
    public static function publishItem(int $listingId): array
    {
        $fail = static fn (string $msg, int $httpCode = 0, ?int $productId = null): array => self::failPublish(
            $listingId,
            $productId,
            $msg,
            $httpCode
        );

        try {
            $listing = self::fetchListing($listingId);
            if ($listing === null) {
                return $fail('Listing no encontrado.');
            }

            $productId = (int) ($listing['product_id'] ?? 0);
            if ($productId <= 0) {
                return $fail('El listing debe estar vinculado a un producto.', 0, $productId);
            }

            $product = self::fetchProduct($productId);
            if ($product === null) {
                return $fail('Producto no encontrado.', 0, $productId);
            }

            $categoryId = trim((string) ($listing['ml_category_id'] ?? ''));
            if ($categoryId === '') {
                return $fail('Falta la categoría ML (ml_category_id).', 0, $productId);
            }

            $title = trim((string) ($listing['title'] ?? ''));
            if ($title === '') {
                return $fail('Falta el título del listing.', 0, $productId);
            }

            $price = $listing['price'] ?? null;
            if ($price === null || (float) $price <= 0) {
                $markup = isset($listing['ml_markup']) && $listing['ml_markup'] !== null && $listing['ml_markup'] !== ''
                    ? (float) $listing['ml_markup']
                    : null;
                $price = self::calculateMlPrice($productId, $markup);
            }
            if ((float) $price <= 0) {
                return $fail('No se pudo calcular un precio válido para publicar.', 0, $productId);
            }

            if (self::countProductImages($productId) === 0) {
                $msg = 'El producto no tiene imágenes. Subí al menos una foto antes de publicar en ML.';
                self::saveListingError($listingId, $msg);

                return [
                    'success' => false,
                    'ml_item_id' => '',
                    'error' => $msg,
                ];
            }

            $pictures = self::buildPictures($productId, 'publishItem');
            if ($pictures === []) {
                return $fail(
                    'El producto no tiene imágenes accesibles públicamente para ML. Verificá las fotos en product_images.',
                    0,
                    $productId
                );
            }

            $quantity = self::resolveQuantity($listing);
            $attributes = self::buildPublishAttributes($product, $categoryId);
            $attrIds = array_map(static fn (array $a): string => (string) ($a['id'] ?? ''), $attributes);
            self::logInfo(
                'publishItem',
                self::listingContext($listingId, $productId),
                'atributos POST: ' . ($attrIds !== [] ? implode(', ', $attrIds) : '(ninguno)')
            );

            $payload = [
                'title' => self::truncate($title, 60),
                'category_id' => $categoryId,
                'price' => round((float) $price, 2),
                'currency_id' => 'ARS',
                'available_quantity' => $quantity,
                'buying_mode' => 'buy_it_now',
                'listing_type_id' => (string) ($listing['listing_type_id'] ?? 'gold_special'),
                'condition' => 'new',
                'pictures' => $pictures,
                'shipping' => [
                    'mode' => 'me2',
                    'local_pick_up' => false,
                    'free_shipping' => false,
                ],
                'attributes' => $attributes,
                'sale_terms' => [
                    ['id' => 'WARRANTY_TYPE', 'value_name' => 'Garantía del vendedor'],
                    ['id' => 'WARRANTY_TIME', 'value_name' => '30 días'],
                ],
            ];

            $result = self::apiRequest('POST', '/items', $payload, true);
            if (!$result['success']) {
                return $fail($result['error'], $result['http_code'], $productId);
            }

            $data = $result['data'];
            $mlItemId = trim((string) ($data['id'] ?? ''));
            if ($mlItemId === '') {
                return $fail('ML no devolvió el ID del ítem publicado.', $result['http_code'], $productId);
            }

            self::uploadItemDescription($mlItemId, $product);
            $snapshot = self::fetchMlItemSnapshot($mlItemId, 'publishItem');
            $thumbnail = self::sanitizeMlThumbnailForStorage(
                self::extractThumbnailFromMlItemData(is_array($data) ? $data : [])
            );
            if ($thumbnail === null) {
                $thumbnail = $snapshot['thumbnail'];
            }

            $permalink = trim((string) ($data['permalink'] ?? ''));

            self::updateListing($listingId, [
                'ml_item_id' => $mlItemId,
                'ml_permalink' => $permalink !== '' ? $permalink : null,
                'ml_thumbnail' => $thumbnail,
                'ml_pictures_count' => $snapshot['pictures_count'],
                'price' => round((float) $price, 2),
                'status' => 'active',
                'last_synced_at' => date('Y-m-d H:i:s'),
                'last_sync_error' => null,
            ], 'publishItem');

            return [
                'success' => true,
                'ml_item_id' => $mlItemId,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            $productId = null;
            try {
                $listing = self::fetchListing($listingId);
                $productId = $listing !== null ? (int) ($listing['product_id'] ?? 0) : null;
            } catch (\Throwable) {
            }

            return $fail($e->getMessage(), 0, $productId ?: null);
        }
    }

    /** @return array{success: bool, ml_item_id: string, error: string, ml_not_found?: bool} */
    public static function syncItem(int $listingId): array
    {
        $fail = static fn (string $msg, int $httpCode = 0, ?int $productId = null, ?string $mlItemId = ''): array => self::failSync(
            $listingId,
            $productId,
            $mlItemId ?? '',
            $msg,
            $httpCode
        );

        try {
            $listing = self::fetchListing($listingId);
            if ($listing === null) {
                return $fail('Listing no encontrado.');
            }

            $productId = (int) ($listing['product_id'] ?? 0);
            $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
            if ($mlItemId === '') {
                return $fail('El listing aún no fue publicado en ML.', 0, $productId > 0 ? $productId : null);
            }

            $price = $listing['price'] ?? null;
            if ($price === null || (float) $price <= 0) {
                if ($productId > 0) {
                    $markup = isset($listing['ml_markup']) && $listing['ml_markup'] !== null && $listing['ml_markup'] !== ''
                        ? (float) $listing['ml_markup']
                        : null;
                    $price = self::calculateMlPrice($productId, $markup);
                }
            }
            if ($price === null || (float) $price <= 0) {
                return $fail('No se pudo determinar el precio a sincronizar.', 0, $productId > 0 ? $productId : null, $mlItemId);
            }

            $payload = [
                'price' => round((float) $price, 2),
                'available_quantity' => self::resolveQuantity($listing),
            ];

            $result = self::apiRequest('PUT', '/items/' . rawurlencode($mlItemId), $payload, true);
            if (!$result['success']) {
                if ($result['http_code'] === 404) {
                    return self::markListingClosedMlNotFound(
                        $listingId,
                        $mlItemId,
                        $productId > 0 ? $productId : null
                    );
                }

                return $fail($result['error'], $result['http_code'], $productId > 0 ? $productId : null, $mlItemId);
            }

            // Un solo GET: contar pictures y, si ml_thumbnail es NULL, intentar thumbnail real.
            $snapshot = self::fetchMlItemSnapshot($mlItemId, 'syncItem');
            $mlThumbnailMissing = trim((string) ($listing['ml_thumbnail'] ?? '')) === '';

            $updateData = [
                'price' => round((float) $price, 2),
                'ml_pictures_count' => $snapshot['pictures_count'],
                'last_synced_at' => date('Y-m-d H:i:s'),
                'last_sync_error' => null,
            ];
            if ($mlThumbnailMissing) {
                $thumbToStore = self::sanitizeMlThumbnailForStorage((string) ($snapshot['thumbnail'] ?? ''));
                if ($thumbToStore !== null) {
                    $updateData['ml_thumbnail'] = $thumbToStore;
                    self::logInfo(
                        'syncItem',
                        "ml_item_id={$mlItemId} listing_id={$listingId}",
                        'ml_thumbnail actualizado desde GET: ' . $thumbToStore
                    );
                } else {
                    self::logInfo(
                        'syncItem',
                        "ml_item_id={$mlItemId} listing_id={$listingId}",
                        'ml_thumbnail sigue NULL (URL rechazada: processing-image, frontend/statics o resources/frontend)'
                    );
                }
            }

            self::updateListing($listingId, $updateData, 'syncItem');

            return [
                'success' => true,
                'ml_item_id' => $mlItemId,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            return $fail($e->getMessage());
        }
    }

    /**
     * Re-sincroniza solo las fotos del ítem en ML. Llamar solo cuando el usuario lo pida explícitamente.
     *
     * @return array{success: bool, ml_item_id: string, error: string, ml_not_found?: bool}
     */
    public static function syncItemPictures(int $listingId): array
    {
        $fail = static fn (string $msg, int $httpCode = 0, ?int $productId = null, ?string $mlItemId = ''): array => self::failSync(
            $listingId,
            $productId,
            $mlItemId ?? '',
            $msg,
            $httpCode
        );

        try {
            $listing = self::fetchListing($listingId);
            if ($listing === null) {
                return $fail('Listing no encontrado.');
            }

            $productId = (int) ($listing['product_id'] ?? 0);
            $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
            if ($mlItemId === '') {
                return $fail('El listing aún no fue publicado en ML.', 0, $productId > 0 ? $productId : null);
            }
            if ($productId <= 0) {
                return $fail('El listing no tiene producto asociado.', 0, null, $mlItemId);
            }

            $pictures = self::buildPictures($productId, 'syncItemPictures');
            if ($pictures === []) {
                return $fail(
                    'No hay imágenes accesibles para enviar a ML.',
                    0,
                    $productId,
                    $mlItemId
                );
            }

            $result = self::apiRequest(
                'PUT',
                '/items/' . rawurlencode($mlItemId),
                ['pictures' => $pictures],
                true
            );
            if (!$result['success']) {
                if ($result['http_code'] === 404) {
                    return self::markListingClosedMlNotFound(
                        $listingId,
                        $mlItemId,
                        $productId
                    );
                }

                return $fail($result['error'], $result['http_code'], $productId, $mlItemId);
            }

            $snapshot = self::fetchMlItemSnapshot($mlItemId, 'syncItemPictures');
            $mlThumbnailMissing = trim((string) ($listing['ml_thumbnail'] ?? '')) === '';

            $updateData = [
                'ml_pictures_count' => $snapshot['pictures_count'],
                'last_synced_at' => date('Y-m-d H:i:s'),
                'last_sync_error' => null,
            ];
            if ($mlThumbnailMissing) {
                $thumbToStore = self::sanitizeMlThumbnailForStorage((string) ($snapshot['thumbnail'] ?? ''));
                if ($thumbToStore !== null) {
                    $updateData['ml_thumbnail'] = $thumbToStore;
                    self::logInfo(
                        'syncItemPictures',
                        "ml_item_id={$mlItemId} listing_id={$listingId}",
                        'ml_thumbnail actualizado desde GET: ' . $thumbToStore
                    );
                }
            }

            self::updateListing($listingId, $updateData, 'syncItemPictures');

            return [
                'success' => true,
                'ml_item_id' => $mlItemId,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            return $fail($e->getMessage());
        }
    }

    public static function pauseItem(string $mlItemId): bool
    {
        $mlItemId = trim($mlItemId);
        if ($mlItemId === '') {
            self::logError('pauseItem', 'ml_item_id=vacio', 0, 'ID de ítem ML vacío');

            return false;
        }

        try {
            $result = self::apiRequest('PUT', '/items/' . rawurlencode($mlItemId), ['status' => 'paused'], true);
            if (!$result['success']) {
                self::logError('pauseItem', "ml_item_id={$mlItemId}", $result['http_code'], $result['error']);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            self::logError('pauseItem', "ml_item_id={$mlItemId}", 0, $e->getMessage());

            return false;
        }
    }

    public static function reactivateItem(string $mlItemId): bool
    {
        $mlItemId = trim($mlItemId);
        if ($mlItemId === '') {
            self::logError('reactivateItem', 'ml_item_id=vacio', 0, 'ID de ítem ML vacío');

            return false;
        }

        try {
            $result = self::apiRequest('PUT', '/items/' . rawurlencode($mlItemId), ['status' => 'active'], true);
            if (!$result['success']) {
                self::logError('reactivateItem', "ml_item_id={$mlItemId}", $result['http_code'], $result['error']);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            self::logError('reactivateItem', "ml_item_id={$mlItemId}", 0, $e->getMessage());

            return false;
        }
    }

    /**
     * Cierra el ítem en ML y lo elimina. Los fallos se registran en ml_errors.log;
     * el caller debe borrar el registro local aunque falle el DELETE.
     */
    public static function closeAndDeleteItem(string $mlItemId): void
    {
        $mlItemId = trim($mlItemId);
        if ($mlItemId === '') {
            return;
        }

        try {
            $close = self::apiRequest('PUT', '/items/' . rawurlencode($mlItemId), ['status' => 'closed'], true);
            if (!$close['success']) {
                self::logError(
                    'closeAndDeleteItem',
                    "ml_item_id={$mlItemId}",
                    $close['http_code'],
                    'No se pudo cerrar el ítem antes de eliminar: ' . ($close['error'] ?: 'sin detalle')
                );
            }

            $delete = self::apiRequest('DELETE', '/items/' . rawurlencode($mlItemId), null, true);
            if (!$delete['success']) {
                self::logError(
                    'closeAndDeleteItem',
                    "ml_item_id={$mlItemId}",
                    $delete['http_code'],
                    'DELETE ítem ML falló (se eliminará el listing local): ' . ($delete['error'] ?: 'sin detalle')
                );
            }
        } catch (\Throwable $e) {
            self::logError('closeAndDeleteItem', "ml_item_id={$mlItemId}", 0, $e->getMessage());
        }
    }

    /** @return array{success: bool, orders: list<array<string, mixed>>, error: string} */
    public static function getOrders(int $offset = 0): array
    {
        try {
            $userId = trim(setting('ml_user_id', '') ?? '');
            if ($userId === '') {
                return [
                    'success' => false,
                    'orders' => [],
                    'error' => 'No hay usuario ML conectado.',
                ];
            }

            $offset = max(0, $offset);
            $path = '/orders/search?seller=' . rawurlencode($userId)
                . '&sort=date_desc&offset=' . $offset;

            $result = self::apiRequest('GET', $path, null, true);
            if (!$result['success']) {
                self::logError('getOrders', "offset={$offset}", $result['http_code'], $result['error']);

                return [
                    'success' => false,
                    'orders' => [],
                    'error' => $result['error'],
                ];
            }

            $data = $result['data'];
            $orders = [];
            if (is_array($data['results'] ?? null)) {
                foreach ($data['results'] as $orderRef) {
                    if (is_array($orderRef)) {
                        $orders[] = $orderRef;
                        continue;
                    }
                    $orderId = trim((string) $orderRef);
                    if ($orderId === '') {
                        continue;
                    }
                    $detail = self::apiRequest('GET', '/orders/' . rawurlencode($orderId), null, true);
                    if ($detail['success'] && is_array($detail['data'])) {
                        $orders[] = $detail['data'];
                    }
                }
            }

            return [
                'success' => true,
                'orders' => $orders,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            self::logError('getOrders', "offset={$offset}", 0, $e->getMessage());

            return [
                'success' => false,
                'orders' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /** @param array<string, mixed> $product */
    public static function buildDescription(array $product): string
    {
        $parts = [];

        $desc = trim((string) ($product['full_description'] ?? ''));
        if ($desc === '') {
            $desc = trim((string) ($product['description'] ?? ''));
        }
        if ($desc === '') {
            $desc = trim((string) ($product['short_description'] ?? ''));
        }
        if ($desc !== '') {
            $parts[] = $desc;
        }

        $dilution = trim((string) ($product['dilution'] ?? ''));
        if ($dilution !== '') {
            $parts[] = 'Dilución: ' . $dilution;
        }

        if (isset($product['usage_cost']) && $product['usage_cost'] !== null && $product['usage_cost'] !== '') {
            $usageCost = (float) $product['usage_cost'];
            if ($usageCost > 0) {
                $parts[] = 'Costo de uso estimado: $' . number_format($usageCost, 2, ',', '.');
            }
        }

        $presentation = trim((string) ($product['presentation'] ?? ''));
        $content = trim((string) ($product['content'] ?? ''));
        if ($presentation !== '' || $content !== '') {
            $rendimiento = trim($presentation . ($presentation !== '' && $content !== '' ? ' — ' : '') . $content);
            if ($rendimiento !== '') {
                $parts[] = 'Presentación / rendimiento: ' . $rendimiento;
            }
        }

        $parts[] = self::DESCRIPTION_FOOTER;

        return implode("\n\n", $parts);
    }

    /** @return list<array<string, mixed>> */
    public static function getCategoryAttributes(string $categoryId): array
    {
        $categoryId = trim($categoryId);
        if ($categoryId === '') {
            return [];
        }

        $cacheDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/cache'
            : dirname(__DIR__, 2) . '/storage/cache';
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $categoryId) ?? $categoryId;
        $cacheFile = $cacheDir . '/ml_category_' . $safeId . '.json';

        if (is_file($cacheFile)) {
            $age = time() - (int) filemtime($cacheFile);
            if ($age < 86400) {
                $cached = json_decode((string) file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        $result = self::apiRequest(
            'GET',
            '/categories/' . rawurlencode($categoryId) . '/attributes',
            null,
            true
        );

        if (!$result['success'] || !is_array($result['data'])) {
            self::logError(
                'getCategoryAttributes',
                "category_id={$categoryId}",
                $result['http_code'],
                $result['error'] ?: 'Sin datos de atributos'
            );

            return [];
        }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $encoded = json_encode($result['data'], JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            @file_put_contents($cacheFile, $encoded);
        }

        return $result['data'];
    }

    /** @param array<string, mixed> $product */
    private static function uploadItemDescription(string $mlItemId, array $product): void
    {
        $plainText = self::buildDescription($product);
        if ($plainText === '') {
            self::logInfo('uploadItemDescription', "ml_item_id={$mlItemId}", 'descripcion vacia, PUT omitido');

            return;
        }

        $result = self::apiRequest(
            'PUT',
            '/items/' . rawurlencode($mlItemId) . '/description',
            ['plain_text' => $plainText],
            true
        );

        if ($result['success']) {
            self::logInfo(
                'uploadItemDescription',
                "ml_item_id={$mlItemId}",
                'HTTP ' . $result['http_code'] . ' OK — descripcion subida (' . strlen($plainText) . ' chars)'
            );

            return;
        }

        self::logError(
            'uploadItemDescription',
            "ml_item_id={$mlItemId}",
            $result['http_code'],
            $result['error']
        );
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array{id: string, value_name: string}>
     */
    private static function buildPublishAttributes(array $product, string $categoryId): array
    {
        $mapped = self::mapProductToMlAttributeValues($product);
        $knownIds = ['BRAND', 'MODEL', 'ALPHANUMERIC_MODEL', 'PACKAGE_TYPE', 'NET_CONTENT'];
        $attributes = [];
        $included = [];

        foreach ($knownIds as $id) {
            $value = trim((string) ($mapped[$id] ?? ''));
            if ($value === '') {
                continue;
            }
            $attributes[] = ['id' => $id, 'value_name' => $value];
            $included[$id] = true;
        }

        foreach (self::getCategoryAttributes($categoryId) as $def) {
            if (!is_array($def)) {
                continue;
            }
            $id = trim((string) ($def['id'] ?? ''));
            if ($id === '' || $id === 'NET_WEIGHT' || isset($included[$id])) {
                continue;
            }

            $tags = $def['tags'] ?? [];
            if (!is_array($tags)) {
                $tags = [];
            }
            if (empty($tags['required']) && empty($tags['catalog_required'])) {
                continue;
            }

            $value = trim((string) ($mapped[$id] ?? ''));
            if ($value === '') {
                continue;
            }

            $attributes[] = ['id' => $id, 'value_name' => $value];
            $included[$id] = true;
        }

        if (!isset($included['BRAND'])) {
            $attributes[] = ['id' => 'BRAND', 'value_name' => 'SEIQ'];
        }

        return $attributes;
    }

    /**
     * @return array{pictures_count: int, thumbnail: ?string}
     */
    private static function fetchMlItemSnapshot(string $mlItemId, string $context): array
    {
        $result = self::apiRequest('GET', '/items/' . rawurlencode($mlItemId), null, true);
        $empty = ['pictures_count' => 0, 'thumbnail' => null];

        if (!$result['success'] || !is_array($result['data'])) {
            self::logError(
                $context,
                "ml_item_id={$mlItemId}",
                $result['http_code'],
                'No se pudo verificar ítem en ML: ' . ($result['error'] ?: 'sin respuesta')
            );

            return $empty;
        }

        $data = $result['data'];
        $pictures = $data['pictures'] ?? [];
        $count = is_array($pictures) ? count($pictures) : 0;

        $secureThumbnail = trim((string) ($data['secure_thumbnail'] ?? ''));
        $thumbnailField = trim((string) ($data['thumbnail'] ?? ''));
        $firstPictureSecureUrl = '';
        if (is_array($pictures) && isset($pictures[0]) && is_array($pictures[0])) {
            $firstPictureSecureUrl = trim((string) ($pictures[0]['secure_url'] ?? ''));
        }

        self::logInfo(
            $context,
            "ml_item_id={$mlItemId}",
            'ML GET raw: secure_thumbnail=' . ($secureThumbnail !== '' ? $secureThumbnail : '(vacío)')
            . ' | thumbnail=' . ($thumbnailField !== '' ? $thumbnailField : '(vacío)')
            . ' | pictures[0].secure_url=' . ($firstPictureSecureUrl !== '' ? $firstPictureSecureUrl : '(vacío)')
        );

        $rawThumb = self::extractThumbnailFromMlItemData($data);
        $thumbnail = self::sanitizeMlThumbnailForStorage($rawThumb);

        self::logInfo(
            $context,
            "ml_item_id={$mlItemId}",
            'pictures_en_ml=' . $count
            . ' | thumbnail_resuelto=' . ($rawThumb !== '' ? $rawThumb : '(vacío)')
            . ' | guardar_en_bd=' . ($thumbnail ?? 'NULL')
        );
        if ($rawThumb !== '' && $thumbnail === null) {
            self::logInfo(
                $context,
                "ml_item_id={$mlItemId}",
                'ml_thumbnail rechazado (processing-image, frontend/statics o resources/frontend): ' . $rawThumb
            );
        }

        return ['pictures_count' => $count, 'thumbnail' => $thumbnail];
    }

    /** @param array<string, mixed> $data */
    private static function extractThumbnailFromMlItemData(array $data): string
    {
        $thumb = trim((string) ($data['secure_thumbnail'] ?? $data['thumbnail'] ?? ''));
        if ($thumb !== '' && !self::isRejectedMlThumbnailUrl($thumb)) {
            return $thumb;
        }

        $pictures = $data['pictures'] ?? [];
        if (!is_array($pictures)) {
            return '';
        }

        foreach ($pictures as $picture) {
            if (!is_array($picture)) {
                continue;
            }
            $url = trim((string) ($picture['secure_url'] ?? $picture['url'] ?? ''));
            if ($url !== '' && !self::isRejectedMlThumbnailUrl($url)) {
                return $url;
            }
        }

        return '';
    }

    private static function isRejectedMlThumbnailUrl(string $url): bool
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return true;
        }

        foreach (['processing-image', 'frontend/statics', 'resources/frontend'] as $needle) {
            if (str_contains($url, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function sanitizeMlThumbnailForStorage(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || self::isRejectedMlThumbnailUrl($url)) {
            return null;
        }

        return str_replace('http://', 'https://', $url);
    }

    /** @param array<string, mixed> $product */
    public static function unitVolumeTextForProduct(array $product): string
    {
        return self::resolveUnitVolumeSourceText($product);
    }

    /** @param array<string, mixed> $product */
    private static function resolveUnitVolumeSourceText(array $product): string
    {
        $content = trim((string) ($product['content'] ?? ''));
        $unitVolume = trim((string) ($product['unit_volume'] ?? ''));

        if ($content === '') {
            return $unitVolume;
        }

        if (preg_match('/(\d+)\s*[x×]\s*(.+)$/iu', $content, $m)) {
            return trim((string) $m[2]);
        }

        $contentLower = strtolower($content);
        $isBoxPresentation = preg_match('/\bx\s*\d+\s*u?\b/i', $content)
            || preg_match('/\bx\d{1,3}\b/', $contentLower)
            || str_contains($contentLower, 'caja')
            || str_contains($contentLower, 'pack');

        if ($isBoxPresentation) {
            return $unitVolume;
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, string>
     */
    private static function mapProductToMlAttributeValues(array $product): array
    {
        $name = trim((string) ($product['name'] ?? ''));
        $code = trim((string) ($product['code'] ?? ''));
        $packageType = self::inferPackageType($product);
        $netContent = self::extractNetContent($product);

        $mapped = [
            'BRAND' => 'SEIQ',
        ];

        if ($name !== '') {
            $mapped['MODEL'] = self::truncate($name, 255);
        }
        if ($code !== '') {
            $mapped['ALPHANUMERIC_MODEL'] = self::truncate($code, 255);
        }
        if ($packageType !== null) {
            $mapped['PACKAGE_TYPE'] = $packageType;
        }
        if ($netContent !== null) {
            $mapped['NET_CONTENT'] = $netContent;
        }

        return $mapped;
    }

    /** @param array<string, mixed> $product */
    private static function inferPackageType(array $product): ?string
    {
        $unitText = strtolower(self::resolveUnitVolumeSourceText($product));
        if ($unitText === '') {
            return null;
        }

        if (str_contains($unitText, 'sobre')) {
            return 'Sobre';
        }
        if (str_contains($unitText, 'bidón') || str_contains($unitText, 'bidon') || preg_match('/\b\d+\s*l(?:itros?|ts?)?\b/', $unitText)) {
            return 'Bidón';
        }
        if (preg_match('/\b(cc|ml)\b/', $unitText)) {
            return 'Botella';
        }

        return null;
    }

    /** @param array<string, mixed> $product */
    private static function extractNetContent(array $product): ?string
    {
        $unitText = self::resolveUnitVolumeSourceText($product);
        if ($unitText === '') {
            return null;
        }

        return self::parseNetContentFromText($unitText);
    }

    private static function parseNetContentFromText(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/(\d+)\s*[x×]\s*([\d.,]+\s*(?:litros?|lts?|lt|ml|cc|g|kg)\b)/i', $text, $m)) {
            $text = trim((string) $m[2]);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(litros?|lts?|lt)\b/i', $text, $m)) {
            $num = str_replace(',', '.', $m[1]);

            return rtrim($num, '.') . ' L';
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(ml|cc)\b/i', $text, $m)) {
            $num = str_replace(',', '.', $m[1]);

            return rtrim($num, '.') . ' ml';
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|g|gr)\b/i', $text, $m)) {
            $num = str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if ($unit === 'gr') {
                $unit = 'g';
            }

            return rtrim($num, '.') . ' ' . $unit;
        }

        return null;
    }

    public static function countProductImages(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        try {
            return (int) Database::getInstance()->fetchColumn(
                'SELECT COUNT(*) FROM product_images WHERE product_id = ?',
                [$productId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<array{source: string}> */
    public static function buildPictures(int $productId, string $logContext = 'buildPictures'): array
    {
        if ($productId <= 0) {
            return [];
        }

        try {
            $rows = Database::getInstance()->fetchAll(
                'SELECT id, filename FROM product_images WHERE product_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC',
                [$productId]
            );

            $foundCount = count($rows);
            $candidateUrls = [];
            foreach ($rows as $row) {
                $filename = basename((string) ($row['filename'] ?? ''));
                if ($filename === '') {
                    continue;
                }
                $candidateUrls[] = productImageUrl($productId, $filename);
                if (count($candidateUrls) >= self::MAX_PICTURES) {
                    break;
                }
            }

            self::logInfo(
                $logContext,
                "product_id={$productId} images_found={$foundCount}",
                'URLs: ' . ($candidateUrls === [] ? '(ninguna)' : implode(' | ', $candidateUrls))
            );

            $pictures = [];
            foreach ($candidateUrls as $url) {
                $check = self::verifyPublicUrl($url);
                self::logInfo(
                    $logContext,
                    "product_id={$productId}",
                    "HEAD {$url} => HTTP {$check['http_code']}" . ($check['accessible'] ? ' OK' : ' FAIL')
                );
                if ($check['accessible']) {
                    $pictures[] = ['source' => $url];
                }
            }

            if ($foundCount >= 1 && $pictures !== []) {
                $mlUrl = self::mlBadgePictureUrl();
                $mlCheck = self::verifyPublicUrl($mlUrl);
                self::logInfo(
                    $logContext,
                    "product_id={$productId}",
                    'HEAD ' . $mlUrl . ' => HTTP ' . $mlCheck['http_code'] . ($mlCheck['accessible'] ? ' OK' : ' FAIL')
                );
                if ($mlCheck['accessible']) {
                    array_splice($pictures, 1, 0, [['source' => $mlUrl]]);
                    if (count($pictures) > self::MAX_PICTURES) {
                        $pictures = array_slice($pictures, 0, self::MAX_PICTURES);
                    }
                }
            }

            self::logInfo(
                $logContext,
                "product_id={$productId}",
                'pictures_ready=' . count($pictures) . ' de ' . count($candidateUrls)
            );

            return $pictures;
        } catch (\Throwable $e) {
            self::logError('buildPictures', "product_id={$productId}", 0, $e->getMessage());

            return [];
        }
    }

    private static function mlBadgePictureUrl(): string
    {
        return productImagePublicBaseUrl() . self::ML_BADGE_PICTURE_PATH;
    }

    /** @return array{accessible: bool, http_code: int} */
    private static function verifyPublicUrl(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['accessible' => false, 'http_code' => 0];
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'LimpiaOeste-ML/1.0',
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'accessible' => $httpCode >= 200 && $httpCode < 400,
            'http_code' => $httpCode,
        ];
    }

    /** @return array<string, mixed>|null */
    private static function fetchListing(int $listingId): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT * FROM ml_listings WHERE id = ?',
            [$listingId]
        );
    }

    /** @return array<string, mixed>|null */
    private static function fetchProduct(int $productId): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug, c.slug AS category_leaf_slug,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    c.markup_locked AS category_markup_locked,
                    c.markup_minorista AS category_markup_minorista,
                    c.default_discount,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    pc.markup_locked AS parent_markup_locked,
                    pc.markup_minorista AS parent_markup_minorista,
                    pc.slug AS parent_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE p.id = ?',
            [$productId]
        );
    }

    /** @param array<string, mixed> $listing */
    private static function resolveQuantity(array $listing): int
    {
        if ((int) ($listing['use_real_stock'] ?? 0) === 1) {
            // Reservado para stock real futuro; hoy sigue usando override/default.
        }

        if (isset($listing['available_quantity_override']) && $listing['available_quantity_override'] !== null) {
            return max(1, (int) $listing['available_quantity_override']);
        }

        return max(1, (int) (setting('ml_default_quantity', '12') ?? '12'));
    }

    /** @param array<string, mixed> $data */
    private static function updateListing(int $listingId, array $data, string $logContext = 'updateListing'): void
    {
        $db = Database::getInstance();
        $logThumbnail = false;
        $attempted = null;

        if (array_key_exists('ml_thumbnail', $data)) {
            $attempted = $data['ml_thumbnail'];
            if ($attempted !== null && self::isRejectedMlThumbnailUrl((string) $attempted)) {
                self::logError(
                    $logContext,
                    "listing_id={$listingId}",
                    0,
                    'UPDATE ml_thumbnail bloqueado: URL rechazada ' . (string) $attempted
                );
                unset($data['ml_thumbnail']);
            } else {
                $logThumbnail = true;
            }
        }

        if ($data === []) {
            return;
        }

        $rowsAffected = $db->update('ml_listings', $data, 'id = :id', ['id' => $listingId]);

        if (!$logThumbnail) {
            return;
        }

        $attempted = $data['ml_thumbnail'] ?? $attempted;
        $attemptedLog = $attempted === null ? 'NULL' : (string) $attempted;
        self::logInfo(
            $logContext,
            "listing_id={$listingId}",
            "UPDATE ml_thumbnail filas_afectadas={$rowsAffected} valor_intentado={$attemptedLog}"
        );

        $listing = $db->fetch('SELECT ml_item_id FROM ml_listings WHERE id = ?', [$listingId]);
        $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
        if ($mlItemId === '') {
            self::logInfo(
                $logContext,
                "listing_id={$listingId}",
                'SELECT post-UPDATE omitido: ml_item_id vacío en listing'
            );

            return;
        }

        $row = $db->fetch('SELECT ml_thumbnail FROM ml_listings WHERE ml_item_id = ?', [$mlItemId]);
        $stored = $row['ml_thumbnail'] ?? null;
        $storedLog = ($stored === null || trim((string) $stored) === '') ? 'NULL' : trim((string) $stored);
        self::logInfo(
            $logContext,
            "ml_item_id={$mlItemId} listing_id={$listingId}",
            "SELECT post-UPDATE ml_thumbnail={$storedLog}"
        );
    }

    private static function getSiteId(): string
    {
        $site = trim(setting('ml_site_id', '') ?? '');
        if ($site !== '') {
            return $site;
        }

        $fromEnv = trim(Env::get('ML_SITE_ID'));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return 'MLA';
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{success: bool, http_code: int, data: array<string, mixed>|null, error: string}
     */
    private static function apiRequest(string $method, string $path, ?array $body, bool $auth): array
    {
        $url = str_starts_with($path, 'http') ? $path : self::API_BASE . $path;
        $headers = ['Accept: application/json'];

        if ($auth) {
            try {
                $token = MercadoLibreTokenManager::getValidAccessToken();
                $headers[] = 'Authorization: Bearer ' . $token;
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'data' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $jsonBody = null;
        if ($body !== null) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
            if ($jsonBody === false) {
                return [
                    'success' => false,
                    'http_code' => 0,
                    'data' => null,
                    'error' => 'No se pudo codificar el cuerpo JSON.',
                ];
            }
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'success' => false,
                'http_code' => 0,
                'data' => null,
                'error' => 'No se pudo inicializar curl.',
            ];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if ($jsonBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = $jsonBody;
        }

        curl_setopt_array($ch, $opts);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError !== '') {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'data' => null,
                'error' => 'Error de red: ' . $curlError,
            ];
        }

        /** @var array<string, mixed>|list<mixed>|null $decoded */
        $decoded = json_decode((string) $responseBody, true);

        if ($httpCode >= 400) {
            $msg = self::extractApiErrorMessage($decoded, (string) $responseBody);

            return [
                'success' => false,
                'http_code' => $httpCode,
                'data' => is_array($decoded) ? $decoded : null,
                'error' => $msg,
            ];
        }

        return [
            'success' => true,
            'http_code' => $httpCode,
            'data' => is_array($decoded) ? $decoded : null,
            'error' => '',
        ];
    }

    /** @param array<string, mixed>|list<mixed>|null $decoded */
    private static function extractApiErrorMessage(?array $decoded, string $rawBody): string
    {
        if (!is_array($decoded)) {
            return $rawBody !== '' ? $rawBody : 'Error desconocido de la API ML.';
        }

        if (isset($decoded['message']) && is_string($decoded['message']) && $decoded['message'] !== '') {
            $msg = $decoded['message'];
            if (isset($decoded['cause']) && is_array($decoded['cause'])) {
                $causes = [];
                foreach ($decoded['cause'] as $cause) {
                    if (is_array($cause)) {
                        $cMsg = trim((string) ($cause['message'] ?? $cause['code'] ?? ''));
                        if ($cMsg !== '') {
                            $causes[] = $cMsg;
                        }
                    }
                }
                if ($causes !== []) {
                    $msg .= ' — ' . implode('; ', $causes);
                }
            }

            return $msg;
        }

        if (isset($decoded['error']) && is_string($decoded['error'])) {
            return $decoded['error'];
        }

        return $rawBody !== '' ? $rawBody : 'Error desconocido de la API ML.';
    }

    /** @return array{success: bool, ml_item_id: string, error: string} */
    private static function failPublish(int $listingId, ?int $productId, string $msg, int $httpCode): array
    {
        self::logError('publishItem', self::listingContext($listingId, $productId), $httpCode, $msg);
        self::saveListingError($listingId, $msg);

        return [
            'success' => false,
            'ml_item_id' => '',
            'error' => $msg,
        ];
    }

    /**
     * El ítem ya no existe en ML; actualiza el listing local a closed.
     *
     * @return array{success: bool, ml_item_id: string, error: string, ml_not_found: bool}
     */
    private static function markListingClosedMlNotFound(int $listingId, string $mlItemId, ?int $productId): array
    {
        $ctx = self::listingContext($listingId, $productId) . " ml_item_id={$mlItemId}";
        self::logInfo('syncItem', $ctx, 'Ítem no encontrado en ML (404); listing marcado como closed.');

        self::updateListing($listingId, [
            'status' => 'closed',
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_sync_error' => null,
        ]);

        return [
            'success' => true,
            'ml_item_id' => $mlItemId,
            'error' => '',
            'ml_not_found' => true,
        ];
    }

    /** @return array{success: bool, ml_item_id: string, error: string} */
    private static function failSync(int $listingId, ?int $productId, string $mlItemId, string $msg, int $httpCode): array
    {
        self::logError('syncItem', self::listingContext($listingId, $productId), $httpCode, $msg);
        self::saveListingError($listingId, $msg);

        return [
            'success' => false,
            'ml_item_id' => $mlItemId,
            'error' => $msg,
        ];
    }

    private static function listingContext(int $listingId, ?int $productId): string
    {
        $ctx = "listing_id={$listingId}";
        if ($productId !== null && $productId > 0) {
            $ctx .= " product_id={$productId}";
        }

        return $ctx;
    }

    private static function saveListingError(int $listingId, string $msg): void
    {
        try {
            self::updateListing($listingId, ['last_sync_error' => $msg]);
        } catch (\Throwable) {
        }
    }

    private static function logError(string $method, string $context, int $httpCode, string $message): void
    {
        $logDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/logs'
            : dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $codePart = $httpCode > 0 ? (string) $httpCode : 'ERR';
        $line = sprintf(
            '[%s] ERROR %s %s: %s - %s',
            date('Y-m-d H:i:s'),
            $method,
            $context,
            $codePart,
            str_replace(["\r", "\n"], ' ', $message)
        );

        @error_log($line . PHP_EOL, 3, $logDir . '/ml_errors.log');
    }

    private static function logInfo(string $method, string $context, string $message): void
    {
        $logDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/logs'
            : dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            '[%s] INFO %s %s: %s',
            date('Y-m-d H:i:s'),
            $method,
            $context,
            str_replace(["\r", "\n"], ' ', $message)
        );

        @error_log($line . PHP_EOL, 3, $logDir . '/ml_errors.log');
    }

    private static function truncate(string $text, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }

        return substr($text, 0, $max);
    }
}
