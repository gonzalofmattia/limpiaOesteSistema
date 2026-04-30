<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;

final class ComboController extends Controller
{
    public function index(): void
    {
        redirect('/productos');
    }

    public function create(): void
    {
        $this->view('products/combo_form', [
            'title' => 'Nuevo combo',
            'combo' => null,
            'comboProducts' => [],
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/combos/crear');
        }
        $db = Database::getInstance();
        $payload = $this->validatePayload($db);
        if ($payload['errors'] !== []) {
            flash('error', implode(' ', $payload['errors']));
            redirect('/combos/crear');
        }
        $db->getPdo()->beginTransaction();
        try {
            $comboId = $db->insert('combos', [
                'name' => $payload['name'],
                'description' => $payload['description'],
                'markup_percentage' => $payload['markup_percentage'],
                'subtotal_override' => $payload['subtotal_override'],
                'discount_percentage' => $payload['discount_percentage'],
                'is_active' => $payload['is_active'],
            ]);
            $sortProducts = $payload['products'];
            foreach ($sortProducts as $p) {
                $db->insert('combo_products', [
                    'combo_id' => $comboId,
                    'product_id' => (int) $p['product_id'],
                    'quantity' => (int) $p['quantity'],
                ]);
            }
            $db->getPdo()->commit();
            flash('success', 'Combo creado.');
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'No se pudo crear el combo: ' . $e->getMessage());
        }
        redirect('/productos?tab=combos');
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $combo = $db->fetch('SELECT * FROM combos WHERE id = ?', [(int) $id]);
        if (!$combo) {
            flash('error', 'Combo no encontrado.');
            redirect('/productos?tab=combos');
        }
        $comboProducts = $db->fetchAll(
            'SELECT cp.product_id, cp.quantity,
                    p.code, p.name, p.presentation, p.content, p.sale_unit_description,
                    COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                    c.default_markup AS category_default_markup,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                    p.precio_lista_unitario, p.precio_lista_caja, p.precio_lista_bidon,
                    p.precio_lista_litro, p.precio_lista_bulto, p.precio_lista_sobre,
                    p.discount_override, p.markup_override, p.stock_units, p.stock_committed_units
             FROM combo_products cp
             JOIN products p ON p.id = cp.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE cp.combo_id = ?
             ORDER BY cp.id ASC',
            [(int) $id]
        );
        $this->view('products/combo_form', [
            'title' => 'Editar combo',
            'combo' => $combo,
            'comboProducts' => $comboProducts,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/combos/' . (int) $id . '/editar');
        }
        $db = Database::getInstance();
        $combo = $db->fetch('SELECT id FROM combos WHERE id = ?', [(int) $id]);
        if (!$combo) {
            flash('error', 'Combo no encontrado.');
            redirect('/productos?tab=combos');
        }
        $payload = $this->validatePayload($db);
        if ($payload['errors'] !== []) {
            flash('error', implode(' ', $payload['errors']));
            redirect('/combos/' . (int) $id . '/editar');
        }
        $db->getPdo()->beginTransaction();
        try {
            $db->update('combos', [
                'name' => $payload['name'],
                'description' => $payload['description'],
                'markup_percentage' => $payload['markup_percentage'],
                'subtotal_override' => $payload['subtotal_override'],
                'discount_percentage' => $payload['discount_percentage'],
                'is_active' => $payload['is_active'],
            ], 'id = :id', ['id' => (int) $id]);
            $db->delete('combo_products', 'combo_id = :combo_id', ['combo_id' => (int) $id]);
            foreach ($payload['products'] as $p) {
                $db->insert('combo_products', [
                    'combo_id' => (int) $id,
                    'product_id' => (int) $p['product_id'],
                    'quantity' => (int) $p['quantity'],
                ]);
            }
            $db->getPdo()->commit();
            flash('success', 'Combo actualizado.');
        } catch (\Throwable $e) {
            $db->getPdo()->rollBack();
            flash('error', 'No se pudo actualizar el combo: ' . $e->getMessage());
        }
        redirect('/productos?tab=combos');
    }

    public function destroy(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos?tab=combos');
        }
        $db = Database::getInstance();
        $db->delete('combos', 'id = :id', ['id' => (int) $id]);
        flash('success', 'Combo eliminado.');
        redirect('/productos?tab=combos');
    }

