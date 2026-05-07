<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClientReceivableSummary;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteDeliveryStock;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class QuoteController extends Controller
{
    private const EDITABLE_STATUSES = ['draft', 'sent', 'accepted', 'delivered'];

    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $status = trim((string) $this->query('status', ''));
        $sort = trim((string) $this->query('sort', 'created_at'));
        $dir = strtolower(trim((string) $this->query('dir', 'desc')));
        $allowedStatuses = ['draft', 'sent', 'accepted', 'delivered', 'partially_delivered', 'rejected', 'expired'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $status = '';
        }
        $allowedSort = [
            'quote_number' => 'q.quote_number',
            'client_name' => 'c.name',
            'created_at' => 'q.created_at',
            'total' => 'q.total',
            'status' => 'q.status',
        ];
        if (!isset($allowedSort[$sort])) {
            $sort = 'created_at';
        }
        $dir = $dir === 'asc' ? 'ASC' : 'DESC';
        $orderBySql = $allowedSort[$sort] . ' ' . $dir;
        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'q.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(q.quote_number LIKE ? OR c.name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));
        $rawStatusCounts = $db->fetchAll(
            "SELECT status, COUNT(*) AS qty
             FROM quotes
             GROUP BY status"
        );
        $statusCounts = [];
        foreach ($rawStatusCounts as $row) {
            $key = (string) ($row['status'] ?? '');
            if ($key === '') {
                continue;
            }
            $statusCounts[$key] = (int) ($row['qty'] ?? 0);
        }
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
                 ORDER BY ' . $orderBySql . '
                 LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
                $params
            );
        } else {
            $rows = $db->fetchAll(
                'SELECT q.*, c.name AS client_name, 0 AS attachments_count
                 FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 ' . $whereSql . '
                 ORDER BY ' . $orderBySql . '
                 LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
                $params
            );
        }
        $this->view('quotes/index', [
            'title' => 'Presupuestos',
            'quotes' => $rows,
            'search' => $search,
            'status' => $status,
            'status_counts' => $statusCounts,
            'sort' => $sort,
            'dir' => strtolower($dir),
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
        $comboComponents = $this->comboComponentsMapForQuoteItems($db, $items);
        $pendingItems = [];
        if ((string) ($quote['status'] ?? '') === 'partially_delivered') {
            $qid = (int) $id;
            $pendingItems = $db->fetchAll(
                'SELECT u.name, u.presentation, u.pendiente, u.tipo, u.combo_nombre FROM (
                    SELECT p.name,
                           TRIM(CONCAT(TRIM(IFNULL(p.presentation, \'\')), \' \', TRIM(IFNULL(p.content, \'\')))) AS presentation,
                           GREATEST(0, FLOOR(CAST(qi.quantity AS DECIMAL(12,4)) - CAST(COALESCE(qi.qty_delivered, 0) AS DECIMAL(12,4)) + 0.0001)) AS pendiente,
                           \'producto\' AS tipo,
                           NULL AS combo_nombre
                    FROM quote_items qi
                    JOIN products p ON p.id = qi.product_id
                    WHERE qi.quote_id = ?
                      AND COALESCE(qi.combo_id, 0) = 0
                      AND CAST(qi.quantity AS DECIMAL(12,4)) > CAST(COALESCE(qi.qty_delivered, 0) AS DECIMAL(12,4)) + 0.0001
                    UNION ALL
                    SELECT p.name,
                           TRIM(CONCAT(TRIM(IFNULL(p.presentation, \'\')), \' \', TRIM(IFNULL(p.content, \'\')))) AS presentation,
                           GREATEST(
                             0,
                             FLOOR(CAST(qi.quantity AS DECIMAL(12,4)) * cp.quantity + 0.0001)
                             - FLOOR(CAST(COALESCE(qi.qty_delivered, 0) AS DECIMAL(12,4)) * cp.quantity + 0.0001)
                           ) AS pendiente,
                           \'combo_componente\' AS tipo,
                           c.name AS combo_nombre
                    FROM quote_items qi
                    JOIN combos c ON c.id = qi.combo_id
                    JOIN combo_products cp ON cp.combo_id = qi.combo_id
                    JOIN products p ON p.id = cp.product_id
                    WHERE qi.quote_id = ?
                      AND qi.combo_id IS NOT NULL AND qi.combo_id > 0
                      AND GREATEST(
                            0,
                            FLOOR(CAST(qi.quantity AS DECIMAL(12,4)) * cp.quantity + 0.0001)
                            - FLOOR(CAST(COALESCE(qi.qty_delivered, 0) AS DECIMAL(12,4)) * cp.quantity + 0.0001)
                          ) > 0
                ) u
                ORDER BY CASE u.tipo WHEN \'combo_componente\' THEN 0 ELSE 1 END, u.combo_nombre, u.name',
                [$qid, $qid]
            );
        }
        $this->view('quotes/preview', [
            'title' => 'Presupuesto ' . $quote['quote_number'],
            'quote' => $quote,
            'clientBalance' => (int) ($quote['client_id'] ?? 0) > 0
                ? ClientReceivableSummary::hybridBalanceForClient($db, (int) $quote['client_id'])
                : 0.0,
            'items' => $items,
            'comboComponents' => $comboComponents,
            'pendingItems' => $pendingItems,
            'readonly' => false,
            'quoteAttachments' => $quoteAttachments,
            'invoiceAttachmentCount' => $invoiceAttachmentCount,
        ]);
    }

    public function apiItemsExplotados(string $id): void
    {
        $db = Database::getInstance();
        $quoteId = (int) $id;
        $exists = $db->fetch('SELECT id FROM quotes WHERE id = ?', [$quoteId]);
        if ($exists === null) {
            $this->json(['error' => 'No encontrado'], 404);
            return;
        }
        $lines = $db->fetchAll(
            'SELECT qi.*, p.code, p.name AS product_name, p.presentation, p.content, cmb.name AS combo_name
             FROM quote_items qi
             LEFT JOIN products p ON p.id = qi.product_id
             LEFT JOIN combos cmb ON cmb.id = qi.combo_id
             WHERE qi.quote_id = ?
             ORDER BY qi.sort_order, qi.id',
            [$quoteId]
        );
        $items = [];
        foreach ($lines as $qi) {
            $comboId = (int) ($qi['combo_id'] ?? 0);
            if ($comboId > 0) {
                $rows = $db->fetchAll(
                    'SELECT cp.id, cp.quantity AS qty_por_combo, p.id AS product_id, p.name AS producto_nombre,
                            p.presentation, p.content
                     FROM combo_products cp
                     JOIN products p ON p.id = cp.product_id
                     WHERE cp.combo_id = ?
                     ORDER BY cp.id ASC',
                    [$comboId]
                );
                $qtyCombo = (float) ($qi['quantity'] ?? 0);
                $qdelCombo = (float) ($qi['qty_delivered'] ?? 0);
                foreach ($rows as $cp) {
                    $qpc = max(1, (int) ($cp['qty_por_combo'] ?? 1));
                    $cantTotal = round($qtyCombo * $qpc, 2);
                    $qtyDel = $qtyCombo > 1e-9
                        ? round(($qdelCombo / $qtyCombo) * $qpc, 2)
                        : 0.0;
                    $pend = max(0.0, round($cantTotal - $qtyDel, 2));
                    $pendEntero = max(0, (int) floor($pend + 1e-9));
                    $pres = trim(trim((string) ($cp['presentation'] ?? '')) . ' ' . trim((string) ($cp['content'] ?? '')));
                    $items[] = [
                        'quote_item_id' => (int) ($qi['id'] ?? 0),
                        'tipo' => 'combo_componente',
                        'combo_nombre' => (string) ($qi['combo_name'] ?? ''),
                        'nombre' => (string) ($cp['producto_nombre'] ?? ''),
                        'presentacion' => trim($pres),
                        'cantidad_total' => $cantTotal,
                        'qty_delivered' => $qtyDel,
                        'pendiente' => $pend,
                        'pendiente_entero' => $pendEntero,
                        'combo_id' => $comboId,
                        'product_id' => (int) ($cp['product_id'] ?? 0),
                    ];
                }
                continue;
            }
            $pid = (int) ($qi['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qtyTotal = (float) ($qi['quantity'] ?? 0);
            $qtyDel = (float) ($qi['qty_delivered'] ?? 0);
            $pend = max(0.0, round($qtyTotal - $qtyDel, 2));
            $pendEntero = max(0, (int) floor($pend + 1e-9));
            $pres = trim(trim((string) ($qi['presentation'] ?? '')) . ' ' . trim((string) ($qi['content'] ?? '')));
            $nombre = trim((string) ($qi['code'] ?? '') . ' — ' . (string) ($qi['product_name'] ?? ''));
            $items[] = [
                'quote_item_id' => (int) ($qi['id'] ?? 0),
                'tipo' => 'producto',
                'nombre' => $nombre,
                'presentacion' => trim($pres),
                'cantidad_total' => round($qtyTotal, 2),
                'qty_delivered' => round($qtyDel, 2),
                'pendiente' => $pend,
                'pendiente_entero' => $pendEntero,
                'combo_id' => null,
                'product_id' => $pid,
            ];
        }
        $this->json(['items' => $items]);
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
        $allowed = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'delivered', 'partially_delivered'];
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
            if ($oldStatus === 'accepted' && $status !== 'accepted' && $status !== 'delivered' && $status !== 'partially_delivered') {
                QuoteDeliveryStock::releaseCommittedStock($db, (int) $id);
            }
            if ($oldStatus === 'partially_delivered' && $status !== 'partially_delivered' && $status !== 'delivered') {
                QuoteDeliveryStock::releaseRemainingCommittedStock($db, (int) $id);
            }
            if ($oldStatus === 'delivered' && $status !== 'delivered' && $deliveryApplied) {
                $revert = QuoteDeliveryStock::revertDeliveredStock((int) $id, $db);
                if (!$revert['success']) {
                    throw new \RuntimeException('No se pudo revertir stock delivered: ' . implode(' | ', $revert['errors']));
                }
                $extra['delivery_stock_applied'] = 0;
            }
            if ($status === 'accepted' && $oldStatus !== 'accepted') {
                QuoteDeliveryStock::commitStock($db, (int) $id);
            }
            if ($status === 'delivered' && $oldStatus !== 'delivered' && !$deliveryApplied) {
                if ($oldStatus === 'partially_delivered') {
                    QuoteDeliveryStock::markRemainingDeliveredFromPartial($db, (int) $id);
                } else {
                    QuoteDeliveryStock::markDelivered($db, (int) $id);
                }
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
                $clientId = (int) ($quote['client_id'] ?? 0);
                $amount = round((float) ($quote['total'] ?? 0), 2);
                if (!$existing) {
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
                } else {
                    // Si ya existe factura de CC para el presupuesto, sincronizar monto/cliente.
                    $db->update(
                        'account_transactions',
                        ['amount' => $amount, 'account_id' => $clientId],
                        'id = :id',
                        ['id' => (int) ($existing['id'] ?? 0)]
                    );
                }
                $this->recalculateClientBalance((int) ($quote['client_id'] ?? 0));
            }

            if ($hasAccountTable
                && in_array($oldStatus, ['accepted', 'delivered', 'partially_delivered'], true)
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

    public function partialDelivery(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos/' . $id);
        }
        $quoteId = (int) $id;
        $db = Database::getInstance();
        $quote = $db->fetch('SELECT id, status FROM quotes WHERE id = ?', [$quoteId]);
        if (!$quote) {
            flash('error', 'Presupuesto no encontrado.');
            redirect('/presupuestos');
            return;
        }
        $st = (string) ($quote['status'] ?? '');
        if (!in_array($st, ['accepted', 'partially_delivered'], true)) {
            flash('error', 'Solo se puede registrar entrega parcial con el presupuesto aceptado o en entrega parcial.');
            redirect('/presupuestos/' . $id);
            return;
        }
        $rawItems = $_POST['items'] ?? [];
        if (!is_array($rawItems)) {
            flash('error', 'Datos inválidos.');
            redirect('/presupuestos/' . $id);
            return;
        }
        if ($rawItems === []) {
            flash('info', 'No se registró entrega.');
            redirect('/presupuestos/' . $id);
            return;
        }
        try {
            $deliveredQtys = $this->convertExplodedPartialPostToDeliveredQtys($db, $quoteId, $rawItems);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('/presupuestos/' . $id);
            return;
        }
        if ($deliveredQtys === []) {
            flash('info', 'No se indicaron cantidades a entregar; no se registró entrega.');
            redirect('/presupuestos/' . $id);
            return;
        }
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            QuoteDeliveryStock::markPartialDelivery($db, $quoteId, $deliveredQtys);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo registrar la entrega parcial: ' . $e->getMessage());
            redirect('/presupuestos/' . $id);
            return;
        }
        flash('success', 'Entrega parcial registrada.');
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
            $qst = (string) ($quote['status'] ?? '');
            if ($qst === 'accepted') {
                QuoteDeliveryStock::releaseCommittedStock($db, $quoteId);
            } elseif ($qst === 'partially_delivered') {
                QuoteDeliveryStock::releaseRemainingCommittedStock($db, $quoteId);
                QuoteDeliveryStock::reversePartialDeliveriesPhysical($db, $quoteId);
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
                $existingQuote = $db->fetch('SELECT id, status, client_id, delivery_stock_applied FROM quotes WHERE id = ?', [$id]);
                if (!$existingQuote) {
                    $db->getPdo()->rollBack();
                    return ['error' => 'No encontrado.'];
                }
                $prevClientId = (int) ($existingQuote['client_id'] ?? 0);
                if (!$this->quoteIsEditable($existingQuote)) {
                    $db->getPdo()->rollBack();
                    return ['error' => 'No se puede editar un presupuesto en este estado.'];
                }
                if ((string) ($existingQuote['status'] ?? '') === 'delivered'
                    && (int) ($existingQuote['delivery_stock_applied'] ?? 0) === 1) {
                    $revert = QuoteDeliveryStock::revertDeliveredStock($id, $db);
                    if (!$revert['success']) {
                        $db->getPdo()->rollBack();
                        return ['error' => 'No se pudo revertir stock entregado: ' . implode(' | ', $revert['errors'])];
                    }
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
            $subtotalNetDiscountable = 0.0;
            $subtotalNetNoDiscount = 0.0;
            $totalWithIvaDiscountable = 0.0;
            $totalWithIvaNoDiscount = 0.0;
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
                    $subtotalNetNoDiscount += $lineSub;
                    $totalWithIvaNoDiscount += $lineSub;
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
                $lineSubNet = round($calcNet['precio_venta'] * $qty, 2);
                $subtotalNetDiscountable += $lineSubNet;
                $totalWithIvaDiscountable += $lineSub;
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

            $subtotalNet = round($subtotalNetDiscountable + $subtotalNetNoDiscount, 2);
            $totalWithIva = round($totalWithIvaDiscountable + $totalWithIvaNoDiscount, 2);
            $ivaAmount = $includeIva ? round($totalWithIva - $subtotalNet, 2) : 0.0;
            $baseTotal = $includeIva ? $totalWithIva : $subtotalNet;
            $baseDiscountable = $includeIva ? $totalWithIvaDiscountable : $subtotalNetDiscountable;

            if ($baseDiscountable <= 0.0) {
                $discountAmount = null;
                $discountPercentage = null;
            } elseif ($discountAmountInput !== null && $discountPercentage !== null) {
                // Ambos vinieron del POST — mantener porcentaje del frontend, acotar monto
                $discountAmount = max(0.0, min($baseDiscountable, round($discountAmountInput, 2)));
                // Mantener $discountPercentage tal cual vino del POST
            } elseif ($discountAmountInput !== null) {
                // Solo vino monto — calcular porcentaje desde monto
                $discountAmount = max(0.0, min($baseDiscountable, round($discountAmountInput, 2)));
                $discountPercentage = $baseDiscountable > 0 ? round(($discountAmount / $baseDiscountable) * 100, 2) : null;
            } elseif ($discountPercentage !== null) {
                // Solo vino porcentaje — calcular monto desde porcentaje
                $discountAmount = round($baseDiscountable * ($discountPercentage / 100), 2);
                $discountAmount = max(0.0, min($baseDiscountable, $discountAmount));
            } else {
                $discountAmount = null;
                $discountPercentage = null;
            }

            if (($discountAmount ?? 0.0) <= 0.0) {
                $discountAmount = null;
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
            $existingStatus = strtolower(trim((string) ($existingQuote['status'] ?? '')));
            if ($existingQuote !== null && in_array($existingStatus, ['accepted', 'partially_delivered', 'delivered'], true)) {
                $hasAcc = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
                if ($hasAcc) {
                    $invoiceTx = $db->fetch(
                        "SELECT id
                         FROM account_transactions
                         WHERE reference_type = 'quote'
                           AND reference_id = ?
                           AND transaction_type = 'invoice'
                           AND account_type = 'client'
                         LIMIT 1",
                        [(int) $id]
                    );
                    if ($invoiceTx === null) {
                        // Fallback para datos legados sin reference_type='quote'
                        $invoiceTx = $db->fetch(
                            "SELECT id
                             FROM account_transactions
                             WHERE reference_id = ?
                               AND transaction_type = 'invoice'
                               AND account_type = 'client'
                             ORDER BY id DESC
                             LIMIT 1",
                            [(int) $id]
                        );
                    }
                    if ($invoiceTx !== null) {
                        $db->update(
                            'account_transactions',
                            ['amount' => round($total, 2), 'account_id' => $clientId],
                            'id = :id',
                            ['id' => (int) ($invoiceTx['id'] ?? 0)]
                        );
                        $this->recalculateClientBalance($clientId);
                    }
                }
            }
            if ($existingQuote !== null && in_array($existingStatus, ['accepted', 'partially_delivered'], true)) {
                $this->removeQuoteFromDraftSupplierOrders($db, (int) $id);
            }
            if ($existingQuote !== null && $existingStatus === 'accepted') {
                QuoteDeliveryStock::commitStock($db, $id);
            }
            if ($existingQuote !== null && $existingStatus === 'delivered') {
                QuoteDeliveryStock::markDelivered($db, $id);
                $db->update('quotes', ['delivery_stock_applied' => 1], 'id = :id', ['id' => $id]);
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
        // Si el valor viene en formato JS estándar (ej: "4502.19", "10.00")
        // detectarlo por el patrón: dígitos, opcionalmente punto, opcionalmente decimales
        // sin comas — parsear directamente como float
        if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return round((float) $raw, 2);
        }
        // Si tiene comas (formato argentino "4.502,19" o "10,00"), usar parseArgentineAmount
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

    private function removeQuoteFromDraftSupplierOrders(Database $db, int $quoteId): void
    {
        if ($quoteId <= 0) {
            return;
        }
        $rows = $db->fetchAll(
            "SELECT id, included_quotes
             FROM seiq_orders
             WHERE status IN ('draft', 'sent')
               AND included_quotes IS NOT NULL
               AND included_quotes <> ''"
        );
        foreach ($rows as $row) {
            $orderId = (int) ($row['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $decoded = json_decode((string) ($row['included_quotes'] ?? ''), true);
            if (!is_array($decoded) || $decoded === []) {
                continue;
            }
            $updated = [];
            foreach ($decoded as $qid) {
                $qid = (int) $qid;
                if ($qid > 0 && $qid !== $quoteId) {
                    $updated[] = $qid;
                }
            }
            if (count($updated) === count($decoded)) {
                continue;
            }
            $db->update(
                'seiq_orders',
                ['included_quotes' => $updated === [] ? null : json_encode(array_values($updated), JSON_UNESCAPED_UNICODE)],
                'id = :id',
                ['id' => $orderId]
            );
        }
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

    /**
     * Componentes de combo por id de línea de presupuesto (solo líneas con combo_id).
     *
     * @param list<array<string,mixed>> $items
     * @return array<int, list<array<string,mixed>>>
     */
    private function comboComponentsMapForQuoteItems(Database $db, array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $cid = (int) ($item['combo_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $iid = (int) ($item['id'] ?? 0);
            if ($iid <= 0) {
                continue;
            }
            $map[$iid] = $db->fetchAll(
                'SELECT cp.quantity, p.name, p.presentation, p.content
                 FROM combo_products cp
                 JOIN products p ON p.id = cp.product_id
                 WHERE cp.combo_id = ?
                 ORDER BY cp.id ASC',
                [$cid]
            );
        }

        return $map;
    }

    /**
     * Convierte POST items[quote_item_id][product_id] (explosión) a cantidades por línea para markPartialDelivery.
     *
     * @param array<int|string, mixed> $rawItems
     * @return array<int, float> quote_item_id => cantidad a sumar en unidades de línea (combo o producto)
     */
    private function convertExplodedPartialPostToDeliveredQtys(Database $db, int $quoteId, array $rawItems): array
    {
        $nested = false;
        foreach ($rawItems as $v) {
            if (is_array($v)) {
                $nested = true;
                break;
            }
        }
        $lines = $db->fetchAll(
            'SELECT id, combo_id, product_id, quantity, qty_delivered FROM quote_items WHERE quote_id = ?',
            [$quoteId]
        );
        $lineById = [];
        foreach ($lines as $ln) {
            $lineById[(int) $ln['id']] = $ln;
        }
        if (!$nested) {
            $out = [];
            foreach ($rawItems as $key => $val) {
                $itemId = (int) $key;
                if ($itemId <= 0 || !isset($lineById[$itemId])) {
                    continue;
                }
                $addQty = (int) max(0, (int) floor((float) str_replace(',', '.', trim((string) $val)) + 1e-9));
                if ($addQty <= 0) {
                    continue;
                }
                $ln = $lineById[$itemId];
                $maxAdd = max(0, (int) floor((float) $ln['quantity'] - (float) ($ln['qty_delivered'] ?? 0) + 1e-9));
                if ($addQty > $maxAdd) {
                    throw new \InvalidArgumentException('Alguna cantidad supera lo pendiente de entregar en una línea.');
                }
                $out[$itemId] = (float) $addQty;
            }

            return $out;
        }
        $out = [];
        foreach ($rawItems as $itemKey => $inner) {
            if (!is_array($inner)) {
                continue;
            }
            $itemId = (int) $itemKey;
            if ($itemId <= 0 || !isset($lineById[$itemId])) {
                continue;
            }
            $line = $lineById[$itemId];
            $comboId = (int) ($line['combo_id'] ?? 0);
            if ($comboId <= 0) {
                $pidLine = (int) ($line['product_id'] ?? 0);
                $raw = null;
                if ($pidLine > 0) {
                    $raw = $inner[(string) $pidLine] ?? $inner[$pidLine] ?? null;
                }
                if ($raw === null) {
                    $raw = $inner['0'] ?? $inner[0] ?? null;
                }
                if ($raw === null) {
                    foreach ($inner as $v) {
                        if (!is_array($v)) {
                            $raw = $v;
                            break;
                        }
                    }
                }
                $addQty = (int) max(0, (int) floor((float) str_replace(',', '.', trim((string) ($raw ?? '0'))) + 1e-9));
                if ($addQty <= 0) {
                    continue;
                }
                $maxAdd = max(0, (int) floor((float) $line['quantity'] - (float) ($line['qty_delivered'] ?? 0) + 1e-9));
                if ($addQty > $maxAdd) {
                    throw new \InvalidArgumentException('Alguna cantidad supera lo pendiente de entregar en una línea.');
                }
                $out[$itemId] = (float) $addQty;
                continue;
            }
            $cps = $db->fetchAll(
                'SELECT product_id, quantity FROM combo_products WHERE combo_id = ? ORDER BY id ASC',
                [$comboId]
            );
            if ($cps === []) {
                continue;
            }
            $qtyLine = (float) ($line['quantity'] ?? 0);
            $qtyDelLine = (float) ($line['qty_delivered'] ?? 0);
            $maxComboAdd = max(0.0, round($qtyLine - $qtyDelLine, 2));
            $ratios = [];
            foreach ($cps as $cp) {
                $pid = (int) $cp['product_id'];
                $perCombo = max(1, (int) ($cp['quantity'] ?? 1));
                $rawSent = str_replace(',', '.', trim((string) ($inner[(string) $pid] ?? $inner[$pid] ?? '0')));
                $sent = (int) max(0, (int) floor((float) $rawSent + 1e-9));
                $ratios[] = $sent / $perCombo;
            }
            $qtyCombo = min($ratios);
            $qtyCombo = round($qtyCombo, 2);
            $qtyCombo = min($qtyCombo, $maxComboAdd);
            if ($qtyCombo <= 0) {
                continue;
            }
            $out[$itemId] = $qtyCombo;
        }

        return $out;
    }

    /** @param array<string,mixed> $quote @param list<array<string,mixed>> $items */
    private function renderQuotePdf(array $quote, array $items): string
    {
        $comboComponents = $this->comboComponentsMapForQuoteItems(Database::getInstance(), $items);
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
