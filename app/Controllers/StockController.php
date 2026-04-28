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
        $q = trim((string) $this->query('q', ''));
        $where = ['p.is_active = 1', 'COALESCE(p.stock_units, 0) > 0'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.code LIKE :q OR p.name LIKE :q2)';
            $params['q'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }

        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.stock_units, p.units_per_box,
                    c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.stock_units DESC, p.name ASC',
            $params
        );

        $this->view('stock/index', [
            'title' => 'Stock actual',
            'products' => $rows,
            'q' => $q,
        ]);
    }
}
