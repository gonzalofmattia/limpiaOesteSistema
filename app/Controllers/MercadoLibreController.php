<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;

final class MercadoLibreController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT q.*
             FROM quotes q
             WHERE COALESCE(q.is_mercadolibre, 0) = 1
             ORDER BY q.created_at DESC'
        );
        $sales = [];
        foreach ($rows as $row) {
            $quoteId = (int) ($row['id'] ?? 0);
            $stats = $this->buildQuoteStats($db, $quoteId);
            $sales[] = array_merge($row, $stats);
        }
        $this->view('ventas-ml/index', ['title' => 'Ventas ML', 'sales' => $sales]);
    }

    public function create(): void
    {
        $this->view('ventas-ml/form', ['title' => 'Nueva venta ML']);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/ventas-ml/crear');
            return;
        }
        $db = Database::getInstance();
        $saleDate = trim((string) $this->input('sale_date', date('Y-m-d')));
        $mlSaleTotal = $this->parseRequiredMoney($this->input('ml_sale_total', ''));
        $mlNetAmount = $this->parseRequiredMoney($this->input('ml_net_amount', ''));
        if ($mlSaleTotal === null || $mlNetAmount === null) {
            flash('error', 'Ingresá el total de venta ML y el neto recibido de Mercado Pago.');
            redirect('/ventas-ml/crear');
            return;
        }
        $linesRaw = $_POST['items'] ?? [];
        if (!is_array($linesRaw) || $linesRaw === []) {
            flash('error', 'Agregá al menos un producto.');
            redirect('/ventas-ml/crear');
            return;
        }

        $db->getPdo()->beginTransaction();
        try {
            $clientId = $this->ensureMercadoLibreClient($db);
            $quoteId = $db->insert('quotes', [
                'quote_number' => $this->nextQuoteNumber($db),
                'client_id' => $clientId,
                'title' => 'Venta MercadoLibre',
                'notes' => 'Venta registrada desde módulo Ventas ML',
                'validity_days' => 1,
                'custom_markup' => null,
                'include_iva' => 0,
                'is_mercadolibre' => 1,
                'ml_net_amount' => round($mlNetAmount, 2),
                'ml_sale_total' => round($mlSaleTotal, 2),
                'subtotal' => 0,
                'discount_percentage' => null,
                'discount_amount' => null,
                'iva_amount' => 0,
                'total' => round($mlSaleTotal, 2),
                'status' => 'accepted',
                'created_at' => $saleDate . ' 00:00:00',
            ]);

            $sort = 0;
            $computedSubtotal = 0.0;
            foreach ($linesRaw as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $unitType = QuoteLinePricing::normalizeUnitType((string) ($line['unit_type'] ?? 'caja'));
                $unitPrice = $this->parseRequiredMoney($line['unit_price'] ?? '');
                if ($productId <= 0 || $unitPrice === null || $unitPrice < 0) {
                    continue;
                }
                $product = $db->fetch(
                    'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug
                     FROM products p
                     JOIN categories c ON c.id = p.category_id
                     LEFT JOIN categories pc ON c.parent_id = pc.id
                     WHERE p.id = ?',
                    [$productId]
                );
                if (!$product) {
                    continue;
                }
                $labels = QuoteLinePricing::snapshotLabels($product, (string) ($product['category_slug'] ?? ''), $unitType);
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $computedSubtotal += $lineSubtotal;
                $individualUnit = $unitType === 'caja'
                    ? round($unitPrice / max(1, (int) ($product['units_per_box'] ?? 1)), 2)
                    : round($unitPrice, 2);
                $db->insert('quote_items', [
                    'quote_id' => $quoteId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_type' => $unitType,
                    'unit_label' => $labels['unit_label'],
                    'unit_description' => $labels['unit_description'],
                    'unit_price' => round($unitPrice, 2),
                    'individual_unit_price' => $individualUnit,
                    'subtotal' => $lineSubtotal,
                    'price_field_used' => 'manual_ml',
                    'discount_applied' => null,
                    'markup_applied' => null,
                    'notes' => null,
                    'sort_order' => $sort++,
                ]);
            }
            if ($sort === 0) {
                $db->getPdo()->rollBack();
                flash('error', 'No se pudo guardar ninguna línea válida.');
                redirect('/ventas-ml/crear');
                return;
            }
            $db->update('quotes', [
                'subtotal' => round($computedSubtotal, 2),
                'ml_sale_total' => round($mlSaleTotal, 2),
                'ml_net_amount' => round($mlNetAmount, 2),
                'total' => round($mlSaleTotal, 2),
            ], 'id = :id', ['id' => $quoteId]);
            $db->getPdo()->commit();
            flash('success', 'Venta ML guardada correctamente.');
            redirect('/ventas-ml/' . $quoteId);
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'No se pudo guardar la venta ML: ' . $e->getMessage());
            redirect('/ventas-ml/crear');
        }
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $sale = $db->fetch(
            'SELECT q.*
             FROM quotes q
             WHERE q.id = ? AND COALESCE(q.is_mercadolibre, 0) = 1',
            [(int) $id]
        );
        if (!$sale) {
            flash('error', 'Venta ML no encontrada.');
            redirect('/ventas-ml');
            return;
        }
        $items = $db->fetchAll(
            'SELECT qi.*, p.code, p.name
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             WHERE qi.quote_id = ?
             ORDER BY qi.sort_order, qi.id',
            [(int) $id]
        );
        $stats = $this->buildQuoteStats($db, (int) $id);
        $this->view('ventas-ml/show', [
            'title' => 'Venta ML ' . ($sale['quote_number'] ?? ''),
            'sale' => $sale,
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    private function ensureMercadoLibreClient(Database $db): int
    {
        $row = $db->fetch(
            "SELECT id FROM clients WHERE LOWER(name) = 'mercadolibre' OR LOWER(name) = 'mercado libre' LIMIT 1"
        );
        if ($row) {
            return (int) $row['id'];
        }

        return $db->insert('clients', [
            'name' => 'MercadoLibre',
            'business_name' => 'MercadoLibre',
            'notes' => 'Cliente automático para ventas ML',
            'is_active' => 1,
        ]);
    }

    /** @return array{products_count:int,ml_costs:float,gain:float,items_total:float} */
    private function buildQuoteStats(Database $db, int $quoteId): array
    {
        $items = $db->fetchAll(
            'SELECT qi.quantity, qi.unit_type, qi.subtotal,
                    p.*, COALESCE(pc.slug, c.slug) AS category_slug,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup
             FROM quote_items qi
             JOIN products p ON p.id = qi.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE qi.quote_id = ?',
            [$quoteId]
        );
        $itemsTotal = 0.0;
        $costTotal = 0.0;
        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitType = QuoteLinePricing::normalizeUnitType((string) ($item['unit_type'] ?? 'caja'));
            $slug = strtolower((string) ($item['category_slug'] ?? ''));
            $resolved = QuoteLinePricing::resolveListaForQuote($item, $slug, $unitType);
            $calc = PricingEngine::calculateWithListaSeiq((float) $resolved['lista_seiq'], $item, null, false);
            $costTotal += round((float) ($calc['costo'] ?? 0) * $quantity, 2);
            $itemsTotal += (float) ($item['subtotal'] ?? 0);
        }
        $sale = $db->fetch('SELECT ml_sale_total, ml_net_amount FROM quotes WHERE id = ?', [$quoteId]) ?? [];
        $totalMl = (float) ($sale['ml_sale_total'] ?? $itemsTotal);
        $neto = (float) ($sale['ml_net_amount'] ?? 0);
        $mlCosts = round($totalMl - $neto, 2);
        $gain = round($neto - $costTotal, 2);

        return [
            'products_count' => count($items),
            'ml_costs' => $mlCosts,
            'gain' => $gain,
            'items_total' => round($itemsTotal, 2),
        ];
    }

    private function parseRequiredMoney(mixed $value): ?float
    {
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

    private function nextQuoteNumber(Database $db): string
    {
        $prefix = setting('quote_prefix', 'LO') ?? 'LO';
        $year = (int) date('Y');
        $like = $prefix . '-' . $year . '-%';
        $last = $db->fetchColumn(
            'SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$like]
        );
        $n = 0;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $n = (int) $m[1];
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $n + 1);
    }
}
