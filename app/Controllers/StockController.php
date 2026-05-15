<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Helpers\SeiqOrderBuilder;
use App\Models\Database;

final class StockController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $hasAdjustmentsTable = $this->hasAdjustmentsTable($db);
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 50);
        $perPage = $perPage > 0 ? min($perPage, 100) : 50;
        $q = trim((string) $this->query('search', ''));
        if ($q === '') {
            $q = trim((string) $this->query('q', ''));
        }
        $stockFilter = trim((string) $this->query('stock_filter', ''));
        $where = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.code LIKE :q OR p.name LIKE :q2)';
            $params['q'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }

        $inTransitJoin = SeiqOrderBuilder::inTransitJoinSql();

        if ($stockFilter === 'bajo') {
            $where[] = 'p.stock_minimum IS NOT NULL AND ' . SeiqOrderBuilder::effectiveStockSql() . ' < p.stock_minimum';
        } else {
            $where[] = '(COALESCE(p.stock_units, 0) > 0 OR COALESCE(t.in_transit_units, 0) > 0)';
        }

        $total = (int) $db->fetchColumn(
            'SELECT COUNT(*)
             FROM products p
             JOIN categories c ON c.id = p.category_id
             ' . $inTransitJoin . '
             WHERE ' . implode(' AND ', $where),
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.is_active, p.stock_units, COALESCE(p.stock_committed_units, 0) AS stock_committed_units,
                    p.units_per_box, p.stock_minimum,
                    c.name AS category_name, COALESCE(t.in_transit_units, 0) AS in_transit_units
             FROM products p
             JOIN categories c ON c.id = p.category_id
             ' . $inTransitJoin . '
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.stock_units DESC, p.name ASC
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );

        $lowStockCount = SeiqOrderBuilder::countBelowMinimum($db);

        $adjustments = [];
        if ($hasAdjustmentsTable) {
            $adjustments = $db->fetchAll(
                "SELECT sa.*, p.code, p.name
                 FROM stock_adjustments sa
                 JOIN products p ON p.id = sa.product_id
                 ORDER BY sa.created_at DESC, sa.id DESC
                 LIMIT 20"
            );
        }

        $this->view('stock/index', [
            'title' => 'Stock actual',
            'products' => $rows,
            'q' => $q,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'adjustments' => $adjustments,
            'hasAdjustmentsTable' => $hasAdjustmentsTable,
            'stockFilter' => $stockFilter,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    public function adjust(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/stock-actual');
            return;
        }
        $db = Database::getInstance();
        if (!$this->hasAdjustmentsTable($db)) {
            flash('error', 'Falta la tabla de historial de ajustes. Ejecutá la migración 2026_04_28_stock_adjustments.sql.');
            redirect('/stock-actual');
            return;
        }

        $code = strtoupper(trim((string) $this->input('product_code', '')));
        $newStock = (int) $this->input('new_stock', -1);
        $notes = trim((string) $this->input('notes', ''));

        if ($code === '') {
            flash('error', 'Ingresá el código del producto.');
            redirect('/stock-actual');
            return;
        }
        if ($newStock < 0) {
            flash('error', 'El nuevo stock debe ser un número mayor o igual a 0.');
            redirect('/stock-actual');
            return;
        }

        $product = $db->fetch(
            'SELECT id, code, name, stock_units FROM products WHERE UPPER(code) = ? LIMIT 1',
            [$code]
        );
        if (!$product) {
            flash('error', 'No se encontró un producto con ese código.');
            redirect('/stock-actual');
            return;
        }

        $prevStock = (int) ($product['stock_units'] ?? 0);
        $delta = $newStock - $prevStock;
        if ($delta === 0) {
            flash('info', 'Sin cambios: el stock ya estaba en ese valor.');
            redirect('/stock-actual');
            return;
        }

        $username = trim((string) ($_SESSION['admin_username'] ?? 'admin'));
        $db->getPdo()->beginTransaction();
        try {
            $db->update('products', ['stock_units' => $newStock], 'id = :id', ['id' => (int) $product['id']]);
            $db->insert('stock_adjustments', [
                'product_id' => (int) $product['id'],
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'difference' => $delta,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $username,
            ]);
            $db->getPdo()->commit();
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'No se pudo guardar el ajuste: ' . $e->getMessage());
            redirect('/stock-actual');
            return;
        }

        flash('success', 'Stock actualizado para ' . (string) $product['code'] . '.');
        redirect('/stock-actual');
    }

    public function inlineAdjust(): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 403);
            return;
        }
        $db = Database::getInstance();
        if (!$this->hasAdjustmentsTable($db)) {
            $this->json(['success' => false, 'error' => 'Falta la tabla de historial de ajustes.'], 422);
            return;
        }

        $raw = (string) file_get_contents('php://input');
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->json(['success' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }

        if (!is_array($payload)) {
            $this->json(['success' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }

        $productId = (int) ($payload['product_id'] ?? 0);
        $newStock = (int) ($payload['new_stock'] ?? -1);
        $notesRaw = trim((string) ($payload['notes'] ?? ''));
        $notes = $notesRaw !== '' ? $notesRaw : 'Ajuste manual inline';

        if ($productId <= 0) {
            $this->json(['success' => false, 'error' => 'Producto inválido.'], 400);
            return;
        }
        if ($newStock < 0) {
            $this->json(['success' => false, 'error' => 'El stock debe ser mayor o igual a 0.'], 400);
            return;
        }

        $product = $db->fetch(
            'SELECT id, stock_units, COALESCE(stock_committed_units, 0) AS stock_committed_units FROM products WHERE id = ? LIMIT 1',
            [$productId]
        );
        if (!$product) {
            $this->json(['success' => false, 'error' => 'Producto no encontrado.'], 404);
            return;
        }

        $prevStock = (int) ($product['stock_units'] ?? 0);
        $committed = (int) ($product['stock_committed_units'] ?? 0);
        $delta = $newStock - $prevStock;
        if ($delta === 0) {
            $disponible = $newStock - $committed;
            $this->json([
                'success' => true,
                'stock_units' => $newStock,
                'stock_committed_units' => $committed,
                'disponible' => $disponible,
            ]);
            return;
        }

        $db->getPdo()->beginTransaction();
        try {
            $db->update('products', ['stock_units' => $newStock], 'id = :id', ['id' => $productId]);
            $db->insert('stock_adjustments', [
                'product_id' => $productId,
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'difference' => $delta,
                'notes' => $notes,
                'created_by' => 'admin',
            ]);
            $db->getPdo()->commit();
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            $this->json(['success' => false, 'error' => 'No se pudo guardar: ' . $e->getMessage()], 500);
            return;
        }

        $row = $db->fetch(
            'SELECT stock_units, COALESCE(stock_committed_units, 0) AS stock_committed_units FROM products WHERE id = ? LIMIT 1',
            [$productId]
        );
        $su = (int) ($row['stock_units'] ?? $newStock);
        $sc = (int) ($row['stock_committed_units'] ?? $committed);
        $disponible = $su - $sc;

        $this->json([
            'success' => true,
            'stock_units' => $su,
            'stock_committed_units' => $sc,
            'disponible' => $disponible,
        ]);
        return;
    }

    public function reorderSuggestion(): void
    {
        $this->json(['suggestions' => $this->buildReorderSuggestions()]);
    }

    /** @return list<array<string, mixed>> */
    private function buildReorderSuggestions(): array
    {
        $db = Database::getInstance();
        $inTransit = SeiqOrderBuilder::unitsInTransit($db);

        $products = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.stock_units,
                    COALESCE(p.stock_committed_units, 0) AS stock_committed_units,
                    p.units_per_box, p.stock_minimum,
                    c.name AS category_name,
                    COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id,
                    s.name AS supplier_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE p.is_active = 1
               AND p.stock_minimum > 0
               AND COALESCE(c.supplier_id, pc.supplier_id) IS NOT NULL'
        );

        $suggestions = [];
        foreach ($products as $p) {
            $disponible = (int) $p['stock_units'] - (int) $p['stock_committed_units'];
            $productId = (int) $p['id'];
            $enCamino = $inTransit[$productId] ?? 0;
            $stockEfectivo = $disponible + $enCamino;
            $minimo = (int) $p['stock_minimum'];

            if ($stockEfectivo >= $minimo) {
                continue;
            }

            $faltante = $minimo - $stockEfectivo;
            $unitsPerBox = max(1, (int) $p['units_per_box']);
            $cajasAPedir = (int) ceil($faltante / $unitsPerBox);

            $suggestions[] = [
                'product_id' => $productId,
                'code' => (string) $p['code'],
                'name' => (string) $p['name'],
                'category_name' => (string) ($p['category_name'] ?? ''),
                'supplier_id' => (int) ($p['supplier_id'] ?? 0),
                'supplier_name' => (string) ($p['supplier_name'] ?? ''),
                'units_per_box' => $unitsPerBox,
                'stock_efectivo' => $stockEfectivo,
                'disponible' => $disponible,
                'en_camino' => $enCamino,
                'minimo' => $minimo,
                'faltante' => $faltante,
                'cajas_sugeridas' => $cajasAPedir,
            ];
        }

        usort(
            $suggestions,
            static fn (array $a, array $b): int => ($a['supplier_name'] <=> $b['supplier_name'])
                ?: ($a['category_name'] <=> $b['category_name'])
                ?: ($a['name'] <=> $b['name'])
        );

        return $suggestions;
    }

    public function reposicion(): void
    {
        redirect('/stock-actual?sugerencia=1');
    }

    public function projection(): void
    {
        $db = Database::getInstance();

        $daysHistory = null;
        try {
            $raw = $db->fetchColumn(
                "SELECT MIN(q.created_at)
                 FROM quotes q
                 WHERE q.status IN ('accepted','delivered','partially_delivered')"
            );
            if ($raw !== null && $raw !== false && $raw !== '') {
                $oldest = new \DateTimeImmutable((string) $raw);
                $daysHistory = (int) $oldest->diff(new \DateTimeImmutable('now'))->days;
            }
        } catch (\Throwable) {
        }
        $insufficientHistory = $daysHistory === null || $daysHistory < 30;

        $sql = "SELECT p.id, p.code, p.name, p.stock_units, p.units_per_box, p.precio_lista_caja,
                       p.discount_override,
                       COALESCE(pc.slug, c.slug) AS category_slug,
                       pc.slug AS parent_slug,
                       c.default_discount,
                       pc.default_discount AS parent_discount,
                       s.name AS supplier_name,
                       s.slug AS supplier_slug,
                       COALESCE(v.v30, 0) AS vendido_30d,
                       COALESCE(v.v60, 0) AS vendido_60d,
                       COALESCE(v.v90, 0) AS vendido_90d
                FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN categories pc ON c.parent_id = pc.id
                LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
                LEFT JOIN (
                    SELECT x.product_id,
                           SUM(CASE WHEN x.sale_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN x.units_sold ELSE 0 END) AS v30,
                           SUM(CASE WHEN x.sale_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) THEN x.units_sold ELSE 0 END) AS v60,
                           SUM(x.units_sold) AS v90
                    FROM (
                        SELECT qi.product_id AS product_id,
                               CASE WHEN qi.unit_type = 'caja'
                                    THEN qi.quantity * COALESCE(p2.units_per_box, 1)
                                    ELSE qi.quantity
                               END AS units_sold,
                               q.created_at AS sale_at
                        FROM quote_items qi
                        JOIN quotes q ON q.id = qi.quote_id
                        JOIN products p2 ON p2.id = qi.product_id
                        WHERE q.status IN ('accepted','delivered','partially_delivered')
                          AND q.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                          AND qi.product_id IS NOT NULL

                        UNION ALL

                        SELECT cp.product_id AS product_id,
                               (qi.quantity * cp.quantity) AS units_sold,
                               q.created_at AS sale_at
                        FROM quote_items qi
                        JOIN quotes q ON q.id = qi.quote_id
                        JOIN combo_products cp ON cp.combo_id = qi.combo_id
                        WHERE q.status IN ('accepted','delivered','partially_delivered')
                          AND q.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                          AND qi.combo_id IS NOT NULL
                    ) x
                    GROUP BY x.product_id
                ) v ON v.product_id = p.id
                WHERE p.is_active = 1
                ORDER BY
                    (CASE WHEN COALESCE(v.v90, 0) = 0 THEN 1 ELSE 0 END) ASC,
                    (CASE WHEN COALESCE(v.v90, 0) > 0 THEN (p.stock_units * 90.0 / v.v90) ELSE 999999 END) ASC,
                    p.name ASC";

        $rawRows = $db->fetchAll($sql);

        $totalCompra30d = 0.0;
        $bySupplier = ['seiq' => 0.0, 'higienik' => 0.0, 'otros' => 0.0];
        $rows = [];

        foreach ($rawRows as $r) {
            $v90 = (float) ($r['vendido_90d'] ?? 0);
            $promedioDiario = $v90 > 0 ? $v90 / 90.0 : 0.0;
            $stock = (int) ($r['stock_units'] ?? 0);
            $upb = (int) ($r['units_per_box'] ?? 0);
            $listaCaja = $r['precio_lista_caja'];
            $listaCajaF = ($listaCaja !== null && $listaCaja !== '') ? (float) $listaCaja : 0.0;

            $discount = PricingEngine::getEffectiveDiscount($r);
            $costoCaja = $listaCajaF > 0 ? PricingEngine::calculateCost($listaCajaF, $discount) : 0.0;

            $sinActividad = $v90 <= 0;
            $necesidad30 = $promedioDiario > 0 ? $promedioDiario * 30.0 : 0.0;
            $cajas = 0;
            if ($promedioDiario > 0 && $upb > 0) {
                $cajas = (int) ceil($necesidad30 / $upb);
            }
            $costoEstimado = ($sinActividad || $cajas <= 0 || $costoCaja <= 0) ? 0.0 : round($cajas * $costoCaja, 2);

            $diasRestantes = null;
            if ($promedioDiario > 0) {
                $diasRestantes = $stock / $promedioDiario;
            }

            $slug = (string) ($r['supplier_slug'] ?? '');
            if (!$sinActividad && $costoEstimado > 0) {
                $totalCompra30d += $costoEstimado;
                if ($slug === 'seiq') {
                    $bySupplier['seiq'] += $costoEstimado;
                } elseif ($slug === 'higienik') {
                    $bySupplier['higienik'] += $costoEstimado;
                } else {
                    $bySupplier['otros'] += $costoEstimado;
                }
            }

            $rows[] = $r + [
                'promedio_diario' => $promedioDiario,
                'dias_restantes' => $diasRestantes,
                'necesidad_30d_unidades' => $necesidad30,
                'cajas_a_pedir' => $cajas,
                'costo_caja' => $costoCaja,
                'costo_estimado' => $costoEstimado,
                'sin_actividad' => $sinActividad,
                'tiene_precio_caja' => $listaCajaF > 0,
                'discount_percent' => $discount,
            ];
        }

        $this->view('stock/projection', [
            'title' => 'Proyección de compra',
            'subtitle' => 'Estimación informativa próximos 30 días según ventas (90 días)',
            'rows' => $rows,
            'totalCompra30d' => $totalCompra30d,
            'bySupplier' => $bySupplier,
            'insufficientHistory' => $insufficientHistory,
            'daysHistory' => $daysHistory,
        ]);
    }

    private function hasAdjustmentsTable(Database $db): bool
    {
        try {
            return (bool) $db->fetchColumn("SHOW TABLES LIKE 'stock_adjustments'");
        } catch (\Throwable) {
            return false;
        }
    }
}
