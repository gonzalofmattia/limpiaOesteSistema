<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Análisis de precios competitivos en MercadoLibre vía búsqueda pública.
 */
final class MlPriceIntelligence
{
    private const API_BASE = 'https://api.mercadolibre.com';
    private const CACHE_TTL = 86400;
    private const ML_COMMISSION_FACTOR = 0.88;
    private const MIN_MARGIN_FACTOR = 1.4;
    private const STATUS_OK = 'Precio OK';
    private const STATUS_CHEAP = 'Estás caro';
    private const STATUS_RAISE = 'Podés subir';

    /** @return list<array<string, mixed>> */
    public static function fetchActiveListings(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT l.id, l.product_id, l.ml_item_id, l.title, l.price, l.ml_category_id,
                    l.ml_thumbnail, l.ml_permalink, l.status,
                    p.name AS product_name, p.code AS product_code,
                    cov.filename AS cover_filename
             FROM ml_listings l
             INNER JOIN products p ON p.id = l.product_id AND p.is_active = 1
             LEFT JOIN product_images cov ON cov.id = (
                 SELECT pi.id FROM product_images pi
                 WHERE pi.product_id = p.id
                 ORDER BY pi.is_cover DESC, pi.sort_order ASC, pi.id ASC
                 LIMIT 1
             )
             WHERE l.status = ?
               AND l.ml_item_id IS NOT NULL AND TRIM(l.ml_item_id) <> \'\'
             ORDER BY p.name ASC',
            ['active']
        );
    }

    /** @param array<string, mixed> $listing */
    public static function analyzeListing(array $listing, string $mlUserId, bool $forceRefresh = false): array
    {
        $listingId = (int) ($listing['id'] ?? 0);
        if ($listingId <= 0) {
            return self::emptyAnalysis('Listing inválido.');
        }

        if (!$forceRefresh) {
            $cached = self::loadCache($listingId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $productId = (int) ($listing['product_id'] ?? 0);
        $currentPrice = round((float) ($listing['price'] ?? 0), 2);
        $title = trim((string) ($listing['title'] ?? ''));
        $categoryId = trim((string) ($listing['ml_category_id'] ?? ''));
        $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));

        $cost = self::resolveProductCost($productId);
        $minPrice = $cost > 0 ? self::calculateMinAcceptablePrice($cost) : 0.0;

        if ($title === '' || $categoryId === '') {
            $result = self::buildAnalysisResult(
                $currentPrice,
                $minPrice,
                0.0,
                0.0,
                self::STATUS_OK,
                [],
                'Faltan título o categoría ML.'
            );
            self::saveCache($listingId, $result);

            return $result;
        }

        $search = self::searchCompetitors($title, $categoryId, $mlUserId, $mlItemId);
        if (!$search['success']) {
            $result = self::buildAnalysisResult(
                $currentPrice,
                $minPrice,
                0.0,
                0.0,
                self::STATUS_OK,
                [],
                $search['error']
            );
            self::saveCache($listingId, $result);

            return $result;
        }

        $competitors = $search['competitors'];
        $avgPrice = 0.0;
        if ($competitors !== []) {
            $sum = 0.0;
            foreach ($competitors as $c) {
                $sum += (float) ($c['price'] ?? 0);
            }
            $avgPrice = round($sum / count($competitors), 2);
        }

        $suggestedPrice = $avgPrice > 0 ? max($avgPrice, $minPrice) : 0.0;
        if ($suggestedPrice > 0) {
            $suggestedPrice = round($suggestedPrice, 2);
        }

        $status = self::determineStatus($currentPrice, $avgPrice);

        $result = self::buildAnalysisResult(
            $currentPrice,
            $minPrice,
            $avgPrice,
            $suggestedPrice,
            $status,
            $competitors,
            null,
            $cost
        );
        self::saveCache($listingId, $result);

        return $result;
    }

    public static function calculateMinAcceptablePrice(float $cost): float
    {
        if ($cost <= 0) {
            return 0.0;
        }

        return round($cost * self::MIN_MARGIN_FACTOR / self::ML_COMMISSION_FACTOR, 2);
    }

    public static function determineStatus(float $currentPrice, float $avgCompetitorPrice): string
    {
        if ($currentPrice <= 0 || $avgCompetitorPrice <= 0) {
            return self::STATUS_OK;
        }

        if ($avgCompetitorPrice > $currentPrice * 1.05) {
            return self::STATUS_RAISE;
        }

        if ($currentPrice > $avgCompetitorPrice * 1.10) {
            return self::STATUS_CHEAP;
        }

        return self::STATUS_OK;
    }

    /** @return array{success: bool, error: string, competitors: list<array<string, mixed>>} */
    public static function searchCompetitors(
        string $title,
        string $categoryId,
        string $mlUserId,
        string $excludeItemId = ''
    ): array {
        $siteId = trim((string) (setting('ml_site_id', 'MLA') ?? 'MLA'));
        if ($siteId === '') {
            $siteId = 'MLA';
        }

        $query = http_build_query([
            'q' => $title,
            'category' => $categoryId,
            'limit' => 10,
        ]);
        $url = self::API_BASE . '/sites/' . rawurlencode($siteId) . '/search?' . $query;

        $response = self::httpGet($url);
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'competitors' => []];
        }

        $data = $response['data'];
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        $competitors = [];
        $ownUserId = trim($mlUserId);

        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemId = trim((string) ($item['id'] ?? ''));
            if ($itemId === '' || ($excludeItemId !== '' && $itemId === $excludeItemId)) {
                continue;
            }

            $seller = is_array($item['seller'] ?? null) ? $item['seller'] : [];
            $sellerId = trim((string) ($seller['id'] ?? ''));
            if ($ownUserId !== '' && $sellerId !== '' && $sellerId === $ownUserId) {
                continue;
            }

            if (($item['buying_mode'] ?? '') !== 'buy_it_now') {
                continue;
            }

            $condition = is_array($item['condition'] ?? null)
                ? (string) ($item['condition']['id'] ?? '')
                : (string) ($item['condition'] ?? '');
            if ($condition !== 'new') {
                continue;
            }

            $price = (float) ($item['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $competitors[] = [
                'item_id' => $itemId,
                'title' => trim((string) ($item['title'] ?? '')),
                'price' => round($price, 2),
                'sold_quantity' => (int) ($item['sold_quantity'] ?? 0),
                'seller_reputation' => self::formatSellerReputation($seller),
                'permalink' => trim((string) ($item['permalink'] ?? '')),
            ];

            if (count($competitors) >= 5) {
                break;
            }
        }

        return ['success' => true, 'error' => '', 'competitors' => $competitors];
    }

    /** @return array<string, mixed>|null */
    public static function loadCache(int $listingId): ?array
    {
        $file = self::cacheFilePath($listingId);
        if (!is_file($file)) {
            return null;
        }

        $age = time() - (int) filemtime($file);
        if ($age >= self::CACHE_TTL) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return null;
        }

        $decoded['cached_at'] = date('Y-m-d H:i:s', (int) filemtime($file));
        $decoded['from_cache'] = true;

        return $decoded;
    }

    /** @param array<string, mixed> $data */
    public static function saveCache(int $listingId, array $data): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $data['analyzed_at'] = date('Y-m-d H:i:s');
        $data['from_cache'] = false;
        unset($data['cached_at']);

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            @file_put_contents(self::cacheFilePath($listingId), $encoded);
        }
    }

    /** @return array{success: bool, error: string, new_price: float} */
    public static function applySuggestedPrice(int $listingId): array
    {
        $cached = self::loadCache($listingId);
        if ($cached === null) {
            return ['success' => false, 'error' => 'No hay análisis en caché. Ejecutá el análisis primero.', 'new_price' => 0.0];
        }

        $suggested = round((float) ($cached['suggested_price'] ?? 0), 2);
        if ($suggested <= 0) {
            return ['success' => false, 'error' => 'No hay precio sugerido válido.', 'new_price' => 0.0];
        }

        $listing = Database::getInstance()->fetch('SELECT id, price, ml_item_id FROM ml_listings WHERE id = ?', [$listingId]);
        if ($listing === null) {
            return ['success' => false, 'error' => 'Listing no encontrado.', 'new_price' => 0.0];
        }

        $current = round((float) ($listing['price'] ?? 0), 2);
        if (abs($current - $suggested) < 0.01) {
            return ['success' => true, 'error' => '', 'new_price' => $suggested];
        }

        Database::getInstance()->update(
            'ml_listings',
            ['price' => $suggested, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$listingId]
        );

        $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
        if ($mlItemId !== '') {
            $sync = MercadoLibreService::syncItem($listingId);
            if (!$sync['success']) {
                return [
                    'success' => false,
                    'error' => 'Precio guardado localmente pero falló sync ML: ' . ($sync['error'] ?: 'Error desconocido'),
                    'new_price' => $suggested,
                ];
            }
        }

        $cached['current_price'] = $suggested;
        $cached['status'] = self::determineStatus($suggested, (float) ($cached['avg_competitor_price'] ?? 0));
        self::saveCache($listingId, $cached);

        return ['success' => true, 'error' => '', 'new_price' => $suggested];
    }

    public static function statusRaiseLabel(): string
    {
        return self::STATUS_RAISE;
    }

    private static function resolveProductCost(int $productId): float
    {
        if ($productId <= 0) {
            return 0.0;
        }

        $breakdown = MercadoLibreService::calculateMlPriceBreakdown($productId);
        if ($breakdown === null) {
            return 0.0;
        }

        return (float) ($breakdown['costo_base'] ?? 0.0);
    }

    /**
     * @param list<array<string, mixed>> $competitors
     * @return array<string, mixed>
     */
    private static function buildAnalysisResult(
        float $currentPrice,
        float $minPrice,
        float $avgPrice,
        float $suggestedPrice,
        string $status,
        array $competitors,
        ?string $error = null,
        float $cost = 0.0
    ): array {
        return [
            'current_price' => $currentPrice,
            'cost' => round($cost, 2),
            'min_acceptable_price' => $minPrice,
            'avg_competitor_price' => $avgPrice,
            'suggested_price' => $suggestedPrice,
            'status' => $status,
            'competitors_count' => count($competitors),
            'competitors' => $competitors,
            'error' => $error,
        ];
    }

    /** @return array<string, mixed> */
    private static function emptyAnalysis(string $error): array
    {
        return self::buildAnalysisResult(0.0, 0.0, 0.0, 0.0, self::STATUS_OK, [], $error);
    }

    /** @param array<string, mixed> $seller */
    private static function formatSellerReputation(array $seller): string
    {
        $rep = is_array($seller['seller_reputation'] ?? null) ? $seller['seller_reputation'] : [];
        $level = trim((string) ($rep['level_id'] ?? ''));
        $power = trim((string) ($rep['power_seller_status'] ?? ''));

        $parts = [];
        if ($power !== '') {
            $parts[] = $power === 'platinum' ? 'Platinum' : ($power === 'gold' ? 'Gold' : ucfirst($power));
        }
        if ($level !== '') {
            $parts[] = str_replace('_', ' ', $level);
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /** @return array{success: bool, error: string, data: array<string, mixed>|null} */
    private static function httpGet(string $url): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['success' => false, 'error' => 'No se pudo inicializar la conexión.', 'data' => null];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'LimpiaOeste-ML-PriceIntel/1.0',
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            return ['success' => false, 'error' => 'Error de red: ' . $curlError, 'data' => null];
        }

        if ($httpCode >= 400) {
            return ['success' => false, 'error' => "API ML respondió HTTP {$httpCode}.", 'data' => null];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Respuesta inválida de ML.', 'data' => null];
        }

        return ['success' => true, 'error' => '', 'data' => $decoded];
    }

    private static function cacheDir(): string
    {
        return defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/cache'
            : dirname(__DIR__, 2) . '/storage/cache';
    }

    private static function cacheFilePath(int $listingId): string
    {
        return self::cacheDir() . '/ml_price_intel_' . $listingId . '.json';
    }
}
