<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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

        $totalReceivable = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(balance), 0) FROM clients WHERE COALESCE(balance, 0) > 0"
        );
        $clientsWithDebt = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM clients WHERE COALESCE(balance, 0) > 0"
        );
        $supplierDebts = $this->getSupplierDebts($db);
        $totalPayable = 0.0;
        foreach ($supplierDebts as $supplier) {
            $totalPayable += (float) $supplier['debt'];
        }

        $recentTransactions = $db->fetchAll(
            "SELECT at.*, c.name AS client_name, s.name AS supplier_name
             FROM account_transactions at
             LEFT JOIN clients c ON c.id = at.account_id AND at.account_type = 'client'
             LEFT JOIN suppliers s ON s.id = at.account_id AND at.account_type = 'supplier'
             ORDER BY at.transaction_date DESC, at.id DESC
             LIMIT 20"
        );

        $clientsForForm = $db->fetchAll(
            "SELECT id, name, balance FROM clients WHERE is_active = 1 ORDER BY name"
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
        $rows = $db->fetchAll(
            "SELECT c.id, c.name, c.balance,
                    COALESCE(SUM(CASE WHEN at.transaction_type = 'invoice' THEN at.amount ELSE 0 END), 0) AS total_invoiced,
                    COALESCE(SUM(CASE WHEN at.transaction_type = 'payment' THEN at.amount ELSE 0 END), 0) AS total_paid,
                    COALESCE(SUM(CASE WHEN at.transaction_type = 'adjustment' THEN at.amount ELSE 0 END), 0) AS total_adjustments
             FROM clients c
             LEFT JOIN account_transactions at
                ON at.account_type = 'client' AND at.account_id = c.id
             GROUP BY c.id
             ORDER BY c.name"
        );

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

        $this->view('cuenta-corriente/clients', [
            'title' => 'Cuentas a cobrar',
            'rows' => $rows,
            'onlyWithDebt' => $onlyWithDebt,
            'totalReceivable' => round($totalReceivable, 2),
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
        $running = 0.0;
        foreach ($transactions as &$tx) {
            $running += $this->transactionImpact($tx);
            $tx['running_balance'] = round($running, 2);
            $tx['debe'] = $this->transactionImpact($tx) > 0 ? abs((float) $tx['amount']) : 0.0;
            $tx['haber'] = $this->transactionImpact($tx) < 0 ? abs((float) $tx['amount']) : 0.0;
        }
        unset($tx);

        $this->view('cuenta-corriente/client-detail', [
            'title' => 'Cuenta corriente - ' . $client['name'],
            'client' => $client,
            'transactions' => $transactions,
            'balance' => round($running, 2),
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

        $this->view('cuenta-corriente/seiq', [
            'title' => 'Cuenta proveedor - ' . $supplier['name'],
            'supplier' => $supplier,
            'transactions' => $transactions,
            'debt' => round($runningDebt, 2),
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

        $amount = $this->parseAmount((string) $this->input('amount', '0'));
        $method = (string) $this->input('payment_method', 'efectivo');
        $reference = trim((string) $this->input('payment_reference', ''));
        $date = (string) $this->input('transaction_date', date('Y-m-d'));
        $notes = trim((string) $this->input('notes', ''));

        if ($amount <= 0) {
            flash('error', 'El monto debe ser mayor a cero.');
            redirect('/cuenta-corriente/cliente/' . $clientId);
            return;
        }

        $description = 'Cobro ' . ($method === 'transferencia' ? 'transferencia' : 'efectivo');
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

        $amount = $this->parseAmount((string) $this->input('amount', '0'));
        $method = (string) $this->input('payment_method', 'efectivo');
        $reference = trim((string) $this->input('payment_reference', ''));
        $date = (string) $this->input('transaction_date', date('Y-m-d'));
        $notes = trim((string) $this->input('notes', ''));

        if ($amount <= 0) {
            flash('error', 'El monto debe ser mayor a cero.');
            redirect('/cuenta-corriente/proveedor/' . $supplierId);
            return;
        }

        $description = 'Pago a proveedor ' . ($method === 'transferencia' ? 'transferencia' : 'efectivo');
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
        $amount = $this->parseSignedAmount((string) $this->input('amount', '0'));
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
        if ((string) ($movement['reference_type'] ?? '') !== 'manual') {
            flash('error', 'Solo se pueden eliminar movimientos manuales.');
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
        $this->renderStatementPdf('client', $client, $transactions);
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

    private function parseAmount(string $raw): float
    {
        $norm = str_replace(['.', ','], ['', '.'], $raw);
        return round((float) $norm, 2);
    }

    private function parseSignedAmount(string $raw): float
    {
        $norm = str_replace(',', '.', str_replace('.', '', $raw));
        return round((float) $norm, 2);
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

    /** @param array<string,mixed> $entity @param list<array<string,mixed>> $transactions */
    private function renderStatementPdf(string $entityType, array $entity, array $transactions): void
    {
        $rows = [];
        $running = 0.0;
        foreach ($transactions as $tx) {
            $impact = $this->transactionImpact($tx);
            $running += $impact;
            $rows[] = [
                'transaction_date' => $tx['transaction_date'],
                'description' => $tx['description'],
                'debe' => $impact > 0 ? abs((float) $tx['amount']) : 0.0,
                'haber' => $impact < 0 ? abs((float) $tx['amount']) : 0.0,
                'saldo' => round($running, 2),
            ];
        }

        $pdfData = [
            'entityType' => $entityType,
            'entity' => $entity,
            'rows' => $rows,
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
