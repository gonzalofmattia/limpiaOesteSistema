<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CategoryHierarchy;
use App\Helpers\ImageUploader;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;

final class ApiController extends Controller
{
    public function searchProducts(): void
    {
        $q = trim((string) $this->query('q', ''));
        if (strlen($q) < 2) {
            $this->json(['results' => []]);
            return;
        }
        $supplierSlug = trim((string) $this->query('supplier', ''));
        $db = Database::getInstance();
        $like = '%' . $q . '%';
        $params = [$like, $like];
        $supplierSql = '';
        if ($supplierSlug !== '') {
            $supplierSql = ' AND s.slug = ? ';
            $params[] = $supplierSlug;
        }
        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.sale_unit_label, p.sale_unit_type, p.sale_unit_description,
                    p.units_per_box, p.content, p.stock_units, COALESCE(p.stock_committed_units, 0) AS stock_committed_units,
                    c.name AS category_name, c.slug AS category_slug,
                    pc.name AS parent_category_name,
                    s.name AS supplier_name,
                    s.slug AS supplier_slug,
                    c.presentation_info AS category_presentation_info
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE p.is_active = 1 AND (p.code LIKE ? OR p.name LIKE ?) ' . $supplierSql . '
             ORDER BY p.name
             LIMIT 30',
            $params
        );
        foreach ($rows as &$r) {
            $parent = trim((string) ($r['parent_category_name'] ?? ''));
            $leaf = trim((string) ($r['category_name'] ?? ''));
            $pres = trim((string) ($r['category_presentation_info'] ?? ''));
            $line = $parent !== '' ? $parent . ' > ' . $leaf : $leaf;
            if ($pres !== '') {
                $line .= ' — ' . $pres;
            }
            $supplier = trim((string) ($r['supplier_name'] ?? ''));
            if ($supplier !== '') {
                $line = $supplier . ' > ' . $line;
            }
            $stockUnits = max(0, (int) ($r['stock_units'] ?? 0));
            $committedUnits = max(0, (int) ($r['stock_committed_units'] ?? 0));
            $r['stock_available_units'] = $stockUnits - $committedUnits;
            $r['category_context'] = $line;
        }
        unset($r);
        $this->json(['results' => $rows]);
    }

    public function getProductPrice(string $id): void
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT p.*,
                    COALESCE(pc.slug, c.slug) AS category_slug,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE p.id = ? AND p.is_active = 1',
            [(int) $id]
        );
        if (!$row) {
            $this->json(['error' => 'No encontrado'], 404);
            return;
        }
        $markupParam = $this->query('markup', '');
        $ov = $markupParam !== '' && is_numeric(str_replace(',', '.', (string) $markupParam))
            ? (float) str_replace(',', '.', (string) $markupParam)
            : null;
        $includeIva = (int) $this->query('include_iva', 0) === 1;
        $unitMode = (string) $this->query('unit_type', 'caja');
        if ($unitMode !== 'unidad') {
            $unitMode = 'caja';
        }
        $slug = strtolower((string) $row['category_slug']);
        $resolved = QuoteLinePricing::resolveListaForQuote($row, $slug, $unitMode);
        $snap = QuoteLinePricing::snapshotLabels($row, $slug, $unitMode);
        $listaSeiq = $resolved['lista_seiq'];
        $fieldUsed = $resolved['price_field_used'];
        $calc = PricingEngine::calculateWithListaSeiq($listaSeiq, $row, $ov, $includeIva);
        $this->json([
            'unit_type' => $unitMode,
            'unit_label' => $snap['unit_label'],
            'unit_description' => $snap['unit_description'],
            'price_field_used' => $fieldUsed,
            'field' => $fieldUsed,
            'calc' => $calc,
            'formatted' => [
                'lista' => formatPrice($calc['precio_lista_seiq']),
                'costo' => formatPrice($calc['costo']),
                'venta' => formatPrice($calc['precio_venta']),
                'iva' => $calc['precio_con_iva'] !== null ? formatPrice($calc['precio_con_iva']) : null,
            ],
        ]);
    }

    public function getCategoryProducts(string $id): void
    {
        $db = Database::getInstance();
        $ids = CategoryHierarchy::expandFilterCategoryIds($db, (int) $id);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->fetchAll(
            "SELECT p.id, p.code, p.name FROM products p WHERE p.category_id IN ({$marks}) AND p.is_active = 1 ORDER BY p.sort_order, p.name",
            $ids
        );
        $this->json(['products' => $rows]);
    }

    public function previewPricing(): void
    {
        $raw = file_get_contents('php://input');
        $data = [];
        if ($raw && str_starts_with(trim($raw), '{')) {
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->json(['error' => 'JSON inválido'], 400);
                return;
            }
        } else {
            $data = $_POST;
        }
        $token = is_array($data) ? ($data['_csrf'] ?? '') : '';
        if ($token === '') {
            $token = $_POST['_csrf'] ?? '';
        }
        if (!is_string($token) || $token === '' || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
            $this->json(['error' => 'CSRF'], 419);
            return;
        }
        $slug = (string) ($data['category_slug'] ?? '');
        if ($slug === '') {
            $cid = (int) ($data['category_id'] ?? 0);
            if ($cid > 0) {
                $db = Database::getInstance();
                $c = $db->fetch(
                    'SELECT COALESCE(pc.slug, c.slug) AS slug, c.default_discount, c.default_markup,
                            c.markup_override AS category_markup_override,
                            pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                            pc.markup_override AS parent_markup_override
                     FROM categories c
                     LEFT JOIN categories pc ON c.parent_id = pc.id
                     WHERE c.id = ?',
                    [$cid]
                );
                if ($c) {
                    $slug = (string) $c['slug'];
                    $data['default_discount'] = $c['default_discount'];
                    $data['category_default_markup'] = $c['default_markup'];
                    $data['category_markup_override'] = $c['category_markup_override'];
                    $data['parent_discount'] = $c['parent_discount'];
                    $data['parent_default_markup'] = $c['parent_default_markup'];
                    $data['parent_markup_override'] = $c['parent_markup_override'];
                }
            }
        } else {
            $db = Database::getInstance();
            $c = $db->fetch(
                'SELECT c.default_discount, c.default_markup,
                        c.markup_override AS category_markup_override,
                        pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                        pc.markup_override AS parent_markup_override
                 FROM categories c
                 LEFT JOIN categories pc ON c.parent_id = pc.id
                 WHERE c.slug = ?',
                [$slug]
            );
            if ($c) {
                $data['default_discount'] = $data['default_discount'] ?? $c['default_discount'];
                $data['category_default_markup'] = $data['category_default_markup'] ?? $c['default_markup'];
                $data['category_markup_override'] = $data['category_markup_override'] ?? $c['category_markup_override'];
                $data['parent_discount'] = $data['parent_discount'] ?? $c['parent_discount'];
                $data['parent_default_markup'] = $data['parent_default_markup'] ?? $c['parent_default_markup'];
                $data['parent_markup_override'] = $data['parent_markup_override'] ?? $c['parent_markup_override'];
            }
        }
        $field = (string) ($data['price_field'] ?? '');
        if ($field === '') {
            $field = PricingEngine::getPrimaryPriceField($slug ?: 'masivo');
        }
        $listMarkup = $data['list_markup'] ?? null;
        $ov = $listMarkup !== null && $listMarkup !== '' && is_numeric(str_replace(',', '.', (string) $listMarkup))
            ? (float) str_replace(',', '.', (string) $listMarkup)
            : null;

        $product = [
            'discount_override' => isset($data['discount_override']) && $data['discount_override'] !== ''
                ? (float) str_replace(',', '.', (string) $data['discount_override']) : null,
            'markup_override' => isset($data['markup_override']) && $data['markup_override'] !== ''
                ? (float) str_replace(',', '.', (string) $data['markup_override']) : null,
            'default_discount' => isset($data['default_discount']) ? (float) $data['default_discount'] : 0,
            'parent_discount' => isset($data['parent_discount']) && $data['parent_discount'] !== '' && $data['parent_discount'] !== null
                ? (float) str_replace(',', '.', (string) $data['parent_discount']) : null,
            'category_default_markup' => $data['category_default_markup'] ?? null,
            'parent_default_markup' => $data['parent_default_markup'] ?? null,
            'category_markup_override' => self::toFloat($data['category_markup_override'] ?? null),
            'parent_markup_override' => self::toFloat($data['parent_markup_override'] ?? null),
            'precio_lista_unitario' => self::toFloat($data['precio_lista_unitario'] ?? null),
            'precio_lista_caja' => self::toFloat($data['precio_lista_caja'] ?? null),
            'precio_lista_bidon' => self::toFloat($data['precio_lista_bidon'] ?? null),
            'precio_lista_litro' => self::toFloat($data['precio_lista_litro'] ?? null),
            'precio_lista_bulto' => self::toFloat($data['precio_lista_bulto'] ?? null),
            'precio_lista_sobre' => self::toFloat($data['precio_lista_sobre'] ?? null),
        ];

        $includeIva = !empty($data['include_iva']);
        $calc = PricingEngine::calculate($product, $field, $ov, $includeIva);
        $this->json([
            'calc' => $calc,
            'formatted' => [
                'lista' => formatPrice($calc['precio_lista_seiq']),
                'costo' => formatPrice($calc['costo']),
                'venta' => formatPrice($calc['precio_venta']),
                'margen' => formatPrice($calc['margen_pesos']),
                'iva' => $calc['precio_con_iva'] !== null ? formatPrice($calc['precio_con_iva']) : null,
            ],
            'discount_source' => $product['discount_override'] !== null ? 'override' : 'categoría',
            'markup_source' => $ov !== null ? 'lista' : (
                $product['markup_override'] !== null ? 'producto' : (
                    $product['category_default_markup'] !== null && $product['category_default_markup'] !== ''
                        ? 'categoría' : 'global'
                )
            ),
        ]);
    }

    private static function toFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = str_replace(['$', ' '], '', (string) $v);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float) $s : null;
    }

    public function serveProductImage(string $id, string $img): void
    {
        $this->outputProductImage((int) $id, (int) $img, false);
    }

    public function serveProductThumb(string $id, string $img): void
    {
        $this->outputProductImage((int) $id, (int) $img, true);
    }

    private function outputProductImage(int $productId, int $imageId, bool $thumb): void
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT id, product_id, filename, mime_type FROM product_images WHERE id = ? AND product_id = ?',
            [$imageId, $productId]
        );
        if (!$row) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No encontrado';
            return;
        }
        $uploader = new ImageUploader();
        $path = $thumb
            ? $uploader->thumbPath($productId, (string) $row['filename'])
            : $uploader->originalPath($productId, (string) $row['filename']);
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No encontrado';
            return;
        }
        $mime = (string) ($row['mime_type'] ?? 'application/octet-stream');
        $size = (int) filesize($path);
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        if ($size > 0) {
            header('Content-Length: ' . (string) $size);
        }
        readfile($path);
        exit;
    }

    public function catalogCategories(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        $tree = CategoryHierarchy::buildTree($rows);
        $out = [];
        foreach ($tree as $root) {
            $item = [
                'id' => (int) $root['id'],
                'name' => (string) $root['name'],
                'slug' => (string) ($root['slug'] ?? ''),
                'children' => [],
            ];
            foreach ($root['children'] ?? [] as $ch) {
                $item['children'][] = [
                    'id' => (int) $ch['id'],
                    'name' => (string) $ch['name'],
                    'slug' => (string) ($ch['slug'] ?? ''),
                ];
            }
            $out[] = $item;
        }
        $this->json(['categories' => $out]);
    }

    public function catalogProducts(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT p.*,
                    COALESCE(pc.slug, c.slug) AS category_effective_slug,
                    c.name AS category_leaf_name,
                    pc.name AS category_parent_name,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    cov.id AS cover_image_id,
                    cov.filename AS cover_filename,
                    cov.alt_text AS cover_alt_text
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN product_images cov ON cov.id = (
                 SELECT pi.id FROM product_images pi
                 WHERE pi.product_id = p.id AND pi.is_cover = 1
                 ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1
             )
             WHERE p.is_published = 1 AND p.is_active = 1
               AND COALESCE(pc.slug, c.slug) <> \'alimenticia\'
               AND EXISTS (
                   SELECT 1 FROM product_images pi2
                   WHERE pi2.product_id = p.id AND pi2.is_cover = 1
               )
             ORDER BY p.sort_order ASC, p.name ASC'
        );
        $products = [];
        foreach ($rows as $row) {
            $products[] = $this->catalogProductPayload($row, false);
        }
        $this->json(['products' => $products]);
    }

    public function catalogProductDetail(string $slug): void
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            $this->json(['error' => 'No encontrado'], 404);
            return;
        }
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT p.*,
                    COALESCE(pc.slug, c.slug) AS category_effective_slug,
                    c.name AS category_leaf_name,
                    pc.name AS category_parent_name,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    cov.id AS cover_image_id,
                    cov.filename AS cover_filename,
                    cov.alt_text AS cover_alt_text
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN product_images cov ON cov.id = (
                 SELECT pi.id FROM product_images pi
                 WHERE pi.product_id = p.id AND pi.is_cover = 1
                 ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1
             )
             WHERE p.slug = ? AND p.is_published = 1 AND p.is_active = 1
               AND COALESCE(pc.slug, c.slug) <> \'alimenticia\'',
            [$slug]
        );
        if (!$row) {
            $this->json(['error' => 'No encontrado'], 404);
            return;
        }
        $payload = $this->catalogProductPayload($row, true);
        $imgs = $db->fetchAll(
            'SELECT id, filename, alt_text, sort_order, is_cover
             FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC',
            [(int) $row['id']]
        );
        $pid = (int) $row['id'];
        $images = [];
        foreach ($imgs as $im) {
            $iid = (int) $im['id'];
            $images[] = [
                'id' => $iid,
                'thumb_url' => url('/api/productos/' . $pid . '/imagen/' . $iid . '/thumb'),
                'full_url' => url('/api/productos/' . $pid . '/imagen/' . $iid),
                'alt_text' => $im['alt_text'],
                'is_cover' => (int) ($im['is_cover'] ?? 0) === 1,
                'sort_order' => (int) ($im['sort_order'] ?? 0),
            ];
        }
        $payload['images'] = $images;
        $this->json($payload);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function catalogProductPayload(array $row, bool $detail): array
    {
        $pid = (int) $row['id'];
        $effSlug = strtolower((string) ($row['category_effective_slug'] ?? ''));
        $resolved = QuoteLinePricing::resolveListaForQuote($row, $effSlug, 'caja');
        $lista = $resolved['lista_seiq'];
        $mayorOv = self::optionalMarkupOverride('catalog_markup_mayorista');
        $minorOv = self::optionalMarkupOverride('catalog_markup_minorista');
        $calcMayor = PricingEngine::calculateWithListaSeiq($lista, $row, $mayorOv, false);
        $calcMinor = $minorOv !== null
            ? PricingEngine::calculateWithListaSeiq($lista, $row, $minorOv, false)
            : $calcMayor;
        $parent = trim((string) ($row['category_parent_name'] ?? ''));
        $leaf = trim((string) ($row['category_leaf_name'] ?? ''));
        if ($parent !== '') {
            $catName = $parent;
            $subName = $leaf !== '' ? $leaf : null;
        } else {
            $catName = $leaf;
            $subName = null;
        }
        $cv = trim((string) ($row['content_volume'] ?? ''));
        if ($cv === '') {
            $cv = trim((string) ($row['content'] ?? ''));
        }
        $short = trim((string) ($row['short_description'] ?? ''));
        if ($short === '') {
            $short = trim((string) ($row['short_name'] ?? ''));
        }
        $coverId = isset($row['cover_image_id']) ? (int) $row['cover_image_id'] : 0;
        $cover = null;
        if ($coverId > 0) {
            $cover = [
                'id' => $coverId,
                'thumb_url' => url('/api/productos/' . $pid . '/imagen/' . $coverId . '/thumb'),
                'full_url' => url('/api/productos/' . $pid . '/imagen/' . $coverId),
                'alt_text' => $row['cover_alt_text'] ?? null,
            ];
        }
        $out = [
            'id' => $pid,
            'name' => (string) $row['name'],
            'slug' => (string) ($row['slug'] ?? ''),
            'short_description' => $short !== '' ? $short : null,
            'category' => $catName,
            'subcategory' => $subName,
            'content_volume' => $cv !== '' ? $cv : null,
            'presentation' => $this->nullIfEmpty($row['presentation'] ?? null),
            'dilution' => $this->nullIfEmpty($row['dilution'] ?? null),
            'equivalence' => $this->nullIfEmpty($row['equivalence'] ?? null),
            'cover_image' => $cover,
            'prices' => [
                'mayorista' => $calcMayor['precio_venta'],
                'minorista' => $calcMinor['precio_venta'],
            ],
        ];
        if ($detail) {
            $fd = trim((string) ($row['full_description'] ?? ''));
            if ($fd === '') {
                $fd = trim((string) ($row['description'] ?? ''));
            }
            $out['full_description'] = $fd !== '' ? $fd : null;
        }

        return $out;
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private static function optionalMarkupOverride(string $settingKey): ?float
    {
        $raw = trim((string) (setting($settingKey, '') ?? ''));
        if ($raw === '') {
            return null;
        }
        $s = str_replace(',', '.', $raw);
        if (!is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }
}
