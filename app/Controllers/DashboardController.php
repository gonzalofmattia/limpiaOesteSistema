<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClientReceivableSummary;
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
        $accountsTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
        $receivable = 0.0;
        $clientsWithDebt = 0;
        $supplierDebts = [];
        if ($accountsTable) {
            $receivable = ClientReceivableSummary::totalReceivable($db);
            $clientsWithDebt = ClientReceivableSummary::countClientsWithDebt($db);
            $supplierDebts = $db->fetchAll(
                "SELECT s.id, s.name,
                        COALESCE(SUM(CASE WHEN at.transaction_type = 'invoice' THEN at.amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN at.transaction_type = 'payment' THEN at.amount ELSE 0 END), 0) +
                        COALESCE(SUM(CASE WHEN at.transaction_type = 'adjustment' THEN at.amount ELSE 0 END), 0) AS debt
                 FROM suppliers s
                 LEFT JOIN account_transactions at
                    ON at.account_type = 'supplier' AND at.account_id = s.id
                 WHERE s.is_active = 1
                 GROUP BY s.id, s.name
                 ORDER BY s.name"
            );
        }

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
            'accountsEnabled' => $accountsTable,
            'receivable' => $receivable,
            'clientsWithDebt' => $clientsWithDebt,
            'supplierDebts' => $supplierDebts,
        ]);
    }
}
