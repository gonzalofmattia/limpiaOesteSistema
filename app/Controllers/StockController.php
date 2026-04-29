<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class StockController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $hasAdjustmentsTable = $this->hasAdjustmentsTable($db);
        $q = trim((string) $this->query('q', ''));
        $where = ['p.is_active = 1', 'COALESCE(p.stock_units, 0) > 0'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.code LIKE :q OR p.name LIKE :q2)';
            $params['q'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }

        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.stock_units, COALESCE(p.stock_committed_units, 0) AS stock_committed_units, p.units_per_box,
                    c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.stock_units DESC, p.name ASC',
            $params
        );

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
            'adjustments' => $adjustments,
            'hasAdjustmentsTable' => $hasAdjustmentsTable,
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

    private function hasAdjustmentsTable(Database $db): bool
    {
        try {
            return (bool) $db->fetchColumn("SHOW TABLES LIKE 'stock_adjustments'");
        } catch (\Throwable) {
            return false;
        }
    }
}
