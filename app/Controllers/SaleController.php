<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClientReceivableSummary;
use App\Models\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SaleController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $filters = $this->filtersFromQuery();
        $allSales = $this->fetchSales($db, $filters);
        $summary = $this->buildSummary($allSales);
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $total = count($allSales);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $sales = array_slice($allSales, $offset, $perPage);

        $this->view('sales/index', [
            'title' => 'Ventas',
            'sales' => $sales,
            'filters' => $filters,
            'summary' => $summary,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $quote = $db->fetch(
            "SELECT q.*, c.name AS client_name, c.business_name, c.contact_person, c.phone, c.email, c.address, c.city
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id
             WHERE q.id = ? AND q.status IN ('accepted', 'delivered')",
            [(int) $id]
        );
        if (!$quote) {
            flash('error', 'Venta no encontrada.');
            redirect('/ventas');
            return;
        }
        $items = $db->fetchAll(
            "SELECT qi.*, p.code, p.name, cmb.name AS combo_name
             FROM quote_items qi
             LEFT JOIN products p ON p.id = qi.product_id
             LEFT JOIN combos cmb ON cmb.id = qi.combo_id
             WHERE qi.quote_id = ?
             ORDER BY qi.sort_order, qi.id",
            [(int) $id]
        );
        $accountTx = $db->fetchAll(
            "SELECT *
             FROM account_transactions
             WHERE account_type = 'client'
               AND account_id = ?
               AND (
                    (reference_type = 'quote' AND reference_id = ?)
                    OR transaction_type = 'payment'
               )
             ORDER BY transaction_date DESC, id DESC",
            [(int) ($quote['client_id'] ?? 0), (int) $id]
        );
        $clientBalance = ClientReceivableSummary::hybridBalanceForClient($db, (int) ($quote['client_id'] ?? 0));
        $payment = $this->paymentStatusFromClientBalance($clientBalance);
        $payment['client_balance'] = $clientBalance;

        $this->view('sales/show', [
            'title' => 'Venta ' . ((string) ($quote['sale_number'] ?? '') !== '' ? (string) $quote['sale_number'] : (string) $quote['quote_number']),
            'quote' => $quote,
            'items' => $items,
            'accountTx' => $accountTx,
            'payment' => $payment,
        ]);
    }

    public function reports(): void
    {
        $db = Database::getInstance();
        $filters = $this->filtersFromQuery();
        $dateFrom = $filters['from'] ?: '1900-01-01';
        $dateTo = $filters['to'] ?: date('Y-m-d');

        $productRows = $db->fetchAll(
            "SELECT x.product_id, p.code, p.name,
                    SUM(x.units) AS units_sold,
                    ROUND(SUM(x.units * x.unit_price), 2) AS amount_sold
             FROM (
                SELECT qi.quote_id, qi.product_id,
                       CASE WHEN qi.unit_type = 'unidad' THEN qi.quantity ELSE qi.quantity * GREATEST(1, COALESCE(pr.units_per_box, 1)) END AS units,
                       CASE WHEN qi.unit_type = 'unidad'
                            THEN qi.unit_price
                            ELSE (qi.unit_price / GREATEST(1, COALESCE(pr.units_per_box, 1))) END AS unit_price
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                JOIN products pr ON pr.id = qi.product_id
                WHERE qi.product_id IS NOT NULL
                  AND q.status IN ('accepted','delivered')
                  AND DATE(q.created_at) BETWEEN ? AND ?
                UNION ALL
                SELECT qi.quote_id, cp.product_id,
                       (qi.quantity * cp.quantity) AS units,
                       (
                         qi.subtotal /
                         GREATEST(1, qi.quantity) /
                         GREATEST(1, totals.combo_units)
                       ) AS unit_price
                FROM quote_items qi
                JOIN quotes q ON q.id = qi.quote_id
                JOIN combo_products cp ON cp.combo_id = qi.combo_id
                JOIN (
                    SELECT combo_id, SUM(quantity) AS combo_units
                    FROM combo_products
                    GROUP BY combo_id
                ) totals ON totals.combo_id = qi.combo_id
                WHERE qi.combo_id IS NOT NULL
                  AND q.status IN ('accepted','delivered')
                  AND DATE(q.created_at) BETWEEN ? AND ?
             ) x
             JOIN products p ON p.id = x.product_id
             GROUP BY x.product_id, p.code, p.name
             ORDER BY units_sold DESC",
            [$dateFrom, $dateTo, $dateFrom, $dateTo]
        );

        $clientRows = $db->fetchAll(
            "SELECT c.id, c.name,
                    COUNT(q.id) AS sales_count,
                    ROUND(SUM(q.total), 2) AS total_amount,
                    ROUND(AVG(q.total), 2) AS avg_ticket,
                    MAX(DATE(q.created_at)) AS last_sale
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             WHERE q.status IN ('accepted','delivered')
               AND DATE(q.created_at) BETWEEN ? AND ?
             GROUP BY c.id, c.name
             ORDER BY total_amount DESC",
            [$dateFrom, $dateTo]
        );

        $comboRows = $db->fetchAll(
            "SELECT cmb.id, cmb.name,
                    SUM(qi.quantity) AS combo_qty,
                    ROUND(SUM(qi.subtotal), 2) AS combo_amount
             FROM quote_items qi
             JOIN quotes q ON q.id = qi.quote_id
             JOIN combos cmb ON cmb.id = qi.combo_id
             WHERE qi.combo_id IS NOT NULL
               AND q.status IN ('accepted','delivered')
               AND DATE(q.created_at) BETWEEN ? AND ?
             GROUP BY cmb.id, cmb.name
             ORDER BY combo_qty DESC",
            [$dateFrom, $dateTo]
        );

        $hasCostColumn = (bool) $db->fetchColumn(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'products'
               AND COLUMN_NAME IN ('usage_cost', 'cost', 'cost_price')"
        );

        $profitRows = $db->fetchAll(
            "SELECT q.id, q.sale_number, q.quote_number, DATE(q.created_at) AS sale_date,
                    c.name AS client_name,
                    ROUND(q.total, 2) AS sale_total,
                    ROUND(COALESCE(SUM(qi.cost_subtotal_snapshot), 0), 2) AS cost_total,
                    ROUND(q.total - COALESCE(SUM(qi.cost_subtotal_snapshot), 0), 2) AS margin_amount,
                    ROUND(
                        CASE
                            WHEN q.total > 0 THEN ((q.total - COALESCE(SUM(qi.cost_subtotal_snapshot), 0)) / q.total) * 100
                            ELSE 0
                        END
                    , 2) AS margin_percent
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             LEFT JOIN quote_items qi ON qi.quote_id = q.id
             WHERE q.status IN ('accepted','delivered')
               AND DATE(q.created_at) BETWEEN ? AND ?
             GROUP BY q.id, q.sale_number, q.quote_number, sale_date, c.name, q.total
             ORDER BY sale_date DESC, q.id DESC",
            [$dateFrom, $dateTo]
        );

        $this->view('sales/reports', [
            'title' => 'Reportes de ventas',
            'filters' => $filters,
            'productRows' => $productRows,
            'clientRows' => $clientRows,
            'comboRows' => $comboRows,
            'hasCostData' => $hasCostColumn,
            'profitRows' => $profitRows,
        ]);
    }

    public function exportExcel(): void
    {
        $db = Database::getInstance();
        $sales = $this->fetchSales($db, $this->filtersFromQuery());
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Nro Venta', 'Nro Presupuesto', 'Fecha', 'Cliente', 'Items', 'Total', 'Entrega'], null, 'A1');
        $row = 2;
        foreach ($sales as $sale) {
            $sheet->fromArray([
                (string) ($sale['sale_number'] ?? ''),
                (string) ($sale['quote_number'] ?? ''),
                (string) ($sale['created_date'] ?? ''),
                (string) ($sale['client_name'] ?? ''),
                (int) ($sale['items_count'] ?? 0),
                (float) ($sale['total'] ?? 0),
                (string) ($sale['delivery_label'] ?? ''),
            ], null, 'A' . $row++);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ventas.xlsx"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportReportExcel(): void
    {
        $db = Database::getInstance();
        $filters = $this->filtersFromQuery();
        $dateFrom = $filters['from'] ?: '1900-01-01';
        $dateTo = $filters['to'] ?: date('Y-m-d');
        $report = (string) $this->query('type', 'productos');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;

        if ($report === 'clientes') {
            $data = $db->fetchAll(
                "SELECT c.name, COUNT(q.id) AS sales_count, ROUND(SUM(q.total),2) AS total_amount,
                        ROUND(AVG(q.total),2) AS avg_ticket, MAX(DATE(q.created_at)) AS last_sale
                 FROM quotes q
                 JOIN clients c ON c.id = q.client_id
                 WHERE q.status IN ('accepted','delivered')
                   AND DATE(q.created_at) BETWEEN ? AND ?
                 GROUP BY c.id, c.name
                 ORDER BY total_amount DESC",
                [$dateFrom, $dateTo]
            );
            $sheet->fromArray(['Cliente', 'Cantidad ventas', 'Monto total', 'Ticket promedio', 'Ultima compra'], null, 'A' . $row++);
            foreach ($data as $d) {
                $sheet->fromArray([$d['name'], (int) $d['sales_count'], (float) $d['total_amount'], (float) $d['avg_ticket'], (string) $d['last_sale']], null, 'A' . $row++);
            }
        } elseif ($report === 'combos') {
            $data = $db->fetchAll(
                "SELECT cmb.name, SUM(qi.quantity) AS combo_qty, ROUND(SUM(qi.subtotal),2) AS combo_amount
                 FROM quote_items qi
                 JOIN quotes q ON q.id = qi.quote_id
                 JOIN combos cmb ON cmb.id = qi.combo_id
                 WHERE qi.combo_id IS NOT NULL
                   AND q.status IN ('accepted','delivered')
                   AND DATE(q.created_at) BETWEEN ? AND ?
                 GROUP BY cmb.id, cmb.name
                 ORDER BY combo_qty DESC",
                [$dateFrom, $dateTo]
            );
            $sheet->fromArray(['Combo', 'Cantidad', 'Monto'], null, 'A' . $row++);
            foreach ($data as $d) {
                $sheet->fromArray([$d['name'], (int) $d['combo_qty'], (float) $d['combo_amount']], null, 'A' . $row++);
            }
        } elseif ($report === 'rentabilidad') {
            $data = $db->fetchAll(
                "SELECT q.sale_number, q.quote_number, DATE(q.created_at) AS sale_date,
                        c.name AS client_name, ROUND(q.total,2) AS sale_total,
                        ROUND(COALESCE(SUM(qi.cost_subtotal_snapshot),0),2) AS cost_total,
                        ROUND(q.total - COALESCE(SUM(qi.cost_subtotal_snapshot),0),2) AS margin_amount,
                        ROUND(
                            CASE
                                WHEN q.total > 0 THEN ((q.total - COALESCE(SUM(qi.cost_subtotal_snapshot), 0)) / q.total) * 100
                                ELSE 0
                            END
                        ,2) AS margin_percent
                 FROM quotes q
                 JOIN clients c ON c.id = q.client_id
                 LEFT JOIN quote_items qi ON qi.quote_id = q.id
                 WHERE q.status IN ('accepted','delivered')
                   AND DATE(q.created_at) BETWEEN ? AND ?
                 GROUP BY q.id, q.sale_number, q.quote_number, sale_date, c.name, q.total
                 ORDER BY sale_date DESC, q.id DESC",
                [$dateFrom, $dateTo]
            );
            $sheet->fromArray(['Nro Venta', 'Nro Presupuesto', 'Fecha', 'Cliente', 'Venta', 'Costo snapshot', 'Margen $', 'Margen %'], null, 'A' . $row++);
            foreach ($data as $d) {
                $sheet->fromArray([
                    $d['sale_number'],
                    $d['quote_number'],
                    $d['sale_date'],
                    $d['client_name'],
                    (float) $d['sale_total'],
                    (float) $d['cost_total'],
                    (float) $d['margin_amount'],
                    (float) $d['margin_percent'],
                ], null, 'A' . $row++);
            }
        } else {
            $data = $db->fetchAll(
                "SELECT p.code, p.name, SUM(x.units) AS units_sold, ROUND(SUM(x.units * x.unit_price), 2) AS amount_sold
                 FROM (
                    SELECT qi.product_id,
                           CASE WHEN qi.unit_type = 'unidad' THEN qi.quantity ELSE qi.quantity * GREATEST(1, COALESCE(pr.units_per_box, 1)) END AS units,
                           CASE WHEN qi.unit_type = 'unidad'
                                THEN qi.unit_price
                                ELSE (qi.unit_price / GREATEST(1, COALESCE(pr.units_per_box, 1))) END AS unit_price
                    FROM quote_items qi
                    JOIN quotes q ON q.id = qi.quote_id
                    JOIN products pr ON pr.id = qi.product_id
                    WHERE qi.product_id IS NOT NULL
                      AND q.status IN ('accepted','delivered')
                      AND DATE(q.created_at) BETWEEN ? AND ?
                    UNION ALL
                    SELECT cp.product_id, (qi.quantity * cp.quantity) AS units,
                           (
                             qi.subtotal /
                             GREATEST(1, qi.quantity) /
                             GREATEST(1, totals.combo_units)
                           ) AS unit_price
                    FROM quote_items qi
                    JOIN quotes q ON q.id = qi.quote_id
                    JOIN combo_products cp ON cp.combo_id = qi.combo_id
                    JOIN (
                        SELECT combo_id, SUM(quantity) AS combo_units
                        FROM combo_products
                        GROUP BY combo_id
                    ) totals ON totals.combo_id = qi.combo_id
                    WHERE qi.combo_id IS NOT NULL
                      AND q.status IN ('accepted','delivered')
                      AND DATE(q.created_at) BETWEEN ? AND ?
                 ) x
                 JOIN products p ON p.id = x.product_id
                 GROUP BY x.product_id, p.code, p.name
                 ORDER BY units_sold DESC",
                [$dateFrom, $dateTo, $dateFrom, $dateTo]
            );
            $sheet->fromArray(['Codigo', 'Producto', 'Unidades vendidas', 'Monto vendido'], null, 'A' . $row++);
            foreach ($data as $d) {
                $sheet->fromArray([$d['code'], $d['name'], (int) $d['units_sold'], (float) $d['amount_sold']], null, 'A' . $row++);
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ventas-reportes.xlsx"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /** @return array{from:string,to:string,client:string,delivery:string,q:string} */
    private function filtersFromQuery(): array
    {
        $q = trim((string) $this->query('search', ''));
        if ($q === '') {
            $q = trim((string) $this->query('q', ''));
        }
        return [
            'from' => trim((string) $this->query('from', '')),
            'to' => trim((string) $this->query('to', '')),
            'client' => trim((string) $this->query('client', '')),
            'delivery' => trim((string) $this->query('delivery', '')),
            'q' => $q,
        ];
    }

    /** @param array{from:string,to:string,client:string,delivery:string,q:string} $filters */
    private function fetchSales(Database $db, array $filters): array
    {
        $where = ["qt.status IN ('accepted', 'delivered')"];
        $params = [];
        if ($filters['from'] !== '') {
            $where[] = 'DATE(qt.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if ($filters['to'] !== '') {
            $where[] = 'DATE(qt.created_at) <= ?';
            $params[] = $filters['to'];
        }
        if ($filters['client'] !== '') {
            $where[] = 'c.name LIKE ?';
            $params[] = '%' . $filters['client'] . '%';
        }
        if ($filters['delivery'] !== '') {
            $where[] = 'qt.status = ?';
            $params[] = $filters['delivery'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(qt.quote_number LIKE ? OR qt.sale_number LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        $rows = $db->fetchAll(
            "SELECT qt.id, qt.sale_number, qt.quote_number, DATE(qt.created_at) AS created_date, qt.total, qt.status,
                    c.name AS client_name,
                    COALESCE(items.items_count, 0) AS items_count
             FROM quotes qt
             LEFT JOIN clients c ON c.id = qt.client_id
             LEFT JOIN (
                SELECT quote_id, SUM(quantity) AS items_count
                FROM quote_items
                GROUP BY quote_id
             ) items ON items.quote_id = qt.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY qt.created_at DESC, qt.id DESC",
            $params
        );

        $out = [];
        foreach ($rows as $row) {
            $deliveryLabel = ((string) ($row['status'] ?? '') === 'delivered') ? 'Entregado' : 'Pendiente';
            $deliveryBadge = ((string) ($row['status'] ?? '') === 'delivered')
                ? 'bg-emerald-100 text-emerald-800'
                : 'bg-amber-100 text-amber-800';
            $row['delivery_label'] = $deliveryLabel;
            $row['delivery_badge'] = $deliveryBadge;
            $out[] = $row;
        }
        return $out;
    }

    /** @param list<array<string,mixed>> $sales */
    private function buildSummary(array $sales): array
    {
        $total = 0.0;
        $count = count($sales);
        $pendingDeliveryCount = 0;
        foreach ($sales as $sale) {
            $total += (float) ($sale['total'] ?? 0);
            if (($sale['status'] ?? '') === 'accepted') {
                $pendingDeliveryCount++;
            }
        }
        return [
            'total_amount' => round($total, 2),
            'count' => $count,
            'avg_ticket' => $count > 0 ? round($total / $count, 2) : 0.0,
            'pending_delivery_count' => $pendingDeliveryCount,
        ];
    }

    /**
     * Solo dos estados según saldo híbrido del cliente: al día (≤0) o pendiente (>0).
     *
     * @return array{status:string,label:string,badge:string,pending:float}
     */
    private function paymentStatusFromClientBalance(float $clientBalance): array
    {
        $balance = round($clientBalance, 2);
        if ($balance <= 0.0) {
            return [
                'status' => 'paid',
                'label' => 'Cobrado (cliente al día)',
                'badge' => 'bg-emerald-100 text-emerald-800',
                'pending' => 0.0,
            ];
        }

        return [
            'status' => 'pending',
            'label' => 'Pendiente (saldo cliente)',
            'badge' => 'bg-rose-100 text-rose-800',
            'pending' => $balance,
        ];
    }
}
