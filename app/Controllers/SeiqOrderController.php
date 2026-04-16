<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
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
        $rows = $db->fetchAll(
            'SELECT so.*, s.name AS supplier_name, s.slug AS supplier_slug
             FROM seiq_orders so
             LEFT JOIN suppliers s ON s.id = so.supplier_id
             ORDER BY so.created_at DESC'
        );
        $this->view('pedido-seiq/index', ['title' => 'Pedidos a Proveedores', 'orders' => $rows]);
    }

    public function generate(): void
    {
        $db = Database::getInstance();
        if (!$this->ensureSeiqSchema($db)) {
            return;
        }
        $built = SeiqOrderBuilder::buildFromDatabase($db);
        if ($built['error'] === 'empty') {
            flash('info', 'No hay presupuestos aceptados para generar pedido.');
            redirect('/pedidos-proveedor');
            return;
        }
        if ($built['error'] === 'no_items') {
            flash('info', 'Los presupuestos aceptados no tienen ítems.');
            redirect('/pedidos-proveedor');
            return;
        }
        /** @var array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int} $bundle */
        $bundle = $built['bundle'];
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
        if ($built['bundle'] === null) {
            flash('error', 'No hay datos para generar el pedido.');
            redirect('/pedidos-proveedor/generar');
        }
        $notes = trim((string) $this->input('notes', ''));
        $bundle = $built['bundle'];
        $acceptedQuotes = $built['acceptedQuotes'];
        $quoteIds = array_map(static fn ($q) => (int) $q['id'], $acceptedQuotes);

        $includedJson = json_encode($quoteIds, JSON_THROW_ON_ERROR);
        $supplierBundles = SeiqOrderBuilder::groupConsolidatedBySupplier($bundle['consolidated']);
        if ($supplierBundles === []) {
            flash('error', 'No se encontraron proveedores asociados a los productos.');
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
                $orderNumber = $this->nextOrderNumber($db, (string) ($supplier['slug'] ?? ''));
                $orderId = $db->insert('seiq_orders', [
                    'supplier_id' => $supplierId,
                    'order_number' => $orderNumber,
                    'notes' => $notes !== '' ? $notes : null,
                    'included_quotes' => $includedJson,
                    'total_products' => $sb['total_products'],
                    'total_boxes' => $sb['total_boxes'],
                    'status' => 'draft',
                    'sent_at' => null,
                    'received_at' => null,
                    'pdf_path' => null,
                ]);

                $sort = 0;
                foreach ($sb['consolidated'] as $row) {
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
                $pdfFile = $this->renderSeiqOrderPdf($orderId, $orderNumber, $sb['consolidated'], $supplier);
                $db->update('seiq_orders', ['pdf_path' => $pdfFile], 'id = :id', ['id' => $orderId]);
                $lastOrderId = $orderId;
                $numbers[] = $orderNumber;
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

        $waMessage = $this->buildWhatsAppMessage($order, $items, (int) $order['total_boxes']);
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
            ];
        }
        $orderForPdf = [
            'order_number' => (string) $order['order_number'],
            'created_at' => (string) $order['created_at'],
            'total_boxes' => (int) $order['total_boxes'],
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
            $pricePerBox = $this->orderItemCostPerBox($it);
            $boxes = (int) ($it['boxes_to_order'] ?? 0);
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
            ];
        }
        $orderForPdf = [
            'order_number' => (string) $order['order_number'],
            'created_at' => (string) $order['created_at'],
            'total_boxes' => (int) $order['total_boxes'],
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
        $exists = $db->fetch('SELECT id FROM seiq_orders WHERE id = ?', [(int) $id]);
        if (!$exists) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-proveedor');
            return;
        }
        $data = ['status' => $status];
        if ($status === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'received') {
            $manualAmount = (float) str_replace(['.', ','], ['', '.'], (string) $this->input('received_amount', '0'));
            if ($manualAmount <= 0) {
                flash('error', 'Para recibir el pedido debés ingresar el monto del remito/factura.');
                redirect('/pedidos-proveedor/' . $id);
                return;
            }
            $data['received_at'] = date('Y-m-d H:i:s');
        }
        $db->update('seiq_orders', $data, 'id = :id', ['id' => (int) $id]);

        if ($status === 'received') {
            $this->registerSupplierInvoiceOnReceive($db, (int) $id);
        }
        flash('success', 'Estado actualizado.');
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
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->query(
            "UPDATE quotes SET status = 'delivered' WHERE id IN ({$ph})",
            $ids
        );
        flash('success', 'Presupuestos marcados como entregados.');
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

    private function ensureSeiqSchema(Database $db): bool
    {
        try {
            $hasOrders = (bool) $db->fetchColumn("SHOW TABLES LIKE 'seiq_orders'");
            $hasItems = (bool) $db->fetchColumn("SHOW TABLES LIKE 'seiq_order_items'");
            $hasSuppliers = (bool) $db->fetchColumn("SHOW TABLES LIKE 'suppliers'");
            if ($hasOrders && $hasItems && $hasSuppliers) {
                return true;
            }
        } catch (\Throwable) {
            // Continúa al mismo manejo de error.
        }

        flash('error', 'Falta migrar base de datos para pedidos de proveedores. Ejecutá install.php o la migración multi-proveedor en producción.');
        redirect('/');
        return false;
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
            $manualAmount = (float) str_replace(['.', ','], ['', '.'], (string) $this->input('received_amount', '0'));
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
                    p.discount_override, c.default_discount, pc.default_discount AS parent_discount,
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
            $lines[] = [
                'code' => $row['code'],
                'name' => $row['name'],
                'presentation' => $row['presentation'],
                'content' => $row['content'],
                'sale_unit_description' => $row['sale_unit_description'],
                'boxes_to_order' => (int) $row['boxes_to_order'],
            ];
        }
        usort($lines, static fn (array $a, array $b) => strcmp((string) $a['code'], (string) $b['code']));
        $order = [
            'order_number' => $orderNumber,
            'created_at' => date('Y-m-d H:i:s'),
            'total_boxes' => array_sum(array_column($lines, 'boxes_to_order')),
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
     * @param list<array<string,mixed>> $items
     */
    private function buildWhatsAppMessage(array $order, array $items, int $totalBoxes): string
    {
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
