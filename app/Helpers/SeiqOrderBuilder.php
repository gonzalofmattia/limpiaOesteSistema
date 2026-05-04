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
        $accepted = $db->fetchAll(
            'SELECT q.*, c.name AS client_name
             FROM quotes q
             LEFT JOIN clients c ON q.client_id = c.id
             WHERE q.status IN (\'accepted\', \'partially_delivered\')
             ORDER BY q.created_at'
        );
        if ($accepted === []) {
            return [];
        }
        $alreadyIncluded = self::alreadyIncludedQuoteIds($db);
        if ($alreadyIncluded === []) {
            return $accepted;
        }

        return array_values(array_filter(
            $accepted,
            static fn (array $q): bool => !isset($alreadyIncluded[(int) ($q['id'] ?? 0)])
        ));
    }

    /**
     * IDs de presupuestos ya incluidos en pedidos a proveedor existentes.
     *
     * @return array<int, true>
     */
    private static function alreadyIncludedQuoteIds(Database $db): array
    {
        $out = [];
        $rows = $db->fetchAll(
            "SELECT included_quotes
             FROM seiq_orders
             WHERE included_quotes IS NOT NULL
               AND included_quotes <> ''"
        );
        foreach ($rows as $r) {
            $raw = (string) ($r['included_quotes'] ?? '');
            if ($raw === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $qid) {
                $id = (int) $qid;
                if ($id > 0) {
                    $out[$id] = true;
                }
            }
        }

        return $out;
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
        $unitsByQuote = QuoteDeliveryStock::pendingUnitsByProductForQuotes($db, $quoteIds);
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
     * @param list<int> $quoteIds
     * @param array<int, array<string,mixed>> $acceptedById
     * @return array<int, list<array<string,mixed>>>
     */
    private static function buildDemandDetailsByProduct(Database $db, array $quoteIds, array $acceptedById): array
    {
        $rows = QuoteDeliveryStock::pendingDemandBreakdownForQuotes($db, $quoteIds);
        if ($rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qid = (int) ($row['quote_id'] ?? 0);
            if ($pid <= 0 || $qid <= 0) {
                continue;
            }
            $q = $acceptedById[$qid] ?? null;
            $entry = [
                'quote_id' => $qid,
                'quote_number' => (string) ($q['quote_number'] ?? ('#' . $qid)),
                'client_name' => (string) ($q['client_name'] ?? '—'),
                'units' => (int) ($row['units'] ?? 0),
                'source_type' => (string) ($row['source_type'] ?? 'product'),
                'combo_id' => $row['combo_id'] ?? null,
                'combo_name' => $row['combo_name'] ?? null,
            ];
            if (!isset($out[$pid])) {
                $out[$pid] = [];
            }
            $out[$pid][] = $entry;
        }

        foreach ($out as &$items) {
            usort($items, static function (array $a, array $b): int {
                $cmp = strcmp((string) ($a['quote_number'] ?? ''), (string) ($b['quote_number'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp((string) ($a['source_type'] ?? ''), (string) ($b['source_type'] ?? ''));
            });
        }
        unset($items);

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
    /**
     * Compromisos reales por producto (unidades) desde quote_items y estados accepted / partially_delivered.
     *
     * @return array<int, int> product_id => unidades comprometidas pendientes
     */
    private static function calculateRealCommitments(Database $db): array
    {
        $rows = $db->fetchAll(
            "SELECT id FROM quotes WHERE status IN ('accepted', 'partially_delivered')"
        );
        $quoteIds = [];
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $quoteIds[] = $id;
            }
        }
        if ($quoteIds === []) {
            return [];
        }
        $byQuote = QuoteDeliveryStock::pendingUnitsByProductForQuotes($db, $quoteIds);
        $merged = [];
        foreach ($byQuote as $qItems) {
            foreach ($qItems as $pid => $u) {
                $pid = (int) $pid;
                $u = (int) $u;
                if ($pid <= 0 || $u <= 0) {
                    continue;
                }
                $merged[$pid] = ($merged[$pid] ?? 0) + $u;
            }
        }

        return $merged;
    }

    /**
     * @param array<int, int> $realCommitments product_id => unidades comprometidas reales (pendientes)
     */
    public static function consolidate(array $items, array $realCommitments): array
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
            // Usar compromisos calculados en tiempo real desde quote_items
            // en lugar de stock_committed_units que puede estar desincronizado
            $committedUnits = max(0, (int) ($realCommitments[$row['product_id']] ?? 0));
            $stockAvailable = $stockUnits - $committedUnits;
            // El faltante real para compra debe salir del compromiso total vs stock fisico.
            // Si usamos "vendido de este lote - disponible", duplicamos el comprometido
            // y terminamos pidiendo de mas (caso stock=2, comprometido=2 -> faltante debe ser 0).
            $unitsToOrderAfterStock = max(0, $committedUnits - $stockUnits);

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
        $acceptedById = [];
        foreach ($acceptedQuotes as $q) {
            $acceptedById[(int) ($q['id'] ?? 0)] = $q;
        }
        $demandDetailsByProduct = self::buildDemandDetailsByProduct($db, $quoteIds, $acceptedById);
        $realCommitments = self::calculateRealCommitments($db);
        $bundle = self::consolidate($items, $realCommitments);
        foreach ($bundle['consolidated'] as &$row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $row['demand_details'] = $demandDetailsByProduct[$pid] ?? [];
        }
        unset($row);

        return ['acceptedQuotes' => $acceptedQuotes, 'bundle' => $bundle, 'error' => null];
    }
}
