<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Análisis de precios competitivos en MercadoLibre vía búsqueda por categoría (OAuth).
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
    private const SEARCH_LOG_LIMIT = 5;
    private const CATEGORY_SEARCH_LIMIT = 20;
    private const COMPETITOR_PICK_LIMIT = 5;

    /** @var list<string> Misma lista que SeiqImageScraper::MATCH_ABBREVIATIONS */
    private const MATCH_ABBREVIATIONS = [
        'desengras.',
        'limp.',
        'limpiad.',
        'desinfec.',
        'gr.',
        'gral.',
    ];

    private static int $loggedSearchCount = 0;

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

    public static function clearAllCache(): int
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            return 0;
        }

        $deleted = 0;
        $files = glob($dir . '/ml_price_intel_*.json') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function resetSearchLogCounter(): void
    {
        self::$loggedSearchCount = 0;
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
        $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));

        $cost = self::resolveProductCost($productId);
        $minPrice = $cost > 0 ? self::calculateMinAcceptablePrice($cost) : 0.0;

        $categoryId = self::resolveListingCategoryId($listing);
        if ($categoryId === '') {
            $result = self::buildAnalysisResult(
                $currentPrice,
                $minPrice,
                0.0,
                0.0,
                self::STATUS_OK,
                [],
                'No se pudo determinar categoría ML.',
                0.0,
                ''
            );
            self::saveCache($listingId, $result);

            return $result;
        }

        $search = self::searchCompetitorsByCategory(
            $categoryId,
            $mlUserId,
            $mlItemId,
            $listingId,
            $currentPrice
        );
        if (!$search['success']) {
            $result = self::buildAnalysisResult(
                $currentPrice,
                $minPrice,
                0.0,
                0.0,
                self::STATUS_OK,
                [],
                $search['error'],
                0.0,
                $search['search_label'] ?? ''
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
            $cost,
            $search['search_label'] ?? ''
        );
        self::saveCache($listingId, $result);

        return $result;
    }

    public static function resolveListingCategoryId(array $listing): string
    {
        $categoryId = trim((string) ($listing['ml_category_id'] ?? ''));
        if ($categoryId !== '') {
            return $categoryId;
        }

        $title = trim((string) ($listing['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($listing['product_name'] ?? ''));
        }
        if ($title === '') {
            return '';
        }

        $predicted = MercadoLibreService::predictCategory($title);

        return $predicted !== null ? trim($predicted) : '';
    }

    public static function buildSearchQuery(string $productName): string
    {
        $term = self::normalizeProductNameForSearch($productName);
        if ($term === '') {
            return 'seiq';
        }

        if (preg_match('/\bseiq\b/u', $term) === 1) {
            return $term;
        }

        return $term . ' seiq';
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

    /**
     * @return array{
     *   success: bool,
     *   error: string,
     *   search_label: string,
     *   raw_results_count: int,
     *   competitors: list<array<string, mixed>>
     * }
     */
    public static function searchCompetitorsByCategory(
        string $categoryId,
        string $mlUserId,
        string $excludeItemId = '',
        int $listingId = 0,
        float $currentPrice = 0.0
    ): array {
        $siteId = trim((string) (setting('ml_site_id', 'MLA') ?? 'MLA'));
        if ($siteId === '') {
            $siteId = 'MLA';
        }

        $categoryId = trim($categoryId);
        $searchLabel = 'category=' . $categoryId;
        $query = http_build_query([
            'category' => $categoryId,
            'limit' => self::CATEGORY_SEARCH_LIMIT,
        ]);
        $url = self::API_BASE . '/sites/' . rawurlencode($siteId) . '/search?' . $query;

        try {
            $accessToken = MercadoLibreTokenManager::getValidAccessToken();
        } catch (\Throwable $e) {
            $error = 'No se pudo obtener token ML: ' . $e->getMessage();
            self::logSearchIfNeeded($listingId, $searchLabel, false, 0, 0, 0, $error);

            return [
                'success' => false,
                'error' => $error,
                'search_label' => $searchLabel,
                'raw_results_count' => 0,
                'competitors' => [],
            ];
        }

        $response = self::httpGet($url, $accessToken);
        if (!$response['success']) {
            self::logSearchIfNeeded(
                $listingId,
                $searchLabel,
                false,
                (int) ($response['http_code'] ?? 0),
                0,
                0,
                $response['error']
            );

            return [
                'success' => false,
                'error' => $response['error'],
                'search_label' => $searchLabel,
                'raw_results_count' => 0,
                'competitors' => [],
            ];
        }

        $data = $response['data'];
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        $rawCount = count($results);
        $candidates = [];
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

            $price = (float) ($item['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $candidates[] = [
                'item_id' => $itemId,
                'title' => trim((string) ($item['title'] ?? '')),
                'price' => round($price, 2),
                'sold_quantity' => (int) ($item['sold_quantity'] ?? 0),
                'seller_reputation' => self::formatSellerReputation($seller),
                'seller_nickname' => trim((string) ($seller['nickname'] ?? '')),
                'permalink' => trim((string) ($item['permalink'] ?? '')),
            ];
        }

        $competitors = self::pickClosestByPrice($candidates, $currentPrice, self::COMPETITOR_PICK_LIMIT);

        self::logSearchIfNeeded(
            $listingId,
            $searchLabel,
            true,
            (int) ($response['http_code'] ?? 200),
            $rawCount,
            count($competitors),
            'ok candidates=' . count($candidates)
        );

        return [
            'success' => true,
            'error' => '',
            'search_label' => $searchLabel,
            'raw_results_count' => $rawCount,
            'competitors' => $competitors,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return list<array<string, mixed>>
     */
    private static function pickClosestByPrice(array $candidates, float $currentPrice, int $limit): array
    {
        if ($candidates === []) {
            return [];
        }

        usort($candidates, static function (array $a, array $b) use ($currentPrice): int {
            $priceA = (float) ($a['price'] ?? 0);
            $priceB = (float) ($b['price'] ?? 0);
            $diffA = abs($priceA - $currentPrice);
            $diffB = abs($priceB - $currentPrice);

            if ($diffA === $diffB) {
                return $priceA <=> $priceB;
            }

            return $diffA <=> $diffB;
        });

        return array_slice($candidates, 0, $limit);
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

    private static function normalizeProductNameForSearch(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\([^)]*\)/u', '', $value) ?? $value;
        $value = preg_replace('/\bp\s*\/\s*/iu', ' ', $value) ?? $value;
        $value = preg_replace('/\bc\s*\/\s*/iu', ' ', $value) ?? $value;

        $value = mb_strtolower($value, 'UTF-8');

        foreach (self::MATCH_ABBREVIATIONS as $abbr) {
            $value = str_replace($abbr, ' ', $value);
        }

        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
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
        float $cost = 0.0,
        string $searchQuery = ''
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
            'search_query' => $searchQuery,
            'error' => $error,
        ];
    }

    /** @return array<string, mixed> */
    private static function emptyAnalysis(string $error): array
    {
        return self::buildAnalysisResult(0.0, 0.0, 0.0, 0.0, self::STATUS_OK, [], $error);
    }

    private static function logSearchIfNeeded(
        int $listingId,
        string $query,
        bool $success,
        int $httpCode,
        int $rawCount,
        int $filteredCount,
        string $detail
    ): void {
        if (self::$loggedSearchCount >= self::SEARCH_LOG_LIMIT) {
            return;
        }

        self::$loggedSearchCount++;
        $context = 'listing_id=' . ($listingId > 0 ? (string) $listingId : 'n/a');
        $message = sprintf(
            'query="%s" http=%d raw_results=%d filtered=%d detail=%s',
            $query,
            $httpCode,
            $rawCount,
            $filteredCount,
            str_replace(["\r", "\n"], ' ', $detail)
        );

        if ($success) {
            self::logInfo('searchByCategory', $context, $message);
        } else {
            self::logError('searchByCategory', $context, $httpCode, $message);
        }
    }

    private static function logError(string $method, string $context, int $httpCode, string $message): void
    {
        $logDir = self::logsDir();
        $codePart = $httpCode > 0 ? (string) $httpCode : 'ERR';
        $line = sprintf(
            '[%s] ERROR %s %s: %s - %s',
            date('Y-m-d H:i:s'),
            $method,
            $context,
            $codePart,
            str_replace(["\r", "\n"], ' ', $message)
        );

        error_log($line . PHP_EOL, 3, $logDir . '/ml_errors.log');
    }

    private static function logInfo(string $method, string $context, string $message): void
    {
        $logDir = self::logsDir();
        $line = sprintf(
            '[%s] INFO %s %s: %s',
            date('Y-m-d H:i:s'),
            $method,
            $context,
            str_replace(["\r", "\n"], ' ', $message)
        );

        error_log($line . PHP_EOL, 3, $logDir . '/ml_errors.log');
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

    /** @return array{success: bool, error: string, http_code: int, data: array<string, mixed>|null} */
    private static function httpGet(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['success' => false, 'error' => 'No se pudo inicializar la conexión.', 'http_code' => 0, 'data' => null];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_USERAGENT => 'LimpiaOeste-ML-PriceIntel/1.0',
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            return [
                'success' => false,
                'error' => 'Error de red: ' . ($curlError !== '' ? $curlError : 'sin respuesta'),
                'http_code' => $httpCode,
                'data' => null,
            ];
        }

        if ($httpCode >= 400) {
            return [
                'success' => false,
                'error' => "API ML respondió HTTP {$httpCode}.",
                'http_code' => $httpCode,
                'data' => null,
            ];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Respuesta inválida de ML.', 'http_code' => $httpCode, 'data' => null];
        }

        return ['success' => true, 'error' => '', 'http_code' => $httpCode, 'data' => $decoded];
    }

    private static function logsDir(): string
    {
        $logDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/logs'
            : dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        return $logDir;
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
