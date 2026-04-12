<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PriceListController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT * FROM price_lists ORDER BY created_at DESC');
        $this->view('pricelists/index', ['title' => 'Listas de precios', 'lists' => $rows]);
    }

    public function generateForm(): void
    {
        $db = Database::getInstance();
        $categories = $db->fetchAll('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        $this->view('pricelists/generate', ['title' => 'Generar lista', 'categories' => $categories]);
    }

    public function preview(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/listas/generar');
        }
        $bundle = $this->collectGenerateInput();
        if ($bundle['error']) {
            flash('error', $bundle['error']);
            redirect('/listas/generar');
        }
        $this->view('pricelists/preview', [
            'title' => 'Vista previa — ' . $bundle['name'],
            'bundle' => $bundle,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/listas/generar');
        }
        $bundle = $this->collectGenerateInput();
        if ($bundle['error']) {
            flash('error', $bundle['error']);
            redirect('/listas/generar');
        }
        $db = Database::getInstance();
        $catFilter = json_encode($bundle['category_ids'], JSON_THROW_ON_ERROR);
        $listId = $db->insert('price_lists', [
            'name' => $bundle['name'],
            'description' => $bundle['description'] ?: null,
            'custom_markup' => $bundle['markup'],
            'include_iva' => $bundle['include_iva'],
            'category_filter' => $catFilter,
            'status' => 'active',
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($bundle['lines'] as $line) {
            $calc = $line['calc'];
            $db->insert('price_list_items', [
                'price_list_id' => $listId,
                'product_id' => $line['product_id'],
                'precio_base_usado' => $calc['precio_lista_seiq'],
                'costo_limpia_oeste' => $calc['costo'],
                'precio_venta' => $calc['precio_venta'],
                'precio_venta_iva' => $calc['precio_con_iva'],
                'markup_applied' => $calc['markup_percent'],
                'discount_applied' => $calc['discount_percent'],
                'price_field_used' => $line['field'],
            ]);
        }

        $pdfPath = $this->renderPdf($listId, $bundle);
        $db->query('UPDATE price_lists SET pdf_path = ? WHERE id = ?', [$pdfPath, $listId]);

        flash('success', 'Lista generada y PDF guardado.');
        redirect('/listas/' . $listId);
    }

    public function show(string $id): void
    {
        $db = Database::getInstance();
        $list = $db->fetch('SELECT * FROM price_lists WHERE id = ?', [(int) $id]);
        if (!$list) {
            flash('error', 'Lista no encontrada.');
            redirect('/listas');
        }
        $items = $db->fetchAll(
            'SELECT pli.*, p.code, p.name, p.presentation, p.content, c.name AS category_name, c.slug AS category_slug
             FROM price_list_items pli
             JOIN products p ON p.id = pli.product_id
             JOIN categories c ON c.id = p.category_id
             WHERE pli.price_list_id = ?
             ORDER BY c.sort_order, p.sort_order, p.name',
            [(int) $id]
        );
        $this->view('pricelists/show', ['title' => $list['name'], 'list' => $list, 'items' => $items]);
    }

    public function downloadPdf(string $id): void
    {
        $db = Database::getInstance();
        $list = $db->fetch('SELECT * FROM price_lists WHERE id = ?', [(int) $id]);
        if (!$list || empty($list['pdf_path'])) {
            flash('error', 'PDF no disponible.');
            redirect('/listas');
        }
        $full = BASE_PATH . '/storage/pdfs/' . basename((string) $list['pdf_path']);
        if (!is_file($full)) {
            flash('error', 'Archivo no encontrado.');
            redirect('/listas/' . $id);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($full) . '"');
        readfile($full);
        exit;
    }

    /** @return array{name:string,description:string,category_ids:list<int>,markup:?float,include_iva:bool,price_field:string,lines:list<array<string,mixed>>,error:?string} */
    private function collectGenerateInput(): array
    {
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            return ['error' => 'El nombre es obligatorio.', 'name' => '', 'description' => '', 'category_ids' => [], 'markup' => null, 'include_iva' => false, 'price_field' => '', 'lines' => []];
        }
        $desc = trim((string) $this->input('description', ''));
        $cats = $_POST['category_ids'] ?? [];
        if (!is_array($cats)) {
            $cats = [];
        }
        $categoryIds = array_values(array_filter(array_map('intval', $cats)));
        if ($categoryIds === []) {
            return ['error' => 'Seleccioná al menos una categoría.', 'name' => $name, 'description' => $desc, 'category_ids' => [], 'markup' => null, 'include_iva' => false, 'price_field' => '', 'lines' => []];
        }
        $markRaw = trim((string) $this->input('custom_markup', ''));
        $markup = $markRaw === '' ? null : (float) str_replace(',', '.', $markRaw);
        $includeIva = isset($_POST['include_iva']) && (string) $_POST['include_iva'] === '1';
        $priceField = trim((string) $this->input('price_field', ''));
        if ($priceField === '') {
            return ['error' => 'Elegí el campo de precio.', 'name' => $name, 'description' => $desc, 'category_ids' => $categoryIds, 'markup' => $markup, 'include_iva' => $includeIva, 'price_field' => '', 'lines' => []];
        }

        $db = Database::getInstance();
        $in = implode(',', array_fill(0, count($categoryIds), '?'));
        $products = $db->fetchAll(
            "SELECT p.*, c.slug AS category_slug, c.name AS category_name, c.default_discount, c.default_markup AS category_default_markup
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.category_id IN ({$in})
             ORDER BY c.sort_order, p.sort_order, p.name",
            $categoryIds
        );

        $lines = [];
        foreach ($products as $p) {
            $field = $priceField;
            if (empty($p[$field])) {
                $field = PricingEngine::getPrimaryPriceField($p['category_slug']);
            }
            if (empty($p[$field])) {
                continue;
            }
            $calc = PricingEngine::calculate($p, $field, $markup, $includeIva);
            $individualVenta = QuoteLinePricing::individualUnitSellingPrice(
                $p,
                (string) $p['category_slug'],
                $markup,
                $includeIva
            );
            $lines[] = [
                'product_id' => (int) $p['id'],
                'product' => $p,
                'field' => $field,
                'calc' => $calc,
                'individual_venta' => $individualVenta,
            ];
        }

        return [
            'name' => $name,
            'description' => $desc,
            'category_ids' => $categoryIds,
            'markup' => $markup,
            'include_iva' => $includeIva,
            'price_field' => $priceField,
            'lines' => $lines,
            'error' => null,
        ];
    }

    /** @param array<string,mixed> $bundle */
    private function renderPdf(int $listId, array $bundle): string
    {
        $grouped = [];
        foreach ($bundle['lines'] as $line) {
            $cat = $line['product']['category_name'];
            $grouped[$cat][] = $line;
        }
        ob_start();
        extract([
            'listName' => $bundle['name'],
            'generatedAt' => date('d/m/Y H:i'),
            'includeIva' => $bundle['include_iva'],
            'grouped' => $grouped,
        ]);
        require APP_PATH . '/Views/pdf/pricelist.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($bundle['name']));
        $file = 'lista-' . $listId . '-' . time() . '-' . substr((string) $slug, 0, 40) . '.pdf';
        $dir = STORAGE_PATH . '/pdfs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $file;
        file_put_contents($path, $dompdf->output());
        return $file;
    }
}
