<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteDeliveryStock;
use App\Helpers\SeiqOrderBuilder;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class SeiqOrderController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE (so.order_number LIKE ? OR s.name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*)
             FROM seiq_orders so
             LEFT JOIN suppliers s ON s.id = so.supplier_id
             {$where}",
            $params
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = $db->fetchAll(
            'SELECT so.*, s.name AS supplier_name, s.slug AS supplier_slug
             FROM seiq_orders so
             LEFT JOIN suppliers s ON s.id = so.supplier_id
             ' . $where . '
             ORDER BY so.created_at DESC
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        $this->view('pedido-seiq/index', [
            'title' => 'Pedidos a Proveedores',
            'orders' => $rows,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function generate(): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $built = SeiqOrderBuilder::buildFromDatabase($db);
        $bundle = $built['bundle'];
        if ($bundle === null) {
            $bundle = [
                'consolidated' => [],
                'total_products' => 0,
                'total_boxes' => 0,
            ];
        }
        /** @var array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int} $bundle */
        $supplierBundles = SeiqOrderBuilder::groupConsolidatedBySupplier($bundle['consolidated']);
        $this->view('pedido-seiq/generate', [
            'title' => 'Generar pedidos a proveedores',
            'acceptedQuotes' => $built['acceptedQuotes'],
            'bundle' => $bundle,
            'supplierBundles' => $supplierBundles,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedidos-proveedor/generar');
        }
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $built = SeiqOrderBuilder::buildFromDatabase($db);
        $notes = trim((string) $this->input('notes', ''));
        $manualBoxesInput = $_POST['boxes_to_order'] ?? [];
        $manualBoxes = is_array($manualBoxesInput) ? $manualBoxesInput : [];
        $bundle = $built['bundle'] ?? ['consolidated' => [], 'total_products' => 0, 'total_boxes' => 0];
        $manualBySupplier = $this->parseManualRowsBySupplier($db, $_POST['manual_lines'] ?? []);
        $supplierBundles = SeiqOrderBuilder::groupConsolidatedBySupplier($bundle['consolidated']);
        if ($supplierBundles === [] && $manualBySupplier === []) {
            flash('error', 'Agregá al menos un producto para generar el pedido.');
            redirect('/pedidos-proveedor/generar');
            return;
        }

        $db->getPdo()->beginTransaction();
        try {
            $lastOrderId = 0;
            $numbers = [];
            foreach ($supplierBundles as $entry) {
                $supplier = $entry['supplier'];
                $supplierId = (int) ($supplier['id'] ?? 0);
                $sb = $entry['bundle'];
                $rowsForOrder = [];
                $supplierTotalBoxes = 0;
                foreach ($sb['consolidated'] as $row) {
                    $productId = (int) ($row['product_id'] ?? 0);
                    if ($productId <= 0) {
                        continue;
                    }
                    $unitsPerBox = max(1, (int) ($row['units_per_box'] ?? 1));
                    $unitsToOrderAfterStock = max(0, (int) ($row['units_to_order_after_stock'] ?? $row['total_units_needed'] ?? 0));
                    $defaultBoxes = max(0, (int) ($row['boxes_to_order'] ?? 0));
                    $boxesToOrder = $this->resolveBoxesToOrder($manualBoxes[$productId] ?? null, $defaultBoxes);
                    $row['boxes_to_order'] = $boxesToOrder;
                    $row['units_remainder'] = max(0, ($boxesToOrder * $unitsPerBox) - $unitsToOrderAfterStock);
                    $row['origin'] = 'auto';
                    $rowsForOrder[] = $row;
                    $supplierTotalBoxes += $boxesToOrder;
                }
                foreach (($manualBySupplier[$supplierId] ?? []) as $manualRow) {
                    $rowsForOrder[] = $manualRow;
                    $supplierTotalBoxes += (int) ($manualRow['boxes_to_order'] ?? 0);
                }
                if ($rowsForOrder === [] || $supplierTotalBoxes <= 0) {
                    continue;
                }
                $includedQuoteIdsForOrder = [];
                foreach ($rowsForOrder as $row) {
                    if ((string) ($row['origin'] ?? 'auto') !== 'auto') {
                        continue;
                    }
                    if ((int) ($row['boxes_to_order'] ?? 0) <= 0) {
                        continue;
                    }
                    $fromQuotes = $row['from_quotes'] ?? [];
                    if (!is_array($fromQuotes)) {
                        continue;
                    }
                    foreach (array_keys($fromQuotes) as $qid) {
                        $qid = (int) $qid;
                        if ($qid > 0) {
                            $includedQuoteIdsForOrder[$qid] = true;
                        }
                    }
                }
                $includedQuoteIds = array_keys($includedQuoteIdsForOrder);
                sort($includedQuoteIds);
                $includedJson = $includedQuoteIds === []
                    ? null
                    : json_encode(array_values($includedQuoteIds), JSON_THROW_ON_ERROR);
                $orderNumber = $this->nextOrderNumber($db, (string) ($supplier['slug'] ?? ''));
                $orderId = $db->insert('seiq_orders', [
                    'supplier_id' => $supplierId,
                    'order_number' => $orderNumber,
                    'notes' => $notes !== '' ? $notes : null,
                    'included_quotes' => $includedJson,
                    'total_products' => count($rowsForOrder),
                    'total_boxes' => $supplierTotalBoxes,
                    'status' => 'draft',
                    'sent_at' => null,
                    'received_at' => null,
                    'pdf_path' => null,
                ]);

                $sort = 0;
                foreach ($rowsForOrder as $row) {
                    $db->insert('seiq_order_items', [
                        'seiq_order_id' => $orderId,
                        'product_id' => (int) $row['product_id'],
                        'qty_units_sold' => (int) $row['qty_units_sold'],
                        'qty_boxes_sold' => (int) $row['qty_boxes_sold'],
                        'total_units_needed' => (int) $row['total_units_needed'],
                        'units_per_box' => (int) $row['units_per_box'],
                        'boxes_to_order' => (int) $row['boxes_to_order'],
                        'units_remainder' => (int) $row['units_remainder'],
                        'origin' => (string) ($row['origin'] ?? 'auto'),
                        'sort_order' => $sort++,
                    ]);
                }
                unset($manualBySupplier[$supplierId]);
                $pdfFile = $this->renderSeiqOrderPdf($orderId, $orderNumber, $rowsForOrder, $supplier);
                $db->update('seiq_orders', ['pdf_path' => $pdfFile], 'id = :id', ['id' => $orderId]);
                $lastOrderId = $orderId;
                $numbers[] = $orderNumber;
            }
            foreach ($manualBySupplier as $supplierId => $manualRows) {
                if (!is_int($supplierId) || $supplierId <= 0 || $manualRows === []) {
                    continue;
                }
                $supplier = $db->fetch('SELECT id, name, slug, cliente_id, cliente_nombre, condicion_pago, observaciones FROM suppliers WHERE id = ?', [$supplierId]);
                if (!$supplier) {
                    continue;
                }
                $supplierTotalBoxes = (int) array_sum(array_map(static fn (array $r): int => (int) ($r['boxes_to_order'] ?? 0), $manualRows));
                if ($supplierTotalBoxes <= 0) {
                    continue;
                }
                $orderNumber = $this->nextOrderNumber($db, (string) ($supplier['slug'] ?? ''));
                $orderId = $db->insert('seiq_orders', [
                    'supplier_id' => $supplierId,
                    'order_number' => $orderNumber,
                    'notes' => $notes !== '' ? $notes : null,
                    'included_quotes' => null,
                    'total_products' => count($manualRows),
                    'total_boxes' => $supplierTotalBoxes,
                    'status' => 'draft',
                    'sent_at' => null,
                    'received_at' => null,
                    'pdf_path' => null,
                ]);
                $sort = 0;
                foreach ($manualRows as $row) {
                    $db->insert('seiq_order_items', [
                        'seiq_order_id' => $orderId,
                        'product_id' => (int) $row['product_id'],
                        'qty_units_sold' => 0,
                        'qty_boxes_sold' => 0,
                        'total_units_needed' => (int) $row['total_units_needed'],
                        'units_per_box' => (int) $row['units_per_box'],
                        'boxes_to_order' => (int) $row['boxes_to_order'],
                        'units_remainder' => (int) $row['units_remainder'],
                        'origin' => 'manual',
                        'sort_order' => $sort++,
                    ]);
                }
                $pdfFile = $this->renderSeiqOrderPdf($orderId, $orderNumber, $manualRows, $supplier);
                $db->update('seiq_orders', ['pdf_path' => $pdfFile], 'id = :id', ['id' => $orderId]);
                $lastOrderId = $orderId;
                $numbers[] = $orderNumber;
            }
            if ($numbers === []) {
                throw new \RuntimeException('Agregá al menos un producto para generar el pedido.');
            }

            $db->getPdo()->commit();
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'Error al guardar: ' . $e->getMessage());
            redirect('/pedidos-proveedor/generar');
            return;
        }

        flash('success', 'Pedidos generados: ' . implode(', ', $numbers));
        redirect('/pedidos-proveedor/' . $lastOrderId);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $order = $db->fetch(
            'SELECT so.*, s.name AS supplier_name, s.slug AS supplier_slug,
                    s.cliente_id, s.cliente_nombre, s.condicion_pago, s.observaciones
             FROM seiq_orders so
             LEFT JOIN suppliers s ON s.id = so.supplier_id
             WHERE so.id = ?',
            [(int) $id]
        );
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-proveedor');
            return;
        }
        $items = $db->fetchAll(
            'SELECT soi.*, p.code, p.name AS product_name, p.presentation, p.content,
                    p.sale_unit_label, p.sale_unit_description, p.precio_lista_caja, p.precio_lista_unitario,
                    c.name AS category_name, c.slug AS category_slug, pc.name AS parent_category_name
                    , pc.slug AS parent_category_slug
             FROM seiq_order_items soi
             JOIN products p ON p.id = soi.product_id
             JOIN categories c ON p.category_id = c.id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE soi.seiq_order_id = ?
             ORDER BY soi.sort_order, p.code',
            [(int) $id]
        );

        $includedIds = [];
        if (!empty($order['included_quotes'])) {
            $decoded = json_decode((string) $order['included_quotes'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $qid) {
                    $includedIds[] = (int) $qid;
                }
            }
        }
        $includedQuotes = [];
        if ($includedIds !== []) {
            $ph = implode(',', array_fill(0, count($includedIds), '?'));
            $includedQuotes = $db->fetchAll(
                "SELECT q.*, c.name AS client_name FROM quotes q
                 LEFT JOIN clients c ON c.id = q.client_id
                 WHERE q.id IN ({$ph}) ORDER BY q.created_at",
                $includedIds
            );
        }

        $waMessage = $this->buildWhatsAppMessage($order, $items);
        $suggestedReceivedAmount = 0.0;
        foreach ($items as $it) {
            $suggestedReceivedAmount += $this->orderItemCostPerBox($it) * (int) ($it['boxes_to_order'] ?? 0);
        }
        $existingInvoice = $db->fetch(
            "SELECT amount FROM account_transactions
             WHERE reference_type = 'seiq_order' AND reference_id = ? AND transaction_type = 'invoice'
             LIMIT 1",
            [(int) $id]
        );
        if ($existingInvoice && (float) ($existingInvoice['amount'] ?? 0) > 0) {
            $suggestedReceivedAmount = (float) $existingInvoice['amount'];
        }

        $this->view('pedido-seiq/show', [
            'title' => 'Pedido ' . $order['order_number'],
            'order' => $order,
            'items' => $items,
            'includedQuotes' => $includedQuotes,
            'waMessage' => $waMessage,
            'suggestedReceivedAmount' => round($suggestedReceivedAmount, 2),
        ]);
    }

    public function downloadPdf(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $order = $db->fetch(
            'SELECT so.*, s.name AS supplier_name, s.slug AS supplier_slug,
                    s.cliente_id, s.cliente_nombre, s.condicion_pago, s.observaciones
             FROM seiq_orders so
             LEFT JOIN suppliers s ON s.id = so.supplier_id
             WHERE so.id = ?',
            [(int) $id]
        );
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-proveedor');
            return;
        }
        $items = $db->fetchAll(
            'SELECT soi.*, p.code, p.name AS product_name, p.presentation, p.content,
                    p.sale_unit_label, p.sale_unit_description
             FROM seiq_order_items soi
             JOIN products p ON p.id = soi.product_id
             WHERE soi.seiq_order_id = ?
             ORDER BY p.code',
            [(int) $id]
        );
        $lines = [];
        foreach ($items as $it) {
            $lines[] = [
                'code' => $it['code'],
                'name' => $it['product_name'],
                'presentation' => $it['presentation'],
                'content' => $it['content'],
                'sale_unit_description' => $it['sale_unit_description'],
                'boxes_to_order' => (int) $it['boxes_to_order'],
                'origin' => (string) ($it['origin'] ?? 'auto'),
            ];
        }
        $lines = $this->filterSupplierOrderLinesForSupplierFacing($lines);
        $orderForPdf = [
            'order_number' => (string) $order['order_number'],
            'created_at' => (string) $order['created_at'],
            'total_boxes' => (int) array_sum(array_column($lines, 'boxes_to_order')),
            'supplier_name' => (string) ($order['supplier_name'] ?? ''),
            'cliente_id' => (string) ($order['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($order['cliente_nombre'] ?? ''),
            'condicion_pago' => (string) ($order['condicion_pago'] ?? ''),
            'observaciones' => (string) ($order['observaciones'] ?? ''),
        ];
        $order = $orderForPdf;
        ob_start();
        require APP_PATH . '/Views/pdf/seiq-order.php';
        $html = ob_get_clean();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $order['order_number'] . '.pdf"');
        echo $dompdf->output();
        exit;
    }

    public function downloadPdfWithPrices(string $id): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $order = $db->fetch(
            'SELECT so.*, s.name AS supplier_name, s.slug AS supplier_slug,
                    s.cliente_id, s.cliente_nombre, s.condicion_pago, s.observaciones
             FROM seiq_orders so
             LEFT JOIN suppliers s ON s.id = so.supplier_id
             WHERE so.id = ?',
            [(int) $id]
        );
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-proveedor');
            return;
        }
        $items = $db->fetchAll(
            'SELECT soi.*, p.code, p.name AS product_name, p.presentation, p.content,
                    p.sale_unit_label, p.sale_unit_description, p.precio_lista_caja, p.precio_lista_unitario,
                    p.discount_override, c.default_discount, pc.default_discount AS parent_discount,
                    c.slug AS category_slug, pc.slug AS parent_category_slug
             FROM seiq_order_items soi
             JOIN products p ON p.id = soi.product_id
             JOIN categories c ON p.category_id = c.id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE soi.seiq_order_id = ?
             ORDER BY p.code',
            [(int) $id]
        );
        $lines = [];
        $totalAmount = 0.0;
        foreach ($items as $it) {
            $boxes = (int) ($it['boxes_to_order'] ?? 0);
            if ($boxes <= 0) {
                continue;
            }
            $pricePerBox = $this->orderItemCostPerBox($it);
            $lineTotal = round($pricePerBox * $boxes, 2);
            $totalAmount += $lineTotal;
            $lines[] = [
                'code' => $it['code'],
                'name' => $it['product_name'],
                'presentation' => $it['presentation'],
                'content' => $it['content'],
                'sale_unit_description' => $it['sale_unit_description'],
                'boxes_to_order' => $boxes,
                'price_per_box' => round($pricePerBox, 2),
                'line_total' => $lineTotal,
                'origin' => (string) ($it['origin'] ?? 'auto'),
            ];
        }
        $orderForPdf = [
            'order_number' => (string) $order['order_number'],
            'created_at' => (string) $order['created_at'],
            'total_boxes' => (int) array_sum(array_column($lines, 'boxes_to_order')),
            'supplier_name' => (string) ($order['supplier_name'] ?? ''),
            'cliente_id' => (string) ($order['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($order['cliente_nombre'] ?? ''),
            'condicion_pago' => (string) ($order['condicion_pago'] ?? ''),
            'observaciones' => (string) ($order['observaciones'] ?? ''),
            'total_amount' => round($totalAmount, 2),
        ];
        $order = $orderForPdf;
        ob_start();
        require APP_PATH . '/Views/pdf/seiq-order-prices.php';
        $html = ob_get_clean();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $order['order_number'] . '-precios.pdf"');
        echo $dompdf->output();
        exit;
    }

    public function changeStatus(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $status = (string) $this->input('status', '');
        if (!in_array($status, ['draft', 'sent', 'received'], true)) {
            flash('error', 'Estado inválido.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $orderId = (int) $id;
        $order = $db->fetch('SELECT * FROM seiq_orders WHERE id = ?', [$orderId]);
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-proveedor');
            return;
        }
        $prevStatus = (string) ($order['status'] ?? '');
        $receiptApplied = (int) ($order['receipt_stock_applied'] ?? 0) === 1;

        $data = ['status' => $status];
        if ($status === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'received') {
            $manualAmount = parseArgentineAmount((string) $this->input('received_amount', '0'));
            if ($manualAmount <= 0) {
                flash('error', 'Para recibir el pedido debés ingresar el monto del remito/factura.');
                redirect('/pedidos-proveedor/' . $id);
                return;
            }
            $data['received_at'] = date('Y-m-d H:i:s');
        }

        $pdo = $db->getPdo();
        $stockMessage = '';
        $pdo->beginTransaction();
        try {
            if ($prevStatus === 'received' && $status !== 'received' && $receiptApplied) {
                $this->applySupplierOrderStockDelta($db, $orderId, -1);
                $data['receipt_stock_applied'] = 0;
                $stockMessage = ' Stock de productos ajustado (se revirtió la recepción).';
            }
            if ($status === 'received' && $prevStatus !== 'received' && !$receiptApplied) {
                $this->applySupplierOrderStockDelta($db, $orderId, 1);
                $data['receipt_stock_applied'] = 1;
                $stockMessage = ' Stock de productos actualizado según las cantidades del pedido.';
            }

            $db->update('seiq_orders', $data, 'id = :id', ['id' => $orderId]);

            if ($status === 'received') {
                $this->registerSupplierInvoiceOnReceive($db, $orderId);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo actualizar el estado: ' . $e->getMessage());
            redirect('/pedidos-proveedor/' . $id);
            return;
        }

        flash('success', 'Estado actualizado.' . $stockMessage);
        redirect('/pedidos-proveedor/' . $id);
    }

    public function markQuotesDelivered(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $order = $db->fetch('SELECT * FROM seiq_orders WHERE id = ?', [(int) $id]);
        if (!$order || empty($order['included_quotes'])) {
            flash('error', 'No hay presupuestos asociados.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $decoded = json_decode((string) $order['included_quotes'], true);
        if (!is_array($decoded) || $decoded === []) {
            flash('error', 'Lista de presupuestos vacía.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $ids = array_map(static fn ($x) => (int) $x, $decoded);
        $ids = array_filter($ids, static fn ($x) => $x > 0);
        if ($ids === []) {
            flash('error', 'IDs inválidos.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            foreach ($ids as $qid) {
                $q = $db->fetch('SELECT id, status, delivery_stock_applied FROM quotes WHERE id = ?', [$qid]);
                if (!$q) {
                    continue;
                }
                if ((string) ($q['status'] ?? '') === 'delivered') {
                    continue;
                }
                if ((int) ($q['delivery_stock_applied'] ?? 0) === 1) {
                    continue;
                }
                QuoteDeliveryStock::markDelivered($db, $qid);
                $db->update(
                    'quotes',
                    ['status' => 'delivered', 'delivery_stock_applied' => 1],
                    'id = :id',
                    ['id' => $qid]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo marcar entregados: ' . $e->getMessage());
            redirect('/pedidos-proveedor/' . $id);
            return;
        }

        flash('success', 'Presupuestos marcados como entregados (stock descontado).');
        redirect('/pedidos-proveedor/' . $id);
    }

    private function nextOrderNumber(Database $db, string $supplierSlug = ''): string
    {
        $year = date('Y');
        $prefix = $supplierSlug === 'higienik' ? 'PH' : 'PS';
        $last = $db->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(order_number, 9) AS UNSIGNED))
             FROM seiq_orders
             WHERE order_number LIKE ?",
            [$prefix . '-' . $year . '-%']
        );
        $next = (int) ($last ?? 0) + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $next);
    }

    private function resolveBoxesToOrder(mixed $rawValue, int $default): int
    {
        if ($rawValue === null || $rawValue === '') {
            return max(0, $default);
        }
        $parsed = filter_var($rawValue, FILTER_VALIDATE_INT);
        if ($parsed === false) {
            return max(0, $default);
        }

        return max(0, (int) $parsed);
    }

    private function ensureSeiqSchema(Database $db): bool
    {
        try {
            $hasOrders = (bool) $db->fetchColumn("SHOW TABLES LIKE 'seiq_orders'");
            $hasItems = (bool) $db->fetchColumn("SHOW TABLES LIKE 'seiq_order_items'");
            $hasSuppliers = (bool) $db->fetchColumn("SHOW TABLES LIKE 'suppliers'");
            if ($hasOrders && $hasItems && $hasSuppliers) {
                $this->ensureSeiqOrderItemsOriginColumn($db);
                return true;
            }
        } catch (\Throwable) {
            // Continúa al mismo manejo de error.
        }

        flash('error', 'Falta migrar base de datos para pedidos de proveedores. Ejecutá install.php o la migración multi-proveedor en producción.');
        redirect('/');
        return false;
    }

    private function ensureSeiqOrderItemsOriginColumn(Database $db): void
    {
        try {
            $exists = (bool) $db->fetchColumn(
                "SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = 'seiq_order_items'
                   AND column_name = 'origin'
                 LIMIT 1"
            );
            if ($exists) {
                return;
            }
            $db->query("ALTER TABLE seiq_order_items ADD COLUMN origin ENUM('auto','manual') NOT NULL DEFAULT 'auto' AFTER units_remainder");
        } catch (\Throwable) {
            // No bloquear el flujo; en caso de falla se usará default de inserción previa.
        }
    }

    /**
     * @param mixed $rawLines
     * @return array<int, list<array<string,mixed>>>
     */
    private function parseManualRowsBySupplier(Database $db, mixed $rawLines): array
    {
        if (!is_array($rawLines) || $rawLines === []) {
            return [];
        }
        $byProduct = [];
        foreach ($rawLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            $boxes = max(0, (int) ($line['boxes_to_order'] ?? 0));
            if ($productId <= 0 || $boxes <= 0) {
                continue;
            }
            $byProduct[$productId] = ($byProduct[$productId] ?? 0) + $boxes;
        }
        if ($byProduct === []) {
            return [];
        }
        $ids = array_keys($byProduct);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->fetchAll(
            "SELECT p.id, p.code, p.name, p.units_per_box, p.content, p.sale_unit_label, p.presentation, p.sale_unit_description,
                    c.name AS category_name, c.slug AS category_slug, pc.name AS parent_category_name,
                    COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id, s.name AS supplier_name, s.slug AS supplier_slug
             FROM products p
             JOIN categories c ON p.category_id = c.id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE p.id IN ({$ph})",
            $ids
        );
        $metaByProduct = [];
        foreach ($rows as $row) {
            $metaByProduct[(int) ($row['id'] ?? 0)] = $row;
        }
        $out = [];
        foreach ($byProduct as $productId => $boxes) {
            $meta = $metaByProduct[$productId] ?? null;
            if ($meta === null) {
                continue;
            }
            $supplierId = (int) ($meta['supplier_id'] ?? 0);
            if ($supplierId <= 0) {
                continue;
            }
            $unitsPerBox = max(1, (int) ($meta['units_per_box'] ?? 1));
            $out[$supplierId][] = [
                'product_id' => $productId,
                'code' => (string) ($meta['code'] ?? ''),
                'name' => (string) ($meta['name'] ?? ''),
                'content' => (string) ($meta['content'] ?? ''),
                'presentation' => (string) ($meta['presentation'] ?? ''),
                'sale_unit_label' => (string) ($meta['sale_unit_label'] ?? ''),
                'sale_unit_description' => (string) ($meta['sale_unit_description'] ?? ''),
                'qty_units_sold' => 0,
                'qty_boxes_sold' => 0,
                'total_units_needed' => $boxes * $unitsPerBox,
                'units_per_box' => $unitsPerBox,
                'boxes_to_order' => $boxes,
                'units_remainder' => 0,
                'origin' => 'manual',
                'category_name' => (string) ($meta['category_name'] ?? ''),
                'parent_category_name' => (string) ($meta['parent_category_name'] ?? ''),
                'supplier_id' => $supplierId,
                'supplier_name' => (string) ($meta['supplier_name'] ?? ''),
                'supplier_slug' => (string) ($meta['supplier_slug'] ?? ''),
            ];
        }

        return $out;
    }

    private function registerSupplierInvoiceOnReceive(Database $db, int $orderId): void
    {
        try {
            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if (!$hasAccountTable) {
                return;
            }
            $existing = $db->fetch(
                "SELECT id FROM account_transactions
                 WHERE reference_type = 'seiq_order' AND reference_id = ? AND transaction_type = 'invoice'
                 LIMIT 1",
                [$orderId]
            );
            $order = $db->fetch(
                'SELECT so.order_number, so.supplier_id, s.slug AS supplier_slug
                 FROM seiq_orders so
                 LEFT JOIN suppliers s ON s.id = so.supplier_id
                 WHERE so.id = ?',
                [$orderId]
            );
            if (!$order) {
                return;
            }
            $supplierId = (int) ($order['supplier_id'] ?? 0);
            $manualAmount = parseArgentineAmount((string) $this->input('received_amount', '0'));
            $amount = $manualAmount > 0 ? round($manualAmount, 2) : $this->calculateSeiqOrderTotal($orderId);
            $receivedDate = (string) $this->input('received_date', date('Y-m-d'));
            if ($amount <= 0) {
                return;
            }
            $description = 'Pedido ' . (string) ($order['order_number'] ?? ('#' . $orderId));
            if ($existing) {
                $db->update('account_transactions', [
                    'amount' => $amount,
                    'description' => $description,
                    'transaction_date' => $receivedDate,
                ], 'id = :id', ['id' => (int) $existing['id']]);
                return;
            }

            $db->insert('account_transactions', [
                'account_type' => 'supplier',
                'account_id' => $supplierId,
                'transaction_type' => 'invoice',
                'reference_type' => 'seiq_order',
                'reference_id' => $orderId,
                'amount' => $amount,
                'description' => $description,
                'transaction_date' => $receivedDate,
            ]);
        } catch (\Throwable) {
            // No bloquea actualización de estado por ausencia de esquema/migración.
        }
    }

    private function calculateSeiqOrderTotal(int $orderId): float
    {
        $db = Database::getInstance();
        $items = $db->fetchAll(
            "SELECT soi.boxes_to_order, soi.units_per_box, p.precio_lista_caja, p.precio_lista_unitario,
                    p.discount_override, p.markup_override,
                    c.default_discount, c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    c.slug AS category_slug, pc.slug AS parent_category_slug
             FROM seiq_order_items soi
             JOIN products p ON soi.product_id = p.id
             JOIN categories c ON p.category_id = c.id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE soi.seiq_order_id = ?",
            [$orderId]
        );
        $total = 0.0;
        foreach ($items as $item) {
            $boxes = (int) ($item['boxes_to_order'] ?? 0);
            if ($boxes <= 0) {
                continue;
            }
            $costPerBox = $this->orderItemCostPerBox($item);
            $total += $costPerBox * $boxes;
        }
        return round($total, 2);
    }

    /** @param array<string,mixed> $item */
    private function orderItemPricePerBox(array $item): float
    {
        $slug = (string) ($item['parent_slug'] ?? $item['parent_category_slug'] ?? $item['category_slug'] ?? '');
        if ($slug === 'aerosoles') {
            $units = (int) ($item['units_per_box'] ?? 12);
            if ($units <= 0) {
                $units = 12;
            }
            return round((float) ($item['precio_lista_unitario'] ?? 0) * $units, 2);
        }
        $priceBox = (float) ($item['precio_lista_caja'] ?? 0);
        if ($priceBox > 0) {
            return round($priceBox, 2);
        }

        return round((float) ($item['precio_lista_unitario'] ?? 0), 2);
    }

    /** @param array<string,mixed> $item */
    private function orderItemCostPerBox(array $item): float
    {
        $slug = (string) ($item['parent_slug'] ?? $item['parent_category_slug'] ?? $item['category_slug'] ?? '');
        if ($slug === 'aerosoles') {
            $units = (int) ($item['units_per_box'] ?? 12);
            if ($units <= 0) {
                $units = 12;
            }
            $listaSeiq = (float) ($item['precio_lista_unitario'] ?? 0) * $units;
        } else {
            $listaSeiq = (float) ($item['precio_lista_caja'] ?? 0);
            if ($listaSeiq <= 0) {
                $listaSeiq = (float) ($item['precio_lista_unitario'] ?? 0);
            }
        }
        $calc = PricingEngine::calculateWithListaSeiq($listaSeiq, $item);

        return round((float) ($calc['costo'] ?? 0), 2);
    }

    /**
     * @param list<array<string,mixed>> $consolidated
     */
    private function renderSeiqOrderPdf(int $orderId, string $orderNumber, array $consolidated, array $supplier = []): string
    {
        $lines = [];
        foreach ($consolidated as $row) {
            if ((int) ($row['boxes_to_order'] ?? 0) <= 0) {
                continue;
            }
            $lines[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'presentation' => $row['presentation'],
                'content' => $row['content'],
                'sale_unit_description' => $row['sale_unit_description'],
                'boxes_to_order' => (int) $row['boxes_to_order'],
                'origin' => (string) ($row['origin'] ?? 'auto'),
            ];
        }
        usort($lines, static fn (array $a, array $b) => strcmp((string) $a['code'], (string) $b['code']));
        $order = [
            'order_number' => $orderNumber,
            'created_at' => date('Y-m-d H:i:s'),
            'total_boxes' => (int) array_sum(array_column($lines, 'boxes_to_order')),
            'supplier_name' => (string) ($supplier['name'] ?? ''),
            'cliente_id' => (string) ($supplier['cliente_id'] ?? ''),
            'cliente_nombre' => (string) ($supplier['cliente_nombre'] ?? ''),
            'condicion_pago' => (string) ($supplier['condicion_pago'] ?? ''),
            'observaciones' => (string) ($supplier['observaciones'] ?? ''),
        ];
        ob_start();
        require APP_PATH . '/Views/pdf/seiq-order.php';
        $html = ob_get_clean();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $file = 'seiq-order-' . $orderId . '-' . time() . '.pdf';
        $dir = STORAGE_PATH . '/pdfs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/' . $file, $dompdf->output());

        return $file;
    }

    /**
     * Suma o resta stock según ítems del pedido a proveedor (cajas × unidades por caja).
     *
     * @param 1|-1 $sign 1 al recibir mercadería, -1 al revertir si se saca el estado «received»
     */
    private function applySupplierOrderStockDelta(Database $db, int $orderId, int $sign): void
    {
        if ($sign !== 1 && $sign !== -1) {
            return;
        }
        $items = $db->fetchAll(
            'SELECT product_id, boxes_to_order, units_per_box FROM seiq_order_items WHERE seiq_order_id = ?',
            [$orderId]
        );
        foreach ($items as $it) {
            $pid = (int) ($it['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $boxes = max(0, (int) ($it['boxes_to_order'] ?? 0));
            $upb = max(1, (int) ($it['units_per_box'] ?? 1));
            $delta = $sign * $boxes * $upb;
            if ($delta === 0) {
                continue;
            }
            $db->query(
                'UPDATE products SET stock_units = GREATEST(0, stock_units + :delta) WHERE id = :pid',
                ['delta' => $delta, 'pid' => $pid]
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $lines Filas con clave boxes_to_order (p. ej. PDF nota de pedido).
     * @return list<array<string,mixed>>
     */
    private function filterSupplierOrderLinesForSupplierFacing(array $lines): array
    {
        return array_values(array_filter(
            $lines,
            static fn (array $l): bool => (int) ($l['boxes_to_order'] ?? 0) > 0
        ));
    }

    private function buildWhatsAppMessage(array $order, array $items): string
    {
        $items = array_values(array_filter(
            $items,
            static fn (array $it): bool => (int) ($it['boxes_to_order'] ?? 0) > 0
        ));
        $totalBoxes = (int) array_sum(array_map(
            static fn (array $it): int => (int) ($it['boxes_to_order'] ?? 0),
            $items
        ));

        $lines = [];
        $lines[] = '*Pedido LIMPIA OESTE*';
        $lines[] = 'N°: ' . ($order['order_number'] ?? '');
        $created = isset($order['created_at']) ? strtotime((string) $order['created_at']) : false;
        $lines[] = 'Fecha: ' . ($created ? date('d/m/Y', $created) : date('d/m/Y'));
        $cid = (string) ($order['cliente_id'] ?? '');
        $nom = (string) ($order['cliente_nombre'] ?? '');
        $supplierName = (string) ($order['supplier_name'] ?? 'PROVEEDOR');
        $lines[] = 'Proveedor: ' . $supplierName;
        $lines[] = 'Cliente: ' . $cid . ' - ' . $nom;
        $lines[] = '';
        foreach ($items as $it) {
            $lines[] = '• ' . (int) $it['boxes_to_order'] . 'x ' . ($it['code'] ?? '') . ' - ' . ($it['product_name'] ?? '');
        }
        $lines[] = '';
        $lines[] = '*Total: ' . $totalBoxes . ' cajas/bultos*';
        $lines[] = 'Condición: ' . ((string) ($order['condicion_pago'] ?? ''));

        return implode("\n", $lines);
    }
}
