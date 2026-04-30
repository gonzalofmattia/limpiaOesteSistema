<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Descuenta o restaura stock al marcar un presupuesto como entregado (o revertir).
 *
 * DIAGNOSTICO DE STOCK (2026-04-30)
 * Stock se modifica en:
 * - app/Helpers/QuoteDeliveryStock.php:92 — baja stock_units al aplicar delivered — se ejecuta al pasar un presupuesto a delivered.
 * - app/Helpers/QuoteDeliveryStock.php:102 — repone stock_units al revertir delivered — se ejecuta al salir de delivered.
 * - app/Helpers/QuoteDeliveryStock.php:112 — suma stock_committed_units — se ejecuta al pasar a accepted.
 * - app/Helpers/QuoteDeliveryStock.php:122 — libera stock_committed_units — se ejecuta al salir de accepted o antes de entregar.
 * - app/Controllers/StockController.php:129 — setea stock_units manualmente — se ejecuta en ajuste manual de Stock Actual.
 * - app/Controllers/SeiqOrderController.php:810 — suma/resta stock_units por recepción/reversión de pedido a proveedor — se ejecuta al cambiar estado received.
 * - app/Controllers/QuoteController.php:297/300/304/307 — orquesta liberar/comprometer/aplicar/revertir stock por transición de estado.
 * - app/Controllers/SeiqOrderController.php:545/547 — al marcar presupuestos entregados desde pedido proveedor libera comprometido y descuenta físico.
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

    /**
     * Detalle por presupuesto/producto para trazabilidad de demanda (directo o por combo).
     *
     * @param list<int> $quoteIds
     * @return list<array{
     *   quote_id:int,
     *   product_id:int,
     *   units:int,
     *   source_type:string,
     *   combo_id:?int,
     *   combo_name:?string
     * }>
     */
    public static function demandBreakdownForQuotes(Database $db, array $quoteIds): array
    {
        $quoteIds = array_values(array_filter(array_map(static fn ($x): int => (int) $x, $quoteIds), static fn ($x): bool => $x > 0));
        if ($quoteIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
        $params = array_merge($quoteIds, $quoteIds);
        $rows = $db->fetchAll(
            "SELECT x.quote_id, x.product_id, x.source_type, x.combo_id, x.combo_name,
                    x.quantity, x.unit_type, p.units_per_box
             FROM (
                 SELECT qi.quote_id, qi.product_id, 'product' AS source_type,
                        NULL AS combo_id, NULL AS combo_name,
                        qi.quantity, qi.unit_type
                 FROM quote_items qi
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.product_id IS NOT NULL
                 UNION ALL
                 SELECT qi.quote_id, cp.product_id, 'combo' AS source_type,
                        qi.combo_id AS combo_id, cmb.name AS combo_name,
                        (qi.quantity * cp.quantity) AS quantity, 'unidad' AS unit_type
                 FROM quote_items qi
                 JOIN combo_products cp ON cp.combo_id = qi.combo_id
                 LEFT JOIN combos cmb ON cmb.id = qi.combo_id
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.combo_id IS NOT NULL
             ) x
             JOIN products p ON p.id = x.product_id",
            $params
        );

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
            $units = $mode === 'unidad' ? $qty : $qty * $unitsPerBox;
            if ($units <= 0) {
                continue;
            }
            $comboId = isset($row['combo_id']) && $row['combo_id'] !== null ? (int) $row['combo_id'] : null;
            $out[] = [
                'quote_id' => $qid,
                'product_id' => $pid,
                'units' => $units,
                'source_type' => (string) ($row['source_type'] ?? 'product'),
                'combo_id' => $comboId,
                'combo_name' => isset($row['combo_name']) ? (string) $row['combo_name'] : null,
            ];
        }

        return $out;
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

    /**
     * Flujo canónico de entrega: libera comprometido y descuenta físico.
     */
    public static function markDelivered(Database $db, int $quoteId): void
    {
        self::releaseCommittedStock($db, $quoteId);
        self::applyDelivery($db, $quoteId);
    }
}
