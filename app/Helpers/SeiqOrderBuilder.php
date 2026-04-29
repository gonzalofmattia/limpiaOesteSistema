<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Consolida líneas de presupuestos aceptados para un pedido a Seiq (cajas completas).
 */
final class SeiqOrderBuilder
{
    /** @return list<array<string,mixed>> */
    public static function fetchAcceptedQuotes(Database $db): array
    {
        return $db->fetchAll(
            'SELECT q.*, c.name AS client_name
             FROM quotes q
             LEFT JOIN clients c ON q.client_id = c.id
             WHERE q.status = \'accepted\'
             ORDER BY q.created_at'
        );
    }

    /**
     * @param list<int> $quoteIds
     * @return list<array<string,mixed>>
     */
    public static function fetchQuoteItems(Database $db, array $quoteIds): array
    {
        if ($quoteIds === []) {
            return [];
        }
        $unitsByQuote = QuoteDeliveryStock::unitsByProductForQuotes($db, $quoteIds);
        if ($unitsByQuote === []) {
            return [];
        }
        $productIds = [];
        foreach ($unitsByQuote as $items) {
            foreach ($items as $pid => $_units) {
                $productIds[(int) $pid] = true;
            }
        }
        if ($productIds === []) {
            return [];
        }
        $ids = array_keys($productIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $metaRows = $db->fetchAll(
            "SELECT p.id, p.code, p.name AS product_name, p.units_per_box, p.content,
                    p.sale_unit_label, p.presentation, p.sale_unit_description,
                    p.stock_units, p.stock_committed_units,
                    c.name AS category_name, c.slug AS category_slug,
                    pc.name AS parent_category_name,
                    COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id,
                    s.name AS supplier_name,
                    s.slug AS supplier_slug
             FROM products p
             JOIN categories c ON p.category_id = c.id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE p.id IN ({$placeholders})",
            $ids
        );
        $metaByProduct = [];
        foreach ($metaRows as $row) {
            $metaByProduct[(int) $row['id']] = $row;
        }

        $out = [];
        foreach ($unitsByQuote as $qid => $rows) {
            foreach ($rows as $pid => $units) {
                $meta = $metaByProduct[(int) $pid] ?? null;
                if ($meta === null) {
                    continue;
                }
                $out[] = [
                    'quote_id' => (int) $qid,
                    'product_id' => (int) $pid,
                    'quantity' => (int) $units,
                    'unit_type' => 'unidad',
                ] + $meta;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array{
     *   consolidated: list<array<string,mixed>>,
     *   total_products: int,
     *   total_boxes: int
     * }
     */
    public static function consolidate(array $items): array
    {
        $consolidated = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $unitMode = QuoteLinePricing::normalizeUnitType((string) ($item['unit_type'] ?? 'caja'));

            if (!isset($consolidated[$productId])) {
                $parent = $item['parent_category_name'] ?? null;
                $catName = (string) ($item['category_name'] ?? '');
                $consolidated[$productId] = [
                    'product_id' => $productId,
                    'code' => (string) ($item['code'] ?? ''),
                    'name' => (string) ($item['product_name'] ?? ''),
                    'content' => (string) ($item['content'] ?? ''),
                    'presentation' => (string) ($item['presentation'] ?? ''),
                    'sale_unit_label' => (string) ($item['sale_unit_label'] ?? ''),
                    'sale_unit_description' => (string) ($item['sale_unit_description'] ?? ''),
                    'stock_units' => max(0, (int) ($item['stock_units'] ?? 0)),
                    'stock_committed_units' => max(0, (int) ($item['stock_committed_units'] ?? 0)),
                    'category_slug' => strtolower((string) ($item['category_slug'] ?? '')),
                    'units_per_box' => max(1, (int) ($item['units_per_box'] ?? 1) ?: 1),
                    'category_name' => $catName,
                    'parent_category_name' => $parent !== null && $parent !== '' ? (string) $parent : null,
                    'supplier_id' => isset($item['supplier_id']) ? (int) $item['supplier_id'] : null,
                    'supplier_name' => (string) ($item['supplier_name'] ?? ''),
                    'supplier_slug' => (string) ($item['supplier_slug'] ?? ''),
                    'sort_group' => $parent !== null && $parent !== '' ? (string) $parent : $catName,
                    'subcategory' => $parent !== null && $parent !== '' ? $catName : null,
                    'qty_units_sold' => 0,
                    'qty_boxes_sold' => 0,
                    'from_quotes' => [],
                ];
            }

            $qty = (int) $item['quantity'];
            if ($unitMode === 'unidad') {
                $consolidated[$productId]['qty_units_sold'] += $qty;
            } else {
                $consolidated[$productId]['qty_boxes_sold'] += $qty;
            }
            $consolidated[$productId]['from_quotes'][(int) $item['quote_id']] = true;
        }

        $list = [];
        $totalBoxes = 0;

        foreach ($consolidated as &$row) {
            $unitsPerBox = max(1, (int) ($row['units_per_box'] ?? 1) ?: 1);
            $row['units_per_box'] = $unitsPerBox;
            $totalUnits = (int) $row['qty_units_sold'] + ((int) $row['qty_boxes_sold'] * $unitsPerBox);
            $stockUnits = max(0, (int) ($row['stock_units'] ?? 0));
            $committedUnits = max(0, (int) ($row['stock_committed_units'] ?? 0));
            $stockAvailable = max(0, $stockUnits - $committedUnits);
            $unitsToOrderAfterStock = max(0, $totalUnits - $stockAvailable);

            $row['total_units_needed'] = $totalUnits;
            $row['stock_available_units'] = $stockAvailable;
            $row['stock_committed_units'] = $committedUnits;
            $row['units_to_order_after_stock'] = $unitsToOrderAfterStock;
            $row['units_covered_by_stock'] = min($totalUnits, $stockAvailable);
            $row['units_shortage'] = $unitsToOrderAfterStock;
            $boxesToOrder = $unitsToOrderAfterStock > 0 ? (int) ceil($unitsToOrderAfterStock / $unitsPerBox) : 0;
            $row['boxes_to_order'] = $boxesToOrder;
            $row['units_remainder'] = max(0, ($boxesToOrder * $unitsPerBox) - $unitsToOrderAfterStock);
            $totalBoxes += $boxesToOrder;
            $list[] = $row;
        }
        unset($row);

        usort(
            $list,
            static function (array $a, array $b): int {
                $g = strcmp((string) ($a['sort_group'] ?? ''), (string) ($b['sort_group'] ?? ''));
                if ($g !== 0) {
                    return $g;
                }
                $sa = (string) ($a['subcategory'] ?? '');
                $sb = (string) ($b['subcategory'] ?? '');
                $s = strcmp($sa, $sb);
                if ($s !== 0) {
                    return $s;
                }

                return strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''));
            }
        );

        return [
            'consolidated' => $list,
            'total_products' => count($list),
            'total_boxes' => $totalBoxes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $consolidated
     * @return list<array{supplier: array<string,mixed>, bundle: array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int}}>
     */
    public static function groupConsolidatedBySupplier(array $consolidated): array
    {
        $groups = [];
        foreach ($consolidated as $row) {
            $supplierId = (int) ($row['supplier_id'] ?? 0);
            if ($supplierId <= 0) {
                continue;
            }
            if (!isset($groups[$supplierId])) {
                $groups[$supplierId] = [
                    'supplier' => [
                        'id' => $supplierId,
                        'name' => (string) ($row['supplier_name'] ?? 'Proveedor'),
                        'slug' => (string) ($row['supplier_slug'] ?? ''),
                    ],
                    'items' => [],
                ];
            }
            $groups[$supplierId]['items'][] = $row;
        }
        $result = [];
        foreach ($groups as $group) {
            $items = $group['items'];
            $result[] = [
                'supplier' => $group['supplier'],
                'bundle' => [
                    'consolidated' => $items,
                    'total_products' => count($items),
                    'total_boxes' => (int) array_sum(array_map(static fn (array $x): int => (int) ($x['boxes_to_order'] ?? 0), $items)),
                ],
            ];
        }

        return $result;
    }

    /**
     * @return array{acceptedQuotes: list, bundle: array{consolidated: list, total_products: int, total_boxes: int}|null, error: ?string}
     */
    public static function buildFromDatabase(Database $db): array
    {
        $acceptedQuotes = self::fetchAcceptedQuotes($db);
        if ($acceptedQuotes === []) {
            return ['acceptedQuotes' => [], 'bundle' => null, 'error' => 'empty'];
        }
        $quoteIds = array_map(static fn ($q) => (int) $q['id'], $acceptedQuotes);
        $items = self::fetchQuoteItems($db, $quoteIds);
        if ($items === []) {
            return ['acceptedQuotes' => $acceptedQuotes, 'bundle' => null, 'error' => 'no_items'];
        }
        $bundle = self::consolidate($items);

        return ['acceptedQuotes' => $acceptedQuotes, 'bundle' => $bundle, 'error' => null];
    }
}
