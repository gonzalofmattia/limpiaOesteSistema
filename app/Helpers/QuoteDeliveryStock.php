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

    /**
     * Libera stock_committed_units solo para las unidades físicas indicadas (mapa producto → unidades).
     *
     * @param array<int, int> $productUnits
     */
    public static function releaseCommittedUnitsForProducts(Database $db, array $productUnits): void
    {
        foreach ($productUnits as $pid => $units) {
            $pid = (int) $pid;
            $u = (int) $units;
            if ($pid <= 0 || $u <= 0) {
                continue;
            }
            $db->query(
                'UPDATE products SET stock_committed_units = GREATEST(0, stock_committed_units - :u) WHERE id = :pid',
                ['u' => $u, 'pid' => $pid]
            );
        }
    }

    /**
     * Libera el comprometido aún pendiente (líneas con cantidad no totalmente entregada).
     */
    public static function releaseRemainingCommittedStock(Database $db, int $quoteId): void
    {
        $lines = $db->fetchAll('SELECT * FROM quote_items WHERE quote_id = ?', [$quoteId]);
        $productDeltas = [];
        foreach ($lines as $line) {
            $q = (float) ($line['quantity'] ?? 0);
            $qd = (float) ($line['qty_delivered'] ?? 0);
            $pend = max(0.0, $q - $qd);
            if ($pend <= 0) {
                continue;
            }
            foreach (self::unitsByProductForLineQty($db, $line, $pend) as $pid => $u) {
                $productDeltas[$pid] = ($productDeltas[$pid] ?? 0) + $u;
            }
        }
        self::releaseCommittedUnitsForProducts($db, $productDeltas);
    }

    /**
     * Repone stock físico por unidades ya entregadas en parciales (p. ej. al eliminar presupuesto).
     */
    public static function reversePartialDeliveriesPhysical(Database $db, int $quoteId): void
    {
        $lines = $db->fetchAll('SELECT * FROM quote_items WHERE quote_id = ?', [$quoteId]);
        $productDeltas = [];
        foreach ($lines as $line) {
            $qd = (float) ($line['qty_delivered'] ?? 0);
            if ($qd <= 0) {
                continue;
            }
            foreach (self::unitsByProductForLineQty($db, $line, $qd) as $pid => $u) {
                $productDeltas[$pid] = ($productDeltas[$pid] ?? 0) + $u;
            }
        }
        foreach ($productDeltas as $pid => $units) {
            $pid = (int) $pid;
            $u = (int) $units;
            if ($pid <= 0 || $u <= 0) {
                continue;
            }
            $db->query(
                'UPDATE products SET stock_units = stock_units + :u WHERE id = :pid',
                ['u' => $u, 'pid' => $pid]
            );
        }
    }

    /**
     * Completa entrega física y liberación de comprometido del saldo pendiente (desde estado parcial vía cambio de estado).
     */
    public static function markRemainingDeliveredFromPartial(Database $db, int $quoteId): void
    {
        $lines = $db->fetchAll('SELECT * FROM quote_items WHERE quote_id = ?', [$quoteId]);
        $productDeltas = [];
        foreach ($lines as $line) {
            $q = (float) ($line['quantity'] ?? 0);
            $qd = (float) ($line['qty_delivered'] ?? 0);
            $pend = max(0.0, $q - $qd);
            if ($pend <= 0) {
                continue;
            }
            foreach (self::unitsByProductForLineQty($db, $line, $pend) as $pid => $u) {
                $productDeltas[$pid] = ($productDeltas[$pid] ?? 0) + $u;
            }
        }
        self::releaseCommittedUnitsForProducts($db, $productDeltas);
        foreach ($productDeltas as $pid => $units) {
            $pid = (int) $pid;
            $u = (int) $units;
            if ($pid <= 0 || $u <= 0) {
                continue;
            }
            $db->query(
                'UPDATE products SET stock_units = GREATEST(0, stock_units - :u) WHERE id = :pid',
                ['u' => $u, 'pid' => $pid]
            );
        }
        $db->query('UPDATE quote_items SET qty_delivered = quantity WHERE quote_id = ?', [$quoteId]);
    }

    /**
     * Unidades de producto correspondientes a una cantidad de línea de presupuesto (caja/unidad o combo).
     *
     * @param array<string, mixed> $line Fila quote_items
     * @return array<int, int> product_id => unidades
     */
    private static function unitsByProductForLineQty(Database $db, array $line, float $lineSaleQty): array
    {
        if ($lineSaleQty <= 0) {
            return [];
        }
        $comboId = (int) ($line['combo_id'] ?? 0);
        if ($comboId > 0) {
            $cps = $db->fetchAll(
                'SELECT product_id, quantity FROM combo_products WHERE combo_id = ?',
                [$comboId]
            );
            $out = [];
            foreach ($cps as $cp) {
                $pid = (int) ($cp['product_id'] ?? 0);
                $qcp = max(1, (int) ($cp['quantity'] ?? 1));
                if ($pid <= 0) {
                    continue;
                }
                $u = (int) round($lineSaleQty * $qcp);
                if ($u <= 0) {
                    continue;
                }
                $out[$pid] = ($out[$pid] ?? 0) + $u;
            }

            return $out;
        }
        $pid = (int) ($line['product_id'] ?? 0);
        if ($pid <= 0) {
            return [];
        }
        $p = $db->fetch('SELECT units_per_box FROM products WHERE id = ?', [$pid]);
        $unitsPerBox = max(1, (int) ($p['units_per_box'] ?? 1) ?: 1);
        $mode = QuoteLinePricing::normalizeUnitType((string) ($line['unit_type'] ?? 'caja'));
        $totalUnits = $mode === 'unidad' ? (int) round($lineSaleQty) : (int) round($lineSaleQty * $unitsPerBox);
        if ($totalUnits <= 0) {
            return [];
        }

        return [$pid => $totalUnits];
    }

    /**
     * Línea de presupuesto considerada totalmente entregada (incluye explosión de combo por componente).
     *
     * @param array<string, mixed> $line Fila quote_items (quantity, qty_delivered, combo_id, …)
     */
    private static function isQuoteItemFullyDelivered(Database $db, array $line): bool
    {
        $q = (float) ($line['quantity'] ?? 0);
        $qd = (float) ($line['qty_delivered'] ?? 0);
        if ($q <= 1e-9) {
            return true;
        }
        if ($qd + 1e-6 < $q) {
            return false;
        }
        $comboId = (int) ($line['combo_id'] ?? 0);
        if ($comboId <= 0) {
            return true;
        }
        $cps = $db->fetchAll(
            'SELECT quantity FROM combo_products WHERE combo_id = ? ORDER BY id ASC',
            [$comboId]
        );
        foreach ($cps as $cp) {
            $per = max(1, (int) ($cp['quantity'] ?? 1));
            $orderedUnits = (int) floor($q * $per + 1e-9);
            $deliveredUnits = (int) floor($qd * $per + 1e-9);
            if ($deliveredUnits < $orderedUnits) {
                return false;
            }
        }

        return true;
    }

    public static function applyPartialDelivery(Database $db, int $quoteId, array $deliveredQtys): void
    {
        $productDeltas = [];
        foreach ($deliveredQtys as $itemIdKey => $qtyVal) {
            $itemId = (int) $itemIdKey;
            $addQty = (float) $qtyVal;
            if ($itemId <= 0 || $addQty <= 0) {
                continue;
            }
            $line = $db->fetch('SELECT * FROM quote_items WHERE id = ? AND quote_id = ?', [$itemId, $quoteId]);
            if ($line === null) {
                continue;
            }
            foreach (self::unitsByProductForLineQty($db, $line, $addQty) as $pid => $u) {
                $productDeltas[$pid] = ($productDeltas[$pid] ?? 0) + $u;
            }
            $db->query(
                'UPDATE quote_items SET qty_delivered = qty_delivered + :addq WHERE id = :iid AND quote_id = :qid',
                ['addq' => $addQty, 'iid' => $itemId, 'qid' => $quoteId]
            );
        }
        foreach ($productDeltas as $pid => $units) {
            $pid = (int) $pid;
            $u = (int) $units;
            if ($pid <= 0 || $u <= 0) {
                continue;
            }
            $db->query(
                'UPDATE products SET stock_units = GREATEST(0, stock_units - :u) WHERE id = :pid',
                ['u' => $u, 'pid' => $pid]
            );
        }
    }

    /**
     * @param array<int, float|int|string> $deliveredQtys quote_item_id => cantidad entregada en esta operación (unidades de línea)
     */
    public static function markPartialDelivery(Database $db, int $quoteId, array $deliveredQtys): void
    {
        $filtered = [];
        foreach ($deliveredQtys as $k => $v) {
            $itemId = (int) $k;
            $addQty = (float) $v;
            if ($itemId > 0 && $addQty > 0) {
                $filtered[$itemId] = $addQty;
            }
        }
        if ($filtered === []) {
            return;
        }
        $productDeltas = [];
        foreach ($filtered as $itemId => $addQty) {
            $line = $db->fetch('SELECT * FROM quote_items WHERE id = ? AND quote_id = ?', [$itemId, $quoteId]);
            if ($line === null) {
                continue;
            }
            foreach (self::unitsByProductForLineQty($db, $line, $addQty) as $pid => $u) {
                $productDeltas[$pid] = ($productDeltas[$pid] ?? 0) + $u;
            }
        }
        self::releaseCommittedUnitsForProducts($db, $productDeltas);
        self::applyPartialDelivery($db, $quoteId, $filtered);

        $items = $db->fetchAll(
            'SELECT id, combo_id, product_id, quantity, qty_delivered FROM quote_items WHERE quote_id = ?',
            [$quoteId]
        );
        $complete = true;
        foreach ($items as $it) {
            if (!self::isQuoteItemFullyDelivered($db, $it)) {
                $complete = false;
                break;
            }
        }
        if ($complete) {
            $db->update('quotes', ['status' => 'delivered', 'delivery_stock_applied' => 1], 'id = :id', ['id' => $quoteId]);
        } else {
            $db->update('quotes', ['status' => 'partially_delivered'], 'id = :id', ['id' => $quoteId]);
        }
    }

    /**
     * Misma explosión que unitsByProductForQuotes pero con cantidad pendiente por línea
     * (accepted: línea completa; partially_delivered: quantity - qty_delivered).
     *
     * @param list<int> $quoteIds
     * @return array<int, array<int, int>> quote_id => [product_id => units]
     */
    public static function pendingUnitsByProductForQuotes(Database $db, array $quoteIds): array
    {
        if ($quoteIds === []) {
            return [];
        }
        $rows = self::fetchPendingExplodedRowsForQuotes($db, $quoteIds);
        $out = [];
        foreach ($rows as $row) {
            $qid = (int) ($row['quote_id'] ?? 0);
            $pid = (int) ($row['product_id'] ?? 0);
            if ($qid <= 0 || $pid <= 0) {
                continue;
            }
            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $mode = QuoteLinePricing::normalizeUnitType((string) ($row['unit_type'] ?? 'caja'));
            $unitsPerBox = max(1, (int) ($row['units_per_box'] ?? 1) ?: 1);
            $totalUnits = $mode === 'unidad' ? (int) round($qty) : (int) round($qty * $unitsPerBox);
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
    private static function fetchPendingExplodedRowsForQuotes(Database $db, array $quoteIds): array
    {
        $quoteIds = array_values(array_filter(array_map(static fn ($x): int => (int) $x, $quoteIds), static fn ($x): bool => $x > 0));
        if ($quoteIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
        $params = array_merge($quoteIds, $quoteIds);

        return $db->fetchAll(
            "SELECT x.quote_id, x.product_id, x.pending_qty AS quantity, x.unit_type, p.units_per_box
             FROM (
                 SELECT qi.quote_id, qi.product_id,
                        (CASE WHEN q.status = 'accepted' THEN qi.quantity
                              ELSE GREATEST(0, qi.quantity - COALESCE(qi.qty_delivered, 0)) END) AS pending_qty,
                        qi.unit_type
                 FROM quote_items qi
                 JOIN quotes q ON q.id = qi.quote_id
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.product_id IS NOT NULL
                   AND q.status IN ('accepted','partially_delivered')
                 UNION ALL
                 SELECT qi.quote_id, cp.product_id,
                        (CASE WHEN q.status = 'accepted' THEN qi.quantity
                              ELSE GREATEST(0, qi.quantity - COALESCE(qi.qty_delivered, 0)) END) * cp.quantity AS pending_qty,
                        'unidad' AS unit_type
                 FROM quote_items qi
                 JOIN quotes q ON q.id = qi.quote_id
                 JOIN combo_products cp ON cp.combo_id = qi.combo_id
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.combo_id IS NOT NULL
                   AND q.status IN ('accepted','partially_delivered')
             ) x
             JOIN products p ON p.id = x.product_id
             WHERE x.pending_qty > 0",
            $params
        );
    }

    /**
     * Detalle de demanda pendiente por presupuesto/producto (misma forma que demandBreakdownForQuotes).
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
    public static function pendingDemandBreakdownForQuotes(Database $db, array $quoteIds): array
    {
        $quoteIds = array_values(array_filter(array_map(static fn ($x): int => (int) $x, $quoteIds), static fn ($x): bool => $x > 0));
        if ($quoteIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
        $params = array_merge($quoteIds, $quoteIds);
        $rows = $db->fetchAll(
            "SELECT x.quote_id, x.product_id, x.source_type, x.combo_id, x.combo_name,
                    x.pending_qty AS quantity, x.unit_type, p.units_per_box
             FROM (
                 SELECT qi.quote_id, qi.product_id, 'product' AS source_type,
                        NULL AS combo_id, NULL AS combo_name,
                        (CASE WHEN q.status = 'accepted' THEN qi.quantity
                              ELSE GREATEST(0, qi.quantity - COALESCE(qi.qty_delivered, 0)) END) AS pending_qty,
                        qi.unit_type
                 FROM quote_items qi
                 JOIN quotes q ON q.id = qi.quote_id
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.product_id IS NOT NULL
                   AND q.status IN ('accepted','partially_delivered')
                 UNION ALL
                 SELECT qi.quote_id, cp.product_id, 'combo' AS source_type,
                        qi.combo_id AS combo_id, cmb.name AS combo_name,
                        (CASE WHEN q.status = 'accepted' THEN qi.quantity
                              ELSE GREATEST(0, qi.quantity - COALESCE(qi.qty_delivered, 0)) END) * cp.quantity AS pending_qty,
                        'unidad' AS unit_type
                 FROM quote_items qi
                 JOIN quotes q ON q.id = qi.quote_id
                 JOIN combo_products cp ON cp.combo_id = qi.combo_id
                 LEFT JOIN combos cmb ON cmb.id = qi.combo_id
                 WHERE qi.quote_id IN ({$placeholders}) AND qi.combo_id IS NOT NULL
                   AND q.status IN ('accepted','partially_delivered')
             ) x
             JOIN products p ON p.id = x.product_id
             WHERE x.pending_qty > 0",
            $params
        );

        $out = [];
        foreach ($rows as $row) {
            $qid = (int) ($row['quote_id'] ?? 0);
            $pid = (int) ($row['product_id'] ?? 0);
            if ($qid <= 0 || $pid <= 0) {
                continue;
            }
            $qty = (float) ($row['quantity'] ?? 0);
            $mode = QuoteLinePricing::normalizeUnitType((string) ($row['unit_type'] ?? 'caja'));
            $unitsPerBox = max(1, (int) ($row['units_per_box'] ?? 1) ?: 1);
            $units = $mode === 'unidad' ? (int) round($qty) : (int) round($qty * $unitsPerBox);
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
}
