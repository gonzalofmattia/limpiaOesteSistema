<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteDeliveryStock;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class QuoteController extends Controller
{
    private const EDITABLE_STATUSES = ['draft', 'sent', 'accepted'];

    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(q.quote_number LIKE ? OR c.name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
        $hasAttach = false;
        try {
            $hasAttach = (bool) $db->fetchColumn("SHOW TABLES LIKE 'quote_attachments'");
        } catch (\Throwable) {
            $hasAttach = false;
        }
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*)
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id
             {$whereSql}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        if ($hasAttach) {
            $rows = $db->fetchAll(
                'SELECT q.*, c.name AS client_name,
                        (SELECT COUNT(*) FROM quote_attachments qa WHERE qa.quote_id = q.id) AS attachments_count
                 FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 ' . $whereSql . '
                 ORDER BY q.created_at DESC
                 LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
                $params
            );
        } else {
            $rows = $db->fetchAll(
                'SELECT q.*, c.name AS client_name, 0 AS attachments_count
                 FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 ' . $whereSql . '
                 ORDER BY q.created_at DESC
                 LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
                $params
            );
        }
        $this->view('quotes/index', [
            'title' => 'Presupuestos',
            'quotes' => $rows,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function create(): void
    {
        $db = Database::getInstance();
        $clients = $db->fetchAll('SELECT * FROM clients WHERE is_active = 1 ORDER BY name');
        $this->view('quotes/form', [
            'title' => 'Nuevo presupuesto',
            'quote' => null,
            'items' => [],
            'clients' => $clients,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos/crear');
        }
        $res = $this->persistQuote(null);
        if ($res['error']) {
            flash('error', $res['error']);
            redirect('/presupuestos/crear');
        }
        flash('success', 'Presupuesto creado.');
        redirect('/presupuestos/' . $res['id']);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $quote = $db->fetch(
            'SELECT q.*, c.name AS client_name, c.business_name, c.contact_person, c.phone, c.email, c.address, c.city
             FROM quotes q LEFT JOIN clients c ON c.id = q.client_id WHERE q.id = ?',
            [(int) $id]
        );
        if (!$quote) {
            flash('error', 'No encontrado.');
            redirect('/presupuestos');
        }
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name, cmb.name AS combo_name, p.presentation, p.content, p.sale_unit_description,
                    p.precio_lista_unitario, p.precio_lista_bidon, p.precio_lista_sobre,
                    p.discount_override, p.markup_override,
                    COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override
             FROM quote_items qi
             LEFT JOIN products p ON p.id = qi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN combos cmb ON cmb.id = qi.combo_id
             WHERE qi.quote_id = ? ORDER BY qi.sort_order, qi.id',
            [(int) $id]
        );
        $quoteAttachments = [];
        $invoiceAttachmentCount = 0;
        try {
            if ((bool) $db->fetchColumn("SHOW TABLES LIKE 'quote_attachments'")) {
                $quoteAttachments = $db->fetchAll(
                    "SELECT * FROM quote_attachments WHERE quote_id = ?
                     ORDER BY CASE type WHEN 'remito' THEN 0 ELSE 1 END, created_at DESC",
                    [(int) $id]
                );
                $invoiceAttachmentCount = (int) $db->fetchColumn(
                    "SELECT COUNT(*) FROM quote_attachments WHERE quote_id = ? AND type = 'factura'",
                    [(int) $id]
                );
            }
        } catch (\Throwable) {
            $quoteAttachments = [];
            $invoiceAttachmentCount = 0;
        }
        $this->view('quotes/preview', [
            'title' => 'Presupuesto ' . $quote['quote_number'],
            'quote' => $quote,
            'items' => $items,
            'readonly' => false,
            'quoteAttachments' => $quoteAttachments,
            'invoiceAttachmentCount' => $invoiceAttachmentCount,
        ]);
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $quote = $db->fetch('SELECT * FROM quotes WHERE id = ?', [(int) $id]);
        if (!$quote) {
            flash('error', 'No encontrado.');
            redirect('/presupuestos');
        }
        if (!$this->quoteIsEditable($quote)) {
            flash('error', "No se puede editar un presupuesto en estado '{$quote['status']}'. Si necesitás hacer cambios, primero cambiá el estado a 'Aceptado' y luego editá.");
            redirect('/presupuestos/' . (int) $id);
            return;
        }
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name, cmb.name AS combo_name, p.category_id, p.sale_unit_label, p.sale_unit_type, p.content,
                    p.units_per_box, p.stock_units, COALESCE(p.stock_committed_units, 0) AS stock_committed_units,
                    p.sale_unit_description, COALESCE(pc.slug, c.slug) AS category_slug
             FROM quote_items qi
             LEFT JOIN products p ON p.id = qi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN combos cmb ON cmb.id = qi.combo_id
             WHERE qi.quote_id = ? ORDER BY qi.sort_order, qi.id',
            [(int) $id]
        );
        $clients = $db->fetchAll('SELECT * FROM clients WHERE is_active = 1 ORDER BY name');
        $this->view('quotes/form', [
            'title' => 'Editar presupuesto',
            'quote' => $quote,
            'items' => $items,
            'clients' => $clients,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos/' . $id . '/editar');
        }
        $db = Database::getInstance();
        $quote = $db->fetch('SELECT id, status FROM quotes WHERE id = ?', [(int) $id]);
        if (!$quote) {
            flash('error', 'No encontrado.');
            redirect('/presupuestos');
            return;
        }
        if (!$this->quoteIsEditable($quote)) {
            flash('error', "No se puede editar un presupuesto en estado '{$quote['status']}'. Si necesitás hacer cambios, primero cambiá el estado a 'Aceptado' y luego editá.");
            redirect('/presupuestos/' . $id);
            return;
        }
        $res = $this->persistQuote((int) $id);
        if ($res['error']) {
            flash('error', $res['error']);
            redirect('/presupuestos/' . $id . '/editar');
        }
        flash('success', 'Presupuesto actualizado.');
        redirect('/presupuestos/' . $id);
    }

    public function downloadPdf(string $id): void
    {
        $db = Database::getInstance();
        $quote = $db->fetch(
            'SELECT q.*, c.name AS client_name, c.business_name, c.contact_person, c.phone, c.email, c.address, c.city
             FROM quotes q LEFT JOIN clients c ON c.id = q.client_id WHERE q.id = ?',
            [(int) $id]
        );
        if (!$quote) {
            flash('error', 'No encontrado.');
            redirect('/presupuestos');
        }
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name, cmb.name AS combo_name, p.presentation, p.content, p.sale_unit_description,
                    p.precio_lista_unitario, p.precio_lista_bidon, p.precio_lista_sobre,
                    p.discount_override, p.markup_override,
                    COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override
             FROM quote_items qi
             LEFT JOIN products p ON p.id = qi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN combos cmb ON cmb.id = qi.combo_id
             WHERE qi.quote_id = ? ORDER BY qi.sort_order, qi.id',
            [(int) $id]
        );
        $file = $this->renderQuotePdf($quote, $items);
        $db->query('UPDATE quotes SET pdf_path = ? WHERE id = ?', [$file, (int) $id]);
        $full = STORAGE_PATH . '/pdfs/' . $file;
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($full);
        exit;
    }

    public function changeStatus(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos/' . $id);
        }
        $status = (string) $this->input('status', '');
        $allowed = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'delivered'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Estado inválido.');
            redirect('/presupuestos/' . $id);
        }
        $db = Database::getInstance();
        $quote = $db->fetch('SELECT * FROM quotes WHERE id = ?', [(int) $id]);
        if (!$quote) {
            flash('error', 'Presupuesto no encontrado.');
            redirect('/presupuestos');
            return;
        }
        $oldStatus = (string) ($quote['status'] ?? 'draft');
        $deliveryApplied = (int) ($quote['delivery_stock_applied'] ?? 0) === 1;
        $extra = [];
        if ($status === 'sent') {
            $extra['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'accepted' && empty($quote['sale_number'])) {
            $extra['sale_number'] = $this->nextSaleNumber($db);
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            if ($oldStatus === 'accepted' && $status !== 'accepted' && $status !== 'delivered') {
                QuoteDeliveryStock::releaseCommittedStock($db, (int) $id);
            }
            if ($oldStatus === 'delivered' && $status !== 'delivered' && $deliveryApplied) {
                QuoteDeliveryStock::reverseDelivery($db, (int) $id);
                $extra['delivery_stock_applied'] = 0;
            }
            if ($status === 'accepted' && $oldStatus !== 'accepted') {
                QuoteDeliveryStock::commitStock($db, (int) $id);
            }
            if ($status === 'delivered' && $oldStatus !== 'delivered' && !$deliveryApplied) {
                QuoteDeliveryStock::markDelivered($db, (int) $id);
                $extra['delivery_stock_applied'] = 1;
            }

            $db->update('quotes', array_merge(['status' => $status], $extra), 'id = :id', ['id' => (int) $id]);

            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            // Deuda en CC: al aceptar O al entregar (delivered). Si solo se entrega sin pasar por accepted, igual debe existir el cargo tipo invoice.
            $enteredReceivable =
                ($status === 'accepted' && $oldStatus !== 'accepted')
                || ($status === 'delivered' && $oldStatus !== 'delivered');
            if ($hasAccountTable && $enteredReceivable) {
                $existing = $db->fetch(
                    "SELECT id FROM account_transactions
                     WHERE reference_type = 'quote' AND reference_id = ? AND transaction_type = 'invoice'
                     LIMIT 1",
                    [(int) $id]
                );
                if (!$existing) {
                    $clientId = (int) ($quote['client_id'] ?? 0);
                    $amount = round((float) ($quote['total'] ?? 0), 2);
                    if ($clientId > 0 && $amount > 0) {
                        $db->insert('account_transactions', [
                            'account_type' => 'client',
                            'account_id' => $clientId,
                            'transaction_type' => 'invoice',
                            'reference_type' => 'quote',
                            'reference_id' => (int) $id,
                            'amount' => $amount,
                            'description' => 'Presupuesto ' . (string) ($quote['quote_number'] ?? ('#' . $id)),
                            'transaction_date' => date('Y-m-d'),
                        ]);
                    }
                }
                $this->recalculateClientBalance((int) ($quote['client_id'] ?? 0));
            }

            if ($hasAccountTable
                && in_array($oldStatus, ['accepted', 'delivered'], true)
                && in_array($status, ['draft', 'rejected'], true)) {
                $db->query(
                    "DELETE FROM account_transactions
                     WHERE reference_type = 'quote' AND reference_id = ? AND transaction_type = 'invoice'",
                    [(int) $id]
                );
                $this->recalculateClientBalance((int) ($quote['client_id'] ?? 0));
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar el estado: ' . $e->getMessage());
            redirect('/presupuestos/' . $id);
            return;
        }

        flash('success', 'Estado actualizado.');
        redirect('/presupuestos/' . $id);
    }

    public function delete(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos');
            return;
        }
        $db = Database::getInstance();
        $quoteId = (int) $id;
        $quote = $db->fetch('SELECT id, quote_number, pdf_path, status FROM quotes WHERE id = ?', [$quoteId]);
        if (!$quote) {
            flash('error', 'Presupuesto no encontrado.');
            redirect('/presupuestos');
            return;
        }

        $db->getPdo()->beginTransaction();
        try {
            if ((string) ($quote['status'] ?? '') === 'accepted') {
                QuoteDeliveryStock::releaseCommittedStock($db, $quoteId);
            }
            try {
                $hasAttach = (bool) $db->fetchColumn("SHOW TABLES LIKE 'quote_attachments'");
                if ($hasAttach) {
                    $db->query('DELETE FROM quote_attachments WHERE quote_id = ?', [$quoteId]);
                }
            } catch (\Throwable) {
                // Si no está la tabla por entorno/migración, continúa.
            }

            try {
                $hasAccount = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
                if ($hasAccount) {
                    $db->query(
                        "DELETE FROM account_transactions
                         WHERE reference_type = 'quote' AND reference_id = ?",
                        [$quoteId]
                    );
                }
            } catch (\Throwable) {
                // No bloquea el borrado principal.
            }

            $db->delete('quote_items', 'quote_id = :qid', ['qid' => $quoteId]);
            $db->delete('quotes', 'id = :id', ['id' => $quoteId]);
            $db->getPdo()->commit();
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'No se pudo eliminar el presupuesto: ' . $e->getMessage());
            redirect('/presupuestos');
            return;
        }

        $pdfPath = trim((string) ($quote['pdf_path'] ?? ''));
        if ($pdfPath !== '') {
            $full = STORAGE_PATH . '/pdfs/' . basename($pdfPath);
            if (is_file($full)) {
                @unlink($full);
            }
        }

        flash('success', 'Presupuesto eliminado.');
        redirect('/presupuestos');
    }

    private function recalculateClientBalance(int $clientId): void
    {
        if ($clientId <= 0) {
            return;
        }
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

    /** @return array{id?:int,error:?string} */
    private function persistQuote(?int $id): array
    {
        $db = Database::getInstance();
        $clientId = (int) $this->input('client_id', 0);
        if ($clientId <= 0) {
            return ['error' => 'Seleccioná un cliente.'];
        }
        $title = trim((string) $this->input('title', ''));
        $notes = trim((string) $this->input('notes', ''));
        $validity = max(1, (int) $this->input('validity_days', (int) (setting('quote_validity_days', '7') ?? 7)));
        $markRaw = trim((string) $this->input('custom_markup', ''));
        $customMarkup = $markRaw === '' ? null : (float) str_replace(',', '.', $markRaw);
        $includeIva = isset($_POST['include_iva']) && (string) $_POST['include_iva'] === '1';
        $discountPercentage = $this->parseNullableDecimal($this->input('discount_percentage', null));
        $discountAmountInput = $this->parseNullableDecimal($this->input('discount_amount', null));
        if ($discountPercentage !== null) {
            $discountPercentage = max(0.0, min(100.0, $discountPercentage));
        }

        $lines = $_POST['items'] ?? [];
        if (!is_array($lines) || $lines === []) {
            return ['error' => 'Agregá al menos un producto.'];
        }

        $db->getPdo()->beginTransaction();
        try {
            $existingQuote = null;
            $prevClientId = null;
            if ($id === null) {
                $number = $this->nextQuoteNumber();
                $id = $db->insert('quotes', [
                    'quote_number' => $number,
                    'client_id' => $clientId,
                    'title' => $title ?: null,
                    'notes' => $notes ?: null,
                    'validity_days' => $validity,
                    'custom_markup' => $customMarkup,
                    'include_iva' => $includeIva ? 1 : 0,
                    'is_mercadolibre' => 0,
                    'subtotal' => 0,
                    'discount_percentage' => null,
                    'discount_amount' => null,
                    'iva_amount' => 0,
                    'total' => 0,
                    'status' => 'draft',
                ]);
            } else {
                $existingQuote = $db->fetch('SELECT id, status, client_id FROM quotes WHERE id = ?', [$id]);
                if (!$existingQuote) {
                    $db->getPdo()->rollBack();
                    return ['error' => 'No encontrado.'];
                }
                $prevClientId = (int) ($existingQuote['client_id'] ?? 0);
                if (!$this->quoteIsEditable($existingQuote)) {
                    $db->getPdo()->rollBack();
                    return ['error' => 'No se puede editar un presupuesto en este estado.'];
                }
                if ((string) ($existingQuote['status'] ?? '') === 'accepted') {
                    QuoteDeliveryStock::releaseCommittedStock($db, $id);
                }
                $db->delete('quote_items', 'quote_id = :qid', ['qid' => $id]);
                $db->update('quotes', [
                    'client_id' => $clientId,
                    'title' => $title ?: null,
                    'notes' => $notes ?: null,
                    'validity_days' => $validity,
                    'custom_markup' => $customMarkup,
                    'include_iva' => $includeIva ? 1 : 0,
                    'discount_percentage' => null,
                    'discount_amount' => null,
                ], 'id = :id', ['id' => $id]);
            }

            $subtotalNet = 0.0;
            $totalWithIva = 0.0;
            $sort = 0;
            foreach ($lines as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $comboId = (int) ($row['combo_id'] ?? 0);
                if ($comboId > 0) {
                    $qty = max(1, (int) ($row['quantity'] ?? 1));
                    $combo = $db->fetch('SELECT id, name, markup_percentage, subtotal_override, discount_percentage FROM combos WHERE id = ? AND is_active = 1', [$comboId]);
                    if (!$combo) {
                        continue;
                    }
                    $comboProducts = $db->fetchAll(
                        'SELECT cp.quantity, p.*,
                                COALESCE(pc.slug, c.slug) AS category_slug,
                                c.default_discount, c.default_markup AS category_default_markup,
                                c.markup_override AS category_markup_override,
                                pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                                pc.markup_override AS parent_markup_override
                         FROM combo_products cp
                         JOIN products p ON p.id = cp.product_id
                         JOIN categories c ON c.id = p.category_id
                         LEFT JOIN categories pc ON c.parent_id = pc.id
                         WHERE cp.combo_id = ?',
                        [$comboId]
                    );
                    $subtotalCalc = 0.0;
                    $comboCostUnit = 0.0;
                    foreach ($comboProducts as $cp) {
                        $slug = strtolower((string) $cp['category_slug']);
                        $resolvedCost = QuoteLinePricing::resolveListaForQuote($cp, $slug, 'unidad');
                        $costCalc = PricingEngine::calculateWithListaSeiq(
                            (float) $resolvedCost['lista_seiq'],
                            $cp,
                            $customMarkup,
                            false
                        );
                        $comboCostUnit += round((float) ($costCalc['costo'] ?? 0) * (int) $cp['quantity'], 4);
                        $unitVenta = QuoteLinePricing::individualUnitSellingPrice(
                            $cp,
                            $slug,
                            (float) $combo['markup_percentage'],
                            $includeIva
                        );
                        $unit = round($unitVenta, 2);
                        $subtotalCalc += round($unit * (int) $cp['quantity'], 2);
                    }
                    $comboSubtotal = $combo['subtotal_override'] !== null ? (float) $combo['subtotal_override'] : round($subtotalCalc, 2);
                    $comboDiscount = (float) $combo['discount_percentage'];
                    $comboFinalUnit = round($comboSubtotal * (1 - ($comboDiscount / 100)), 2);
                    $lineSub = round($comboFinalUnit * $qty, 2);
                    $lineCostUnit = round(max(0, $comboCostUnit), 2);
                    $lineCostSub = round($lineCostUnit * $qty, 2);
                    $subtotalNet += $lineSub;
                    $totalWithIva += $lineSub;
                    $db->insert('quote_items', [
                        'quote_id' => $id,
                        'product_id' => null,
                        'combo_id' => $comboId,
                        'quantity' => $qty,
                        'unit_type' => 'combo',
                        'unit_label' => 'Combo',
                        'unit_description' => (string) $combo['name'],
                        'unit_price' => $comboFinalUnit,
                        'individual_unit_price' => $comboFinalUnit,
                        'subtotal' => $lineSub,
                        'price_field_used' => 'combo',
                        'discount_applied' => $comboDiscount,
                        'markup_applied' => (float) $combo['markup_percentage'],
                        'cost_unit_snapshot' => $lineCostUnit,
                        'cost_subtotal_snapshot' => $lineCostSub,
                        'notes' => null,
                        'sort_order' => $sort++,
                    ]);
                    continue;
                }
                $pid = (int) ($row['product_id'] ?? 0);
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $unitMode = QuoteLinePricing::normalizeUnitType((string) ($row['unit_type'] ?? 'caja'));
                if ($pid <= 0) {
                    continue;
                }
                $p = $db->fetch(
                    'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                            c.default_markup AS category_default_markup,
                            c.markup_override AS category_markup_override,
                            pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                            pc.markup_override AS parent_markup_override
                     FROM products p
                     JOIN categories c ON c.id = p.category_id
                     LEFT JOIN categories pc ON c.parent_id = pc.id
                     WHERE p.id = ?',
                    [$pid]
                );
                if (!$p) {
                    continue;
                }
                $slug = strtolower((string) $p['category_slug']);
                $resolved = QuoteLinePricing::resolveListaForQuote($p, $slug, $unitMode);
                $listaSeiq = $resolved['lista_seiq'];
                if ($listaSeiq <= 0) {
                    continue;
                }
                $snap = QuoteLinePricing::snapshotLabels($p, $slug, $unitMode);
                $calcNet = PricingEngine::calculateWithListaSeiq($listaSeiq, $p, $customMarkup, false);
                $calcLine = PricingEngine::calculateWithListaSeiq($listaSeiq, $p, $customMarkup, $includeIva);
                $unitPrice = $includeIva && $calcLine['precio_con_iva'] !== null
                    ? $calcLine['precio_con_iva']
                    : $calcNet['precio_venta'];
                $individualVenta = QuoteLinePricing::individualUnitSellingPrice($p, $slug, $customMarkup, $includeIva);
                $lineSub = round($unitPrice * $qty, 2);
                $lineCostUnit = round((float) ($calcNet['costo'] ?? 0), 2);
                $lineCostSub = round($lineCostUnit * $qty, 2);
                $subtotalNet += round($calcNet['precio_venta'] * $qty, 2);
                $totalWithIva += $lineSub;
                $db->insert('quote_items', [
                    'quote_id' => $id,
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'unit_type' => $unitMode,
                    'unit_label' => $snap['unit_label'],
                    'unit_description' => $snap['unit_description'],
                    'unit_price' => $unitPrice,
                    'individual_unit_price' => round($individualVenta, 2),
                    'subtotal' => $lineSub,
                    'price_field_used' => $resolved['price_field_used'],
                    'discount_applied' => $calcNet['discount_percent'],
                    'markup_applied' => $calcNet['markup_percent'],
                    'cost_unit_snapshot' => $lineCostUnit,
                    'cost_subtotal_snapshot' => $lineCostSub,
                    'notes' => null,
                    'sort_order' => $sort++,
                ]);
            }

            if ($sort === 0) {
                $db->getPdo()->rollBack();
                return ['error' => 'No se pudo calcular ninguna línea válida.'];
            }

            $ivaAmount = $includeIva ? round($totalWithIva - $subtotalNet, 2) : 0.0;
            $baseTotal = $includeIva ? $totalWithIva : $subtotalNet;
            $autoDiscount = $discountPercentage !== null ? round($baseTotal * ($discountPercentage / 100), 2) : 0.0;
            $discountAmount = $discountAmountInput ?? $autoDiscount;
            $discountAmount = max(0.0, min($baseTotal, round($discountAmount, 2)));
            if ($discountAmount <= 0.0) {
                $discountAmount = null;
            }
            if ($discountPercentage !== null && $discountPercentage <= 0.0) {
                $discountPercentage = null;
            }
            $total = max(0.0, round($baseTotal - (float) ($discountAmount ?? 0.0), 2));
            $db->update('quotes', [
                'subtotal' => round($subtotalNet, 2),
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'iva_amount' => round($ivaAmount, 2),
                'total' => round($total, 2),
            ], 'id = :id', ['id' => $id]);
            if ($existingQuote !== null && (string) ($existingQuote['status'] ?? '') === 'accepted') {
                QuoteDeliveryStock::commitStock($db, $id);
            }

            if ($existingQuote !== null && $prevClientId !== null && $prevClientId > 0 && $prevClientId !== $clientId) {
                $hasAcc = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
                if ($hasAcc) {
                    $db->query(
                        "UPDATE account_transactions SET account_id = ?
                         WHERE account_type = 'client' AND transaction_type = 'invoice'
                           AND reference_type = 'quote' AND reference_id = ?",
                        [$clientId, $id]
                    );
                    $this->recalculateClientBalance($prevClientId);
                    $this->recalculateClientBalance($clientId);
                }
            }

            $db->getPdo()->commit();
            return ['id' => $id, 'error' => null];
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            return ['error' => 'Error al guardar: ' . $e->getMessage()];
        }
    }

    private function nextQuoteNumber(): string
    {
        $prefix = setting('quote_prefix', 'LO') ?? 'LO';
        $year = (int) date('Y');
        $db = Database::getInstance();
        $like = $prefix . '-' . $year . '-%';
        $last = $db->fetchColumn(
            'SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$like]
        );
        $n = 0;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $n = (int) $m[1];
        }
        $next = $n + 1;
        return sprintf('%s-%d-%04d', $prefix, $year, $next);
    }

    private function parseNullableDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^-?\s*\$?\s*[\d\.,\s]+$/', $raw)) {
            return null;
        }
        $normalized = parseArgentineAmount($raw);
        return round($normalized, 2);
    }

    /** @param array<string,mixed> $quote */
    private function quoteIsEditable(array $quote): bool
    {
        return in_array((string) ($quote['status'] ?? ''), self::EDITABLE_STATUSES, true);
    }

    private function nextSaleNumber(Database $db): string
    {
        $prefix = setting('sale_prefix', 'V-') ?? 'V-';
        $prefix = trim($prefix);
        if ($prefix === '') {
            $prefix = 'V-';
        }
        $last = $db->fetchColumn(
            'SELECT sale_number FROM quotes WHERE sale_number IS NOT NULL AND sale_number <> "" ORDER BY id DESC LIMIT 1'
        );
        $n = 0;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $n = (int) $m[1];
        }
        return sprintf('%s%04d', $prefix, $n + 1);
    }

    /** @param array<string,mixed> $quote @param list<array<string,mixed>> $items */
    private function renderQuotePdf(array $quote, array $items): string
    {
        ob_start();
        require APP_PATH . '/Views/pdf/quote.php';
        $html = ob_get_clean();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $file = 'presupuesto-' . $quote['id'] . '-' . time() . '.pdf';
        $dir = STORAGE_PATH . '/pdfs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $file, $dompdf->output());
        return $file;
    }
}
