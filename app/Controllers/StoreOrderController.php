<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\MailHelper;
use App\Models\Database;

final class StoreOrderController extends Controller
{
    /**
     * API pública: recibe un pedido desde la tienda web
     * POST api/tienda/pedido
     */
    public function createOrder(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            $this->json(['error' => 'Método no permitido']);
            return;
        }

        $body = file_get_contents('php://input');
        $data = json_decode($body ?: '', true);

        if (!is_array($data)) {
            http_response_code(400);
            $this->json(['error' => 'JSON inválido']);
            return;
        }

        $errors = $this->validateOrder($data);
        if ($errors !== []) {
            http_response_code(422);
            $this->json(['error' => 'Datos inválidos', 'details' => $errors]);
            return;
        }

        $db = Database::getInstance();

        $orderNumber = 'WEB-' . strtoupper(substr(uniqid(), -6));
        while ($db->fetch('SELECT id FROM store_orders WHERE order_number = ?', [$orderNumber])) {
            $orderNumber = 'WEB-' . strtoupper(substr(uniqid(), -6));
        }

        $items = $data['items'];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1));
        }

        $shippingCost = (float) ($data['shipping_cost'] ?? 0);
        $total = $subtotal + $shippingCost;

        $db->query(
            'INSERT INTO store_orders (
                order_number, customer_name, customer_email, customer_phone,
                shipping_method, shipping_address, shipping_locality, shipping_cost,
                payment_method, subtotal, total, items_json, customer_notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orderNumber,
                trim((string) $data['customer_name']),
                trim((string) ($data['customer_email'] ?? '')),
                trim((string) $data['customer_phone']),
                $data['shipping_method'],
                trim((string) ($data['shipping_address'] ?? '')),
                trim((string) ($data['shipping_locality'] ?? '')),
                $shippingCost,
                $data['payment_method'],
                $subtotal,
                $total,
                json_encode($items, JSON_UNESCAPED_UNICODE),
                trim((string) ($data['customer_notes'] ?? '')),
                'pending',
            ]
        );

        try {
            $this->sendAdminNotification($orderNumber, $data, $subtotal, $shippingCost, $total, $items);
        } catch (\Throwable $e) {
            error_log('StoreOrder email error: ' . $e->getMessage());
        }

        $this->json([
            'success' => true,
            'order_number' => $orderNumber,
            'total' => $total,
        ]);
    }

    /** @return list<string> */
    private function validateOrder(array $data): array
    {
        $errors = [];

        if (empty($data['customer_name'])) {
            $errors[] = 'El nombre es requerido';
        }
        if (empty($data['customer_phone'])) {
            $errors[] = 'El teléfono es requerido';
        }
        if (
            empty($data['shipping_method'])
            || !in_array($data['shipping_method'], ['retiro', 'zona_propia', 'consultar'], true)
        ) {
            $errors[] = 'Método de envío inválido';
        }
        if (($data['shipping_method'] ?? '') === 'zona_propia' && empty($data['shipping_address'])) {
            $errors[] = 'La dirección es requerida para envío en zona';
        }
        if (($data['shipping_method'] ?? '') === 'zona_propia' && empty($data['shipping_locality'])) {
            $errors[] = 'La localidad es requerida para envío en zona';
        }
        if (
            empty($data['payment_method'])
            || !in_array($data['payment_method'], ['transferencia', 'mercadopago', 'efectivo'], true)
        ) {
            $errors[] = 'Método de pago inválido';
        }
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors[] = 'El carrito está vacío';
        }

        return $errors;
    }

    /** @param list<array<string, mixed>> $items */
    private function sendAdminNotification(
        string $orderNumber,
        array $data,
        float $subtotal,
        float $shippingCost,
        float $total,
        array $items
    ): void {
        $subject = '🛒 Nuevo pedido web: ' . $orderNumber;

        $shippingLabels = [
            'retiro' => 'Retiro en Luján',
            'zona_propia' => 'Envío zona propia',
            'consultar' => 'Consultar envío',
        ];
        $paymentLabels = [
            'transferencia' => 'Transferencia bancaria',
            'mercadopago' => 'Mercado Pago',
            'efectivo' => 'Efectivo al recibir',
        ];

        $itemsHtml = '';
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $itemTotal = $price * $qty;
            $itemsHtml .= sprintf(
                '<tr><td>%s</td><td style="text-align:center">%d</td><td style="text-align:right">$%s</td><td style="text-align:right">$%s</td></tr>',
                htmlspecialchars((string) ($item['name'] ?? '')),
                $qty,
                number_format($price, 2, ',', '.'),
                number_format($itemTotal, 2, ',', '.')
            );
        }

        $shippingMethod = (string) ($data['shipping_method'] ?? '');
        $paymentMethod = (string) ($data['payment_method'] ?? '');
        $customerEmail = htmlspecialchars((string) ($data['customer_email'] ?? ''));
        $customerName = htmlspecialchars((string) ($data['customer_name'] ?? ''));
        $customerPhone = htmlspecialchars((string) ($data['customer_phone'] ?? ''));

        $html = '<h2>Nuevo pedido desde la tienda web</h2>'
            . '<p><strong>N° de pedido:</strong> ' . htmlspecialchars($orderNumber) . '</p>'
            . '<hr>'
            . '<h3>Cliente</h3>'
            . '<p><strong>Nombre:</strong> ' . $customerName . '<br>'
            . '<strong>Teléfono:</strong> ' . $customerPhone . '<br>'
            . '<strong>Email:</strong> ' . $customerEmail . '</p>'
            . '<h3>Envío</h3>'
            . '<p><strong>Método:</strong> ' . htmlspecialchars($shippingLabels[$shippingMethod] ?? $shippingMethod) . '<br>'
            . $this->shippingDetail($data)
            . '<strong>Costo de envío:</strong> $' . number_format($shippingCost, 2, ',', '.') . '</p>'
            . '<h3>Pago</h3>'
            . '<p><strong>Método:</strong> ' . htmlspecialchars($paymentLabels[$paymentMethod] ?? $paymentMethod) . '</p>'
            . '<h3>Productos</h3>'
            . '<table border="1" cellpadding="6" style="border-collapse:collapse;width:100%">'
            . '<thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead>'
            . '<tbody>' . $itemsHtml . '</tbody></table>'
            . '<p><strong>Subtotal:</strong> $' . number_format($subtotal, 2, ',', '.') . '</p>'
            . '<p><strong>Envío:</strong> $' . number_format($shippingCost, 2, ',', '.') . '</p>'
            . '<p style="font-size:1.2em"><strong>TOTAL: $' . number_format($total, 2, ',', '.') . '</strong></p>';

        $mailer = new MailHelper();
        $mailer->sendInvoice(
            'gonzalo@limpiaoeste.com.ar',
            'Admin Limpia Oeste',
            $subject,
            $html
        );
    }

    /** @param array<string, mixed> $data */
    private function shippingDetail(array $data): string
    {
        if (($data['shipping_method'] ?? '') === 'zona_propia') {
            return sprintf(
                '<strong>Dirección:</strong> %s, %s<br>',
                htmlspecialchars((string) ($data['shipping_address'] ?? '')),
                htmlspecialchars((string) ($data['shipping_locality'] ?? ''))
            );
        }
        return '';
    }

    /**
     * API pública: listar pedidos de un cliente por teléfono
     * GET api/tienda/pedidos?phone=...
     */
    public function getOrdersByPhone(): void
    {
        $phone = trim((string) ($_GET['phone'] ?? ''));
        if ($phone === '') {
            http_response_code(400);
            $this->json(['error' => 'Teléfono requerido']);
            return;
        }

        $db = Database::getInstance();
        $orders = $db->fetchAll(
            'SELECT id, order_number, shipping_method, payment_method,
                    subtotal, shipping_cost, total, status, created_at
             FROM store_orders
             WHERE customer_phone = ?
             ORDER BY created_at DESC
             LIMIT 20',
            [$phone]
        );

        $this->json(['orders' => $orders]);
    }

    /**
     * API pública: detalle de un pedido por número
     * GET api/tienda/pedidos/{order_number}
     */
    public function getOrder(string $orderNumber): void
    {
        $db = Database::getInstance();
        $order = $db->fetch(
            'SELECT * FROM store_orders WHERE order_number = ?',
            [strtoupper($orderNumber)]
        );

        if (!$order) {
            http_response_code(404);
            $this->json(['error' => 'Pedido no encontrado']);
            return;
        }

        $order['items'] = json_decode((string) $order['items_json'], true);
        unset($order['items_json']);

        $this->json(['order' => $order]);
    }

    public function index(): void
    {
        $db = Database::getInstance();
        $status = trim((string) ($_GET['status'] ?? ''));

        $where = $status !== '' ? 'WHERE status = ?' : '';
        $params = $status !== '' ? [$status] : [];

        $orders = $db->fetchAll(
            "SELECT id, order_number, customer_name, customer_phone,
                    shipping_method, payment_method, total, status, created_at
             FROM store_orders
             {$where}
             ORDER BY created_at DESC
             LIMIT 100",
            $params
        );

        $this->view('store-orders/index', [
            'title' => 'Pedidos Web',
            'orders' => $orders,
            'status_filter' => $status,
        ]);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $order = $db->fetch('SELECT * FROM store_orders WHERE id = ?', [(int) $id]);

        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            redirect('/pedidos-web');
        }

        $order['items'] = json_decode((string) $order['items_json'], true) ?? [];

        $this->view('store-orders/show', [
            'title' => 'Pedido ' . $order['order_number'],
            'order' => $order,
        ]);
    }

    public function updateStatus(string $id): void
    {
        $db = Database::getInstance();
        $status = (string) ($_POST['status'] ?? '');

        $validStatuses = ['pending', 'confirmed', 'preparing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            flash('error', 'Estado inválido.');
            redirect('/pedidos-web/' . $id);
        }

        $db->query('UPDATE store_orders SET status = ? WHERE id = ?', [$status, (int) $id]);
        flash('success', 'Estado actualizado.');
        redirect('/pedidos-web/' . $id);
    }

    public function updateAdminNotes(string $id): void
    {
        $db = Database::getInstance();
        $notes = trim((string) ($_POST['admin_notes'] ?? ''));
        $db->query('UPDATE store_orders SET admin_notes = ? WHERE id = ?', [$notes, (int) $id]);
        flash('success', 'Notas guardadas.');
        redirect('/pedidos-web/' . $id);
    }
}
