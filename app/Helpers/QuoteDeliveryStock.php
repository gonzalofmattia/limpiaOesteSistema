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
    public static function unitsByProductForQuote(Database $db, int $quoteId): array
    {
        $rows = $db->fetchAll(
            'SELECT x.product_id, x.quantity, x.unit_type, p.units_per_box
             FROM (
                 SELECT qi.product_id, qi.quantity, qi.unit_type
                 FROM quote_items qi
                 WHERE qi.quote_id = ? AND qi.product_id IS NOT NULL
                 UNION ALL
                 SELECT cp.product_id, (qi.quantity * cp.quantity) AS quantity, \'unidad\' AS unit_type
                 FROM quote_items qi
                 JOIN combo_products cp ON cp.combo_id = qi.combo_id
                 WHERE qi.quote_id = ? AND qi.combo_id IS NOT NULL
             ) x
             JOIN products p ON p.id = x.product_id',
            [$quoteId, $quoteId]
        );
        $out = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qty = (int) ($row['quantity'] ?? 0);
            $mode = QuoteLinePricing::normalizeUnitType((string) ($row['unit_type'] ?? 'caja'));
            $unitsPerBox = max(1, (int) ($row['units_per_box'] ?? 1) ?: 1);
            $totalUnits = $mode === 'unidad' ? $qty : $qty * $unitsPerBox;
            if ($totalUnits <= 0) {
                continue;
            }
            $out[$pid] = ($out[$pid] ?? 0) + $totalUnits;
        }

        return $out;
    }

    public static function applyDelivery(Database $db, int $quoteId): void
    {
        foreach (self::unitsByProductForQuote($db, $quoteId) as $pid => $units) {
            $db->query(
                'UPDATE products SET stock_units = GREATEST(0, stock_units - :u) WHERE id = :pid',
                ['u' => $units, 'pid' => $pid]
            );
        }
    }

    public static function reverseDelivery(Database $db, int $quoteId): void
    {
        foreach (self::unitsByProductForQuote($db, $quoteId) as $pid => $units) {
            $db->query(
                'UPDATE products SET stock_units = stock_units + :u WHERE id = :pid',
                ['u' => $units, 'pid' => $pid]
            );
        }
    }
}
