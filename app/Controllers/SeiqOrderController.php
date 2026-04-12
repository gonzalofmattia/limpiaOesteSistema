<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\SeiqOrderBuilder;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class SeiqOrderController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT * FROM seiq_orders ORDER BY created_at DESC');
        $this->view('pedido-seiq/index', ['title' => 'Pedido a Seiq', 'orders' => $rows]);
    }

    public function generate(): void
    {
        $db = Database::getInstance();
        $built = SeiqOrderBuilder::buildFromDatabase($db);
        if ($built['error'] === 'empty') {
            flash('info', 'No hay presupuestos aceptados para generar pedido.');
            redirect('/pedido-seiq');
            return;
        }
        if ($built['error'] === 'no_items') {
            flash('info', 'Los presupuestos aceptados no tienen ítems.');
            redirect('/pedido-seiq');
            return;
        }
        /** @var array{consolidated: list<array<string,mixed>>, total_products: int, total_boxes: int} $bundle */
        $bundle = $built['bundle'];
        $this->view('pedido-seiq/generate', [
            'title' => 'Generar pedido a Seiq',
            'acceptedQuotes' => $built['acceptedQuotes'],
            'bundle' => $bundle,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedido-seiq/generar');
        }
        $db = Database::getInstance();
        $built = SeiqOrderBuilder::buildFromDatabase($db);
        if ($built['bundle'] === null) {
            flash('error', 'No hay datos para generar el pedido.');
            redirect('/pedido-seiq/generar');
        }
        $notes = trim((string) $this->input('notes', ''));
        $bundle = $built['bundle'];
        $acceptedQuotes = $built['acceptedQuotes'];
        $quoteIds = array_map(static fn ($q) => (int) $q['id'], $acceptedQuotes);

        $orderNumber = $this->nextOrderNumber($db);
        $includedJson = json_encode($quoteIds, JSON_THROW_ON_ERROR);

        $db->getPdo()->beginTransaction();
        try {
            $orderId = $db->insert('seiq_orders', [
                'order_number' => $orderNumber,
                'notes' => $notes !== '' ? $notes : null,
                'included_quotes' => $includedJson,
                'total_products' => $bundle['total_products'],
                'total_boxes' => $bundle['total_boxes'],
                'status' => 'draft',
                'sent_at' => null,
                'received_at' => null,
                'pdf_path' => null,
            ]);

            $sort = 0;
            foreach ($bundle['consolidated'] as $row) {
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

            $pdfFile = $this->renderSeiqOrderPdf($orderId, $orderNumber, $bundle['consolidated']);
            $db->update('seiq_orders', ['pdf_path' => $pdfFile], 'id = :id', ['id' => $orderId]);

            $db->getPdo()->commit();
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'Error al guardar: ' . $e->getMessage());
            redirect('/pedido-seiq/generar');
            return;
        }

        flash('success', "Pedido {$orderNumber} generado.");
        redirect('/pedido-seiq/' . $orderId);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $order = $db->fetch('SELECT * FROM seiq_orders WHERE id = ?', [(int) $id]);
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedido-seiq');
            return;
        }
        $items = $db->fetchAll(
            'SELECT soi.*, p.code, p.name AS product_name, p.presentation, p.content,
                    p.sale_unit_label, p.sale_unit_description,
                    c.name AS category_name, c.slug AS category_slug, pc.name AS parent_category_name
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

        $this->view('pedido-seiq/show', [
            'title' => 'Pedido ' . $order['order_number'],
            'order' => $order,
            'items' => $items,
            'includedQuotes' => $includedQuotes,
            'waMessage' => $waMessage,
        ]);
    }

    public function downloadPdf(string $id): void
    {
        $db = Database::getInstance();
        $order = $db->fetch('SELECT * FROM seiq_orders WHERE id = ?', [(int) $id]);
        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedido-seiq');
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

    public function changeStatus(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedido-seiq/' . $id);
            return;
        }
        $status = (string) $this->input('status', '');
        if (!in_array($status, ['draft', 'sent', 'received'], true)) {
            flash('error', 'Estado inválido.');
            redirect('/pedido-seiq/' . $id);
            return;
        }
        $db = Database::getInstance();
        $exists = $db->fetch('SELECT id FROM seiq_orders WHERE id = ?', [(int) $id]);
        if (!$exists) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedido-seiq');
            return;
        }
        $data = ['status' => $status];
        if ($status === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'received') {
            $data['received_at'] = date('Y-m-d H:i:s');
        }
        $db->update('seiq_orders', $data, 'id = :id', ['id' => (int) $id]);
        flash('success', 'Estado actualizado.');
        redirect('/pedido-seiq/' . $id);
    }

    public function markQuotesDelivered(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/pedido-seiq/' . $id);
            return;
        }
        $db = Database::getInstance();
        $order = $db->fetch('SELECT * FROM seiq_orders WHERE id = ?', [(int) $id]);
        if (!$order || empty($order['included_quotes'])) {
            flash('error', 'No hay presupuestos asociados.');
            redirect('/pedido-seiq/' . $id);
            return;
        }
        $decoded = json_decode((string) $order['included_quotes'], true);
        if (!is_array($decoded) || $decoded === []) {
            flash('error', 'Lista de presupuestos vacía.');
            redirect('/pedido-seiq/' . $id);
            return;
        }
        $ids = array_map(static fn ($x) => (int) $x, $decoded);
        $ids = array_filter($ids, static fn ($x) => $x > 0);
        if ($ids === []) {
            flash('error', 'IDs inválidos.');
            redirect('/pedido-seiq/' . $id);
            return;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->query(
            "UPDATE quotes SET status = 'delivered' WHERE id IN ({$ph})",
            $ids
        );
        flash('success', 'Presupuestos marcados como entregados.');
        redirect('/pedido-seiq/' . $id);
    }

    private function nextOrderNumber(Database $db): string
    {
        $year = date('Y');
        $last = $db->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(order_number, 9) AS UNSIGNED))
             FROM seiq_orders
             WHERE order_number LIKE ?",
            ['PS-' . $year . '-%']
        );
        $next = (int) ($last ?? 0) + 1;

        return sprintf('PS-%s-%04d', $year, $next);
    }

    /**
     * @param list<array<string,mixed>> $consolidated
     */
    private function renderSeiqOrderPdf(int $orderId, string $orderNumber, array $consolidated): string
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
        $cid = setting('seiq_cliente_id') ?? '';
        $nom = setting('seiq_cliente_nombre') ?? '';
        $lines[] = 'Cliente: ' . $cid . ' - ' . $nom;
        $lines[] = '';
        foreach ($items as $it) {
            $lines[] = '• ' . (int) $it['boxes_to_order'] . 'x ' . ($it['code'] ?? '') . ' - ' . ($it['product_name'] ?? '');
        }
        $lines[] = '';
        $lines[] = '*Total: ' . $totalBoxes . ' cajas/bultos*';
        $lines[] = 'Condición: ' . (setting('seiq_condicion_pago') ?? '');

        return implode("\n", $lines);
    }
}
