<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClientReceivableSummary;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class AccountController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }

        $totalReceivable = ClientReceivableSummary::totalReceivable($db);
        $clientsWithDebt = ClientReceivableSummary::countClientsWithDebt($db);
        $supplierDebts = $this->getSupplierDebts($db);
        $totalPayable = 0.0;
        foreach ($supplierDebts as $supplier) {
            $totalPayable += (float) $supplier['debt'];
        }
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE (at.description LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*)
             FROM account_transactions at
             LEFT JOIN clients c ON c.id = at.account_id AND at.account_type = 'client'
             LEFT JOIN suppliers s ON s.id = at.account_id AND at.account_type = 'supplier'
             {$where}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $recentTransactions = $db->fetchAll(
            "SELECT at.*, c.name AS client_name, s.name AS supplier_name
             FROM account_transactions at
             LEFT JOIN clients c ON c.id = at.account_id AND at.account_type = 'client'
             LEFT JOIN suppliers s ON s.id = at.account_id AND at.account_type = 'supplier'
             {$where}
             ORDER BY at.transaction_date DESC, at.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $txAgg = ClientReceivableSummary::sqlTxAggByClientSubquery();
        $qAgg = ClientReceivableSummary::sqlQuotesAcceptedByClientSubquery();
        $hybrid = ClientReceivableSummary::sqlCaseHybridBalance();
        $clientsForForm = $db->fetchAll(
            "SELECT c.id, c.name, ROUND({$hybrid}, 2) AS balance
             FROM clients c
             LEFT JOIN ({$txAgg}) tx ON tx.account_id = c.id
             LEFT JOIN ({$qAgg}) q ON q.client_id = c.id
             WHERE c.is_active = 1
             ORDER BY c.name"
        );
        $suppliersForForm = $db->fetchAll(
            "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name"
        );

        $this->view('cuenta-corriente/index', [
            'title' => 'Cuenta Corriente',
            'totalReceivable' => $totalReceivable,
            'clientsWithDebt' => $clientsWithDebt,
            'supplierDebts' => $supplierDebts,
            'totalPayable' => $totalPayable,
            'recentTransactions' => $recentTransactions,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'clientsForForm' => $clientsForForm,
            'suppliersForForm' => $suppliersForForm,
        ]);
    }

    public function clients(): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }

        $onlyWithDebt = (string) $this->query('only_with_debt', '0') === '1';
        $search = trim((string) $this->query('search', ''));
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $rows = $db->fetchAll(
            "SELECT c.id, c.name, c.balance,
                    COALESCE(SUM(CASE WHEN at.transaction_type = 'invoice' THEN at.amount ELSE 0 END), 0) AS total_invoiced,
                    COALESCE(SUM(CASE WHEN at.transaction_type = 'payment' THEN at.amount ELSE 0 END), 0) AS total_paid,
                    COALESCE(SUM(CASE WHEN at.transaction_type = 'adjustment' THEN at.amount ELSE 0 END), 0) AS total_adjustments,
                    COALESCE((
                        SELECT SUM(
                            CASE
                                WHEN COALESCE(q2.is_mercadolibre, 0) = 1 THEN COALESCE(q2.ml_net_amount, 0)
                                ELSE COALESCE(q2.total, 0)
                            END
                        ) FROM quotes q2
                        WHERE q2.client_id = c.id AND q2.status IN ('accepted', 'delivered')
                    ), 0) AS quotes_accepted_total
             FROM clients c
             LEFT JOIN account_transactions at
                ON at.account_type = 'client' AND at.account_id = c.id
             GROUP BY c.id
             ORDER BY c.name"
        );

        foreach ($rows as &$row) {
            $inv = (float) $row['total_invoiced'];
            $paid = (float) $row['total_paid'];
            $adj = (float) $row['total_adjustments'];
            $quotes = (float) $row['quotes_accepted_total'];
            $computed = $inv > 0 ? ($inv - $paid + $adj) : ($quotes - $paid + $adj);
            $row['balance'] = round($computed, 2);
        }
        unset($row);

        $totalReceivable = 0.0;
        foreach ($rows as $row) {
            $balance = (float) $row['balance'];
            if ($balance > 0) {
                $totalReceivable += $balance;
            }
        }

        if ($onlyWithDebt) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (float) $row['balance'] > 0
            ));
        }
        if ($search !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => stripos((string) ($row['name'] ?? ''), $search) !== false
            ));
        }
        $total = count($rows);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = array_slice($rows, $offset, $perPage);

        $this->view('cuenta-corriente/clients', [
            'title' => 'Cuentas a cobrar',
            'rows' => $rows,
            'onlyWithDebt' => $onlyWithDebt,
            'totalReceivable' => round($totalReceivable, 2),
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function clientDetail(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $clientId = (int) $id;
        $client = $db->fetch('SELECT * FROM clients WHERE id = ?', [$clientId]);
        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            redirect('/cuenta-corriente/clientes');
            return;
        }

        $transactions = $db->fetchAll(
            "SELECT *
             FROM account_transactions
             WHERE account_type = 'client' AND account_id = ?
             ORDER BY transaction_date ASC, id ASC",
            [$clientId]
        );
        $openingBalance = ClientReceivableSummary::openingBalanceForClient($db, $clientId);
        $running = $openingBalance;
        foreach ($transactions as &$tx) {
            $running += $this->transactionImpact($tx);
            $tx['running_balance'] = round($running, 2);
            $tx['debe'] = $this->transactionImpact($tx) > 0 ? abs((float) $tx['amount']) : 0.0;
            $tx['haber'] = $this->transactionImpact($tx) < 0 ? abs((float) $tx['amount']) : 0.0;
        }
        unset($tx);
        $search = trim((string) $this->query('search', ''));
        if ($search !== '') {
            $transactions = array_values(array_filter(
                $transactions,
                static fn (array $tx): bool => stripos((string) ($tx['description'] ?? ''), $search) !== false
            ));
        }
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $total = count($transactions);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $transactions = array_slice($transactions, ($page - 1) * $perPage, $perPage);

        $this->view('cuenta-corriente/client-detail', [
            'title' => 'Cuenta corriente - ' . $client['name'],
            'client' => $client,
            'transactions' => $transactions,
            'openingBalance' => $openingBalance,
            'balance' => ClientReceivableSummary::hybridBalanceForClient($db, $clientId),
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function supplierDetail(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $supplierId = (int) $id;
        $supplier = $db->fetch('SELECT * FROM suppliers WHERE id = ?', [$supplierId]);
        if (!$supplier) {
            flash('error', 'Proveedor no encontrado.');
            redirect('/cuenta-corriente');
            return;
        }

        $transactions = $db->fetchAll(
            "SELECT *
             FROM account_transactions
             WHERE account_type = 'supplier' AND account_id = ?
             ORDER BY transaction_date ASC, id ASC",
            [$supplierId]
        );
        $runningDebt = 0.0;
        foreach ($transactions as &$tx) {
            $impact = $this->transactionImpact($tx);
            $runningDebt += $impact;
            $tx['running_debt'] = round($runningDebt, 2);
            $tx['cargo'] = $impact > 0 ? abs((float) $tx['amount']) : 0.0;
            $tx['pago'] = $impact < 0 ? abs((float) $tx['amount']) : 0.0;
        }
        unset($tx);
        $search = trim((string) $this->query('search', ''));
        if ($search !== '') {
            $transactions = array_values(array_filter(
                $transactions,
                static fn (array $tx): bool => stripos((string) ($tx['description'] ?? ''), $search) !== false
            ));
        }
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $total = count($transactions);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $transactions = array_slice($transactions, ($page - 1) * $perPage, $perPage);

        $this->view('cuenta-corriente/seiq', [
            'title' => 'Cuenta proveedor - ' . $supplier['name'],
            'supplier' => $supplier,
            'transactions' => $transactions,
            'debt' => round($runningDebt, 2),
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function registerCollection(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/cuenta-corriente');
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }

        $clientId = (int) $this->input('client_id', 0);
        $client = $db->fetch('SELECT id, name FROM clients WHERE id = ?', [$clientId]);
        if (!$client) {
            flash('error', 'Cliente inválido.');
            redirect('/cuenta-corriente');
            return;
        }

        $amount = parseArgentineAmount((string) $this->input('amount', '0'));
        $method = $this->normalizePaymentMethod((string) $this->input('payment_method', 'efectivo'));
        $reference = trim((string) $this->input('payment_reference', ''));
        $date = (string) $this->input('transaction_date', date('Y-m-d'));
        $notes = trim((string) $this->input('notes', ''));

        if ($amount <= 0) {
            flash('error', 'El monto debe ser mayor a cero.');
            redirect('/cuenta-corriente/cliente/' . $clientId);
            return;
        }

        $description = 'Cobro ' . strtolower($this->paymentMethodLabel($method));
        if ($reference !== '') {
            $description .= ' (ref: ' . $reference . ')';
        }

        $db->insert('account_transactions', [
            'account_type' => 'client',
            'account_id' => $clientId,
            'transaction_type' => 'payment',
            'reference_type' => 'manual',
            'reference_id' => null,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_reference' => $reference !== '' ? $reference : null,
            'description' => $description,
            'notes' => $notes !== '' ? $notes : null,
            'transaction_date' => $date,
        ]);
        $this->recalculateClientBalance($clientId);
        flash('success', 'Cobro registrado por ' . formatPrice($amount));
        redirect('/cuenta-corriente/cliente/' . $clientId);
    }

    public function quickPayment(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/cuenta-corriente/clientes');
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }

        $clientId = (int) $this->input('client_id', 0);
        $client = $db->fetch('SELECT id, name FROM clients WHERE id = ?', [$clientId]);
        if (!$client) {
            flash('error', 'Cliente inválido.');
            redirect('/cuenta-corriente/clientes');
            return;
        }

        $amountRaw = trim((string) $this->input('amount', '0'));
        $amount = parseArgentineAmount($amountRaw);
        $method = $this->normalizePaymentMethod((string) $this->input('payment_method', 'efectivo'));
        $reference = trim((string) $this->input('payment_reference', ''));
        $notes = trim((string) $this->input('notes', ''));
        $quoteId = (int) $this->input('quote_id', 0);
        $transactionDate = (string) $this->input('transaction_date', date('Y-m-d'));
        $returnTo = $this->sanitizeReturnTo((string) $this->input('return_to', '/cuenta-corriente/clientes'));

        if ($amount <= 0) {
            flash('error', 'El monto debe ser mayor a cero.');
            redirect($returnTo);
            return;
        }

        $description = 'Pago recibido';
        if ($quoteId > 0) {
            $quote = $db->fetch('SELECT quote_number FROM quotes WHERE id = ?', [$quoteId]);
            $description = 'Pago recibido - Presup. ' . (string) ($quote['quote_number'] ?? $quoteId);
        }

        $db->insert('account_transactions', [
            'account_type' => 'client',
            'account_id' => $clientId,
            'transaction_type' => 'payment',
            'reference_type' => $quoteId > 0 ? 'quote' : 'manual',
            'reference_id' => $quoteId > 0 ? $quoteId : null,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_reference' => $reference !== '' ? $reference : null,
            'description' => $description,
            'notes' => $notes !== '' ? $notes : null,
            'transaction_date' => $transactionDate,
        ]);
        $this->recalculateClientBalance($clientId);
        flash('success', 'Pago registrado por ' . formatPrice($amount));
        redirect($returnTo);
    }

    public function registerSupplierPayment(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/cuenta-corriente');
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }

        $supplierId = (int) $this->input('supplier_id', 0);
        $supplier = $db->fetch('SELECT id, name FROM suppliers WHERE id = ?', [$supplierId]);
        if (!$supplier) {
            flash('error', 'Proveedor inválido.');
            redirect('/cuenta-corriente');
            return;
        }

        $amount = parseArgentineAmount((string) $this->input('amount', '0'));
        $method = $this->normalizePaymentMethod((string) $this->input('payment_method', 'efectivo'));
        $reference = trim((string) $this->input('payment_reference', ''));
        $date = (string) $this->input('transaction_date', date('Y-m-d'));
        $notes = trim((string) $this->input('notes', ''));

        if ($amount <= 0) {
            flash('error', 'El monto debe ser mayor a cero.');
            redirect('/cuenta-corriente/proveedor/' . $supplierId);
            return;
        }

        $description = 'Pago a proveedor ' . strtolower($this->paymentMethodLabel($method));
        if ($reference !== '') {
            $description .= ' (ref: ' . $reference . ')';
        }

        $db->insert('account_transactions', [
            'account_type' => 'supplier',
            'account_id' => $supplierId,
            'transaction_type' => 'payment',
            'reference_type' => 'manual',
            'reference_id' => null,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_reference' => $reference !== '' ? $reference : null,
            'description' => $description,
            'notes' => $notes !== '' ? $notes : null,
            'transaction_date' => $date,
        ]);

        flash('success', 'Pago registrado por ' . formatPrice($amount));
        redirect('/cuenta-corriente/proveedor/' . $supplierId);
    }

    public function registerAdjustment(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/cuenta-corriente');
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }

        $accountType = (string) $this->input('account_type', '');
        $accountId = (int) $this->input('account_id', 0);
        $amount = parseArgentineAmount((string) $this->input('amount', '0'));
        $description = trim((string) $this->input('description', ''));
        $date = (string) $this->input('transaction_date', date('Y-m-d'));
        $notes = trim((string) $this->input('notes', ''));

        if (!in_array($accountType, ['client', 'supplier'], true) || $accountId <= 0) {
            flash('error', 'Cuenta inválida para el ajuste.');
            redirect('/cuenta-corriente');
            return;
        }
        if ($amount === 0.0) {
            flash('error', 'El ajuste no puede ser cero.');
            redirect('/cuenta-corriente');
            return;
        }
        if ($description === '') {
            flash('error', 'La descripción del ajuste es obligatoria.');
            redirect('/cuenta-corriente');
            return;
        }

        $db->insert('account_transactions', [
            'account_type' => $accountType,
            'account_id' => $accountId,
            'transaction_type' => 'adjustment',
            'reference_type' => 'manual',
            'reference_id' => null,
            'amount' => $amount,
            'description' => $description,
            'notes' => $notes !== '' ? $notes : null,
            'transaction_date' => $date,
        ]);

        if ($accountType === 'client') {
            $this->recalculateClientBalance($accountId);
            redirect('/cuenta-corriente/cliente/' . $accountId);
            return;
        }
        redirect('/cuenta-corriente/proveedor/' . $accountId);
    }

    public function deleteMovement(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/cuenta-corriente');
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $movement = $db->fetch('SELECT * FROM account_transactions WHERE id = ?', [(int) $id]);
        if (!$movement) {
            flash('error', 'Movimiento no encontrado.');
            redirect('/cuenta-corriente');
            return;
        }
        if (!accountMovementIsEditable($movement)) {
            flash('error', 'Solo se pueden eliminar cobros, pagos o ajustes manuales (no facturas del sistema).');
            $this->redirectByMovement($movement);
            return;
        }

        $db->delete('account_transactions', 'id = :id', ['id' => (int) $id]);
        if ($movement['account_type'] === 'client') {
            $this->recalculateClientBalance((int) $movement['account_id']);
        }
        flash('success', 'Movimiento eliminado.');
        $this->redirectByMovement($movement);
    }

    public function editMovement(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $movement = $db->fetch('SELECT * FROM account_transactions WHERE id = ?', [(int) $id]);
        if (!$movement) {
            flash('error', 'Movimiento no encontrado.');
            redirect('/cuenta-corriente');
            return;
        }
        if (!accountMovementIsEditable($movement)) {
            flash('error', 'Este movimiento no se puede editar.');
            $this->redirectByMovement($movement);
            return;
        }
        $this->view('cuenta-corriente/movement-edit', [
            'title' => 'Editar movimiento',
            'movement' => $movement,
        ]);
    }

    public function updateMovement(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/cuenta-corriente');
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $movement = $db->fetch('SELECT * FROM account_transactions WHERE id = ?', [(int) $id]);
        if (!$movement) {
            flash('error', 'Movimiento no encontrado.');
            redirect('/cuenta-corriente');
            return;
        }
        if (!accountMovementIsEditable($movement)) {
            flash('error', 'Este movimiento no se puede editar.');
            $this->redirectByMovement($movement);
            return;
        }

        $type = (string) ($movement['transaction_type'] ?? '');
        $date = (string) $this->input('transaction_date', date('Y-m-d'));
        $notes = trim((string) $this->input('notes', ''));

        if ($type === 'payment') {
            $amount = parseArgentineAmount((string) $this->input('amount', '0'));
            if ($amount <= 0) {
                flash('error', 'El monto debe ser mayor a cero.');
                redirect('/cuenta-corriente/movimiento/' . $id . '/editar');
                return;
            }
            $method = $this->normalizePaymentMethod((string) $this->input('payment_method', 'efectivo'));
            $reference = trim((string) $this->input('payment_reference', ''));
            $isClient = (string) ($movement['account_type'] ?? '') === 'client';
            $description = $isClient
                ? ('Cobro ' . strtolower($this->paymentMethodLabel($method)))
                : ('Pago a proveedor ' . strtolower($this->paymentMethodLabel($method)));
            if ($reference !== '') {
                $description .= ' (ref: ' . $reference . ')';
            }
            $db->update(
                'account_transactions',
                [
                    'amount' => $amount,
                    'transaction_date' => $date,
                    'payment_method' => $method,
                    'payment_reference' => $reference !== '' ? $reference : null,
                    'description' => $description,
                    'notes' => $notes !== '' ? $notes : null,
                    'reference_type' => 'manual',
                ],
                'id = :id',
                ['id' => (int) $id]
            );
        } elseif ($type === 'adjustment') {
            $amount = parseArgentineAmount((string) $this->input('amount', '0'));
            if ($amount === 0.0) {
                flash('error', 'El ajuste no puede ser cero.');
                redirect('/cuenta-corriente/movimiento/' . $id . '/editar');
                return;
            }
            $description = trim((string) $this->input('description', ''));
            if ($description === '') {
                flash('error', 'La descripción del ajuste es obligatoria.');
                redirect('/cuenta-corriente/movimiento/' . $id . '/editar');
                return;
            }
            $db->update(
                'account_transactions',
                [
                    'amount' => $amount,
                    'transaction_date' => $date,
                    'description' => $description,
                    'notes' => $notes !== '' ? $notes : null,
                    'reference_type' => 'manual',
                ],
                'id = :id',
                ['id' => (int) $id]
            );
        } else {
            flash('error', 'Tipo de movimiento no editable.');
            $this->redirectByMovement($movement);
            return;
        }

        if (($movement['account_type'] ?? '') === 'client') {
            $this->recalculateClientBalance((int) $movement['account_id']);
        }
        flash('success', 'Movimiento actualizado.');
        $this->redirectByMovement($movement);
    }

    public function clientStatementPdf(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $client = $db->fetch('SELECT * FROM clients WHERE id = ?', [(int) $id]);
        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            redirect('/cuenta-corriente/clientes');
            return;
        }
        $transactions = $db->fetchAll(
            "SELECT * FROM account_transactions
             WHERE account_type = 'client' AND account_id = ?
             ORDER BY transaction_date ASC, id ASC",
            [(int) $id]
        );
        $this->renderStatementPdf(
            'client',
            $client,
            $transactions,
            ClientReceivableSummary::openingBalanceForClient($db, (int) $id)
        );
    }

    public function supplierStatementPdf(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSchema($db)) {
            return;
        }
        $supplier = $db->fetch('SELECT * FROM suppliers WHERE id = ?', [(int) $id]);
        if (!$supplier) {
            flash('error', 'Proveedor no encontrado.');
            redirect('/cuenta-corriente');
            return;
        }
        $transactions = $db->fetchAll(
            "SELECT * FROM account_transactions
             WHERE account_type = 'supplier' AND account_id = ?
             ORDER BY transaction_date ASC, id ASC",
            [(int) $id]
        );
        $this->renderStatementPdf('supplier', $supplier, $transactions);
    }

    private function ensureSchema(Database $db): bool
    {
        try {
            $hasTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if (!$hasTable) {
                flash('error', 'Falta migrar la tabla account_transactions.');
                redirect('/');
                return false;
            }
            return true;
        } catch (\Throwable) {
            flash('error', 'No se pudo validar esquema de cuenta corriente.');
            redirect('/');
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    private function getSupplierDebts(Database $db): array
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

    private function recalculateClientBalance(int $clientId): void
    {
        $db = Database::getInstance();
        $invoices = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'invoice'",
            [$clientId]
        );
        $payments = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'payment'",
            [$clientId]
        );
        $adjustments = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'adjustment'",
            [$clientId]
        );
        $balance = $invoices - $payments + $adjustments;
        $db->query('UPDATE clients SET balance = ? WHERE id = ?', [round($balance, 2), $clientId]);
    }

    /** @param array<string,mixed> $transaction */
    private function transactionImpact(array $transaction): float
    {
        $type = (string) $transaction['transaction_type'];
        $amount = (float) $transaction['amount'];
        if ($type === 'invoice') {
            return $amount;
        }
        if ($type === 'payment') {
            return -$amount;
        }
        return $amount;
    }

    /** @param array<string,mixed> $movement */
    private function redirectByMovement(array $movement): void
    {
        if ((string) $movement['account_type'] === 'client') {
            redirect('/cuenta-corriente/cliente/' . (int) $movement['account_id']);
            return;
        }
        redirect('/cuenta-corriente/proveedor/' . (int) $movement['account_id']);
    }

    private function sanitizeReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '') {
            return '/cuenta-corriente/clientes';
        }

        // Si viene URL absoluta, quedarse con path + query.
        if (preg_match('#^https?://#i', $returnTo)) {
            $parsed = parse_url($returnTo);
            if (!is_array($parsed)) {
                return '/cuenta-corriente/clientes';
            }
            $path = (string) ($parsed['path'] ?? '');
            $query = isset($parsed['query']) ? ('?' . $parsed['query']) : '';
            $returnTo = $path . $query;
        }

        if (!str_starts_with($returnTo, '/')) {
            return '/cuenta-corriente/clientes';
        }

        // Normalizar contra el prefijo base de la app para evitar duplicaciones:
        // ej. /limpiaOesteSistema/public/limpiaOesteSistema/public/clientes -> /clientes
        $base = '';
        if (defined('BASE_URL_PATH')) {
            $base = rtrim((string) BASE_URL_PATH, '/');
        } elseif (defined('BASE_URL')) {
            $base = rtrim((string) BASE_URL, '/');
        }
        if ($base !== '' && str_starts_with($returnTo, $base)) {
            while (str_starts_with($returnTo, $base . '/')) {
                $returnTo = substr($returnTo, strlen($base));
            }
            if ($returnTo === $base) {
                $returnTo = '/';
            }
        }

        if ($returnTo === '' || $returnTo[0] !== '/') {
            return '/cuenta-corriente/clientes';
        }

        return $returnTo;
    }

    private function normalizePaymentMethod(string $method): string
    {
        return in_array($method, ['efectivo', 'transferencia', 'mercadopago', 'otro'], true)
            ? $method
            : 'otro';
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'mercadopago' => 'Mercado Pago',
            default => 'Otro',
        };
    }

    /** @param array<string,mixed> $entity @param list<array<string,mixed>> $transactions */
    private function renderStatementPdf(string $entityType, array $entity, array $transactions, float $openingBalance = 0.0): void
    {
        $rows = [];
        $running = $openingBalance;
        if ($entityType === 'client' && abs($openingBalance) > 0.00001) {
            $rows[] = [
                'transaction_date' => null,
                'description' => 'Saldo inicial por presupuestos',
                'debe' => $openingBalance > 0 ? abs($openingBalance) : 0.0,
                'haber' => $openingBalance < 0 ? abs($openingBalance) : 0.0,
                'saldo' => round($running, 2),
                'is_opening_balance' => true,
            ];
        }
        foreach ($transactions as $tx) {
            $impact = $this->transactionImpact($tx);
            $running += $impact;
            $rows[] = [
                'transaction_date' => $tx['transaction_date'],
                'description' => $tx['description'],
                'debe' => $impact > 0 ? abs((float) $tx['amount']) : 0.0,
                'haber' => $impact < 0 ? abs((float) $tx['amount']) : 0.0,
                'saldo' => round($running, 2),
                'is_opening_balance' => false,
            ];
        }

        $pdfData = [
            'entityType' => $entityType,
            'entity' => $entity,
            'rows' => $rows,
            'openingBalance' => $openingBalance,
            'currentBalance' => round($running, 2),
        ];

        ob_start();
        extract($pdfData, EXTR_SKIP);
        require APP_PATH . '/Views/pdf/account-statement.php';
        $html = (string) ob_get_clean();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="estado-cuenta-' . time() . '.pdf"');
        echo $dompdf->output();
        exit;
    }
}
