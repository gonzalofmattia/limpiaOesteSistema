<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\QuoteDeliveryStock;
use App\Models\Database;

final class ToolsController extends Controller
{
    public function reconcileStock(): void
    {
        $db = Database::getInstance();
        $calculated = QuoteDeliveryStock::calculatePendingCommittedUnitsByProduct($db);
        $products = $db->fetchAll(
            'SELECT id, code, name, COALESCE(stock_committed_units, 0) AS stock_committed_units
             FROM products
             WHERE is_active = 1
             ORDER BY code'
        );
        $discrepancies = [];
        foreach ($products as $p) {
            $pid = (int) $p['id'];
            $actual = (int) ($p['stock_committed_units'] ?? 0);
            $calc = (int) ($calculated[$pid] ?? 0);
            if ($actual === $calc) {
                continue;
            }
            $discrepancies[] = [
                'product_id' => $pid,
                'code' => (string) ($p['code'] ?? ''),
                'name' => (string) ($p['name'] ?? ''),
                'committed_actual' => $actual,
                'committed_calculado' => $calc,
                'diferencia' => $calc - $actual,
            ];
        }

        $this->view('tools/reconcile-stock', [
            'title' => 'Reconciliar stock comprometido',
            'subtitle' => 'Compará stock_committed_units con el compromiso real desde presupuestos',
            'discrepancies' => $discrepancies,
            'count' => count($discrepancies),
        ]);
    }

    public function applyReconciliation(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/tools/reconciliar-stock');
            return;
        }
        $db = Database::getInstance();
        $hasAdjustments = (bool) $db->fetchColumn("SHOW TABLES LIKE 'stock_adjustments'");
        $calculated = QuoteDeliveryStock::calculatePendingCommittedUnitsByProduct($db);
        $allProducts = $db->fetchAll(
            'SELECT id, COALESCE(stock_committed_units, 0) AS stock_committed_units, stock_units
             FROM products'
        );

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        $fixed = 0;
        try {
            foreach ($allProducts as $p) {
                $pid = (int) $p['id'];
                $actual = (int) ($p['stock_committed_units'] ?? 0);
                $calc = (int) ($calculated[$pid] ?? 0);
                if ($actual === $calc) {
                    continue;
                }
                $db->update(
                    'products',
                    ['stock_committed_units' => max(0, $calc)],
                    'id = :id',
                    ['id' => $pid]
                );
                if ($hasAdjustments) {
                    $stockUnits = (int) ($p['stock_units'] ?? 0);
                    $db->insert('stock_adjustments', [
                        'product_id' => $pid,
                        'previous_stock' => $stockUnits,
                        'new_stock' => $stockUnits,
                        'difference' => 0,
                        'notes' => 'Reconciliación automática',
                        'created_by' => trim((string) ($_SESSION['admin_username'] ?? 'admin')),
                    ]);
                }
                $fixed++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo aplicar la reconciliación: ' . $e->getMessage());
            redirect('/tools/reconciliar-stock');
            return;
        }

        if (!$hasAdjustments && $fixed > 0) {
            flash('info', "Se corrigieron {$fixed} productos. No hay tabla stock_adjustments para registrar auditoría.");
        } else {
            flash('success', "Reconciliación aplicada: {$fixed} producto(s) corregido(s).");
        }
        redirect('/tools/reconciliar-stock');
    }

    public function fixStock(): void
    {
        $mode = trim((string) $this->query('mode', 'report'));
        $allowed = ['report', 'fix_committed', 'fix_physical_preview', 'fix_physical_apply'];
        if (!in_array($mode, $allowed, true)) {
            $mode = 'report';
        }
        $qs = '?mode=' . urlencode($mode);
        $token = trim((string) $this->query('token', ''));
        $ts = trim((string) $this->query('ts', ''));
        if ($token !== '' && $ts !== '') {
            $qs .= '&token=' . urlencode($token) . '&ts=' . urlencode($ts);
        }
        redirect('/fix_stock.php' . $qs);
    }

    public function fixStockApply(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/fix-stock?mode=fix_physical_preview');
            return;
        }
        $mode = trim((string) $this->input('mode', 'fix_physical_apply'));
        $token = trim((string) $this->input('token', ''));
        $ts = trim((string) $this->input('ts', ''));
        if ($mode !== 'fix_physical_apply' || $token === '' || $ts === '') {
            flash('error', 'Datos incompletos para aplicar corrección.');
            redirect('/fix-stock?mode=fix_physical_preview');
            return;
        }
        redirect('/fix_stock.php?mode=fix_physical_apply&token=' . urlencode($token) . '&ts=' . urlencode($ts));
    }
}
