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
    private static function fetchOpenQuotes(Database $db): array
    {
        return $db->fetchAll(
            'SELECT q.*, c.name AS client_name
             FROM quotes q
             LEFT JOIN clients c ON q.client_id = c.id
             WHERE q.status IN (\'accepted\', \'partially_delivered\')
             ORDER BY q.created_at'
        );
    }

    /** @return list<array<string,mixed>> */
    public static function fetchAcceptedQuotes(Database $db): array
    {
        return self::fetchOpenQuotes($db);
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
        $coveredByQuote = self::coveredUnitsByQuoteProduct($db, $unitsByQuote);
        if ($coveredByQuote !== []) {
            foreach ($unitsByQuote as $qid => &$products) {
                foreach ($products as $pid => $units) {
                    $covered = (int) ($coveredByQuote[(int) $qid][(int) $pid] ?? 0);
                    $products[(int) $pid] = max(0, (int) $units - $covered);
                }
                $products = array_filter(
                    $products,
                    static fn (int $units): bool => $units > 0
                );
            }
            unset($products);
            $unitsByQuote = array_filter(
                $unitsByQuote,
                static fn (array $products): bool => $products !== []
            );
            if ($unitsByQuote === []) {
                return [];
            }
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
     * Reparte unidades ya cubiertas por pedidos previos (sent/received) en base a quote_id + product_id.
     * Como seiq_order_items se guarda consolidado, la distribución por presupuesto se hace proporcional
     * a la demanda pendiente actual de cada presupuesto incluido en ese pedido.
     *
     * @param array<int, array<int, int>> $unitsByQuote quote_id => [product_id => pending_units]
     * @return array<int, array<int, int>> quote_id => [product_id => covered_units]
     */
    private static function coveredUnitsByQuoteProduct(Database $db, array $unitsByQuote): array
    {
        $pendingByQuote = [];
        foreach ($unitsByQuote as $qid => $products) {
            $quoteId = (int) $qid;
            if ($quoteId <= 0 || !is_array($products)) {
                continue;
            }
            foreach ($products as $pid => $units) {
                $productId = (int) $pid;
                $pendingUnits = max(0, (int) $units);
                if ($productId <= 0 || $pendingUnits <= 0) {
                    continue;
                }
                $pendingByQuote[$quoteId][$productId] = $pendingUnits;
            }
        }
        if ($pendingByQuote === []) {
            $logsDir = STORAGE_PATH . '/logs';
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0755, true);
            }
            $logFile = $logsDir . '/seiq_order_builder.log';
            $line = '[' . date('Y-m-d H:i:s') . '] [SeiqOrderBuilder] coveredUnitsByQuoteProduct: pendingByQuote vacío' . PHP_EOL;
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
            return [];
        }

        $orders = $db->fetchAll(
            "SELECT id, included_quotes
             FROM seiq_orders
             WHERE LOWER(status) IN ('sent', 'received')
               AND included_quotes IS NOT NULL
               AND included_quotes <> ''"
        );
        if ($orders === []) {
            return [];
        }
        $orderIds = [];
        $quoteIdsByOrder = [];
        foreach ($orders as $order) {
            $orderId = (int) ($order['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $rawIncluded = trim((string) ($order['included_quotes'] ?? ''));
            if ($rawIncluded === '') {
                continue;
            }
            $decoded = json_decode($rawIncluded, true);
            $candidateIds = [];
            if (is_array($decoded)) {
                foreach ($decoded as $qid) {
                    $candidateIds[] = $qid;
                }
            } else {
                // Compatibilidad: registros legacy en CSV o texto con IDs embebidos.
                if (preg_match_all('/\d+/', $rawIncluded, $m)) {
                    $candidateIds = $m[0];
                }
            }
            if ($candidateIds === []) {
                continue;
            }
            $validQuoteIds = [];
            foreach ($candidateIds as $qid) {
                $qid = (int) $qid;
                if ($qid > 0) {
                    $validQuoteIds[] = $qid;
                }
            }
            $validQuoteIds = array_values(array_unique($validQuoteIds));
            if ($validQuoteIds === []) {
                continue;
            }
            $orderIds[] = $orderId;
            $quoteIdsByOrder[$orderId] = $validQuoteIds;
        }
        if ($orderIds === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($orderIds), '?'));
        $itemRows = $db->fetchAll(
            "SELECT seiq_order_id, product_id, SUM(boxes_to_order * units_per_box) AS ordered_units
             FROM seiq_order_items soi
             WHERE soi.seiq_order_id IN ({$ph})
               AND soi.boxes_to_order > 0
             GROUP BY seiq_order_id, product_id",
            $orderIds
        );
        if ($itemRows === []) {
            return [];
        }

        $covered = [];
        foreach ($itemRows as $row) {
            $orderId = (int) ($row['seiq_order_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $orderedUnits = (int) ($row['ordered_units'] ?? 0);
            if ($orderId <= 0 || $productId <= 0 || $orderedUnits <= 0) {
                continue;
            }
            $quoteIds = $quoteIdsByOrder[$orderId] ?? [];
            if ($quoteIds === []) {
                continue;
            }
            $pendingPerQuote = [];
            $totalPending = 0;
            foreach ($quoteIds as $qid) {
                $pendingUnits = (int) ($pendingByQuote[$qid][$productId] ?? 0);
                if ($pendingUnits <= 0) {
                    continue;
                }
                $pendingPerQuote[$qid] = $pendingUnits;
                $totalPending += $pendingUnits;
            }
            if ($totalPending <= 0) {
                continue;
            }

            $remaining = min($orderedUnits, $totalPending);
            $allocated = [];
            foreach ($pendingPerQuote as $qid => $pendingUnits) {
                $alloc = (int) floor(($remaining * $pendingUnits) / $totalPending);
                $alloc = min($alloc, $pendingUnits);
                if ($alloc <= 0) {
                    continue;
                }
                $allocated[$qid] = $alloc;
            }
            $assigned = (int) array_sum($allocated);
            $remainingToAssign = max(0, $remaining - $assigned);

            if ($remainingToAssign > 0) {
                arsort($pendingPerQuote);
                while ($remainingToAssign > 0) {
                    $progress = false;
                    foreach ($pendingPerQuote as $qid => $pendingUnits) {
                        $used = (int) ($allocated[$qid] ?? 0);
                        if ($used >= $pendingUnits) {
                            continue;
                        }
                        $allocated[$qid] = $used + 1;
                        $remainingToAssign--;
                        $progress = true;
                        if ($remainingToAssign <= 0) {
                            break;
                        }
                    }
                    if (!$progress) {
                        break;
                    }
                }
            }

            foreach ($allocated as $qid => $alloc) {
                if ($alloc <= 0) {
                    continue;
                }
                $covered[$qid][$productId] = (int) ($covered[$qid][$productId] ?? 0) + $alloc;
            }
        }

        $logsDir = STORAGE_PATH . '/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }
        $logFile = $logsDir . '/seiq_order_builder.log';
        $line = '[' . date('Y-m-d H:i:s') . '] [SeiqOrderBuilder] coveredUnitsByQuoteProduct= '
            . json_encode($covered, JSON_UNESCAPED_UNICODE)
            . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        return $covered;
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
        $rows = self::fetchOpenQuotes($db);
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
     * @param array<int, int> $unitsInTransit product_id => unidades en camino (pedidos status=sent)
     */
    public static function consolidate(array $items, array $realCommitments, array $unitsInTransit = []): array
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
            // Descontar unidades ya pedidas al proveedor (en camino, status = sent)
            $inTransitUnits = max(0, (int) ($unitsInTransit[$row['product_id']] ?? 0));
            $effectiveStock = $stockUnits + $inTransitUnits;
            $stockAvailable = $effectiveStock - $committedUnits;
            // El faltante real para compra debe salir del compromiso total vs stock fisico.
            // Si usamos "vendido de este lote - disponible", duplicamos el comprometido
            // y terminamos pidiendo de mas (caso stock=2, comprometido=2 -> faltante debe ser 0).
            $unitsToOrderAfterStock = max(0, $committedUnits - $effectiveStock);
            $row['total_units_needed'] = $totalUnits;
            $row['stock_available_units'] = $stockAvailable;
            $row['stock_committed_units'] = $committedUnits;
            $row['in_transit_units'] = $inTransitUnits;
            $row['effective_stock_units'] = $effectiveStock;
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
     * Unidades ya pedidas al proveedor y en camino (pedidos con estado "sent").
     *
     * @return array<int, int> product_id => unidades en camino
     */
    private static function unitsInTransit(Database $db): array
    {
        $rows = $db->fetchAll(
            "SELECT soi.product_id, SUM(soi.boxes_to_order * soi.units_per_box) AS units_in_transit
             FROM seiq_order_items soi
             JOIN seiq_orders so ON so.id = soi.seiq_order_id
             WHERE so.status = 'sent'
             GROUP BY soi.product_id"
        );
        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $units = (int) ($row['units_in_transit'] ?? 0);
            if ($pid <= 0 || $units <= 0) {
                continue;
            }
            $out[$pid] = $units;
        }

        return $out;
    }

    /**
     * @return array{acceptedQuotes: list, bundle: array{consolidated: list, total_products: int, total_boxes: int}|null, error: ?string}
     */
    public static function buildFromDatabase(Database $db): array
    {
        $openQuotes = self::fetchOpenQuotes($db);
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
        foreach ($openQuotes as $q) {
            $acceptedById[(int) ($q['id'] ?? 0)] = $q;
        }
        $openQuoteIds = array_values(array_map(static fn (array $q): int => (int) ($q['id'] ?? 0), $openQuotes));
        $openQuoteIds = array_values(array_filter($openQuoteIds, static fn (int $id): bool => $id > 0));
        $demandDetailsByProduct = self::buildDemandDetailsByProduct($db, $openQuoteIds, $acceptedById);
        $realCommitments = self::calculateRealCommitments($db);
        $unitsInTransit = self::unitsInTransit($db);
        $bundle = self::consolidate($items, $realCommitments, $unitsInTransit);
        foreach ($bundle['consolidated'] as &$row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $row['demand_details'] = $demandDetailsByProduct[$pid] ?? [];
        }
        unset($row);

        return ['acceptedQuotes' => $acceptedQuotes, 'bundle' => $bundle, 'error' => null];
    }
}
