<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CategoryHierarchy;
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
        $categories = $db->fetchAll(
            'SELECT c.*, COALESCE(c.supplier_id, pc.supplier_id) AS resolved_supplier_id, s.name AS supplier_name, s.slug AS supplier_slug
             FROM categories c
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE c.is_active = 1
             ORDER BY s.name, c.sort_order, c.name'
        );
        $categoryTree = CategoryHierarchy::buildTree($categories);
        $suppliers = $db->fetchAll('SELECT id, name, slug FROM suppliers WHERE is_active = 1 ORDER BY name');
        $this->view('pricelists/generate', [
            'title' => 'Generar lista',
            'categories' => $categories,
            'categoryTree' => $categoryTree,
            'suppliers' => $suppliers,
        ]);
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
        $filterPayload = [
            'categories' => $bundle['category_ids'],
            'products' => $bundle['product_ids'],
        ];
        $catFilter = json_encode($filterPayload, JSON_THROW_ON_ERROR);
        $listId = $db->insert('price_lists', [
            'name' => $bundle['name'],
            'description' => $bundle['description'] ?: null,
            'custom_markup' => $bundle['markup'],
            'include_iva' => ((int) ($bundle['include_iva'] ?? 0) === 1) ? 1 : 0,
            'category_filter' => $catFilter,
            'status' => 'active',
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        $listIva = (int) ($bundle['include_iva'] ?? 0) === 1;
        foreach ($bundle['lines'] as $line) {
            $calc = $line['calc'];
            $db->insert('price_list_items', [
                'price_list_id' => $listId,
                'product_id' => $line['product_id'],
                'precio_base_usado' => $calc['precio_lista_seiq'],
                'costo_limpia_oeste' => $calc['costo'],
                'precio_venta' => $line['pack_venta_net'],
                'precio_venta_iva' => $listIva ? ($line['pack_venta_iva'] ?? null) : null,
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
            'SELECT pli.*, p.code, p.name, p.presentation, p.content, p.sale_unit_description,
                    c.name AS category_name, c.slug AS category_slug, c.presentation_info AS category_presentation_info
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

    /**
     * @return array{
     *   name:string,
     *   description:string,
     *   category_ids:list<int>,
     *   product_ids:list<int>,
     *   markup:?float,
     *   include_iva:int,
     *   price_field:string,
     *   supplier:string,
     *   lines:list<array<string,mixed>>,
     *   error:?string
     * }
     */
    private function collectGenerateInput(): array
    {
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            return ['error' => 'El nombre es obligatorio.', 'name' => '', 'description' => '', 'category_ids' => [], 'product_ids' => [], 'markup' => null, 'include_iva' => 0, 'price_field' => '', 'supplier' => '', 'lines' => []];
        }
        $desc = trim((string) $this->input('description', ''));
        $cats = $_POST['category_ids'] ?? [];
        if (!is_array($cats)) {
            $cats = [];
        }
        $categoryIdsRaw = array_values(array_filter(array_map('intval', $cats)));
        $prodPost = $_POST['product_ids'] ?? [];
        if (!is_array($prodPost)) {
            $prodPost = [];
        }
        $productIdsRaw = array_values(array_unique(array_filter(array_map('intval', $prodPost), static fn (int $id): bool => $id > 0)));
        $markRaw = trim((string) $this->input('custom_markup', ''));
        $markup = $markRaw === '' ? null : (float) str_replace(',', '.', $markRaw);
        $supplierSlug = trim((string) $this->input('supplier', ''));
        $ivaPost = $_POST['include_iva'] ?? '0';
        $includeIva = (string) $ivaPost === '1';
        $priceField = trim((string) $this->input('price_field', ''));
        if ($priceField === '') {
            return ['error' => 'Elegí el campo de precio.', 'name' => $name, 'description' => $desc, 'category_ids' => $categoryIdsRaw, 'product_ids' => $productIdsRaw, 'markup' => $markup, 'include_iva' => $includeIva ? 1 : 0, 'price_field' => '', 'supplier' => $supplierSlug, 'lines' => []];
        }

        $db = Database::getInstance();
        if ($categoryIdsRaw === [] && $supplierSlug !== '' && $productIdsRaw === []) {
            $autoCats = $db->fetchAll(
                'SELECT c.id
                 FROM categories c
                 LEFT JOIN categories pc ON c.parent_id = pc.id
                 LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
                 WHERE c.is_active = 1 AND s.slug = ?',
                [$supplierSlug]
            );
            $categoryIdsRaw = array_map(static fn (array $r): int => (int) $r['id'], $autoCats);
        }
        if ($categoryIdsRaw === [] && $productIdsRaw === []) {
            return ['error' => 'Seleccioná categorías o agregá al menos un producto a la lista.', 'name' => $name, 'description' => $desc, 'category_ids' => [], 'product_ids' => $productIdsRaw, 'markup' => null, 'include_iva' => 0, 'price_field' => $priceField, 'supplier' => $supplierSlug, 'lines' => []];
        }
        $expanded = [];
        foreach ($categoryIdsRaw as $cid) {
            foreach (CategoryHierarchy::expandFilterCategoryIds($db, $cid) as $xid) {
                $expanded[$xid] = true;
            }
        }
        $categoryIds = array_keys($expanded);

        $byId = [];
        if ($categoryIds !== []) {
            $in = implode(',', array_fill(0, count($categoryIds), '?'));
            $fromCats = $db->fetchAll(
                "SELECT p.*,
                    COALESCE(pc.slug, c.slug) AS category_slug,
                    c.name AS category_name,
                    c.presentation_info AS category_presentation_info,
                    pc.name AS parent_category_name,
                    COALESCE(pc.sort_order, c.sort_order) AS chain_parent_sort,
                    c.sort_order AS category_sort,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id,
                    s.name AS supplier_name,
                    s.slug AS supplier_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE p.is_active = 1 AND p.category_id IN ({$in})
             ORDER BY chain_parent_sort, c.parent_id IS NOT NULL, c.sort_order, p.sort_order, p.name",
                $categoryIds
            );
            foreach ($fromCats as $row) {
                $byId[(int) $row['id']] = $row;
            }
        }
        if ($productIdsRaw !== []) {
            $fromPick = $this->fetchProductsForPriceListByIds($db, $productIdsRaw);
            foreach ($fromPick as $row) {
                $id = (int) $row['id'];
                if (!isset($byId[$id])) {
                    $byId[$id] = $row;
                }
            }
        }
        $products = array_values($byId);
        $this->sortProductsForPriceList($products);

        $lines = [];
        foreach ($products as $p) {
            $field = $priceField;
            if (empty($p[$field])) {
                $field = PricingEngine::getPrimaryPriceField((string) $p['category_slug']);
            }
            if (empty($p[$field])) {
                continue;
            }
            $calc = PricingEngine::calculate($p, $field, $markup, $includeIva);
            $pp = QuoteLinePricing::priceListUnitAndPack(
                $p,
                (string) $p['category_slug'],
                $markup,
                $includeIva,
                $calc
            );
            $lines[] = [
                'product_id' => (int) $p['id'],
                'product' => $p,
                'field' => $field,
                'calc' => $calc,
                'individual_venta' => $pp['individual_venta'],
                'pack_venta' => $pp['pack_display'],
                'pack_venta_net' => $pp['pack_net'],
                'pack_venta_iva' => $pp['pack_con_iva'],
            ];
        }

        return [
            'name' => $name,
            'description' => $desc,
            'category_ids' => $categoryIdsRaw,
            'product_ids' => $productIdsRaw,
            'markup' => $markup,
            'include_iva' => $includeIva ? 1 : 0,
            'price_field' => $priceField,
            'supplier' => $supplierSlug,
            'lines' => $lines,
            'pdf_sections' => $this->buildPricelistPdfSections($lines),
            'error' => null,
        ];
    }

    /** @param list<int> $productIds @return list<array<string,mixed>> */
    private function fetchProductsForPriceListByIds(Database $db, array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($productIds), '?'));

        return $db->fetchAll(
            "SELECT p.*,
                    COALESCE(pc.slug, c.slug) AS category_slug,
                    c.name AS category_name,
                    c.presentation_info AS category_presentation_info,
                    pc.name AS parent_category_name,
                    COALESCE(pc.sort_order, c.sort_order) AS chain_parent_sort,
                    c.sort_order AS category_sort,
                    c.default_discount,
                    c.default_markup AS category_default_markup,
                    pc.default_discount AS parent_discount,
                    pc.default_markup AS parent_default_markup,
                    COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id,
                    s.name AS supplier_name,
                    s.slug AS supplier_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE p.is_active = 1 AND p.id IN ({$in})",
            $productIds
        );
    }

    /** @param list<array<string,mixed>> $products */
    private function sortProductsForPriceList(array &$products): void
    {
        usort(
            $products,
            static function (array $a, array $b): int {
                $c1 = (int) ($a['chain_parent_sort'] ?? 0);
                $c2 = (int) ($b['chain_parent_sort'] ?? 0);
                if ($c1 !== $c2) {
                    return $c1 <=> $c2;
                }
                $aSub = !empty($a['parent_category_name']);
                $bSub = !empty($b['parent_category_name']);
                if ($aSub !== $bSub) {
                    return ($aSub ? 1 : 0) <=> ($bSub ? 1 : 0);
                }
                $cs1 = (int) ($a['category_sort'] ?? 0);
                $cs2 = (int) ($b['category_sort'] ?? 0);
                if ($cs1 !== $cs2) {
                    return $cs1 <=> $cs2;
                }
                $ps1 = (int) ($a['sort_order'] ?? 0);
                $ps2 = (int) ($b['sort_order'] ?? 0);
                if ($ps1 !== $ps2) {
                    return $ps1 <=> $ps2;
                }

                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }
        );
    }

    /**
     * @param list<array<string,mixed>> $lines
     * @return list<array{parent:string,blocks:list<array{subtitle:?string,lines:list<array<string,mixed>>}>}>
     */
    private function buildPricelistPdfSections(array $lines): array
    {
        if ($lines === []) {
            return [];
        }
        $byParent = [];
        $order = [];
        foreach ($lines as $line) {
            /** @var array<string,mixed> $pr */
            $pr = $line['product'];
            $hasParent = !empty($pr['parent_category_name']);
            $parentTitle = $hasParent ? (string) $pr['parent_category_name'] : (string) $pr['category_name'];
            $subKey = $hasParent ? (string) $pr['category_name'] : '';
            if (!isset($byParent[$parentTitle])) {
                $order[] = $parentTitle;
                $byParent[$parentTitle] = ['_subs' => [], '_direct' => []];
            }
            if ($subKey !== '') {
                $byParent[$parentTitle]['_subs'][$subKey][] = $line;
            } else {
                $byParent[$parentTitle]['_direct'][] = $line;
            }
        }
        $out = [];
        foreach ($order as $pTitle) {
            $blk = $byParent[$pTitle];
            $blocks = [];
            foreach ($blk['_subs'] as $subTitle => $subLines) {
                $blocks[] = ['subtitle' => $subTitle, 'lines' => $subLines];
            }
            if ($blk['_direct'] !== []) {
                $blocks[] = ['subtitle' => null, 'lines' => $blk['_direct']];
            }
            $out[] = ['parent' => $pTitle, 'blocks' => $blocks];
        }

        return $out;
    }

    /** @param array<string,mixed> $bundle */
    private function renderPdf(int $listId, array $bundle): string
    {
        $pdfSections = $bundle['pdf_sections'] ?? $this->buildPricelistPdfSections($bundle['lines']);
        ob_start();
        extract([
            'listName' => $bundle['name'],
            'generatedAt' => date('d/m/Y H:i'),
            'includeIva' => ((int) ($bundle['include_iva'] ?? 0) === 1),
            'pdfSections' => $pdfSections,
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
