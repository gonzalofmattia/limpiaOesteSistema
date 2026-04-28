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
    public function index(): void
    {
        $db = Database::getInstance();
        $hasAttach = false;
        try {
            $hasAttach = (bool) $db->fetchColumn("SHOW TABLES LIKE 'quote_attachments'");
        } catch (\Throwable) {
            $hasAttach = false;
        }
        if ($hasAttach) {
            $rows = $db->fetchAll(
                'SELECT q.*, c.name AS client_name,
                        (SELECT COUNT(*) FROM quote_attachments qa WHERE qa.quote_id = q.id) AS attachments_count
                 FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 ORDER BY q.created_at DESC'
            );
        } else {
            $rows = $db->fetchAll(
                'SELECT q.*, c.name AS client_name, 0 AS attachments_count
                 FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 ORDER BY q.created_at DESC'
            );
        }
        $this->view('quotes/index', ['title' => 'Presupuestos', 'quotes' => $rows]);
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
            'SELECT qi.*, p.code, p.name, p.presentation, p.content, p.sale_unit_description,
                    p.precio_lista_unitario, p.precio_lista_bidon, p.precio_lista_sobre,
                    p.discount_override, p.markup_override,
                    COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                    c.default_markup AS category_default_markup,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
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
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name, p.category_id, p.sale_unit_label, p.sale_unit_type, p.content,
                    p.sale_unit_description, COALESCE(pc.slug, c.slug) AS category_slug
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
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
            'SELECT qi.*, p.code, p.name, p.presentation, p.content, p.sale_unit_description,
                    p.precio_lista_unitario, p.precio_lista_bidon, p.precio_lista_sobre,
                    p.discount_override, p.markup_override,
                    COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                    c.default_markup AS category_default_markup,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
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

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            if ($oldStatus === 'delivered' && $status !== 'delivered' && $deliveryApplied) {
                QuoteDeliveryStock::reverseDelivery($db, (int) $id);
                $extra['delivery_stock_applied'] = 0;
            }
            if ($status === 'delivered' && $oldStatus !== 'delivered' && !$deliveryApplied) {
                QuoteDeliveryStock::applyDelivery($db, (int) $id);
                $extra['delivery_stock_applied'] = 1;
            }

            $db->update('quotes', array_merge(['status' => $status], $extra), 'id = :id', ['id' => (int) $id]);

            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccountTable && $oldStatus !== 'accepted' && $status === 'accepted') {
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

            if ($hasAccountTable && $oldStatus === 'accepted' && in_array($status, ['draft', 'rejected'], true)) {
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
                $exists = $db->fetch('SELECT id FROM quotes WHERE id = ?', [$id]);
                if (!$exists) {
                    $db->getPdo()->rollBack();
                    return ['error' => 'No encontrado.'];
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
                $pid = (int) ($row['product_id'] ?? 0);
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $unitMode = QuoteLinePricing::normalizeUnitType((string) ($row['unit_type'] ?? 'caja'));
                if ($pid <= 0) {
                    continue;
                }
                $p = $db->fetch(
                    'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                            c.default_markup AS category_default_markup,
                            pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup
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
        $normalized = str_replace(['$', ' '], '', $raw);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
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
