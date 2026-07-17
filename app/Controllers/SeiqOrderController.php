<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteStatusTransitions;
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
        $acceptedQuotes = $built['acceptedQuotes'];
        $quoteIds = array_map(static fn ($q) => (int) $q['id'], $acceptedQuotes);
        $manualBySupplier = $this->parseManualRowsBySupplier($db, $_POST['manual_lines'] ?? []);
        $includedJson = json_encode($quoteIds, JSON_THROW_ON_ERROR);
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
                    if ((int) ($row['boxes_to_order'] ?? 0) <= 0) {
                        continue;
                    }
                    $db->insert('seiq_order_items', [
                        'seiq_order_id' => $orderId,
                        'product_id' => (int) $row['product_id'],
                        'qty_units_sold' => (int) $row['qty_units_sold'],
                        'qty_boxes_sold' => (int) $row['qty_boxes_sold'],
                        'total_units_needed' => (int) $row['total_units_needed'],
                        'units_per_box' => (int) $row['units_per_box'],
                        'boxes_to_order' => (int) $row['boxes_to_order'],
                        'units_remainder' => (int) $row['units_remainder'],
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
                    'included_quotes' => $includedJson,
                    'total_products' => count($manualRows),
                    'total_boxes' => $supplierTotalBoxes,
                    'status' => 'draft',
                    'sent_at' => null,
                    'received_at' => null,
                    'pdf_path' => null,
                ]);
                $sort = 0;
                foreach ($manualRows as $row) {
                    if ((int) ($row['boxes_to_order'] ?? 0) <= 0) {
                        continue;
                    }
                    $db->insert('seiq_order_items', [
                        'seiq_order_id' => $orderId,
                        'product_id' => (int) $row['product_id'],
                        'qty_units_sold' => 0,
                        'qty_boxes_sold' => 0,
                        'total_units_needed' => (int) $row['total_units_needed'],
                        'units_per_box' => (int) $row['units_per_box'],
                        'boxes_to_order' => (int) $row['boxes_to_order'],
                        'units_remainder' => (int) $row['units_remainder'],
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

    public function createFromSuggestion(): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'Token inválido.'], 403);
            return;
        }
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            $this->json(['success' => false, 'error' => 'Tablas de pedidos a proveedor no disponibles.'], 422);
            return;
        }

        $raw = (string) file_get_contents('php://input');
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->json(['success' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }
        if (!is_array($payload)) {
            $this->json(['success' => false, 'error' => 'JSON inválido.'], 400);
            return;
        }

        $itemsInput = $payload['items'] ?? [];
        if (!is_array($itemsInput) || $itemsInput === []) {
            $this->json(['success' => false, 'error' => 'Seleccioná al menos un producto.'], 400);
            return;
        }

        $manualLines = [];
        foreach ($itemsInput as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = (int) ($item['product_id'] ?? 0);
            $boxes = max(0, (int) ($item['boxes'] ?? 0));
            if ($productId <= 0 || $boxes <= 0) {
                continue;
            }
            $manualLines[] = [
                'product_id' => $productId,
                'boxes' => $boxes,
            ];
        }
        if ($manualLines === []) {
            $this->json(['success' => false, 'error' => 'Seleccioná al menos un producto con cajas a pedir.'], 400);
            return;
        }

        $manualBySupplier = $this->parseManualRowsBySupplier($db, $manualLines);
        if ($manualBySupplier === []) {
            $this->json(['success' => false, 'error' => 'No se pudieron resolver los productos o proveedores.'], 400);
            return;
        }

        $includedJson = '[]';
        $db->getPdo()->beginTransaction();
        try {
            $createdOrders = [];
            foreach ($manualBySupplier as $supplierId => $manualRows) {
                if (!is_int($supplierId) || $supplierId <= 0 || $manualRows === []) {
                    continue;
                }
                $supplier = $db->fetch(
                    'SELECT id, name, slug, cliente_id, cliente_nombre, condicion_pago, observaciones FROM suppliers WHERE id = ?',
                    [$supplierId]
                );
                if (!$supplier) {
                    continue;
                }
                $supplierTotalBoxes = (int) array_sum(array_map(
                    static fn (array $r): int => (int) ($r['boxes_to_order'] ?? 0),
                    $manualRows
                ));
                if ($supplierTotalBoxes <= 0) {
                    continue;
                }
                $orderNumber = $this->nextOrderNumber($db, (string) ($supplier['slug'] ?? ''));
                $orderId = $db->insert('seiq_orders', [
                    'supplier_id' => $supplierId,
                    'order_number' => $orderNumber,
                    'notes' => 'Reposición desde sugerencia de stock',
                    'included_quotes' => $includedJson,
                    'total_products' => count($manualRows),
                    'total_boxes' => $supplierTotalBoxes,
                    'status' => 'draft',
                    'sent_at' => null,
                    'received_at' => null,
                    'pdf_path' => null,
                ]);
                $sort = 0;
                foreach ($manualRows as $row) {
                    if ((int) ($row['boxes_to_order'] ?? 0) <= 0) {
                        continue;
                    }
                    $db->insert('seiq_order_items', [
                        'seiq_order_id' => $orderId,
                        'product_id' => (int) $row['product_id'],
                        'qty_units_sold' => 0,
                        'qty_boxes_sold' => 0,
                        'total_units_needed' => (int) $row['total_units_needed'],
                        'units_per_box' => (int) $row['units_per_box'],
                        'boxes_to_order' => (int) $row['boxes_to_order'],
                        'units_remainder' => (int) $row['units_remainder'],
                        'sort_order' => $sort++,
                    ]);
                }
                $pdfFile = $this->renderSeiqOrderPdf($orderId, $orderNumber, $manualRows, $supplier);
                $db->update('seiq_orders', ['pdf_path' => $pdfFile], 'id = :id', ['id' => $orderId]);
                $createdOrders[] = [
                    'id' => $orderId,
                    'number' => $orderNumber,
                    'products' => count($manualRows),
                ];
            }
            if ($createdOrders === []) {
                throw new \RuntimeException('No se pudo crear ningún pedido.');
            }
            $db->getPdo()->commit();
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            $this->json(['success' => false, 'error' => 'Error al crear pedido: ' . $e->getMessage()], 500);
            return;
        }

        $count = count($createdOrders);
        if ($count === 1) {
            $order = $createdOrders[0];
            $msg = sprintf(
                'Pedido %s creado como borrador con %d producto%s.',
                (string) $order['number'],
                (int) $order['products'],
                (int) $order['products'] === 1 ? '' : 's'
            );
            flash('success', $msg);
            $this->json([
                'success' => true,
                'redirect' => url('/pedidos-proveedor/' . (int) $order['id']),
                'message' => $msg,
            ]);
            return;
        }

        $numbers = implode(', ', array_map(static fn (array $o): string => (string) $o['number'], $createdOrders));
        $totalProducts = array_sum(array_map(static fn (array $o): int => (int) $o['products'], $createdOrders));
        $msg = sprintf(
            '%d pedidos creados como borrador (%s) con %d productos en total.',
            $count,
            $numbers,
            $totalProducts
        );
        flash('success', $msg);
        $this->json([
            'success' => true,
            'redirect' => url('/pedidos-proveedor'),
            'message' => $msg,
        ]);
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
        // Igual que API compañeros y PDF con precios: Costo LO por caja vía PricingEngine (requiere join de categorías).
        $suggestedReceivedAmount = $this->calculateSeiqOrderTotal((int) $id);
        $existingInvoice = $db->fetch(
            "SELECT amount FROM account_transactions
             WHERE reference_type = 'seiq_order' AND reference_id = ? AND transaction_type = 'invoice'
             LIMIT 1",
            [(int) $id]
        );
        if ($existingInvoice && (float) ($existingInvoice['amount'] ?? 0) > 0) {
            $suggestedReceivedAmount = (float) $existingInvoice['amount'];
        }

        $orderStatus = (string) ($order['status'] ?? '');

        // Pedidos recibidos en conjunto (solo aplica a pedidos ya recibidos)
        $companionOrders = [];
        $mainOrder = null;
        $siblingOrders = [];
        if ($orderStatus === 'received' && $this->seiqColumnExists($db, 'received_with_order_id')) {
            $companionOrders = $db->fetchAll(
                "SELECT id, order_number, total_boxes, total_products, status
                 FROM seiq_orders
                 WHERE received_with_order_id = ?
                 ORDER BY order_number",
                [(int) $order['id']]
            );
            $mainOrderId = (int) ($order['received_with_order_id'] ?? 0);
            if ($mainOrderId > 0 && $mainOrderId !== (int) $order['id']) {
                $mainOrder = $db->fetch(
                    'SELECT id, order_number, invoice_number, invoice_date, invoice_amount
                     FROM seiq_orders WHERE id = ?',
                    [$mainOrderId]
                );
                $siblingOrders = $db->fetchAll(
                    "SELECT id, order_number, total_boxes, total_products, status
                     FROM seiq_orders
                     WHERE received_with_order_id = ? AND id <> ?
                     ORDER BY order_number",
                    [$mainOrderId, (int) $order['id']]
                );
            }
        }

        $this->view('pedido-seiq/show', [
            'title' => 'Pedido ' . $order['order_number'],
            'order' => $order,
            'items' => $items,
            'includedQuotes' => $includedQuotes,
            'waMessage' => $waMessage,
            'suggestedReceivedAmount' => round($suggestedReceivedAmount, 2),
            'companionOrders' => $companionOrders,
            'mainOrder' => $mainOrder,
            'siblingOrders' => $siblingOrders,
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

    /**
     * Devuelve en JSON los pedidos «sent» del mismo proveedor (excluyendo el actual)
     * para ofrecerlos como acompañantes en el modal de recepción.
     */
    public function apiSentCompanions(string $id): void
    {
        $db = Database::getInstance();
        $orderId = (int) $id;
        $order = $db->fetch('SELECT supplier_id FROM seiq_orders WHERE id = ?', [$orderId]);
        if (!$order) {
            $this->json(['orders' => []], 404);
            return;
        }
        $supplierId = (int) ($order['supplier_id'] ?? 0);
        if ($supplierId <= 0) {
            $this->json(['orders' => []]);
            return;
        }
        $rows = $db->fetchAll(
            "SELECT id, order_number, total_boxes, total_products, sent_at, created_at
             FROM seiq_orders
             WHERE supplier_id = ? AND status = 'sent' AND id <> ?
             ORDER BY created_at ASC",
            [$supplierId, $orderId]
        );
        $orders = [];
        foreach ($rows as $r) {
            $oid = (int) $r['id'];
            $suggested = $this->calculateSeiqOrderTotal($oid);
            $orders[] = [
                'id' => $oid,
                'order_number' => (string) ($r['order_number'] ?? ''),
                'total_boxes' => (int) ($r['total_boxes'] ?? 0),
                'total_products' => (int) ($r['total_products'] ?? 0),
                'sent_at' => (string) ($r['sent_at'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'suggested_amount' => $suggested,
                'suggested_amount_formatted' => number_format($suggested, 2, ',', '.'),
            ];
        }
        $this->json(['orders' => $orders]);
    }

    /**
     * Recepción «consolidada»: el modal envía monto real, número y fecha de la factura
     * del proveedor, opcionalmente junto a otros pedidos «sent» cubiertos por la misma
     * factura. Cada pedido pasa a «received» y se aplica el stock; se crea UN SOLO
     * asiento en account_transactions sobre el pedido principal.
     */
    public function receiveOrder(string $id): void
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
        $orderId = (int) $id;
        $order = $db->fetch('SELECT * FROM seiq_orders WHERE id = ?', [$orderId]);
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-proveedor');
            return;
        }
        if ((string) ($order['status'] ?? '') === 'received') {
            flash('error', 'El pedido ya fue recibido.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }

        $supplierId = (int) ($order['supplier_id'] ?? 0);
        $invoiceAmount = parseArgentineAmount((string) $this->input('invoice_amount', '0'));
        if ($invoiceAmount <= 0) {
            flash('error', 'Ingresá el monto real de la factura del proveedor.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $invoiceNumber = trim((string) $this->input('invoice_number', ''));
        $invoiceDateRaw = trim((string) $this->input('invoice_date', date('Y-m-d')));
        if ($invoiceDateRaw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDateRaw)) {
            $invoiceDateRaw = date('Y-m-d');
        }
        $invoiceDate = $invoiceDateRaw;

        $companionsRaw = $_POST['companion_orders'] ?? [];
        $companionIds = [];
        if (is_array($companionsRaw)) {
            foreach ($companionsRaw as $cid) {
                $cidInt = (int) $cid;
                if ($cidInt > 0 && $cidInt !== $orderId) {
                    $companionIds[$cidInt] = true;
                }
            }
        }
        $companionIds = array_keys($companionIds);

        if ($companionIds !== [] && $supplierId > 0) {
            $ph = implode(',', array_fill(0, count($companionIds), '?'));
            $params = array_merge(array_map('intval', $companionIds), [$supplierId]);
            $validCompanions = $db->fetchAll(
                "SELECT id FROM seiq_orders
                 WHERE id IN ({$ph}) AND supplier_id = ? AND status = 'sent'",
                $params
            );
            $companionIds = array_map(static fn ($r): int => (int) $r['id'], $validCompanions);
        } else {
            $companionIds = [];
        }

        $allOrderIds = array_values(array_unique(array_merge([$orderId], $companionIds)));

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $receivedAt = date('Y-m-d H:i:s');
            $orderNumbers = [];
            foreach ($allOrderIds as $oid) {
                $row = $db->fetch(
                    'SELECT id, order_number, status, receipt_stock_applied
                     FROM seiq_orders WHERE id = ?',
                    [$oid]
                );
                if (!$row) {
                    continue;
                }
                $orderNumbers[] = (string) ($row['order_number'] ?? '');
                $prevStatus = (string) ($row['status'] ?? '');
                $alreadyApplied = (int) ($row['receipt_stock_applied'] ?? 0) === 1;

                $data = [
                    'status' => 'received',
                    'received_at' => $receivedAt,
                    'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
                    'invoice_date' => $invoiceDate,
                ];
                if ($oid === $orderId) {
                    $data['invoice_amount'] = round($invoiceAmount, 2);
                    $data['received_with_order_id'] = null;
                } else {
                    $data['invoice_amount'] = null;
                    $data['received_with_order_id'] = $orderId;
                }
                if ($prevStatus !== 'received' && !$alreadyApplied) {
                    $this->applySupplierOrderStockDelta($db, $oid, 1);
                    $data['receipt_stock_applied'] = 1;
                }
                $db->update('seiq_orders', $data, 'id = :id', ['id' => $oid]);
            }

            $hasAccountTable = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
            if ($hasAccountTable && $supplierId > 0) {
                $existing = $db->fetch(
                    "SELECT id FROM account_transactions
                     WHERE reference_type = 'seiq_order' AND reference_id = ? AND transaction_type = 'invoice'
                     LIMIT 1",
                    [$orderId]
                );
                $descriptionParts = [];
                if ($invoiceNumber !== '') {
                    $descriptionParts[] = 'Factura ' . $invoiceNumber;
                }
                $descriptionParts[] = 'Pedido' . (count($orderNumbers) > 1 ? 's' : '') . ' ' . implode(', ', $orderNumbers);
                $description = implode(' — ', $descriptionParts);

                $invData = [
                    'amount' => round($invoiceAmount, 2),
                    'description' => $description,
                    'transaction_date' => $invoiceDate,
                ];
                if ($existing) {
                    $db->update('account_transactions', $invData, 'id = :id', ['id' => (int) $existing['id']]);
                } else {
                    $db->insert('account_transactions', array_merge([
                        'account_type' => 'supplier',
                        'account_id' => $supplierId,
                        'transaction_type' => 'invoice',
                        'reference_type' => 'seiq_order',
                        'reference_id' => $orderId,
                    ], $invData));
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo registrar la recepción: ' . $e->getMessage());
            redirect('/pedidos-proveedor/' . $id);
            return;
        }

        $extra = count($companionIds) > 0
            ? ' Se recibieron ' . (count($companionIds) + 1) . ' pedidos juntos cubiertos por la factura.'
            : '';
        flash('success', 'Pedido recibido y stock actualizado.' . $extra);
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
                QuoteStatusTransitions::deliver(
                    $db,
                    $qid,
                    (string) ($q['status'] ?? ''),
                    (int) ($q['delivery_stock_applied'] ?? 0) === 1
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

    public function delete(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedidos-proveedor');
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
        if ((string) ($order['status'] ?? '') !== 'draft') {
            flash('error', 'Solo se pueden eliminar pedidos en estado borrador.');
            redirect('/pedidos-proveedor/' . $id);
            return;
        }
        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            $db->query('DELETE FROM seiq_order_items WHERE seiq_order_id = ?', [$orderId]);

            $pdfPath = trim((string) ($order['pdf_path'] ?? ''));
            if ($pdfPath !== '') {
                $full = STORAGE_PATH . '/pdfs/' . basename($pdfPath);
                if (is_file($full)) {
                    @unlink($full);
                }
            }

            try {
                $hasAccount = (bool) $db->fetchColumn("SHOW TABLES LIKE 'account_transactions'");
                if ($hasAccount) {
                    $db->query(
                        "DELETE FROM account_transactions WHERE reference_type = 'seiq_order' AND reference_id = ?",
                        [$orderId]
                    );
                }
            } catch (\Throwable) {
            }

            $db->query('DELETE FROM seiq_orders WHERE id = ?', [$orderId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', 'No se pudo eliminar el pedido: ' . $e->getMessage());
            redirect('/pedidos-proveedor/' . $id);
            return;
        }

        flash('success', 'Pedido eliminado. Los presupuestos asociados vuelven a estar disponibles para nuevos pedidos.');
        redirect('/pedidos-proveedor');
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
                $this->ensureSeiqInvoiceColumns($db);
                return true;
            }
        } catch (\Throwable) {
            // Continúa al mismo manejo de error.
        }

        flash('error', 'Falta migrar base de datos para pedidos de proveedores. Ejecutá install.php o la migración multi-proveedor en producción.');
        redirect('/');
        return false;
    }

    /** Columnas de factura consolidada en seiq_orders (idempotente). */
    private function ensureSeiqInvoiceColumns(Database $db): void
    {
        $pdo = $db->getPdo();
        $columns = [
            'receipt_stock_applied' => 'ALTER TABLE seiq_orders ADD COLUMN receipt_stock_applied TINYINT(1) NOT NULL DEFAULT 0 AFTER received_at',
            'invoice_number' => 'ALTER TABLE seiq_orders ADD COLUMN invoice_number VARCHAR(50) NULL AFTER receipt_stock_applied',
            'invoice_date' => 'ALTER TABLE seiq_orders ADD COLUMN invoice_date DATE NULL AFTER invoice_number',
            'invoice_amount' => 'ALTER TABLE seiq_orders ADD COLUMN invoice_amount DECIMAL(12,2) NULL AFTER invoice_date',
            'received_with_order_id' => 'ALTER TABLE seiq_orders ADD COLUMN received_with_order_id INT NULL AFTER invoice_amount',
        ];
        foreach ($columns as $name => $sql) {
            if (!$this->seiqColumnExists($db, $name)) {
                $pdo->exec($sql);
            }
        }
        if ($this->seiqColumnExists($db, 'received_with_order_id')) {
            $hasIndex = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seiq_orders' AND INDEX_NAME = 'idx_received_with'"
            ) > 0;
            if (!$hasIndex) {
                try {
                    $pdo->exec('ALTER TABLE seiq_orders ADD INDEX idx_received_with (received_with_order_id)');
                } catch (\Throwable) {
                    // Índice duplicado u otro error no bloqueante.
                }
            }
        }
    }

    private function seiqColumnExists(Database $db, string $column): bool
    {
        return (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['seiq_orders', $column]
        ) > 0;
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
        $normalized = [];
        $productIds = [];
        $requestedSupplierIds = [];
        foreach ($rawLines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            $boxes = max(0, (int) ($line['boxes'] ?? $line['boxes_to_order'] ?? 0));
            $supplierId = (int) ($line['supplier_id'] ?? 0);
            if ($productId <= 0 || $boxes <= 0) {
                continue;
            }
            $normalized[] = [
                'product_id' => $productId,
                'boxes' => $boxes,
                'supplier_id' => $supplierId,
            ];
            $productIds[$productId] = true;
            if ($supplierId > 0) {
                $requestedSupplierIds[$supplierId] = true;
            }
        }
        if ($normalized === []) {
            return [];
        }
        $ids = array_keys($productIds);
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
        $validSupplierIds = [];
        if ($requestedSupplierIds !== []) {
            $supplierIds = array_keys($requestedSupplierIds);
            $sph = implode(',', array_fill(0, count($supplierIds), '?'));
            $supplierRows = $db->fetchAll("SELECT id FROM suppliers WHERE id IN ({$sph})", $supplierIds);
            foreach ($supplierRows as $srow) {
                $sid = (int) ($srow['id'] ?? 0);
                if ($sid > 0) {
                    $validSupplierIds[$sid] = true;
                }
            }
        }

        $bySupplierProduct = [];
        foreach ($normalized as $line) {
            $productId = (int) $line['product_id'];
            $boxes = (int) $line['boxes'];
            $requestedSupplierId = (int) $line['supplier_id'];
            $meta = $metaByProduct[$productId] ?? null;
            if ($meta === null) {
                continue;
            }
            $resolvedSupplierId = (int) ($meta['supplier_id'] ?? 0);
            $supplierId = $requestedSupplierId > 0 ? $requestedSupplierId : $resolvedSupplierId;
            if ($supplierId <= 0) {
                continue;
            }
            if ($requestedSupplierId > 0 && !isset($validSupplierIds[$requestedSupplierId])) {
                continue;
            }
            $bySupplierProduct[$supplierId][$productId] = ($bySupplierProduct[$supplierId][$productId] ?? 0) + $boxes;
        }

        $out = [];
        foreach ($bySupplierProduct as $supplierId => $products) {
            foreach ($products as $productId => $boxes) {
                $meta = $metaByProduct[(int) $productId] ?? null;
                if ($meta === null) {
                    continue;
                }
                $unitsPerBox = max(1, (int) ($meta['units_per_box'] ?? 1));
                $out[(int) $supplierId][] = [
                    'product_id' => (int) $productId,
                    'code' => (string) ($meta['code'] ?? ''),
                    'name' => (string) ($meta['name'] ?? ''),
                    'content' => (string) ($meta['content'] ?? ''),
                    'presentation' => (string) ($meta['presentation'] ?? ''),
                    'sale_unit_label' => (string) ($meta['sale_unit_label'] ?? ''),
                    'sale_unit_description' => (string) ($meta['sale_unit_description'] ?? ''),
                    'qty_units_sold' => 0,
                    'qty_boxes_sold' => 0,
                    'total_units_needed' => (int) $boxes * $unitsPerBox,
                    'units_per_box' => $unitsPerBox,
                    'boxes_to_order' => (int) $boxes,
                    'units_remainder' => 0,
                    'category_name' => (string) ($meta['category_name'] ?? ''),
                    'parent_category_name' => (string) ($meta['parent_category_name'] ?? ''),
                    'supplier_id' => (int) $supplierId,
                    'supplier_name' => (string) ($meta['supplier_name'] ?? ''),
                    'supplier_slug' => (string) ($meta['supplier_slug'] ?? ''),
                ];
            }
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
                    c.markup_locked AS category_markup_locked,
                    c.markup_minorista AS category_markup_minorista,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    pc.markup_locked AS parent_markup_locked,
                    pc.markup_minorista AS parent_markup_minorista,
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
            if ($boxes <= 0) {
                continue;
            }
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
