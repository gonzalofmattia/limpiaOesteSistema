<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $productsActive = (int) $db->fetchColumn('SELECT COUNT(*) FROM products WHERE is_active = 1');
        $categoriesCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM categories');
        $clientsCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM clients WHERE is_active = 1');
        $quotesCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM quotes');

        $catStats = $db->fetchAll(
            'SELECT c.id, c.name, c.slug, c.default_discount, c.is_active,
                    COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order, c.name'
        );

        $recentQuotes = $db->fetchAll(
            'SELECT q.*, c.name AS client_name
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id
             ORDER BY q.created_at DESC
             LIMIT 5'
        );

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'productsActive' => $productsActive,
            'categoriesCount' => $categoriesCount,
            'clientsCount' => $clientsCount,
            'quotesCount' => $quotesCount,
            'catStats' => $catStats,
            'recentQuotes' => $recentQuotes,
        ]);
    }
}
