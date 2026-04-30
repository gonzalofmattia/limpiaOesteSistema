<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClientReceivableSummary;
use App\Helpers\QuoteDeliveryStock;
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
        $salesTodayCount = 0;
        $salesTodayAmount = 0.0;
        $salesWeekCount = 0;
        $salesWeekAmount = 0.0;
        $salesMonthCount = 0;
        $salesMonthAmount = 0.0;
        $salesMonthAvgTicket = 0.0;
        $topProductsMonth = [];
        $topClientsMonth = [];
        $topCombosMonth = [];
        $pendingDeliveryCount = 0;
        $pendingDeliveryAmount = 0.0;
        $pendingCollectionDelivered = 0.0;
        $lowStockProducts = $db->fetchAll(
            "SELECT p.id, p.name, p.stock_units, COALESCE(p.stock_committed_units, 0) AS stock_committed_units,
                    (p.stock_units - COALESCE(p.stock_committed_units, 0)) AS stock_available_units
             FROM products p
             WHERE p.is_active = 1
               AND (p.stock_units - COALESCE(p.stock_committed_units, 0)) <= 0
             ORDER BY stock_available_units ASC, p.name ASC
             LIMIT 10"
        );
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
                    COALESCE(
                        qi.cost_subtotal_snapshot,
                        (
                            CASE
                                WHEN q.include_iva = 1 THEN (qi.subtotal / ?)
                                ELSE qi.subtotal
                            END
                        ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
                    )
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

            $today = date('Y-m-d');
            $salesTodayCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE status IN ('accepted','delivered') AND DATE(created_at) = ?", [$today]);
            $salesTodayAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status IN ('accepted','delivered') AND DATE(created_at) = ?", [$today]);
            $salesWeekCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE status IN ('accepted','delivered') AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
            $salesWeekAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status IN ('accepted','delivered') AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
            $salesMonthCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE status IN ('accepted','delivered') AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
            $salesMonthAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status IN ('accepted','delivered') AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
            $salesMonthAvgTicket = $salesMonthCount > 0 ? round($salesMonthAmount / $salesMonthCount, 2) : 0.0;
            $pendingDeliveryCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE status = 'accepted'");
            $pendingDeliveryAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE status = 'accepted'");

            $deliveredRows = $db->fetchAll(
                "SELECT id, total FROM quotes
                 WHERE status = 'delivered'
                   AND YEAR(created_at)=YEAR(CURDATE())
                   AND MONTH(created_at)=MONTH(CURDATE())"
            );
            $quoteIds = array_map(static fn (array $r): int => (int) $r['id'], $deliveredRows);
            $monthProducts = [];
            if ($quoteIds !== []) {
                $byQuote = QuoteDeliveryStock::unitsByProductForQuotes($db, $quoteIds);
                $allProducts = [];
                foreach ($byQuote as $qProducts) {
                    foreach ($qProducts as $pid => $units) {
                        $allProducts[$pid] = ($allProducts[$pid] ?? 0) + (int) $units;
                    }
                }
                if ($allProducts !== []) {
                    $pids = array_keys($allProducts);
                    $ph = implode(',', array_fill(0, count($pids), '?'));
                    $products = $db->fetchAll("SELECT id, code, name FROM products WHERE id IN ({$ph})", $pids);
                    foreach ($products as $p) {
                        $pid = (int) $p['id'];
                        $monthProducts[] = [
                            'code' => (string) ($p['code'] ?? ''),
                            'name' => (string) ($p['name'] ?? ''),
                            'units' => (int) ($allProducts[$pid] ?? 0),
                        ];
                    }
                    usort($monthProducts, static fn (array $a, array $b): int => $b['units'] <=> $a['units']);
                }
            }
            $topProductsMonth = array_slice($monthProducts, 0, 5);
            $topClientsMonth = $db->fetchAll(
                "SELECT c.name, ROUND(SUM(q.total),2) AS total_amount
                 FROM quotes q
                 JOIN clients c ON c.id = q.client_id
                 WHERE q.status IN ('accepted','delivered')
                   AND YEAR(q.created_at)=YEAR(CURDATE()) AND MONTH(q.created_at)=MONTH(CURDATE())
                 GROUP BY c.id, c.name
                 ORDER BY total_amount DESC
                 LIMIT 5"
            );
            $topCombosMonth = $db->fetchAll(
                "SELECT cmb.name, SUM(qi.quantity) AS qty
                 FROM quote_items qi
                 JOIN quotes q ON q.id = qi.quote_id
                 JOIN combos cmb ON cmb.id = qi.combo_id
                 WHERE qi.combo_id IS NOT NULL
                   AND q.status IN ('accepted','delivered')
                   AND YEAR(q.created_at)=YEAR(CURDATE()) AND MONTH(q.created_at)=MONTH(CURDATE())
                 GROUP BY cmb.id, cmb.name
                 ORDER BY qty DESC
                 LIMIT 5"
            );
            $pendingCollectionDelivered = (float) $db->fetchColumn(
                "SELECT COALESCE(SUM(q.total), 0) - COALESCE(SUM(pay.paid),0)
                 FROM quotes q
                 LEFT JOIN (
                    SELECT reference_id, SUM(amount) AS paid
                    FROM account_transactions
                    WHERE account_type='client' AND transaction_type='payment' AND reference_type='quote'
                    GROUP BY reference_id
                 ) pay ON pay.reference_id = q.id
                 WHERE q.status = 'delivered'"
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
            'lowStockProducts' => $lowStockProducts,
            'salesTodayCount' => $salesTodayCount,
            'salesTodayAmount' => $salesTodayAmount,
            'salesWeekCount' => $salesWeekCount,
            'salesWeekAmount' => $salesWeekAmount,
            'salesMonthCount' => $salesMonthCount,
            'salesMonthAmount' => $salesMonthAmount,
            'salesMonthAvgTicket' => $salesMonthAvgTicket,
            'topProductsMonth' => $topProductsMonth,
            'topClientsMonth' => $topClientsMonth,
            'topCombosMonth' => $topCombosMonth,
            'pendingDeliveryCount' => $pendingDeliveryCount,
            'pendingDeliveryAmount' => $pendingDeliveryAmount,
            'pendingCollectionDelivered' => max(0.0, round($pendingCollectionDelivered, 2)),
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
                    COALESCE(
                        qi.cost_subtotal_snapshot,
                        (
                            CASE
                                WHEN q.include_iva = 1 THEN (qi.subtotal / ?)
                                ELSE qi.subtotal
                            END
                        ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
                    )
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
                        COALESCE(SUM(COALESCE(
                            qi.cost_subtotal_snapshot,
                            (
                                CASE
                                    WHEN q.include_iva = 1 THEN (qi.subtotal / ?)
                                    ELSE qi.subtotal
                                END
                            ) / (1 + (COALESCE(qi.markup_applied, 0) / 100))
                        )), 0) AS estimated_cost
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
