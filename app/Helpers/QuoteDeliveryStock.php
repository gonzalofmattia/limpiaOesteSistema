<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Descuenta o restaura stock al marcar un presupuesto como entregado (o revertir).
 */
final class QuoteDeliveryStock
{
    /**
     * @return array<int, int> product_id => unidades totales vendidas en el presupuesto
     */
    public static function unitsByProductForQuote(int $quoteId): array
    {
        $db = Database::getInstance();
        $byQuote = self::unitsByProductForQuotes($db, [$quoteId]);

        return $byQuote[$quoteId] ?? [];
    }

    /**
     * @param list<int> $quoteIds
     * @return array<int, array<int, int>> quote_id => [product_id => units]
     */
    public static function unitsByProductForQuotes(Database $db, array $quoteIds): array
    {
        if ($quoteIds === []) {
            return [];
        }
        $rows = self::fetchExplodedRowsForQuotes($db, $quoteIds);
        $out = [];
        foreach ($rows as $row) {
            $qid = (int) ($row['quote_id'] ?? 0);
            $pid = (int) ($row['product_id'] ?? 0);
            if ($qid <= 0 || $pid <= 0) {
                continue;
            }
            $qty = (int) ($row['quantity'] ?? 0);
            $mode = QuoteLinePricing::normalizeUnitType((string) ($row['unit_type'] ?? 'caja'));
            $unitsPerBox = max(1, (int) ($row['units_per_box'] ?? 1) ?: 1);
            $totalUnits = $mode === 'unidad' ? $qty : $qty * $unitsPerBox;
            if ($totalUnits <= 0) {
                continue;
            }
            if (!isset($out[$qid])) {
                $out[$qid] = [];
            }
            $out[$qid][$pid] = ($out[$qid][$pid] ?? 0) + $totalUnits;
        }

        return $out;
    }

    /**
     * @param list<int> $quoteIds
     * @return list<array<string,mixed>>
     */
    private static function fetchExplodedRowsForQuotes(Database $db, array $quoteIds): array
    {
        $quoteIds = array_values(array_filter(array_map(static fn ($x): int => (int) $x, $quoteIds), static fn ($x): bool => $x > 0));
        if ($quoteIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
        $params = array_merge($quoteIds, $quoteIds);

        return $db->fetchAll(
            "SELECT x.quote_id, x.product_id, x.quantity, x.unit_type, p.units_per_box
             FROM (
                 SELECT qi.quote_id, qi.product_id, qi.quantity, qi.unit_type
                 FROM quote_items qi
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.product_id IS NOT NULL
                 UNION ALL
                 SELECT qi.quote_id, cp.product_id, (qi.quantity * cp.quantity) AS quantity, 'unidad' AS unit_type
                 FROM quote_items qi
                 JOIN combo_products cp ON cp.combo_id = qi.combo_id
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.combo_id IS NOT NULL
             ) x
             JOIN products p ON p.id = x.product_id",
            $params
        );
    }

    public static function applyDelivery(Database $db, int $quoteId): void
    {
        foreach (self::unitsByProductForQuote($quoteId) as $pid => $units) {
            $db->query(
                'UPDATE products SET stock_units = GREATEST(0, stock_units - :u) WHERE id = :pid',
                ['u' => $units, 'pid' => $pid]
            );
        }
    }

    public static function reverseDelivery(Database $db, int $quoteId): void
    {
        foreach (self::unitsByProductForQuote($quoteId) as $pid => $units) {
            $db->query(
                'UPDATE products SET stock_units = stock_units + :u WHERE id = :pid',
                ['u' => $units, 'pid' => $pid]
            );
        }
    }

    public static function commitStock(Database $db, int $quoteId): void
    {
        foreach (self::unitsByProductForQuote($quoteId) as $pid => $units) {
            $db->query(
                'UPDATE products SET stock_committed_units = stock_committed_units + :u WHERE id = :pid',
                ['u' => $units, 'pid' => $pid]
            );
        }
    }

    public static function releaseCommittedStock(Database $db, int $quoteId): void
    {
        foreach (self::unitsByProductForQuote($quoteId) as $pid => $units) {
            $db->query(
                'UPDATE products SET stock_committed_units = GREATEST(0, stock_committed_units - :u) WHERE id = :pid',
                ['u' => $units, 'pid' => $pid]
            );
        }
    }
}
