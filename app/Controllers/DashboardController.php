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
        $accountsTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
        $salesBaseWhere = "status IN ('accepted','delivered') AND COALESCE(sale_number,'') <> ''";
        $today = date('Y-m-d');

        $salesTodayCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE {$salesBaseWhere} AND DATE(created_at)=?", [$today]);
        $salesTodayAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE {$salesBaseWhere} AND DATE(created_at)=?", [$today]);
        $salesWeekCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE {$salesBaseWhere} AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
        $salesWeekAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE {$salesBaseWhere} AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)");
        $salesMonthCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM quotes WHERE {$salesBaseWhere} AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
        $salesMonthAmount = (float) $db->fetchColumn("SELECT COALESCE(SUM(total),0) FROM quotes WHERE {$salesBaseWhere} AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
        $salesMonthAvgTicket = $salesMonthCount > 0 ? round($salesMonthAmount / $salesMonthCount, 2) : 0.0;
        $periodKey = strtolower(trim((string) $this->query('periodo', '30')));
        $period = $this->resolveMainPeriod($periodKey, $today);
        $periodLabel = (string) $period['label'];
        $periodWhere = (string) $period['where'];
        /** @var list<mixed> $periodParams */
        $periodParams = $period['params'];

        $mainSalesCount = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM quotes WHERE {$salesBaseWhere} {$periodWhere}",
            $periodParams
        );
        $mainSalesAmount = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(total),0) FROM quotes WHERE {$salesBaseWhere} {$periodWhere}",
            $periodParams
        );

        $receivable = 0.0;
        $clientsWithDebt = 0;
        if ($accountsTable) {
            $receivable = ClientReceivableSummary::totalReceivable($db);
            $clientsWithDebt = ClientReceivableSummary::countClientsWithDebt($db);
        }

        $supplierDebts = $accountsTable ? $this->fetchSupplierDebtsSameAsAccount($db) : [];

        $mainCostEstimated = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(qi.cost_subtotal_snapshot),0)
             FROM quote_items qi
             INNER JOIN quotes q ON q.id = qi.quote_id
             WHERE q.status IN ('accepted','delivered') AND COALESCE(q.sale_number,'') <> ''" . str_replace('DATE(created_at)', 'DATE(q.created_at)', $periodWhere),
            $periodParams
        );
        $mainCostNullCount = (int) $db->fetchColumn(
            "SELECT COUNT(*)
             FROM quote_items qi
             INNER JOIN quotes q ON q.id = qi.quote_id
             WHERE q.status IN ('accepted','delivered') AND COALESCE(q.sale_number,'') <> ''" . str_replace('DATE(created_at)', 'DATE(q.created_at)', $periodWhere) . "
               AND qi.cost_subtotal_snapshot IS NULL",
            $periodParams
        );
        $profitEstimated = round($mainSalesAmount - $mainCostEstimated, 2);
        $profitMarginPercent = $mainSalesAmount > 0 ? round(($profitEstimated / $mainSalesAmount) * 100, 1) : 0.0;
        $profitToneClass = 'text-red-600';
        if ($profitMarginPercent > 30.0) {
            $profitToneClass = 'text-emerald-600';
        } elseif ($profitMarginPercent >= 20.0) {
            $profitToneClass = 'text-amber-600';
        }

        $pendingDeliveryCount = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM quotes WHERE status = 'accepted'"
        );
        $pendingDeliveryAmount = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(total),0) FROM quotes WHERE status = 'accepted'"
        );

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $dt = strtotime("-{$i} months");
            $ym = date('Y-m', $dt);
            $months[$ym] = ['label' => date('M', $dt), 'total' => 0.0];
        }
        $monthlyRows = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total),0) AS total
             FROM quotes
             WHERE {$salesBaseWhere} AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym
             ORDER BY ym"
        );
        foreach ($monthlyRows as $row) {
            $ym = (string) ($row['ym'] ?? '');
            if (isset($months[$ym])) {
                $months[$ym]['total'] = round((float) ($row['total'] ?? 0), 2);
            }
        }
        $monthlyLabels = [];
        $monthlySales = [];
        foreach ($months as $m) {
            $monthlyLabels[] = (string) $m['label'];
            $monthlySales[] = (float) $m['total'];
        }

        $allSalesQuoteIds = $db->fetchAll(
            "SELECT id
             FROM quotes
             WHERE {$salesBaseWhere}"
        );
        $quoteIds = array_map(static fn (array $r): int => (int) $r['id'], $allSalesQuoteIds);
        $topProductsAll = [];
        if ($quoteIds !== []) {
            $unitsByQuote = QuoteDeliveryStock::unitsByProductForQuotes($db, $quoteIds);
            $unitsByProduct = [];
            foreach ($unitsByQuote as $products) {
                foreach ($products as $pid => $units) {
                    $unitsByProduct[$pid] = ($unitsByProduct[$pid] ?? 0) + (int) $units;
                }
            }
            if ($unitsByProduct !== []) {
                $pids = array_keys($unitsByProduct);
                $in = implode(',', array_fill(0, count($pids), '?'));
                $products = $db->fetchAll("SELECT id, name FROM products WHERE id IN ({$in})", $pids);
                foreach ($products as $p) {
                    $pid = (int) $p['id'];
                    $topProductsAll[] = [
                        'name' => (string) ($p['name'] ?? ''),
                        'units' => (int) ($unitsByProduct[$pid] ?? 0),
                    ];
                }
                usort($topProductsAll, static fn (array $a, array $b): int => $b['units'] <=> $a['units']);
                $topProductsAll = array_slice($topProductsAll, 0, 5);
            }
        }

        $topClientsAll = $db->fetchAll(
            "SELECT c.name, ROUND(SUM(q.total),2) AS total_amount
             FROM quotes q
             INNER JOIN clients c ON c.id = q.client_id
             WHERE {$salesBaseWhere}
             GROUP BY c.id, c.name
             ORDER BY total_amount DESC
             LIMIT 5"
        );
        $recentQuotes = $db->fetchAll(
            "SELECT q.id, q.quote_number, q.total, c.name AS client_name
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id
             ORDER BY q.created_at DESC
             LIMIT 5"
        );

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'accountsEnabled' => $accountsTable,
            'salesTodayCount' => $salesTodayCount,
            'salesTodayAmount' => $salesTodayAmount,
            'salesWeekCount' => $salesWeekCount,
            'salesWeekAmount' => $salesWeekAmount,
            'salesMonthCount' => $salesMonthCount,
            'salesMonthAmount' => $salesMonthAmount,
            'salesMonthAvgTicket' => $salesMonthAvgTicket,
            'periodKey' => $periodKey,
            'periodLabel' => $periodLabel,
            'mainSalesCount' => $mainSalesCount,
            'mainSalesAmount' => $mainSalesAmount,
            'mainCostEstimated' => $mainCostEstimated,
            'mainCostNullCount' => $mainCostNullCount,
            'receivable' => $receivable,
            'clientsWithDebt' => $clientsWithDebt,
            'pendingDeliveryCount' => $pendingDeliveryCount,
            'pendingDeliveryAmount' => $pendingDeliveryAmount,
            'supplierDebts' => $supplierDebts,
            'deliveredMonthNet' => $mainSalesAmount,
            'deliveredMonthCost' => $mainCostEstimated,
            'deliveredMonthCostNullCount' => $mainCostNullCount,
            'profitEstimated' => $profitEstimated,
            'profitMarginPercent' => $profitMarginPercent,
            'profitToneClass' => $profitToneClass,
            'monthlyLabels' => $monthlyLabels,
            'monthlySales' => $monthlySales,
            'topProductsAll' => $topProductsAll,
            'topClientsAll' => $topClientsAll,
            'recentQuotes' => $recentQuotes,
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
            $title = 'Detalle - Pendiente (saldo clientes)';
            $value = ClientReceivableSummary::totalReceivable($db);
            $explain = 'Suma de saldos a cobrar (clientes con saldo híbrido > 0: facturas/presupuestos menos cobros y ajustes). Sin estado “parcial”.';
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

    /**
     * @return array{label:string,where:string,params:list<mixed>}
     */
    private function resolveMainPeriod(string $key, string $today): array
    {
        if ($key === '7') {
            return [
                'label' => 'últ. 7 días',
                'where' => 'AND DATE(created_at) BETWEEN ? AND ?',
                'params' => [date('Y-m-d', strtotime('-6 days')), $today],
            ];
        }
        if ($key === '90') {
            return [
                'label' => 'últ. 90 días',
                'where' => 'AND DATE(created_at) BETWEEN ? AND ?',
                'params' => [date('Y-m-d', strtotime('-89 days')), $today],
            ];
        }
        if ($key === 'month') {
            return [
                'label' => 'este mes',
                'where' => 'AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())',
                'params' => [],
            ];
        }
        if ($key === 'lastmonth') {
            $start = date('Y-m-01', strtotime('first day of last month'));
            $end = date('Y-m-t', strtotime('last day of last month'));
            return [
                'label' => 'mes anterior',
                'where' => 'AND DATE(created_at) BETWEEN ? AND ?',
                'params' => [$start, $end],
            ];
        }
        if ($key === 'all') {
            return [
                'label' => 'histórico',
                'where' => '',
                'params' => [],
            ];
        }

        return [
            'label' => 'últ. 30 días',
            'where' => 'AND DATE(created_at) BETWEEN ? AND ?',
            'params' => [date('Y-m-d', strtotime('-29 days')), $today],
        ];
    }

    /**
     * Misma lógica que AccountController::getSupplierDebts(): facturas − pagos + ajustes por proveedor.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchSupplierDebtsSameAsAccount(Database $db): array
    {
        return $db->fetchAll(
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
}
