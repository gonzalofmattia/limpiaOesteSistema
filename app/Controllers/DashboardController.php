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
        $acceptedQuotesTotal = 0.0;
        $acceptedQuotesCount = 0;
        $collectedTotal = 0.0;
        $supplierPaymentsTotal = 0.0;
        $deliveredNetTotal = 0.0;
        $deliveredCostTotal = 0.0;
        $deliveredProfit = 0.0;
        $monthlyLabels = [];
        $monthlyAccepted = [];
        $monthlyCollected = [];
        $monthlySupplierPayments = [];
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

            $acceptedQuotesTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(total), 0)
                 FROM quotes
                 WHERE status IN ('accepted', 'delivered')"
            );
            $acceptedQuotesCount = (int) $db->fetchColumn(
                "SELECT COUNT(*)
                 FROM quotes
                 WHERE status IN ('accepted', 'delivered')"
            );
            $collectedTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM account_transactions
                 WHERE account_type = 'client' AND transaction_type = 'payment'"
            );
            $supplierPaymentsTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM account_transactions
                 WHERE account_type = 'supplier' AND transaction_type = 'payment'"
            );

            $deliveredNetTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(subtotal), 0)
                 FROM quotes
                 WHERE status = 'delivered'"
            );
            $ivaRateSetting = (float) (setting('iva_rate', '21') ?? 21);
            $ivaDivisor = 1 + ($ivaRateSetting / 100);
            if ($ivaDivisor <= 0) {
                $ivaDivisor = 1.21;
            }
            $deliveredCostTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(
                    (
                        CASE
                            WHEN q.include_iva = 1 THEN (qi.subtotal / ?)
                            ELSE qi.subtotal
                        END
                    ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
                ), 0) AS delivered_cost
                 FROM quote_items qi
                 INNER JOIN quotes q ON q.id = qi.quote_id
                 WHERE q.status = 'delivered'",
                [$ivaDivisor]
            );
            $deliveredProfit = round($deliveredNetTotal - $deliveredCostTotal, 2);

            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $key = date('Y-m', strtotime("-{$i} months"));
                $months[$key] = [
                    'label' => date('M y', strtotime($key . '-01')),
                    'accepted' => 0.0,
                    'collected' => 0.0,
                    'supplier_payments' => 0.0,
                ];
            }

            $acceptedRows = $db->fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total), 0) AS accepted_total
                 FROM quotes
                 WHERE status IN ('accepted', 'delivered')
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY ym
                 ORDER BY ym"
            );
            foreach ($acceptedRows as $row) {
                $ym = (string) ($row['ym'] ?? '');
                if (!isset($months[$ym])) {
                    continue;
                }
                $months[$ym]['accepted'] = round((float) ($row['accepted_total'] ?? 0), 2);
            }

            $monthlyRows = $db->fetchAll(
                "SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS ym,
                        SUM(CASE WHEN account_type = 'client' AND transaction_type = 'payment' THEN amount ELSE 0 END) AS collected,
                        SUM(CASE WHEN account_type = 'supplier' AND transaction_type = 'payment' THEN amount ELSE 0 END) AS supplier_payments
                 FROM account_transactions
                 WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY ym
                 ORDER BY ym"
            );
            foreach ($monthlyRows as $row) {
                $ym = (string) ($row['ym'] ?? '');
                if (!isset($months[$ym])) {
                    continue;
                }
                $months[$ym]['collected'] = round((float) ($row['collected'] ?? 0), 2);
                $months[$ym]['supplier_payments'] = round((float) ($row['supplier_payments'] ?? 0), 2);
            }

            foreach ($months as $month) {
                $monthlyLabels[] = (string) $month['label'];
                $monthlyAccepted[] = (float) $month['accepted'];
                $monthlyCollected[] = (float) $month['collected'];
                $monthlySupplierPayments[] = (float) $month['supplier_payments'];
            }
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
            'acceptedQuotesTotal' => $acceptedQuotesTotal,
            'acceptedQuotesCount' => $acceptedQuotesCount,
            'collectedTotal' => $collectedTotal,
            'supplierPaymentsTotal' => $supplierPaymentsTotal,
            'deliveredNetTotal' => $deliveredNetTotal,
            'deliveredCostTotal' => $deliveredCostTotal,
            'deliveredProfit' => $deliveredProfit,
            'monthlyLabels' => $monthlyLabels,
            'monthlyAccepted' => $monthlyAccepted,
            'monthlyCollected' => $monthlyCollected,
            'monthlySupplierPayments' => $monthlySupplierPayments,
        ]);
    }

    public function detail(string $metric): void
    {
        $db = Database::getInstance();
        $metric = strtolower(trim($metric));
        $title = 'Detalle indicador';
        $value = 0.0;
        $explain = '';
        $rows = [];

        if ($metric === 'aceptados') {
            $title = 'Detalle - Presupuestos aceptados';
            $value = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(total), 0)
                 FROM quotes
                 WHERE status IN ('accepted', 'delivered')"
            );
            $explain = "Suma de total de presupuestos con estado accepted o delivered.";
            $rows = $db->fetchAll(
                "SELECT q.id, q.quote_number, q.total, q.status, q.created_at, c.name AS client_name
                 FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 WHERE q.status IN ('accepted', 'delivered')
                 ORDER BY q.created_at DESC
                 LIMIT 30"
            );
        } elseif ($metric === 'cobrado') {
            $title = 'Detalle - Cobrado';
            $value = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM account_transactions
                 WHERE account_type = 'client' AND transaction_type = 'payment'"
            );
            $explain = "Suma de pagos de clientes registrados en cuenta corriente.";
            $rows = $db->fetchAll(
                "SELECT at.id, at.transaction_date, at.amount, at.description, c.name AS client_name
                 FROM account_transactions at
                 LEFT JOIN clients c ON c.id = at.account_id
                 WHERE at.account_type = 'client' AND at.transaction_type = 'payment'
                 ORDER BY at.transaction_date DESC, at.id DESC
                 LIMIT 30"
            );
        } elseif ($metric === 'ganancia') {
            $title = 'Detalle - Ganancia estimada';
            $deliveredNetTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(subtotal), 0)
                 FROM quotes
                 WHERE status = 'delivered'"
            );
            $ivaRateSetting = (float) (setting('iva_rate', '21') ?? 21);
            $ivaDivisor = 1 + ($ivaRateSetting / 100);
            if ($ivaDivisor <= 0) {
                $ivaDivisor = 1.21;
            }
            $deliveredCostTotal = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(
                    (
                        CASE
                            WHEN q.include_iva = 1 THEN (qi.subtotal / ?)
                            ELSE qi.subtotal
                        END
                    ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
                ), 0) AS delivered_cost
                 FROM quote_items qi
                 INNER JOIN quotes q ON q.id = qi.quote_id
                 WHERE q.status = 'delivered'",
                [$ivaDivisor]
            );
            $value = round($deliveredNetTotal - $deliveredCostTotal, 2);
            $explain = 'Ganancia = entregado neto - costo estimado de líneas entregadas.';
            $rows = $db->fetchAll(
                "SELECT q.id, q.quote_number, q.subtotal AS delivered_net,
                        COALESCE(SUM(
                            (
                                CASE
                                    WHEN q.include_iva = 1 THEN (qi.subtotal / ?)
                                    ELSE qi.subtotal
                                END
                            ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
                        ), 0) AS estimated_cost
                 FROM quotes q
                 LEFT JOIN quote_items qi ON qi.quote_id = q.id
                 WHERE q.status = 'delivered'
                 GROUP BY q.id
                 ORDER BY q.created_at DESC
                 LIMIT 30",
                [$ivaDivisor]
            );
        } elseif ($metric === 'pendiente') {
            $title = 'Detalle - Pendiente de cobro';
            $value = ClientReceivableSummary::totalReceivable($db);
            $explain = 'Suma de saldos positivos de clientes (híbrido facturas/aceptados menos cobros y ajustes).';
            $txAgg = ClientReceivableSummary::sqlTxAggByClientSubquery();
            $qAgg = ClientReceivableSummary::sqlQuotesAcceptedByClientSubquery();
            $hybrid = ClientReceivableSummary::sqlCaseHybridBalance();
            $rows = $db->fetchAll(
                "SELECT c.id, c.name, ROUND({$hybrid}, 2) AS balance
                 FROM clients c
                 LEFT JOIN ({$txAgg}) tx ON tx.account_id = c.id
                 LEFT JOIN ({$qAgg}) q ON q.client_id = c.id
                 WHERE c.is_active = 1
                 HAVING balance > 0
                 ORDER BY balance DESC
                 LIMIT 30"
            );
        } else {
            flash('error', 'Indicador no encontrado.');
            redirect('/');
            return;
        }

        $this->view('dashboard/detail', [
            'title' => $title,
            'metric' => $metric,
            'value' => $value,
            'explain' => $explain,
            'rows' => $rows,
        ]);
    }
}
