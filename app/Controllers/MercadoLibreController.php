<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClaudeDescriptionGenerator;
use App\Helpers\Env;
use App\Helpers\MercadoLibreService;
use App\Helpers\MercadoLibreTokenManager;
use App\Helpers\MlImageImporter;
use App\Helpers\SeiqImageScraper;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;

final class MercadoLibreController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT q.*
             FROM quotes q
             WHERE COALESCE(q.is_mercadolibre, 0) = 1
             ORDER BY q.created_at DESC'
        );
        $sales = [];
        foreach ($rows as $row) {
            $quoteId = (int) ($row['id'] ?? 0);
            $stats = $this->buildQuoteStats($db, $quoteId);
            $sales[] = array_merge($row, $stats);
        }
        $this->view('ventas-ml/index', ['title' => 'Ventas ML', 'sales' => $sales]);
    }

    public function create(): void
    {
        $this->view('ventas-ml/form', [
            'title' => 'Nueva venta ML',
            'sale' => null,
            'items' => [],
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/ventas-ml/crear');
            return;
        }
        $result = $this->persistSale(null);
        if ($result['error']) {
            flash('error', $result['error']);
            redirect('/ventas-ml/crear');
            return;
        }
        flash('success', 'Venta ML guardada correctamente.');
        redirect('/ventas-ml/' . (int) $result['id']);
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $sale = $db->fetch(
            'SELECT q.*
             FROM quotes q
             WHERE q.id = ? AND COALESCE(q.is_mercadolibre, 0) = 1',
            [(int) $id]
        );
        if (!$sale) {
            flash('error', 'Venta ML no encontrada.');
            redirect('/ventas-ml');
            return;
        }
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name, p.sale_unit_label
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             WHERE qi.quote_id = ?
             ORDER BY qi.sort_order, qi.id',
            [(int) $id]
        );
        $this->view('ventas-ml/form', [
            'title' => 'Editar venta ML',
            'sale' => $sale,
            'items' => $items,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/ventas-ml/' . $id . '/editar');
            return;
        }
        $result = $this->persistSale((int) $id);
        if ($result['error']) {
            flash('error', $result['error']);
            redirect('/ventas-ml/' . $id . '/editar');
            return;
        }
        flash('success', 'Venta ML actualizada.');
        redirect('/ventas-ml/' . (int) $result['id']);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $sale = $db->fetch(
            'SELECT q.*
             FROM quotes q
             WHERE q.id = ? AND COALESCE(q.is_mercadolibre, 0) = 1',
            [(int) $id]
        );
        if (!$sale) {
            flash('error', 'Venta ML no encontrada.');
            redirect('/ventas-ml');
            return;
        }
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             WHERE qi.quote_id = ?
             ORDER BY qi.sort_order, qi.id',
            [(int) $id]
        );
        $stats = $this->buildQuoteStats($db, (int) $id);
        $this->view('ventas-ml/show', [
            'title' => 'Venta ML ' . ($sale['quote_number'] ?? ''),
            'sale' => $sale,
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    public function dashboard(): void
    {
        try {
            $db = Database::getInstance();
            $connected = MercadoLibreTokenManager::isConnected();
            $mlUserId = trim(setting('ml_user_id', '') ?? '');

            $statusRows = $db->fetchAll(
                'SELECT status, COUNT(*) AS total FROM ml_listings GROUP BY status'
            );
            $statusCounts = [
                'draft' => 0,
                'active' => 0,
                'paused' => 0,
                'closed' => 0,
            ];
            foreach ($statusRows as $row) {
                $key = (string) ($row['status'] ?? '');
                if (array_key_exists($key, $statusCounts)) {
                    $statusCounts[$key] = (int) ($row['total'] ?? 0);
                }
            }

            $lastSyncedAt = $db->fetchColumn(
                'SELECT MAX(last_synced_at) FROM ml_listings WHERE last_synced_at IS NOT NULL'
            );

            $activeSyncErrors = $db->fetchAll(
                "SELECT l.id, l.title, l.product_id, l.ml_item_id, l.last_sync_error, l.last_synced_at,
                        p.name AS product_name
                 FROM ml_listings l
                 LEFT JOIN products p ON p.id = l.product_id
                 WHERE l.status = 'active'
                   AND l.last_sync_error IS NOT NULL
                   AND TRIM(l.last_sync_error) <> ''
                 ORDER BY l.updated_at DESC
                 LIMIT 10"
            );

            $recentSyncErrors = $db->fetchAll(
                "SELECT l.id, l.title, l.product_id, l.status, l.last_sync_error, l.last_synced_at,
                        p.name AS product_name
                 FROM ml_listings l
                 LEFT JOIN products p ON p.id = l.product_id
                 WHERE l.last_sync_error IS NOT NULL
                   AND TRIM(l.last_sync_error) <> ''
                 ORDER BY l.updated_at DESC
                 LIMIT 5"
            );

            $activeListings = $db->fetchAll(
                "SELECT l.id, l.title, l.product_id, l.ml_item_id, l.price, l.available_quantity_override,
                        l.last_synced_at, l.last_sync_error, l.ml_permalink,
                        p.name AS product_name
                 FROM ml_listings l
                 LEFT JOIN products p ON p.id = l.product_id
                 WHERE l.status = 'active'
                 ORDER BY l.updated_at DESC
                 LIMIT 20"
            );

            $this->view('mercadolibre/dashboard', [
                'title' => 'MercadoLibre',
                'connected' => $connected,
                'ml_user_id' => $mlUserId,
                'status_counts' => $statusCounts,
                'last_synced_at' => $lastSyncedAt ?: null,
                'active_sync_errors' => $activeSyncErrors,
                'recent_sync_errors' => $recentSyncErrors,
                'active_listings' => $activeListings,
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cargar el panel ML: ' . $e->getMessage());
            redirect('/dashboard');
        }
    }

    public function connect(): void
    {
        try {
            $clientId = $this->mlClientId();
            $redirectUri = $this->mlRedirectUri();
            if ($clientId === '' || $redirectUri === '') {
                flash('error', 'Configurá ML_APP_ID y ML_REDIRECT_URI en el .env antes de conectar.');
                redirect('/mercadolibre');
                return;
            }

            $params = http_build_query([
                'response_type' => 'code',
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
            ]);

            redirect('https://auth.mercadolibre.com.ar/authorization?' . $params);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo iniciar la conexión con MercadoLibre: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function callback(): void
    {
        try {
            $error = trim((string) $this->query('error', ''));
            if ($error !== '') {
                $desc = trim((string) $this->query('error_description', $error));
                flash('error', 'MercadoLibre rechazó la autorización: ' . $desc);
                redirect('/mercadolibre');
                return;
            }

            $code = trim((string) $this->query('code', ''));
            if ($code === '') {
                flash('error', 'No se recibió el código de autorización de MercadoLibre.');
                redirect('/mercadolibre');
                return;
            }

            $tokenData = $this->exchangeAuthorizationCode($code);
            MercadoLibreTokenManager::saveTokens($tokenData);
            flash('success', 'Cuenta de MercadoLibre conectada correctamente.');
            redirect('/mercadolibre');
        } catch (\Throwable $e) {
            flash('error', 'No se pudo completar la conexión con MercadoLibre: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function disconnect(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre');
            return;
        }

        try {
            MercadoLibreTokenManager::revokeTokens();
            flash('success', 'Cuenta de MercadoLibre desconectada.');
        } catch (\Throwable $e) {
            flash('error', 'No se pudo desconectar: ' . $e->getMessage());
        }

        redirect('/mercadolibre');
    }

    public function bulkPublish(): void
    {
        try {
            if (!MercadoLibreTokenManager::isConnected()) {
                flash('error', 'Conectá la cuenta de MercadoLibre antes de publicar en masa.');
                redirect('/mercadolibre/conectar');

                return;
            }

            $db = Database::getInstance();
            $defaultMarkup = (float) (setting('ml_default_markup', '75') ?? '75');
            $defaultQuantity = (int) (setting('ml_default_quantity', '12') ?? '12');

            $rows = $db->fetchAll(
                'SELECT p.id, p.code, p.name, p.content, p.unit_volume, p.presentation, p.presentacion_minorista,
                        p.category_id,
                        COALESCE(pc.name, c.name) AS category_name,
                        CASE WHEN COALESCE(TRIM(p.full_description), \'\') <> \'\' THEN 1 ELSE 0 END AS has_description,
                        cov.id AS cover_image_id,
                        CASE WHEN cov.id IS NOT NULL THEN 1 ELSE 0 END AS has_photo
                 FROM products p
                 JOIN categories c ON c.id = p.category_id
                 LEFT JOIN categories pc ON c.parent_id = pc.id
                 LEFT JOIN ml_listings l ON l.product_id = p.id AND l.status = \'active\'
                 LEFT JOIN product_images cov ON cov.id = (
                     SELECT pi.id FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.is_cover DESC, pi.sort_order ASC, pi.id ASC
                     LIMIT 1
                 )
                 WHERE p.is_active = 1
                   AND l.id IS NULL
                 ORDER BY p.name ASC'
            );

            $products = [];
            foreach ($rows as $row) {
                $productId = (int) ($row['id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $price = MercadoLibreService::calculateMlPrice($productId, $defaultMarkup);
                $coverId = (int) ($row['cover_image_id'] ?? 0);
                $products[] = [
                    'id' => $productId,
                    'code' => (string) ($row['code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'category_id' => (int) ($row['category_id'] ?? 0),
                    'category_name' => (string) ($row['category_name'] ?? ''),
                    'has_description' => (int) ($row['has_description'] ?? 0) === 1,
                    'has_photo' => (int) ($row['has_photo'] ?? 0) === 1,
                    'price' => round($price, 2),
                    'price_formatted' => $price > 0 ? formatPrice($price) : '—',
                    'thumb_url' => $coverId > 0
                        ? url('/api/productos/' . $productId . '/imagen/' . $coverId . '/thumb')
                        : '',
                    'suggested_title' => MercadoLibreService::buildSuggestedTitle($row),
                ];
            }

            $categories = $db->fetchAll(
                'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC'
            );

            $this->view('mercadolibre/publicacion_masiva', [
                'title' => 'Publicación masiva ML',
                'subtitle' => 'Publicá varios productos en MercadoLibre',
                'products' => $products,
                'categories' => $categories,
                'default_markup' => $defaultMarkup,
                'default_quantity' => $defaultQuantity,
                'connected' => true,
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cargar la publicación masiva: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function bulkPublishExecute(): void
    {
        if (!verifyCsrf()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);

            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        @ob_end_clean();
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', '0');
        set_time_limit(300);

        $emit = static function (array $payload): void {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
            if (function_exists('flush')) {
                flush();
            }
        };

        try {
            if (!MercadoLibreTokenManager::isConnected()) {
                $emit(['type' => 'error', 'error' => 'Cuenta ML no conectada.']);
                return;
            }

            $rawIds = $this->input('product_ids', '[]');
            $decodedIds = is_string($rawIds) ? json_decode($rawIds, true) : $rawIds;
            if (!is_array($decodedIds)) {
                $emit(['type' => 'error', 'error' => 'Lista de productos inválida.']);
                return;
            }

            $productIds = [];
            foreach ($decodedIds as $id) {
                $pid = (int) $id;
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }
            $productIds = array_values(array_unique($productIds));

            if ($productIds === []) {
                $emit(['type' => 'error', 'error' => 'No hay productos seleccionados.']);
                return;
            }

            $markupRaw = trim((string) $this->input('ml_markup', ''));
            $mlMarkup = $markupRaw !== ''
                ? round((float) str_replace(',', '.', $markupRaw), 2)
                : (float) (setting('ml_default_markup', '75') ?? '75');

            $quantity = max(1, (int) $this->input('available_quantity', setting('ml_default_quantity', '12') ?? '12'));
            $listingType = trim((string) $this->input('listing_type_id', 'gold_special'));
            if ($listingType === '') {
                $listingType = 'gold_special';
            }

            $generateDescription = (string) $this->input('generate_description', '') === '1';

            $db = Database::getInstance();
            $total = count($productIds);
            $ok = 0;
            $fail = 0;

            $emit([
                'type' => 'start',
                'total' => $total,
                'ml_markup' => $mlMarkup,
                'quantity' => $quantity,
                'listing_type_id' => $listingType,
                'generate_description' => $generateDescription,
            ]);

            foreach ($productIds as $index => $productId) {
                if ($index > 0) {
                    usleep(300000);
                }

                $productName = '';
                try {
                    $product = $db->fetch(
                        'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug
                         FROM products p
                         JOIN categories c ON c.id = p.category_id
                         LEFT JOIN categories pc ON c.parent_id = pc.id
                         WHERE p.id = ? AND p.is_active = 1',
                        [$productId]
                    );

                    if (!$product) {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => '',
                            'status' => 'error',
                            'message' => 'Producto no encontrado o inactivo.',
                        ]);
                        continue;
                    }

                    $productName = trim((string) ($product['name'] ?? ''));

                    $activeListing = $db->fetchColumn(
                        'SELECT COUNT(*) FROM ml_listings WHERE product_id = ? AND status = \'active\'',
                        [$productId]
                    );
                    if ((int) $activeListing > 0) {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'status' => 'error',
                            'message' => 'El producto ya tiene un listing activo en ML.',
                        ]);
                        continue;
                    }

                    if (MercadoLibreService::countProductImages($productId) === 0) {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'status' => 'error',
                            'message' => 'El producto no tiene fotos. Subí al menos una imagen.',
                        ]);
                        continue;
                    }

                    if ($generateDescription && trim((string) ($product['full_description'] ?? '')) === '') {
                        $descResult = ClaudeDescriptionGenerator::generateForProduct($productId);
                        if (!$descResult['success']) {
                            $fail++;
                            $emit([
                                'type' => 'progress',
                                'index' => $index + 1,
                                'total' => $total,
                                'product_id' => $productId,
                                'product_name' => $productName,
                                'status' => 'error',
                                'message' => 'No se pudo generar la descripción: ' . ($descResult['error'] ?: 'error'),
                            ]);
                            continue;
                        }

                        $full = trim($descResult['full_description']);
                        $short = trim($descResult['short_description']);
                        $db->update(
                            'products',
                            [
                                'full_description' => $full !== '' ? $full : null,
                                'short_description' => $short !== '' ? mb_substr($short, 0, 255) : null,
                            ],
                            'id = :id',
                            ['id' => $productId]
                        );
                        $product['full_description'] = $full;
                    }

                    $title = $this->truncateText(MercadoLibreService::buildSuggestedTitle($product), 60);
                    if ($title === '') {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'status' => 'error',
                            'message' => 'No se pudo generar el título ML.',
                        ]);
                        continue;
                    }

                    $categoryId = MercadoLibreService::predictCategory($title);
                    if ($categoryId === null) {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'status' => 'error',
                            'message' => 'No se encontró categoría ML para el título.',
                        ]);
                        continue;
                    }

                    $price = MercadoLibreService::calculateMlPrice($productId, $mlMarkup);
                    if ($price <= 0) {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'status' => 'error',
                            'message' => 'No se pudo calcular el precio ML.',
                        ]);
                        continue;
                    }

                    $listingId = $db->insert('ml_listings', [
                        'product_id' => $productId,
                        'combo_id' => null,
                        'ml_category_id' => $categoryId,
                        'title' => $title,
                        'status' => 'draft',
                        'listing_type_id' => $listingType,
                        'price' => round($price, 2),
                        'ml_markup' => $mlMarkup,
                        'available_quantity_override' => $quantity,
                    ]);

                    $publishResult = MercadoLibreService::publishItem((int) $listingId);
                    if (!$publishResult['success']) {
                        $fail++;
                        $emit([
                            'type' => 'progress',
                            'index' => $index + 1,
                            'total' => $total,
                            'product_id' => $productId,
                            'product_name' => $productName,
                            'status' => 'error',
                            'listing_id' => (int) $listingId,
                            'message' => $publishResult['error'] ?: 'Error al publicar en ML.',
                        ]);
                        continue;
                    }

                    $ok++;
                    $emit([
                        'type' => 'progress',
                        'index' => $index + 1,
                        'total' => $total,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'status' => 'ok',
                        'listing_id' => (int) $listingId,
                        'ml_item_id' => (string) ($publishResult['ml_item_id'] ?? ''),
                        'message' => 'Publicado: ' . ($publishResult['ml_item_id'] ?: 'OK'),
                    ]);
                } catch (\Throwable $e) {
                    $fail++;
                    $emit([
                        'type' => 'progress',
                        'index' => $index + 1,
                        'total' => $total,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $emit([
                'type' => 'done',
                'ok' => $ok,
                'errors' => $fail,
                'total' => $total,
            ]);
        } catch (\Throwable $e) {
            $emit(['type' => 'error', 'error' => $e->getMessage()]);
        }
    }

    public function importImages(): void
    {
        try {
            $importer = new MlImageImporter();
            $this->view('mercadolibre/import_images', [
                'title' => 'Importar imágenes desde ML',
                'without_photos_count' => $importer->countActiveWithoutPhotos(),
                'products' => $importer->getActiveProductsWithPhotoStatus(),
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cargar la importación de imágenes: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function importImagesExecute(): void
    {
        if (!verifyCsrf()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');

        $importer = new MlImageImporter();

        $limitRaw = trim((string) $this->input('limit', ''));
        $limit = $limitRaw !== '' ? max(1, (int) $limitRaw) : null;
        $products = $importer->getActiveProductsWithoutPhotos($limit);
        $total = count($products);
        $imported = 0;
        $notFound = 0;
        $errors = 0;

        $emit = static function (array $payload): void {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
            if (function_exists('flush')) {
                flush();
            }
        };

        $emit([
            'type' => 'start',
            'total' => $total,
            'limit' => $limit,
        ]);

        foreach ($products as $index => $product) {
            $result = $importer->importProduct($product);
            $status = (string) ($result['status'] ?? 'error');

            if ($status === 'ok') {
                $imported++;
            } elseif ($status === 'no_encontrado') {
                $notFound++;
            } elseif ($status !== 'skipped') {
                $errors++;
            }

            $emit([
                'type' => 'progress',
                'index' => $index + 1,
                'total' => $total,
                'product_id' => (int) ($product['id'] ?? 0),
                'product_name' => (string) ($product['name'] ?? ''),
                'search_term' => (string) ($result['search_term'] ?? ''),
                'image_url' => (string) ($result['image_url'] ?? ''),
                'status' => $status,
                'message' => (string) ($result['message'] ?? ''),
            ]);
        }

        $emit([
            'type' => 'done',
            'imported' => $imported,
            'not_found' => $notFound,
            'errors' => $errors,
            'total' => $total,
        ]);
    }

    public function importSeiqImagesExecute(): void
    {
        if (!verifyCsrf()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');

        $scraper = new SeiqImageScraper();
        $emit = static function (array $payload): void {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
            if (function_exists('flush')) {
                flush();
            }
        };

        $scraper->run(static function (array $event) use ($emit): void {
            $emit($event);
        });
    }

    public function linkExisting(): void
    {
        try {
            if (!MercadoLibreTokenManager::isConnected()) {
                flash('error', 'Conectá la cuenta de MercadoLibre antes de vincular publicaciones.');
                redirect('/mercadolibre/conectar');

                return;
            }

            MercadoLibreService::diagnoseSellerItemsForLinking();
            $fetch = MercadoLibreService::fetchSellerItemsForLinking();
            if (!$fetch['success']) {
                flash('error', 'No se pudieron obtener las publicaciones de ML: ' . ($fetch['error'] ?: 'error desconocido'));
                redirect('/mercadolibre');

                return;
            }

            $db = Database::getInstance();
            $linkedRows = $db->fetchAll(
                'SELECT l.ml_item_id, l.id AS listing_id, l.title AS listing_title, l.status,
                        l.product_id, p.code AS product_code, p.name AS product_name
                 FROM ml_listings l
                 LEFT JOIN products p ON p.id = l.product_id
                 WHERE l.ml_item_id IS NOT NULL AND TRIM(l.ml_item_id) <> \'\''
            );

            $linkedByMlId = [];
            foreach ($linkedRows as $row) {
                $mlId = trim((string) ($row['ml_item_id'] ?? ''));
                if ($mlId !== '') {
                    $linkedByMlId[$mlId] = $row;
                }
            }

            $unlinked = [];
            $linked = [];
            foreach ($fetch['items'] as $item) {
                $mlId = trim((string) ($item['ml_item_id'] ?? ''));
                if ($mlId === '') {
                    continue;
                }
                if (isset($linkedByMlId[$mlId])) {
                    $linked[] = array_merge($item, [
                        'listing_id' => (int) ($linkedByMlId[$mlId]['listing_id'] ?? 0),
                        'product_id' => (int) ($linkedByMlId[$mlId]['product_id'] ?? 0),
                        'product_code' => (string) ($linkedByMlId[$mlId]['product_code'] ?? ''),
                        'product_name' => (string) ($linkedByMlId[$mlId]['product_name'] ?? ''),
                        'local_status' => (string) ($linkedByMlId[$mlId]['status'] ?? ''),
                    ]);
                } else {
                    $unlinked[] = $item;
                }
            }

            MercadoLibreService::logLinkExisting(
                'ml_user_id=' . trim(setting('ml_user_id', '') ?? ''),
                'Página cargada: ' . count($fetch['items']) . ' items ML, '
                . count($unlinked) . ' sin vincular, ' . count($linked) . ' ya vinculados'
            );

            $this->view('mercadolibre/link_existing', [
                'title' => 'Vincular publicaciones ML',
                'unlinked' => $unlinked,
                'linked' => $linked,
                'fetch_error' => '',
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cargar la vinculación: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function saveLinkExisting(): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 419);

            return;
        }

        try {
            if (!MercadoLibreTokenManager::isConnected()) {
                $this->json(['success' => false, 'error' => 'Cuenta ML no conectada.'], 403);

                return;
            }

            $batch = (string) $this->input('batch', '') === '1';
            $pairs = [];

            if ($batch) {
                $raw = $this->input('items', '[]');
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                if (!is_array($decoded)) {
                    $this->json(['success' => false, 'error' => 'Lista de items inválida.'], 400);

                    return;
                }
                foreach ($decoded as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $pairs[] = [
                        'ml_item_id' => trim((string) ($row['ml_item_id'] ?? '')),
                        'product_id' => (int) ($row['product_id'] ?? 0),
                    ];
                }
            } else {
                $pairs[] = [
                    'ml_item_id' => trim((string) $this->input('ml_item_id', '')),
                    'product_id' => (int) $this->input('product_id', 0),
                ];
            }

            if ($pairs === []) {
                $this->json(['success' => false, 'error' => 'No hay publicaciones para vincular.'], 400);

                return;
            }

            $linked = [];
            $errors = [];

            foreach ($pairs as $pair) {
                $result = $this->persistMlItemLink($pair['ml_item_id'], $pair['product_id']);
                if ($result['success']) {
                    $linked[] = $result['data'];
                } else {
                    $errors[] = [
                        'ml_item_id' => $pair['ml_item_id'],
                        'error' => $result['error'],
                    ];
                }
            }

            if ($batch) {
                $this->json([
                    'success' => $errors === [],
                    'linked' => $linked,
                    'errors' => $errors,
                    'error' => $errors !== [] ? 'Algunas vinculaciones fallaron.' : '',
                ]);

                return;
            }

            if ($linked !== []) {
                $this->json([
                    'success' => true,
                    'listing_id' => (int) ($linked[0]['listing_id'] ?? 0),
                    'ml_item_id' => (string) ($linked[0]['ml_item_id'] ?? ''),
                    'error' => '',
                ]);

                return;
            }

            $this->json([
                'success' => false,
                'error' => (string) ($errors[0]['error'] ?? 'No se pudo vincular.'),
            ], 422);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array{success: bool, data: array<string, mixed>, error: string}
     */
    private function persistMlItemLink(string $mlItemId, int $productId): array
    {
        $mlItemId = trim($mlItemId);
        if ($mlItemId === '') {
            return ['success' => false, 'data' => [], 'error' => 'ml_item_id inválido.'];
        }
        if ($productId <= 0) {
            return ['success' => false, 'data' => [], 'error' => 'Seleccioná un producto del catálogo.'];
        }

        $db = Database::getInstance();

        $existsProduct = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM products WHERE id = ? AND is_active = 1',
            [$productId]
        );
        if ($existsProduct === 0) {
            return ['success' => false, 'data' => [], 'error' => 'Producto no encontrado o inactivo.'];
        }

        $already = $db->fetch(
            'SELECT id FROM ml_listings WHERE ml_item_id = ? LIMIT 1',
            [$mlItemId]
        );
        if ($already) {
            return ['success' => false, 'data' => [], 'error' => 'Esta publicación ya está vinculada en el sistema.'];
        }

        $item = MercadoLibreService::fetchItemForLinking($mlItemId);
        if ($item === null) {
            return ['success' => false, 'data' => [], 'error' => 'No se pudo obtener el ítem desde MercadoLibre.'];
        }

        $title = $this->truncateText(trim((string) ($item['title'] ?? '')), 60);
        if ($title === '') {
            return ['success' => false, 'data' => [], 'error' => 'El ítem de ML no tiene título.'];
        }

        $qty = max(1, (int) ($item['available_quantity'] ?? 12));
        $listingId = $db->insert('ml_listings', [
            'product_id' => $productId,
            'ml_item_id' => $mlItemId,
            'ml_category_id' => ($item['ml_category_id'] ?? '') !== '' ? $item['ml_category_id'] : null,
            'title' => $title,
            'status' => (string) ($item['status'] ?? 'active'),
            'listing_type_id' => (string) ($item['listing_type_id'] ?? 'gold_special'),
            'price' => round((float) ($item['price'] ?? 0), 2),
            'available_quantity_override' => $qty,
            'ml_permalink' => ($item['ml_permalink'] ?? '') !== '' ? $item['ml_permalink'] : null,
            'ml_thumbnail' => ($item['ml_thumbnail'] ?? null) ?: null,
            'last_synced_at' => date('Y-m-d H:i:s'),
            'last_sync_error' => null,
        ]);

        return [
            'success' => true,
            'data' => [
                'listing_id' => (int) $listingId,
                'ml_item_id' => $mlItemId,
                'product_id' => $productId,
            ],
            'error' => '',
        ];
    }

    public function listings(): void
    {
        try {
            $db = Database::getInstance();
            $page = max(1, (int) $this->query('page', 1));
            $perPage = (int) $this->query('per_page', 20);
            $perPage = $perPage > 0 ? min($perPage, 100) : 20;
            $statusFilter = trim((string) $this->query('status', ''));

            $where = '1=1';
            $params = [];
            if (in_array($statusFilter, ['draft', 'active', 'paused', 'closed'], true)) {
                $where .= ' AND l.status = ?';
                $params[] = $statusFilter;
            }

            $total = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM ml_listings l WHERE {$where}",
                $params
            );
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;

            $listings = $db->fetchAll(
                "SELECT l.*, p.name AS product_name, p.code AS product_code,
                        cov.filename AS cover_filename
                 FROM ml_listings l
                 LEFT JOIN products p ON p.id = l.product_id
                 LEFT JOIN product_images cov ON cov.id = (
                     SELECT pi.id FROM product_images pi
                     WHERE pi.product_id = p.id
                     ORDER BY pi.is_cover DESC, pi.sort_order ASC, pi.id ASC
                     LIMIT 1
                 )
                 WHERE {$where}
                 ORDER BY l.updated_at DESC, l.id DESC
                 LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset,
                $params
            );

            $this->view('mercadolibre/listings', [
                'title' => 'Listings MercadoLibre',
                'listings' => $listings,
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'status_filter' => $statusFilter,
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cargar los listings: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function newListing(): void
    {
        try {
            $this->view('mercadolibre/new_listing', [
                'title' => 'Nuevo listing ML',
                'listing' => null,
                'products' => $this->activeProductsForSelect(),
                'default_markup' => (float) (setting('ml_default_markup', '75') ?? '75'),
                'default_quantity' => (int) (setting('ml_default_quantity', '12') ?? '12'),
                'initial_image_count' => 0,
                'initial_description' => '',
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo abrir el formulario: ' . $e->getMessage());
            redirect('/mercadolibre/listings');
        }
    }

    public function storeListing(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings/nueva');
            return;
        }

        try {
            $payload = $this->validateListingPayload(null);
            if ($payload['error'] !== null) {
                flash('error', $payload['error']);
                redirect('/mercadolibre/listings/nueva');
                return;
            }

            $db = Database::getInstance();
            $listingId = $db->insert('ml_listings', $payload['data']);
            $this->saveProductDescriptionIfEmpty((int) $payload['data']['product_id']);
            flash('success', 'Listing guardado como borrador.');
            redirect('/mercadolibre/listings/' . $listingId . '/editar');
        } catch (\Throwable $e) {
            flash('error', 'No se pudo guardar el listing: ' . $e->getMessage());
            redirect('/mercadolibre/listings/nueva');
        }
    }

    public function editListing(string $id): void
    {
        try {
            $listing = $this->findListing((int) $id);
            if ($listing === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $productId = (int) ($listing['product_id'] ?? 0);
            $initialDescription = '';
            if ($productId > 0) {
                $prod = Database::getInstance()->fetch(
                    'SELECT full_description, short_description, description FROM products WHERE id = ?',
                    [$productId]
                );
                if ($prod) {
                    $initialDescription = trim((string) ($prod['full_description'] ?? ''));
                    if ($initialDescription === '') {
                        $initialDescription = trim((string) ($prod['short_description'] ?? ''));
                    }
                    if ($initialDescription === '') {
                        $initialDescription = trim((string) ($prod['description'] ?? ''));
                    }
                }
            }
            $this->view('mercadolibre/new_listing', [
                'title' => 'Editar listing ML',
                'listing' => $listing,
                'products' => $this->activeProductsForSelect(),
                'default_markup' => (float) (setting('ml_default_markup', '75') ?? '75'),
                'default_quantity' => (int) (setting('ml_default_quantity', '12') ?? '12'),
                'initial_image_count' => $productId > 0 ? MercadoLibreService::countProductImages($productId) : 0,
                'initial_description' => $initialDescription,
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cargar el listing: ' . $e->getMessage());
            redirect('/mercadolibre/listings');
        }
    }

    public function updateListing(string $id): void
    {
        $listingId = (int) $id;
        if ($this->isInlineListingRequest()) {
            $this->updateListingInline($listingId);

            return;
        }

        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings/' . $listingId . '/editar');
            return;
        }

        try {
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $payload = $this->validateListingPayload($listing);
            if ($payload['error'] !== null) {
                flash('error', $payload['error']);
                redirect('/mercadolibre/listings/' . $listingId . '/editar');
                return;
            }

            Database::getInstance()->update(
                'ml_listings',
                $payload['data'],
                'id = :id',
                ['id' => $listingId]
            );
            $this->saveProductDescriptionIfEmpty((int) $payload['data']['product_id']);
            flash('success', 'Listing actualizado.');
            redirect('/mercadolibre/listings/' . $listingId . '/editar');
        } catch (\Throwable $e) {
            flash('error', 'No se pudo actualizar el listing: ' . $e->getMessage());
            redirect('/mercadolibre/listings/' . $listingId . '/editar');
        }
    }

    public function publishListing(string $id): void
    {
        $listingId = (int) $id;
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings');
            return;
        }

        try {
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $this->saveProductDescriptionIfEmpty((int) ($listing['product_id'] ?? 0));
            $result = MercadoLibreService::publishItem($listingId);
            if ($result['success']) {
                flash('success', 'Publicado en MercadoLibre: ' . ($result['ml_item_id'] ?: 'OK'));
            } else {
                flash('error', 'No se pudo publicar: ' . ($result['error'] ?: 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            flash('error', 'Error al publicar: ' . $e->getMessage());
        }

        redirect('/mercadolibre/listings');
    }

    public function syncListing(string $id): void
    {
        $listingId = (int) $id;
        if ($this->isInlineListingRequest()) {
            $this->syncListingInline($listingId);

            return;
        }

        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings');
            return;
        }

        try {
            if ($this->findListing($listingId) === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $result = MercadoLibreService::syncItem($listingId);
            if (!empty($result['ml_not_found'])) {
                flash('info', 'El ítem ya no existe en MercadoLibre; el listing se marcó como cerrado.');
            } elseif ($result['success']) {
                flash('success', 'Listing sincronizado correctamente.');
            } else {
                flash('error', 'No se pudo sincronizar: ' . ($result['error'] ?: 'Error desconocido'));
            }
        } catch (\Throwable $e) {
            flash('error', 'Error al sincronizar: ' . $e->getMessage());
        }

        redirect('/mercadolibre/listings');
    }

    public function pauseListing(string $id): void
    {
        $listingId = (int) $id;
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings');
            return;
        }

        try {
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
            if ($mlItemId === '') {
                flash('error', 'El listing aún no está publicado en ML.');
                redirect('/mercadolibre/listings');
                return;
            }

            if (MercadoLibreService::pauseItem($mlItemId)) {
                Database::getInstance()->update('ml_listings', [
                    'status' => 'paused',
                    'last_synced_at' => date('Y-m-d H:i:s'),
                    'last_sync_error' => null,
                ], 'id = :id', ['id' => $listingId]);
                flash('success', 'Listing pausado en MercadoLibre.');
            } else {
                flash('error', 'No se pudo pausar el listing en MercadoLibre.');
            }
        } catch (\Throwable $e) {
            flash('error', 'Error al pausar: ' . $e->getMessage());
        }

        redirect('/mercadolibre/listings');
    }

    public function reactivateListing(string $id): void
    {
        $listingId = (int) $id;
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings');
            return;
        }

        try {
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
            if ($mlItemId === '') {
                flash('error', 'El listing aún no está publicado en ML.');
                redirect('/mercadolibre/listings');
                return;
            }

            if (MercadoLibreService::reactivateItem($mlItemId)) {
                Database::getInstance()->update('ml_listings', [
                    'status' => 'active',
                    'last_synced_at' => date('Y-m-d H:i:s'),
                    'last_sync_error' => null,
                ], 'id = :id', ['id' => $listingId]);
                flash('success', 'Listing reactivado en MercadoLibre.');
            } else {
                flash('error', 'No se pudo reactivar el listing en MercadoLibre.');
            }
        } catch (\Throwable $e) {
            flash('error', 'Error al reactivar: ' . $e->getMessage());
        }

        redirect('/mercadolibre/listings');
    }

    public function deleteListing(string $id): void
    {
        $listingId = (int) $id;
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/listings');
            return;
        }

        try {
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                flash('error', 'Listing no encontrado.');
                redirect('/mercadolibre/listings');
                return;
            }

            $status = (string) ($listing['status'] ?? 'draft');
            $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
            $canDelete = $status === 'draft' || $status === 'closed' || $mlItemId === '';
            if (!$canDelete) {
                flash('error', 'Solo se pueden eliminar borradores, listings cerrados o los que nunca se publicaron en ML.');
                redirect('/mercadolibre/listings');
                return;
            }

            if ($mlItemId !== '') {
                MercadoLibreService::closeAndDeleteItem($mlItemId);
            }

            Database::getInstance()->delete('ml_listings', 'id = :id', ['id' => $listingId]);
            flash('success', 'Listing eliminado.');
        } catch (\Throwable $e) {
            flash('error', 'Error al eliminar: ' . $e->getMessage());
        }

        redirect('/mercadolibre/listings');
    }

    public function syncAll(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre');
            return;
        }

        set_time_limit(60);

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                "SELECT id, title FROM ml_listings WHERE status IN ('active', 'paused') ORDER BY id ASC"
            );

            $ok = 0;
            $closed = 0;
            $fail = 0;
            $errorLines = [];

            foreach ($rows as $row) {
                $listingId = (int) ($row['id'] ?? 0);
                if ($listingId <= 0) {
                    continue;
                }
                $result = MercadoLibreService::syncItem($listingId);
                if (!empty($result['ml_not_found'])) {
                    $closed++;
                    continue;
                }
                if ($result['success']) {
                    $ok++;
                    continue;
                }
                $fail++;
                $title = trim((string) ($row['title'] ?? ''));
                $errorLines[] = ($title !== '' ? $title : ('#' . $listingId))
                    . ': ' . ($result['error'] ?: 'Error desconocido');
            }

            if ($rows === []) {
                flash('info', 'No hay listings activos ni pausados para sincronizar.');
            } elseif ($fail === 0) {
                $summary = "Sincronización completa: {$ok} listing(s) actualizado(s).";
                if ($closed > 0) {
                    $summary .= " {$closed} marcado(s) como cerrado(s) (ítem inexistente en ML).";
                }
                flash('success', $summary);
            } else {
                $summary = "Sincronizados: {$ok}. Con error: {$fail}.";
                if ($closed > 0) {
                    $summary .= " Cerrados en ML inexistente: {$closed}.";
                }
                if ($errorLines !== []) {
                    $summary .= ' ' . implode(' | ', array_slice($errorLines, 0, 3));
                }
                flash('error', $summary);
            }
        } catch (\Throwable $e) {
            flash('error', 'Error en sincronización masiva: ' . $e->getMessage());
        }

        redirect('/mercadolibre');
    }

    public function orders(): void
    {
        try {
            $offset = max(0, (int) $this->query('offset', 0));
            $result = MercadoLibreService::getOrders($offset);
            $importedSalesMap = $this->fetchImportedMlSalesMap();
            $orderNetDisplay = $this->buildOrderNetDisplayMap($result['orders'], $importedSalesMap);
            $orderImportErrors = $this->buildOrderImportErrorsMap($result['orders']);

            $this->view('mercadolibre/orders', [
                'title' => 'Órdenes MercadoLibre',
                'connected' => MercadoLibreTokenManager::isConnected(),
                'orders' => $result['orders'],
                'imported_sales_map' => $importedSalesMap,
                'order_net_display' => $orderNetDisplay,
                'order_import_errors' => $orderImportErrors,
                'orders_success' => $result['success'],
                'orders_error' => $result['error'],
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            flash('error', 'No se pudieron cargar las órdenes ML: ' . $e->getMessage());
            redirect('/mercadolibre');
        }
    }

    public function importOrder(string $orderId): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/ordenes');
            return;
        }

        $orderId = trim($orderId);
        if ($orderId === '') {
            flash('error', 'ID de orden inválido.');
            redirect('/mercadolibre/ordenes');
            return;
        }

        try {
            $db = Database::getInstance();
            $existing = $this->findMlSaleByMlOrderId($db, $orderId);
            if ($existing !== null) {
                redirect('/ventas-ml/' . $existing);
                return;
            }

            $fetch = MercadoLibreService::getOrder($orderId);
            if (!$fetch['success'] || !is_array($fetch['order'])) {
                flash('error', 'No se pudo obtener la orden de ML: ' . ($fetch['error'] ?: 'error desconocido'));
                redirect('/mercadolibre/ordenes');
                return;
            }

            $payload = $this->buildSalePayloadFromMlOrder($fetch['order']);
            if (isset($payload['error']) && trim((string) $payload['error']) !== '') {
                flash('error', (string) $payload['error']);
                redirect('/mercadolibre/ordenes');
                return;
            }
            $result = $this->persistSale(null, $payload);
            if ($result['error'] !== null) {
                flash('error', $result['error']);
                redirect('/mercadolibre/ordenes');
                return;
            }

            flash('success', 'Venta ML importada desde la orden #' . $orderId . '.');
            redirect('/ventas-ml/' . (int) $result['id']);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo importar la orden: ' . $e->getMessage());
            redirect('/mercadolibre/ordenes');
        }
    }

    public function pricePreview(): void
    {
        try {
            $productId = (int) $this->query('product_id', 0);
            if ($productId <= 0) {
                $this->json(['success' => false, 'error' => 'product_id inválido'], 400);
                return;
            }

            $markupRaw = trim((string) $this->query('markup', ''));
            $markup = $markupRaw !== '' ? (float) str_replace(',', '.', $markupRaw) : null;

            $targetRaw = trim((string) $this->query('precio_objetivo', ''));
            $precioObjetivo = $targetRaw !== '' ? (float) str_replace(',', '.', $targetRaw) : null;
            if ($precioObjetivo !== null && $precioObjetivo <= 0) {
                $precioObjetivo = null;
            }

            $exists = Database::getInstance()->fetchColumn(
                'SELECT 1 FROM products WHERE id = ? AND is_active = 1',
                [$productId]
            );
            if (!$exists) {
                $this->json(['success' => false, 'error' => 'Producto no encontrado'], 404);
                return;
            }

            if ($precioObjetivo !== null) {
                $breakdown = MercadoLibreService::calculateMlPriceBreakdown($productId, null, $precioObjetivo);
            } else {
                $effectiveMarkup = $markup ?? (float) (setting('ml_default_markup', '75') ?? '75');
                $breakdown = MercadoLibreService::calculateMlPriceBreakdown($productId, $effectiveMarkup);
            }
            if ($breakdown === null) {
                $this->json(['success' => false, 'error' => 'No se pudo calcular el precio (costo base inválido).'], 422);
                return;
            }

            $this->json(array_merge(['success' => true, 'product_id' => $productId], $breakdown));
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function generateDescription(): void
    {
        try {
            $productId = (int) $this->input('product_id', 0);
            if ($productId <= 0) {
                $this->json(['success' => false, 'error' => 'product_id inválido'], 400);
                return;
            }

            $result = ClaudeDescriptionGenerator::generateForProduct($productId);
            if (!$result['success']) {
                $this->json(['success' => false, 'error' => $result['error']], 422);
                return;
            }

            $this->json([
                'success' => true,
                'descripcion' => $result['descripcion'],
                'full_description' => $result['full_description'],
                'short_description' => $result['short_description'],
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveProductCatalogDescription(): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 419);

            return;
        }

        try {
            $productId = (int) $this->input('product_id', 0);
            if ($productId <= 0) {
                $this->json(['success' => false, 'error' => 'product_id inválido'], 400);

                return;
            }

            $exists = Database::getInstance()->fetchColumn(
                'SELECT COUNT(*) FROM products WHERE id = ? AND is_active = 1',
                [$productId]
            );
            if ((int) $exists === 0) {
                $this->json(['success' => false, 'error' => 'Producto no encontrado o inactivo.'], 404);

                return;
            }

            $result = ClaudeDescriptionGenerator::generateForProduct($productId);
            if (!$result['success']) {
                $this->json(['success' => false, 'error' => $result['error']], 422);

                return;
            }

            $full = trim($result['full_description']);
            $short = trim($result['short_description']);

            Database::getInstance()->update(
                'products',
                [
                    'full_description' => $full !== '' ? $full : null,
                    'short_description' => $short !== '' ? mb_substr($short, 0, 255) : null,
                ],
                'id = :id',
                ['id' => $productId]
            );

            $this->json([
                'success' => true,
                'full' => $full,
                'short' => $short,
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function suggestCategory(): void
    {
        try {
            $title = trim((string) $this->query('title', ''));
            if ($title === '') {
                $this->json(['success' => false, 'category_id' => null, 'error' => 'Ingresá un título para sugerir categoría.'], 400);
                return;
            }

            $categoryId = MercadoLibreService::predictCategory($title);
            if ($categoryId === null) {
                $this->json([
                    'success' => false,
                    'category_id' => null,
                    'error' => 'No se encontró una categoría sugerida. Verificá la conexión con ML.',
                ]);
                return;
            }

            $this->json([
                'success' => true,
                'category_id' => $categoryId,
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'category_id' => null, 'error' => $e->getMessage()], 500);
        }
    }

    public function productImageCount(): void
    {
        try {
            $productId = (int) $this->query('product_id', 0);
            if ($productId <= 0) {
                $this->json(['success' => false, 'count' => 0, 'error' => 'product_id inválido'], 400);
                return;
            }

            $count = MercadoLibreService::countProductImages($productId);
            $this->json([
                'success' => true,
                'count' => $count,
                'has_images' => $count > 0,
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'count' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    private function saveProductDescriptionIfEmpty(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }

        $mlText = trim((string) $this->input('ml_description', ''));
        $catalogFull = trim((string) $this->input('product_full_description', ''));
        $catalogShort = trim((string) $this->input('product_short_description', ''));

        if ($mlText === '' && $catalogFull === '' && $catalogShort === '') {
            return;
        }

        if ($catalogFull === '' && $mlText !== '') {
            $catalogFull = ClaudeDescriptionGenerator::stripMlListingHeader($mlText);
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT full_description, short_description FROM products WHERE id = ?',
            [$productId]
        );
        if (!$row) {
            return;
        }

        $updates = [];
        if (trim((string) ($row['full_description'] ?? '')) === '' && $catalogFull !== '') {
            $updates['full_description'] = $catalogFull;
        }
        if (trim((string) ($row['short_description'] ?? '')) === '' && $catalogShort !== '') {
            $updates['short_description'] = mb_substr($catalogShort, 0, 255);
        }

        if ($updates !== []) {
            $db->update('products', $updates, 'id = :id', ['id' => $productId]);
        }
    }

    /** @return array<string, mixed>|null */
    private function findListing(int $listingId): ?array
    {
        if ($listingId <= 0) {
            return null;
        }

        return Database::getInstance()->fetch(
            'SELECT * FROM ml_listings WHERE id = ?',
            [$listingId]
        );
    }

    private function isInlineListingRequest(): bool
    {
        return (string) ($_POST['inline'] ?? '') === '1';
    }

    private function updateListingInline(int $listingId): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 419);

            return;
        }

        try {
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                $this->json(['success' => false, 'error' => 'Listing no encontrado.'], 404);

                return;
            }

            $productId = (int) ($listing['product_id'] ?? 0);
            if ($productId <= 0) {
                $this->json(['success' => false, 'error' => 'El listing no tiene producto asociado.'], 400);

                return;
            }

            $title = $this->truncateText(trim((string) $this->input('title', (string) ($listing['title'] ?? ''))), 60);
            if ($title === '') {
                $this->json(['success' => false, 'error' => 'El título no puede estar vacío.'], 400);

                return;
            }

            $markupRaw = trim((string) $this->input('ml_markup', ''));
            $mlMarkup = $markupRaw !== '' ? round((float) str_replace(',', '.', $markupRaw), 2) : null;

            $quantity = max(1, (int) $this->input(
                'available_quantity_override',
                (int) ($listing['available_quantity_override'] ?? 12)
            ));

            $price = MercadoLibreService::calculateMlPrice($productId, $mlMarkup);
            if ($price <= 0) {
                $this->json(['success' => false, 'error' => 'No se pudo calcular el precio ML.'], 400);

                return;
            }

            $price = round($price, 2);
            Database::getInstance()->update(
                'ml_listings',
                [
                    'title' => $title,
                    'ml_markup' => $mlMarkup,
                    'available_quantity_override' => $quantity,
                    'price' => $price,
                ],
                'id = :id',
                ['id' => $listingId]
            );

            $this->json([
                'success' => true,
                'title' => $title,
                'ml_markup' => $mlMarkup,
                'ml_markup_display' => $mlMarkup !== null ? rtrim(rtrim(number_format($mlMarkup, 2, ',', ''), '0'), ',') : '',
                'available_quantity_override' => $quantity,
                'price' => $price,
                'price_formatted' => formatPrice($price),
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function syncListingInline(int $listingId): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 419);

            return;
        }

        try {
            if ($this->findListing($listingId) === null) {
                $this->json(['success' => false, 'error' => 'Listing no encontrado.'], 404);

                return;
            }

            $result = MercadoLibreService::syncItem($listingId);
            $listing = $this->findListing($listingId);
            if ($listing === null) {
                $this->json(['success' => false, 'error' => 'Listing no encontrado.'], 404);

                return;
            }

            $mlNotFound = !empty($result['ml_not_found']);
            $success = $mlNotFound || !empty($result['success']);
            $pictures = $listing['ml_pictures_count'] ?? null;
            $picturesInt = $pictures !== null && $pictures !== '' ? (int) $pictures : null;

            $this->json([
                'success' => $success,
                'ml_not_found' => $mlNotFound,
                'error' => $success ? '' : (string) ($result['error'] ?? 'Error al sincronizar'),
                'status' => (string) ($listing['status'] ?? 'draft'),
                'price' => (float) ($listing['price'] ?? 0),
                'price_formatted' => formatPrice((float) ($listing['price'] ?? 0)),
                'ml_pictures_count' => $picturesInt,
                'last_sync_error' => trim((string) ($listing['last_sync_error'] ?? '')),
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** @return list<array<string, mixed>> */
    private function activeProductsForSelect(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT id, code, name, presentation, content
             FROM products
             WHERE is_active = 1
             ORDER BY name ASC'
        );
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array{error: ?string, data: array<string, mixed>}
     */
    private function validateListingPayload(?array $existing): array
    {
        $db = Database::getInstance();
        $productId = (int) $this->input('product_id', 0);
        if ($productId <= 0) {
            return ['error' => 'Seleccioná un producto.', 'data' => []];
        }

        $product = $db->fetch('SELECT id, name FROM products WHERE id = ? AND is_active = 1', [$productId]);
        if (!$product) {
            return ['error' => 'Producto no encontrado o inactivo.', 'data' => []];
        }

        $title = $this->truncateText(trim((string) $this->input('title', '')), 60);
        if ($title === '') {
            $title = $this->truncateText(trim((string) ($product['name'] ?? 'Producto ML')), 60);
        }
        if ($title === '') {
            return ['error' => 'Ingresá un título para MercadoLibre (máx. 60 caracteres).', 'data' => []];
        }

        $categoryId = trim((string) $this->input('ml_category_id', ''));
        $categoryId = $categoryId !== '' ? $categoryId : null;

        $markupRaw = trim((string) $this->input('ml_markup', ''));
        $mlMarkup = $markupRaw !== '' ? round((float) str_replace(',', '.', $markupRaw), 2) : null;

        $priceRaw = trim((string) $this->input('price', ''));
        if ($priceRaw !== '') {
            $price = $this->parseRequiredMoney($priceRaw);
            if ($price === null || $price <= 0) {
                return ['error' => 'El precio ingresado no es válido.', 'data' => []];
            }
        } else {
            $price = MercadoLibreService::calculateMlPrice($productId, $mlMarkup);
            if ($price <= 0) {
                return ['error' => 'No se pudo calcular el precio ML para este producto.', 'data' => []];
            }
        }

        $quantity = max(1, (int) $this->input('available_quantity_override', setting('ml_default_quantity', '12') ?? '12'));
        $listingType = trim((string) $this->input('listing_type_id', 'gold_special'));
        if ($listingType === '') {
            $listingType = 'gold_special';
        }

        $notes = trim((string) $this->input('notes', ''));
        $notes = $notes !== '' ? $notes : null;

        $data = [
            'product_id' => $productId,
            'combo_id' => null,
            'title' => $title,
            'ml_category_id' => $categoryId,
            'price' => round($price, 2),
            'ml_markup' => $mlMarkup,
            'available_quantity_override' => $quantity,
            'listing_type_id' => $listingType,
            'notes' => $notes,
        ];

        if ($existing === null) {
            $data['status'] = 'draft';
            $data['listing_type_id'] = $data['listing_type_id'] ?: 'gold_special';
        }

        return ['error' => null, 'data' => $data];
    }

    /** @return array<string, mixed> */
    private function exchangeAuthorizationCode(string $code): array
    {
        $clientId = $this->mlClientId();
        $clientSecret = $this->mlClientSecret();
        $redirectUri = $this->mlRedirectUri();
        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new \RuntimeException('Credenciales ML incompletas en .env o settings.');
        }

        $postFields = http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $ch = curl_init('https://api.mercadolibre.com/oauth/token');
        if ($ch === false) {
            throw new \RuntimeException('No se pudo inicializar curl.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            throw new \RuntimeException('Error de red al obtener token: ' . $curlError);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            $msg = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error'] ?? $decoded['error_description'] ?? $body)
                : (string) $body;
            throw new \RuntimeException("OAuth falló ({$httpCode}): {$msg}");
        }

        return $decoded;
    }

    private function mlClientId(): string
    {
        $fromSettings = trim(setting('ml_app_id', '') ?? '');
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim(Env::get('ML_APP_ID'));
    }

    private function mlClientSecret(): string
    {
        $fromSettings = trim(setting('ml_client_secret', '') ?? '');
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim(Env::get('ML_CLIENT_SECRET'));
    }

    private function mlRedirectUri(): string
    {
        $fromEnv = trim(Env::get('ML_REDIRECT_URI'));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return rtrim(Env::get('APP_URL'), '/') . '/mercadolibre/callback';
    }

    private function truncateText(string $text, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }

        return substr($text, 0, $max);
    }

    private function ensureMercadoLibreClient(Database $db): int
    {
        $row = $db->fetch(
            "SELECT id FROM clients WHERE LOWER(name) = 'mercadolibre' OR LOWER(name) = 'mercado libre' LIMIT 1"
        );
        if ($row) {
            return (int) $row['id'];
        }

        return $db->insert('clients', [
            'name' => 'MercadoLibre',
            'business_name' => 'MercadoLibre',
            'notes' => 'Cliente automático para ventas ML',
            'is_active' => 1,
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload Datos estructurados (importación ML). Si es null, usa $_POST / input().
     * @return array{id?:int,error:?string}
     */
    private function persistSale(?int $quoteId, ?array $payload = null): array
    {
        $db = Database::getInstance();
        $fromPayload = $payload !== null;

        $saleDate = trim((string) ($fromPayload ? ($payload['sale_date'] ?? date('Y-m-d')) : $this->input('sale_date', date('Y-m-d'))));
        $mlSaleTotal = $this->resolveMoneyValue(
            $fromPayload ? ($payload['ml_sale_total'] ?? null) : $this->input('ml_sale_total', '')
        );
        $mlNetAmount = $this->resolveMoneyValue(
            $fromPayload ? ($payload['ml_net_amount'] ?? null) : $this->input('ml_net_amount', '')
        );
        if ($mlSaleTotal === null || $mlNetAmount === null) {
            return ['error' => 'Ingresá el total de venta ML y el neto recibido de Mercado Pago.'];
        }

        $linesRaw = $fromPayload ? ($payload['items'] ?? []) : ($_POST['items'] ?? []);
        if (!is_array($linesRaw) || $linesRaw === []) {
            return ['error' => 'Agregá al menos un producto.'];
        }

        $title = $fromPayload ? trim((string) ($payload['title'] ?? 'Venta MercadoLibre')) : 'Venta MercadoLibre';
        $notes = $fromPayload
            ? trim((string) ($payload['notes'] ?? 'Venta registrada desde módulo Ventas ML'))
            : 'Venta registrada desde módulo Ventas ML';
        $mlOrderId = $fromPayload ? trim((string) ($payload['ml_order_id'] ?? '')) : '';
        $priceFieldUsed = $fromPayload ? trim((string) ($payload['price_field_used'] ?? 'manual_ml')) : 'manual_ml';
        if ($priceFieldUsed === '') {
            $priceFieldUsed = 'manual_ml';
        }

        $db->getPdo()->beginTransaction();
        try {
            $clientId = $this->ensureMercadoLibreClient($db);
            if ($quoteId === null) {
                $insertData = [
                    'quote_number' => $this->nextQuoteNumber($db),
                    'client_id' => $clientId,
                    'title' => $title !== '' ? $title : 'Venta MercadoLibre',
                    'notes' => $notes !== '' ? $notes : 'Venta registrada desde módulo Ventas ML',
                    'validity_days' => 1,
                    'custom_markup' => null,
                    'include_iva' => 0,
                    'is_mercadolibre' => 1,
                    'ml_net_amount' => round($mlNetAmount, 2),
                    'ml_sale_total' => round($mlSaleTotal, 2),
                    'subtotal' => 0,
                    'discount_percentage' => null,
                    'discount_amount' => null,
                    'iva_amount' => 0,
                    'total' => round($mlSaleTotal, 2),
                    'status' => 'accepted',
                    'created_at' => $saleDate . ' 00:00:00',
                ];
                if ($mlOrderId !== '' && $this->quotesHasMlOrderIdColumn($db)) {
                    $insertData['ml_order_id'] = $mlOrderId;
                }
                $quoteId = $db->insert('quotes', $insertData);
            } else {
                $exists = $db->fetch(
                    'SELECT id FROM quotes WHERE id = ? AND COALESCE(is_mercadolibre, 0) = 1',
                    [$quoteId]
                );
                if (!$exists) {
                    $db->getPdo()->rollBack();
                    return ['error' => 'Venta ML no encontrada.'];
                }
                $db->delete('quote_items', 'quote_id = :qid', ['qid' => $quoteId]);
                $updateData = [
                    'client_id' => $clientId,
                    'ml_net_amount' => round($mlNetAmount, 2),
                    'ml_sale_total' => round($mlSaleTotal, 2),
                    'total' => round($mlSaleTotal, 2),
                    'created_at' => $saleDate . ' 00:00:00',
                ];
                if ($fromPayload) {
                    if ($title !== '') {
                        $updateData['title'] = $title;
                    }
                    if ($notes !== '') {
                        $updateData['notes'] = $notes;
                    }
                    if ($mlOrderId !== '' && $this->quotesHasMlOrderIdColumn($db)) {
                        $updateData['ml_order_id'] = $mlOrderId;
                    }
                }
                $db->update('quotes', $updateData, 'id = :id', ['id' => $quoteId]);
            }

            $sort = 0;
            $computedSubtotal = 0.0;
            foreach ($linesRaw as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $unitType = QuoteLinePricing::normalizeUnitType((string) ($line['unit_type'] ?? 'caja'));
                $unitPrice = $this->resolveMoneyValue($line['unit_price'] ?? '');
                if ($productId <= 0 || $unitPrice === null || $unitPrice < 0) {
                    continue;
                }
                $product = $db->fetch(
                    'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug
                     FROM products p
                     JOIN categories c ON c.id = p.category_id
                     LEFT JOIN categories pc ON c.parent_id = pc.id
                     WHERE p.id = ?',
                    [$productId]
                );
                if (!$product) {
                    continue;
                }
                $labels = QuoteLinePricing::snapshotLabels($product, (string) ($product['category_slug'] ?? ''), $unitType);
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $computedSubtotal += $lineSubtotal;
                $individualUnit = $unitType === 'caja'
                    ? round($unitPrice / max(1, (int) ($product['units_per_box'] ?? 1)), 2)
                    : round($unitPrice, 2);
                $db->insert('quote_items', [
                    'quote_id' => $quoteId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_type' => $unitType,
                    'unit_label' => $labels['unit_label'],
                    'unit_description' => $labels['unit_description'],
                    'unit_price' => round($unitPrice, 2),
                    'individual_unit_price' => $individualUnit,
                    'subtotal' => $lineSubtotal,
                    'price_field_used' => $priceFieldUsed,
                    'discount_applied' => null,
                    'markup_applied' => null,
                    'notes' => null,
                    'sort_order' => $sort++,
                ]);
            }
            if ($sort === 0) {
                $db->getPdo()->rollBack();
                return ['error' => 'No se pudo guardar ninguna línea válida.'];
            }
            $db->update('quotes', [
                'subtotal' => round($computedSubtotal, 2),
                'ml_sale_total' => round($mlSaleTotal, 2),
                'ml_net_amount' => round($mlNetAmount, 2),
                'total' => round($mlSaleTotal, 2),
            ], 'id = :id', ['id' => $quoteId]);
            $db->getPdo()->commit();

            return ['id' => $quoteId, 'error' => null];
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            return ['error' => 'No se pudo guardar la venta ML: ' . $e->getMessage()];
        }
    }

    /** @return array{products_count:int,ml_costs:float,gain:float,items_total:float} */
    private function buildQuoteStats(Database $db, int $quoteId): array
    {
        $items = $db->fetchAll(
            'SELECT qi.quantity, qi.unit_type, qi.subtotal,
                    p.*, COALESCE(pc.slug, c.slug) AS category_slug,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    c.markup_locked AS category_markup_locked,
                    c.markup_minorista AS category_markup_minorista,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    pc.markup_locked AS parent_markup_locked,
                    pc.markup_minorista AS parent_markup_minorista
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE qi.quote_id = ?',
            [$quoteId]
        );
        $itemsTotal = 0.0;
        $costTotal = 0.0;
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitType = QuoteLinePricing::normalizeUnitType((string) ($item['unit_type'] ?? 'caja'));
            $slug = strtolower((string) ($item['category_slug'] ?? ''));
            $resolved = QuoteLinePricing::resolveListaForQuote($item, $slug, $unitType);
            $calc = PricingEngine::calculateWithListaSeiq((float) $resolved['lista_seiq'], $item, null, false);
            $costTotal += round((float) ($calc['costo'] ?? 0) * $quantity, 2);
            $itemsTotal += (float) ($item['subtotal'] ?? 0);
        }
        $sale = $db->fetch('SELECT ml_sale_total, ml_net_amount FROM quotes WHERE id = ?', [$quoteId]) ?? [];
        $totalMl = (float) ($sale['ml_sale_total'] ?? $itemsTotal);
        $neto = (float) ($sale['ml_net_amount'] ?? 0);
        $mlCosts = round($totalMl - $neto, 2);
        $gain = round($neto - $costTotal, 2);

        return [
            'products_count' => count($items),
            'ml_costs' => $mlCosts,
            'gain' => $gain,
            'items_total' => round($itemsTotal, 2),
        ];
    }

    private function parseRequiredMoney(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $normalized = str_replace(['$', ' '], '', $raw);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function resolveMoneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        return $this->parseRequiredMoney($value);
    }

    private function quotesHasMlOrderIdColumn(Database $db): bool
    {
        return (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'quotes'
               AND COLUMN_NAME = 'ml_order_id'"
        ) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSalePayloadFromMlOrder(array $order): array
    {
        $db = Database::getInstance();
        $mlOrderId = trim((string) ($order['id'] ?? ''));

        $orderDate = trim((string) ($order['date_created'] ?? $order['date_closed'] ?? ''));
        $saleDate = date('Y-m-d');
        if ($orderDate !== '') {
            try {
                $saleDate = (new \DateTime($orderDate))->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        $mlSaleTotal = round((float) ($order['total_amount'] ?? $order['paid_amount'] ?? 0), 2);
        if ($mlSaleTotal <= 0) {
            $itemsSum = 0.0;
            foreach ($this->mlOrderLineItems($order) as $row) {
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $unit = (float) ($row['unit_price'] ?? $row['full_unit_price'] ?? 0);
                $itemsSum += round($unit * $qty, 2);
            }
            if ($itemsSum > 0) {
                $mlSaleTotal = round($itemsSum, 2);
            }
        }

        $lines = [];
        foreach ($this->mlOrderLineItems($order) as $item) {
            $mlItemId = trim((string) ($item['item']['id'] ?? $item['item_id'] ?? ''));
            $title = trim((string) ($item['item']['title'] ?? $item['title'] ?? ''));
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitPrice = round((float) ($item['unit_price'] ?? $item['full_unit_price'] ?? 0), 2);

            $productId = 0;
            if ($mlItemId !== '') {
                $productId = (int) $db->fetchColumn(
                    'SELECT product_id FROM ml_listings WHERE ml_item_id = ? LIMIT 1',
                    [$mlItemId]
                );
            }

            if ($productId <= 0) {
                $displayTitle = $title !== '' ? $title : ($mlItemId !== '' ? $mlItemId : 'ítem desconocido');
                $error = 'No se encontró el producto [' . $displayTitle . '] en el catálogo. Vinculá la publicación ML primero desde Vincular publicaciones existentes.';
                $this->logMlOrderImport($mlOrderId, $error);

                return ['error' => $error];
            }

            $lines[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_type' => 'unidad',
                'unit_price' => $unitPrice,
            ];
        }

        if ($lines === []) {
            $error = 'La orden no tiene ítems importables.';
            $this->logMlOrderImport($mlOrderId, $error);

            return ['error' => $error];
        }

        $mlNetResolved = $this->resolveMlNetAmountFromOrder($order, $mlSaleTotal, $mlOrderId);
        $mlNetAmount = $mlNetResolved['amount'];
        $this->logMlOrderImport(
            $mlOrderId,
            'ml_net_amount campo=' . $mlNetResolved['source'] . ' valor=' . number_format($mlNetAmount, 2, '.', '')
        );

        return [
            'sale_date' => $saleDate,
            'ml_sale_total' => $mlSaleTotal,
            'ml_net_amount' => $mlNetAmount,
            'items' => $lines,
            'ml_order_id' => $mlOrderId,
            'title' => $mlOrderId !== '' ? ('Orden ML #' . $mlOrderId) : 'Venta MercadoLibre',
            'notes' => $mlOrderId !== ''
                ? ('Importada desde orden MercadoLibre #' . $mlOrderId)
                : 'Importada desde MercadoLibre',
            'price_field_used' => 'ml_order',
        ];
    }

    /**
     * @return array{amount: float, source: string}
     */
    private function resolveMlNetAmountFromOrder(array $order, ?float $mlSaleTotal = null, string $mlOrderId = ''): array
    {
        $payment = null;
        $payments = $order['payments'] ?? [];
        if (is_array($payments)) {
            foreach ($payments as $row) {
                if (is_array($row)) {
                    $payment = $row;
                    break;
                }
            }
        }

        if ($payment !== null && isset($payment['net_received_amount']) && is_numeric($payment['net_received_amount'])) {
            $amount = round((float) $payment['net_received_amount'], 2);
            if ($amount >= 0) {
                return [
                    'amount' => $amount,
                    'source' => 'payments[0].net_received_amount',
                ];
            }
        }

        $paymentId = trim((string) ($payment['id'] ?? ''));
        if ($paymentId !== '') {
            $fetch = MercadoLibreService::getPayment($paymentId);
            if ($fetch['success'] && is_array($fetch['payment'])) {
                $paymentData = $fetch['payment'];
                $encoded = json_encode($paymentData, JSON_UNESCAPED_UNICODE);
                $this->logMlOrderImport(
                    $mlOrderId,
                    'payment API endpoint=' . ($fetch['endpoint'] !== '' ? $fetch['endpoint'] : 'desconocido')
                    . ' payment_id=' . $paymentId
                    . ' json=' . ($encoded !== false ? $encoded : '{}')
                );

                $netFromPayment = $this->extractNetAmountFromPayment($paymentData);
                if ($netFromPayment !== null) {
                    return [
                        'amount' => $netFromPayment,
                        'source' => 'payment API net_received_amount',
                    ];
                }
            } else {
                $this->logMlOrderImport(
                    $mlOrderId,
                    'payment API falló payment_id=' . $paymentId . ' error=' . ($fetch['error'] ?: 'sin detalle')
                );
            }
        }

        if ($mlSaleTotal === null) {
            $mlSaleTotal = round((float) ($order['total_amount'] ?? $order['paid_amount'] ?? 0), 2);
        }
        $marketplaceFee = (float) ($order['marketplace_fee'] ?? 0);
        if ($mlSaleTotal > 0 && $marketplaceFee >= 0) {
            return [
                'amount' => max(0.0, round($mlSaleTotal - $marketplaceFee, 2)),
                'source' => 'total_amount - marketplace_fee (fallback)',
            ];
        }

        return [
            'amount' => 0.0,
            'source' => 'sin datos de neto',
        ];
    }

    /**
     * @param array<string, mixed> $payment
     */
    private function extractNetAmountFromPayment(array $payment): ?float
    {
        if (isset($payment['net_received_amount']) && is_numeric($payment['net_received_amount'])) {
            $amount = round((float) $payment['net_received_amount'], 2);
            if ($amount >= 0) {
                return $amount;
            }
        }

        $transactionAmount = (float) ($payment['transaction_amount'] ?? 0);
        $marketplaceFee = (float) ($payment['marketplace_fee'] ?? 0);
        if ($transactionAmount > 0) {
            return max(0.0, round($transactionAmount - $marketplaceFee, 2));
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $orders
     * @return array<string, string>
     */
    private function buildOrderImportErrorsMap(array $orders): array
    {
        $db = Database::getInstance();
        $errors = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $orderId = trim((string) ($order['id'] ?? ''));
            if ($orderId === '') {
                continue;
            }

            foreach ($this->mlOrderLineItems($order) as $item) {
                $mlItemId = trim((string) ($item['item']['id'] ?? $item['item_id'] ?? ''));
                $title = trim((string) ($item['item']['title'] ?? $item['title'] ?? ''));
                $productId = 0;
                if ($mlItemId !== '') {
                    $productId = (int) $db->fetchColumn(
                        'SELECT product_id FROM ml_listings WHERE ml_item_id = ? LIMIT 1',
                        [$mlItemId]
                    );
                }
                if ($productId <= 0) {
                    $displayTitle = $title !== '' ? $title : ($mlItemId !== '' ? $mlItemId : 'ítem desconocido');
                    $errors[$orderId] = 'No se encontró el producto [' . $displayTitle . '] en el catálogo. Vinculá la publicación ML primero desde Vincular publicaciones existentes.';
                    break;
                }
            }
        }

        return $errors;
    }

    private function logMlOrderImport(string $mlOrderId, string $message): void
    {
        $logDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/logs'
            : dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            '[%s] INFO importOrder ml_order_id=%s: %s',
            date('Y-m-d H:i:s'),
            $mlOrderId !== '' ? $mlOrderId : '(vacío)',
            str_replace(["\r", "\n"], ' ', $message)
        );

        @error_log($line . PHP_EOL, 3, $logDir . '/ml_errors.log');
    }

    private function nextQuoteNumber(Database $db): string
    {
        $prefix = setting('quote_prefix', 'LO') ?? 'LO';
        $year = (int) date('Y');
        $like = $prefix . '-' . $year . '-%';
        $last = $db->fetchColumn(
            'SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$like]
        );
        $n = 0;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $n = (int) $m[1];
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $n + 1);
    }

    /**
     * @return array<string, array{id: int, ml_net_amount: float|null, ml_sale_total: float|null}>
     */
    private function fetchImportedMlSalesMap(): array
    {
        try {
            $db = Database::getInstance();
            if (!$this->quotesHasMlOrderIdColumn($db)) {
                return [];
            }

            $rows = $db->fetchAll(
                "SELECT id, ml_order_id, ml_net_amount, ml_sale_total FROM quotes
                 WHERE COALESCE(is_mercadolibre, 0) = 1
                   AND ml_order_id IS NOT NULL AND TRIM(ml_order_id) <> ''"
            );
            $map = [];
            foreach ($rows as $row) {
                $mlOrderId = trim((string) ($row['ml_order_id'] ?? ''));
                if ($mlOrderId === '') {
                    continue;
                }
                $map[$mlOrderId] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'ml_net_amount' => isset($row['ml_net_amount']) && $row['ml_net_amount'] !== null
                        ? round((float) $row['ml_net_amount'], 2)
                        : null,
                    'ml_sale_total' => isset($row['ml_sale_total']) && $row['ml_sale_total'] !== null
                        ? round((float) $row['ml_sale_total'], 2)
                        : null,
                ];
            }

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Neto a mostrar por orden: venta importada → ml_net_amount guardado; si no, resuelto desde API.
     *
     * @param list<array<string, mixed>> $orders
     * @param array<string, array{id: int, ml_net_amount: float|null, ml_sale_total: float|null}> $importedSalesMap
     * @return array<string, array{amount: float, source: string, from_system: bool}>
     */
    private function buildOrderNetDisplayMap(array $orders, array $importedSalesMap): array
    {
        $display = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $orderId = trim((string) ($order['id'] ?? ''));
            if ($orderId === '') {
                continue;
            }

            if (isset($importedSalesMap[$orderId]) && $importedSalesMap[$orderId]['ml_net_amount'] !== null) {
                $display[$orderId] = [
                    'amount' => (float) $importedSalesMap[$orderId]['ml_net_amount'],
                    'source' => 'sistema (venta ML importada)',
                    'from_system' => true,
                ];
                continue;
            }

            $mlSaleTotal = round((float) ($order['total_amount'] ?? $order['paid_amount'] ?? 0), 2);
            $resolved = $this->resolveMlNetAmountFromOrder(
                $order,
                $mlSaleTotal > 0 ? $mlSaleTotal : null,
                $orderId
            );
            $display[$orderId] = [
                'amount' => $resolved['amount'],
                'source' => $resolved['source'],
                'from_system' => false,
            ];
        }

        return $display;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mlOrderLineItems(array $order): array
    {
        $raw = $order['items'] ?? $order['order_items'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function findMlSaleByMlOrderId(Database $db, string $mlOrderId): ?int
    {
        if (!$this->quotesHasMlOrderIdColumn($db)) {
            return null;
        }

        $row = $db->fetch(
            'SELECT id FROM quotes
             WHERE ml_order_id = ? AND COALESCE(is_mercadolibre, 0) = 1
             LIMIT 1',
            [$mlOrderId]
        );

        return $row ? (int) ($row['id'] ?? 0) : null;
    }
}
