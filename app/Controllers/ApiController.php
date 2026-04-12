<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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
        $db = Database::getInstance();
        $like = '%' . $q . '%';
        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.sale_unit_label, p.sale_unit_type, p.sale_unit_description,
                    p.units_per_box, p.content, c.name AS category_name, c.slug AS category_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND (p.code LIKE ? OR p.name LIKE ?)
             ORDER BY p.name
             LIMIT 30',
            [$like, $like]
        );
        $this->json(['results' => $rows]);
    }

    public function getProductPrice(string $id): void
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT p.*, c.slug AS category_slug, c.default_discount, c.default_markup AS category_default_markup
             FROM products p
             JOIN categories c ON c.id = p.category_id
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
        $slug = (string) $row['category_slug'];
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
        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name FROM products p WHERE p.category_id = ? AND p.is_active = 1 ORDER BY p.sort_order, p.name',
            [(int) $id]
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
                $c = $db->fetch('SELECT slug, default_discount, default_markup FROM categories WHERE id = ?', [$cid]);
                if ($c) {
                    $slug = (string) $c['slug'];
                    $data['default_discount'] = $c['default_discount'];
                    $data['category_default_markup'] = $c['default_markup'];
                }
            }
        } else {
            $db = Database::getInstance();
            $c = $db->fetch('SELECT default_discount, default_markup FROM categories WHERE slug = ?', [$slug]);
            if ($c) {
                $data['default_discount'] = $data['default_discount'] ?? $c['default_discount'];
                $data['category_default_markup'] = $data['category_default_markup'] ?? $c['default_markup'];
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
            'category_default_markup' => $data['category_default_markup'] ?? null,
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
}