    public function toggle(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos?tab=combos');
        }
        $db = Database::getInstance();
        $combo = $db->fetch('SELECT id, is_active FROM combos WHERE id = ?', [(int) $id]);
        if (!$combo) {
            flash('error', 'Combo no encontrado.');
            redirect('/productos?tab=combos');
        }
        $db->update('combos', ['is_active' => (int) $combo['is_active'] === 1 ? 0 : 1], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Estado del combo actualizado.');
        redirect('/productos?tab=combos');
    }

    public function apiList(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT c.id, c.name, c.markup_percentage, c.subtotal_override, c.discount_percentage,
                    COUNT(cp.id) AS products_count
             FROM combos c
             LEFT JOIN combo_products cp ON cp.combo_id = c.id
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.name'
        );
        $out = [];
        foreach ($rows as $row) {
            $pricing = $this->comboPricing($db, (int) $row['id'], (float) $row['markup_percentage']);
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'products_count' => (int) $row['products_count'],
                'subtotal' => $pricing['subtotal'],
                'discount_percentage' => (float) $row['discount_percentage'],
                'final_price' => $pricing['final_price'],
            ];
        }
        $this->json(['results' => $out]);
    }

    public function apiShow(string $id): void
    {
        $db = Database::getInstance();
        $combo = $db->fetch('SELECT * FROM combos WHERE id = ?' , [(int) $id]);
        if (!$combo) {
            $this->json(['error' => 'No encontrado'], 404);
            return;
        }
        $markup = $this->query('markup', '');
        $markupOverride = $markup !== '' && is_numeric(str_replace(',', '.', (string) $markup))
            ? (float) str_replace(',', '.', (string) $markup)
            : null;
        $effectiveMarkup = $markupOverride ?? (float) $combo['markup_percentage'];
        $pricing = $this->comboPricing($db, (int) $combo['id'], $effectiveMarkup);
        $this->json([
            'id' => (int) $combo['id'],
            'name' => (string) $combo['name'],
            'description' => $combo['description'],
            'markup_percentage' => (float) $combo['markup_percentage'],
            'effective_markup' => $effectiveMarkup,
            'subtotal_override' => $combo['subtotal_override'] !== null ? (float) $combo['subtotal_override'] : null,
            'discount_percentage' => (float) $combo['discount_percentage'],
            'subtotal' => $pricing['subtotal'],
            'final_price' => $pricing['final_price'],
            'savings' => $pricing['savings'],
            'products' => $pricing['products'],
        ]);
    }

    public function apiPrice(string $id): void
    {
        $db = Database::getInstance();
        $combo = $db->fetch('SELECT * FROM combos WHERE id = ? AND is_active = 1', [(int) $id]);
        if (!$combo) {
            $this->json(['error' => 'No encontrado'], 404);
            return;
        }
        $markup = $this->query('markup', '');
        $effectiveMarkup = $markup !== '' && is_numeric(str_replace(',', '.', (string) $markup))
            ? (float) str_replace(',', '.', (string) $markup)
            : (float) $combo['markup_percentage'];
        $pricing = $this->comboPricing($db, (int) $combo['id'], $effectiveMarkup);
        $this->json([
            'id' => (int) $combo['id'],
            'name' => (string) $combo['name'],
            'markup' => $effectiveMarkup,
            'subtotal' => $pricing['subtotal'],
            'final_price' => $pricing['final_price'],
            'savings' => $pricing['savings'],
        ]);
    }

    /**
     * @return array{
     *   errors: list<string>,
     *   name: string,
     *   description: ?string,
     *   markup_percentage: float,
     *   subtotal_override: ?float,
     *   discount_percentage: float,
     *   is_active: int,
     *   products: list<array{product_id:int,quantity:int}>
     * }
     */
    private function validatePayload(Database $db): array
    {
        $errors = [];
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        $markup = (float) str_replace(',', '.', trim((string) $this->input('markup_percentage', '90')));
        if ($markup < 0) {
            $markup = 0;
        }
        $discount = (float) str_replace(',', '.', trim((string) $this->input('discount_percentage', '0')));
        $discount = max(0.0, min(100.0, $discount));
        $subtotalRaw = trim((string) $this->input('subtotal_override', ''));
        $subtotalOverride = $subtotalRaw === '' ? null : (float) str_replace(',', '.', $subtotalRaw);
        $rows = $_POST['products'] ?? [];
        $products = [];
        if (!is_array($rows) || $rows === []) {
            $errors[] = 'Agregá al menos un producto.';
        } else {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid = (int) ($row['product_id'] ?? 0);
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                if ($pid <= 0) {
                    continue;
                }
                $exists = $db->fetch('SELECT id FROM products WHERE id = ?', [$pid]);
                if (!$exists) {
                    continue;
                }
                $products[] = ['product_id' => $pid, 'quantity' => $qty];
            }
            if ($products === []) {
                $errors[] = 'No hay productos válidos para guardar.';
            }
        }

        return [
            'errors' => $errors,
            'name' => $name,
            'description' => trim((string) $this->input('description', '')) ?: null,
            'markup_percentage' => round($markup, 2),
            'subtotal_override' => $subtotalOverride !== null ? round($subtotalOverride, 2) : null,
            'discount_percentage' => round($discount, 2),
            'is_active' => $this->input('is_active', '1') ? 1 : 0,
            'products' => $products,
        ];
    }

    /**
     * @return array{subtotal: float, final_price: float, savings: float, products: list<array<string,mixed>>}
     */
    private function comboPricing(Database $db, int $comboId, float $markup): array
    {
        $combo = $db->fetch('SELECT subtotal_override, discount_percentage FROM combos WHERE id = ?', [$comboId]);
        if (!$combo) {
            return ['subtotal' => 0.0, 'final_price' => 0.0, 'savings' => 0.0, 'products' => []];
        }
        $rows = $db->fetchAll(
            'SELECT cp.product_id, cp.quantity, p.code, p.name, p.presentation, p.content,
                    COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                    c.default_markup AS category_default_markup, pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    p.stock_units, COALESCE(p.stock_committed_units, 0) AS stock_committed_units,
                    p.precio_lista_unitario, p.precio_lista_caja, p.precio_lista_bidon,
                    p.precio_lista_litro, p.precio_lista_bulto, p.precio_lista_sobre,
                    p.discount_override, p.markup_override
             FROM combo_products cp
             JOIN products p ON p.id = cp.product_id
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE cp.combo_id = ?
             ORDER BY cp.id ASC',
            [$comboId]
        );

        $subtotalCalculated = 0.0;
        $detail = [];
        foreach ($rows as $row) {
            $slug = strtolower((string) $row['category_slug']);
            $unitVenta = QuoteLinePricing::individualUnitSellingPrice($row, $slug, $markup, false);
            $qty = max(1, (int) $row['quantity']);
            $unit = round($unitVenta, 2);
            $line = round($unit * $qty, 2);
            $subtotalCalculated += $line;
            $stockUnits = max(0, (int) ($row['stock_units'] ?? 0));
            $committedUnits = max(0, (int) ($row['stock_committed_units'] ?? 0));
            $detail[] = [
                'product_id' => (int) $row['product_id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'presentation' => $row['presentation'],
                'quantity' => $qty,
                'stock_units' => $stockUnits,
                'stock_committed_units' => $committedUnits,
                'stock_available_units' => max(0, $stockUnits - $committedUnits),
                'unit_price' => $unit,
                'subtotal' => $line,
            ];
        }
        $subtotal = $combo['subtotal_override'] !== null
            ? (float) $combo['subtotal_override']
            : round($subtotalCalculated, 2);
        $discount = (float) $combo['discount_percentage'];
        $savings = round($subtotal * ($discount / 100), 2);
        $final = round($subtotal - $savings, 2);

        return [
            'subtotal' => $subtotal,
            'final_price' => max(0.0, $final),
            'savings' => max(0.0, $savings),
            'products' => $detail,
        ];
    }
}
