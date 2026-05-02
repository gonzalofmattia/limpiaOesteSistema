<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CategoryHierarchy;
use App\Helpers\ImageUploader;
use App\Helpers\PricingEngine;
use App\Helpers\QuoteLinePricing;
use App\Models\Database;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ProductController extends Controller
{

    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;
        $catFilter = $this->query('category_id', '');
        $supplierFilter = trim((string) $this->query('supplier', ''));
        $q = trim((string) $this->query('search', ''));
        if ($q === '') {
            $q = trim((string) $this->query('q', ''));
        }
        $status = $this->query('status', '');

        $where = ['1=1'];
        $params = [];
        if ($catFilter !== '' && is_numeric($catFilter)) {
            $ids = CategoryHierarchy::expandFilterCategoryIds($db, (int) $catFilter);
            $marks = [];
            foreach ($ids as $i => $cid) {
                $key = 'cf' . $i;
                $marks[] = ':' . $key;
                $params[$key] = $cid;
            }
            $where[] = 'p.category_id IN (' . implode(',', $marks) . ')';
        }
        if ($q !== '') {
            $where[] = '(p.code LIKE :q OR p.name LIKE :q2)';
            $params['q'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }
        if ($status === '1') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === '0') {
            $where[] = 'p.is_active = 0';
        }
        if ($supplierFilter !== '') {
            $where[] = 's.slug = :supplier_slug';
            $params['supplier_slug'] = $supplierFilter;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*)
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
             WHERE {$whereSql}",
            $params
        );
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*,
                       COALESCE(pc.slug, c.slug) AS category_slug,
                       c.name AS category_name,
                       c.default_discount,
                       c.default_markup AS category_default_markup,
                       c.markup_override AS category_markup_override,
                       pc.default_discount AS parent_discount,
                       pc.default_markup AS parent_default_markup,
                       pc.markup_override AS parent_markup_override,
                       COALESCE(c.supplier_id, pc.supplier_id) AS supplier_id,
                       s.name AS supplier_name,
                       s.slug AS supplier_slug
                FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN categories pc ON c.parent_id = pc.id
                LEFT JOIN suppliers s ON s.id = COALESCE(c.supplier_id, pc.supplier_id)
                WHERE {$whereSql}
                ORDER BY COALESCE(pc.sort_order, c.sort_order), c.parent_id IS NOT NULL, c.sort_order, p.sort_order, p.name
                LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

        $rows = $db->fetchAll($sql, $params);

        $rowsWithPricing = [];
        foreach ($rows as $row) {
            $field = PricingEngine::getPrimaryPriceField((string) $row['category_slug']);
            $calc = PricingEngine::calculate($row, $field, null, false);
            $row['_pricing'] = $calc;
            $row['_price_field'] = $field;
            $rowsWithPricing[] = $row;
        }

        $allCats = $db->fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
        $categoryTree = CategoryHierarchy::buildTree($allCats);
        $categoryFilterOptions = CategoryHierarchy::flatOptionsForSelect($categoryTree);
        $suppliers = $db->fetchAll('SELECT id, name, slug FROM suppliers WHERE is_active = 1 ORDER BY name');
        $combos = $db->fetchAll(
            'SELECT c.*,
                    COUNT(cp.id) AS products_count
             FROM combos c
             LEFT JOIN combo_products cp ON cp.combo_id = c.id
             GROUP BY c.id
             ORDER BY c.name'
        );
        foreach ($combos as &$combo) {
            $comboProducts = $db->fetchAll(
                'SELECT cp.quantity, p.*,
                        COALESCE(pc.slug, c.slug) AS category_slug, c.default_discount,
                        c.default_markup AS category_default_markup,
                        c.markup_override AS category_markup_override,
                        pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                        pc.markup_override AS parent_markup_override
                 FROM combo_products cp
                 JOIN products p ON p.id = cp.product_id
                 JOIN categories c ON c.id = p.category_id
                 LEFT JOIN categories pc ON c.parent_id = pc.id
                 WHERE cp.combo_id = ?',
                [(int) $combo['id']]
            );
            $subtotalCalc = 0.0;
            foreach ($comboProducts as $cp) {
                $slug = strtolower((string) $cp['category_slug']);
                $unitVenta = QuoteLinePricing::individualUnitSellingPrice(
                    $cp,
                    $slug,
                    (float) $combo['markup_percentage'],
                    false
                );
                $subtotalCalc += round((float) $unitVenta * max(1, (int) $cp['quantity']), 2);
            }
            $subtotal = $combo['subtotal_override'] !== null ? (float) $combo['subtotal_override'] : round($subtotalCalc, 2);
            $discount = (float) $combo['discount_percentage'];
            $combo['_subtotal'] = $subtotal;
            $combo['_final_price'] = round($subtotal * (1 - ($discount / 100)), 2);
        }
        unset($combo);

        $this->view('products/index', [
            'title' => 'Productos',
            'products' => $rowsWithPricing,
            'combos' => $combos,
            'categoryFilterOptions' => $categoryFilterOptions,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'total_pages' => $pages,
            'total' => $total,
            'filters' => [
                'category_id' => $catFilter,
                'supplier' => $supplierFilter,
                'q' => $q,
                'status' => $status,
            ],
            'suppliers' => $suppliers,
        ]);
    }

    public function create(): void
    {
        $db = Database::getInstance();
        $categories = $db->fetchAll(
            'SELECT c.*, pc.slug AS parent_slug, pc.default_discount AS parent_default_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override
             FROM categories c
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE c.is_active = 1
             ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
        foreach ($categories as &$catRow) {
            $catRow['effective_slug'] = !empty($catRow['parent_slug']) ? (string) $catRow['parent_slug'] : (string) $catRow['slug'];
        }
        unset($catRow);
        $this->view('products/form', [
            'title' => 'Nuevo producto',
            'product' => null,
            'categories' => $categories,
            'product_images' => [],
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos/crear');
        }
        $db = Database::getInstance();
        $data = $this->validateProduct($db);
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/productos/crear');
        }
        unset($data['errors']);
        $db->insert('products', $data);
        flash('success', 'Producto creado.');
        redirect('/productos');
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $product = $db->fetch(
            'SELECT p.*, COALESCE(pc.slug, c.slug) AS category_slug, c.slug AS category_leaf_slug,
                    c.default_markup AS category_default_markup,
                    c.markup_override AS category_markup_override,
                    c.default_discount,
                    pc.default_discount AS parent_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override,
                    pc.slug AS parent_slug
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE p.id = ?',
            [(int) $id]
        );
        if (!$product) {
            flash('error', 'Producto no encontrado.');
            redirect('/productos');
        }
        $categories = $db->fetchAll(
            'SELECT c.*, pc.slug AS parent_slug, pc.default_discount AS parent_default_discount, pc.default_markup AS parent_default_markup,
                    pc.markup_override AS parent_markup_override
             FROM categories c
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE c.is_active = 1
             ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
        foreach ($categories as &$catRow) {
            $catRow['effective_slug'] = !empty($catRow['parent_slug']) ? (string) $catRow['parent_slug'] : (string) $catRow['slug'];
        }
        unset($catRow);
        $productImages = $db->fetchAll(
            'SELECT id, filename, original_name, mime_type, file_size, sort_order, is_cover, alt_text
             FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC',
            [(int) $id]
        );
        $this->view('products/form', [
            'title' => 'Editar producto',
            'product' => $product,
            'categories' => $categories,
            'product_images' => $productImages,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos/' . $id . '/editar');
        }
        $db = Database::getInstance();
        $exists = $db->fetch('SELECT id FROM products WHERE id = ?', [(int) $id]);
        if (!$exists) {
            flash('error', 'No encontrado.');
            redirect('/productos');
        }
        $data = $this->validateProduct($db, (int) $id);
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/productos/' . $id . '/editar');
        }
        unset($data['errors']);
        $db->update('products', $data, 'id = :id', ['id' => (int) $id]);
        flash('success', 'Producto actualizado.');
        redirect('/productos');
    }

    public function toggle(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos');
        }
        $db = Database::getInstance();
        $p = $db->fetch('SELECT id, is_active FROM products WHERE id = ?', [(int) $id]);
        if (!$p) {
            flash('error', 'No encontrado.');
            redirect('/productos');
        }
        $db->update('products', ['is_active' => $p['is_active'] ? 0 : 1], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Estado actualizado.');
        redirect('/productos');
    }

    public function uploadImages(string $id): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'CSRF'], 419);
            return;
        }
        $pid = (int) $id;
        $db = Database::getInstance();
        $exists = $db->fetch('SELECT id FROM products WHERE id = ?', [$pid]);
        if (!$exists) {
            $this->json(['success' => false, 'error' => 'No encontrado'], 404);
            return;
        }
        $current = (int) $db->fetchColumn('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$pid]);
        if ($current >= 8) {
            $this->json(['success' => false, 'error' => 'Máximo 8 imágenes por producto.'], 400);
            return;
        }
        $files = $this->normalizeFilesInput('images');
        if ($files === []) {
            $this->json(['success' => false, 'error' => 'No se recibieron archivos.'], 400);
            return;
        }
        $slots = 8 - $current;
        $uploader = new ImageUploader();
        $hadAny = $current > 0;
        $created = [];
        $i = 0;
        foreach ($files as $file) {
            if ($i >= $slots) {
                break;
            }
            try {
                $meta = $uploader->upload($file, $pid);
            } catch (\Throwable) {
                $this->json(['success' => false, 'error' => 'No se pudo procesar una o más imágenes.'], 400);
                return;
            }
            $sort = $current + $i;
            $isCover = (!$hadAny && $i === 0) ? 1 : 0;
            $imgId = $db->insert('product_images', [
                'product_id' => $pid,
                'filename' => $meta['filename'],
                'original_name' => $meta['original_name'],
                'mime_type' => $meta['mime_type'],
                'file_size' => $meta['file_size'],
                'sort_order' => $sort,
                'is_cover' => $isCover,
                'alt_text' => null,
            ]);
            $created[] = $this->imageJsonRow($pid, $imgId, $meta['filename'], $isCover, null, $sort);
            $i++;
        }
        $this->json(['success' => true, 'images' => $created]);
    }

    public function reorderImages(string $id): void
    {
        $pid = (int) $id;
        $data = $this->readJsonBody();
        if ($data === null || !isset($data['_csrf']) || !is_string($data['_csrf'])
            || !hash_equals($_SESSION['_csrf'] ?? '', $data['_csrf'])) {
            $this->json(['success' => false, 'error' => 'CSRF'], 419);
            return;
        }
        $order = $data['order'] ?? null;
        if (!is_array($order) || $order === []) {
            $this->json(['success' => false, 'error' => 'Orden inválido'], 400);
            return;
        }
        $db = Database::getInstance();
        $ids = [];
        foreach ($order as $v) {
            if (is_numeric($v)) {
                $ids[] = (int) $v;
            }
        }
        if ($ids === []) {
            $this->json(['success' => false, 'error' => 'Orden inválido'], 400);
            return;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->fetchAll(
            "SELECT id FROM product_images WHERE product_id = ? AND id IN ({$marks})",
            array_merge([$pid], $ids)
        );
        if (count($rows) !== count($ids)) {
            $this->json(['success' => false, 'error' => 'Imágenes inválidas'], 400);
            return;
        }
        $pos = 0;
        foreach ($ids as $imgId) {
            $db->update('product_images', ['sort_order' => $pos], 'id = :id', ['id' => $imgId]);
            $pos++;
        }
        $this->json(['success' => true]);
    }

    public function setCover(string $id, string $img): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'CSRF'], 419);
            return;
        }
        $pid = (int) $id;
        $imgId = (int) $img;
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT id FROM product_images WHERE id = ? AND product_id = ?',
            [$imgId, $pid]
        );
        if (!$row) {
            $this->json(['success' => false, 'error' => 'No encontrado'], 404);
            return;
        }
        $db->update('product_images', ['is_cover' => 0], 'product_id = :pid', ['pid' => $pid]);
        $db->update('product_images', ['is_cover' => 1], 'id = :id', ['id' => $imgId]);
        $this->json(['success' => true]);
    }

    public function deleteImage(string $id, string $img): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'CSRF'], 419);
            return;
        }
        $pid = (int) $id;
        $imgId = (int) $img;
        $db = Database::getInstance();
        $row = $db->fetch(
            'SELECT id, filename, is_cover FROM product_images WHERE id = ? AND product_id = ?',
            [$imgId, $pid]
        );
        if (!$row) {
            $this->json(['success' => false, 'error' => 'No encontrado'], 404);
            return;
        }
        $uploader = new ImageUploader();
        $uploader->deleteFiles($pid, (string) $row['filename']);
        $db->delete('product_images', 'id = :id', ['id' => $imgId]);
        if (!empty($row['is_cover'])) {
            $next = $db->fetch(
                'SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1',
                [$pid]
            );
            if ($next) {
                $db->update('product_images', ['is_cover' => 1], 'id = :id', ['id' => (int) $next['id']]);
            }
        }
        $this->json(['success' => true]);
    }

    public function updateAltText(string $id, string $img): void
    {
        if (!verifyCsrf()) {
            $this->json(['success' => false, 'error' => 'CSRF'], 419);
            return;
        }
        $pid = (int) $id;
        $imgId = (int) $img;
        $alt = trim((string) $this->input('alt_text', ''));
        if (function_exists('mb_substr')) {
            $alt = mb_substr($alt, 0, 255);
        } else {
            $alt = substr($alt, 0, 255);
        }
        $alt = $alt === '' ? null : $alt;
        $db = Database::getInstance();
        $n = $db->update(
            'product_images',
            ['alt_text' => $alt],
            'id = :id AND product_id = :pid',
            ['id' => $imgId, 'pid' => $pid]
        );
        if ($n === 0) {
            $this->json(['success' => false, 'error' => 'No encontrado'], 404);
            return;
        }
        $this->json(['success' => true, 'alt_text' => $alt]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFilesInput(string $key): array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return [];
        }
        $f = $_FILES[$key];
        if (!isset($f['name']) || !is_array($f['name'])) {
            if (!is_string($f['name'] ?? null)) {
                return [];
            }
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return [];
            }

            return [[
                'name' => $f['name'],
                'type' => $f['type'] ?? '',
                'tmp_name' => $f['tmp_name'] ?? '',
                'error' => (int) ($f['error'] ?? 0),
                'size' => (int) ($f['size'] ?? 0),
            ]];
        }
        $out = [];
        $n = count($f['name']);
        for ($i = 0; $i < $n; $i++) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $out[] = [
                'name' => $f['name'][$i],
                'type' => $f['type'][$i] ?? '',
                'tmp_name' => $f['tmp_name'][$i] ?? '',
                'error' => (int) ($f['error'][$i] ?? 0),
                'size' => (int) ($f['size'][$i] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function readJsonBody(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageJsonRow(int $productId, int $imageId, string $filename, int $isCover, ?string $alt, int $sortOrder): array
    {
        return [
            'id' => $imageId,
            'filename' => $filename,
            'thumb_url' => url('/api/productos/' . $productId . '/imagen/' . $imageId . '/thumb'),
            'full_url' => url('/api/productos/' . $productId . '/imagen/' . $imageId),
            'is_cover' => $isCover,
            'alt_text' => $alt,
            'sort_order' => $sortOrder,
        ];
    }

    public function importForm(): void
    {
        $db = Database::getInstance();
        $categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name');
        $multiReport = $_SESSION['import_multi_report'] ?? null;
        unset($_SESSION['import_multi_report']);
        $this->view('products/import', [
            'title' => 'Importar productos',
            'categories' => $categories,
            'multiReport' => $multiReport,
        ]);
    }

    public function downloadImportTemplate(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            flash('error', 'No está disponible la generación de Excel. Ejecutá composer install en el proyecto.');
            redirect('/productos/importar');
        }
        try {
            $this->requireZipExtensionForXlsx('xlsx');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/productos/importar');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $headers = [
            'CODIGO',
            'DESCRIPCION',
            'EQUIVALENCIA',
            'CONTENIDO',
            'PRESENTACION',
            'UNIDADES_POR_CAJA',
            'PRECIO_UNITARIO',
            'PRECIO_CAJA',
            'PRECIO_BIDON',
            'PRECIO_LITRO',
            'PRECIO_BULTO',
            'PRECIO_SOBRE',
            'DILUCION',
            'COSTO_DE_USO',
            'EAN13',
            'PALLET',
            'ETIQUETA_VENTA',
            'DESCRIPCION_VENTA',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $examples = [
            [
                'ECOAAI01',
                'ECOMAX ABRILLANTADOR DE ACERO INOXIDABLE',
                '',
                'Aerosol 260ML/360GR',
                'PACK X 12u',
                12,
                2974.07,
                null,
                null,
                null,
                2880.76,
                null,
                null,
                null,
                '7798270221470',
                '80PACKS',
                'Pack x12',
                'Pack 12 aerosoles 260ML',
            ],
            [
                '861002',
                'DUFT SWEET (colonia Limpiador Desodorante)',
                'Flash',
                '4 x 5 Lts',
                '',
                4,
                null,
                23398.55,
                5849.64,
                1169.93,
                null,
                null,
                '1 en 20',
                58.50,
                '',
                '',
                'Caja 4x5L',
                'Caja 4 bidones x 5 Litros',
            ],
            [
                '391739',
                'DESENGRASANTE ECOMAX',
                '',
                '',
                '10 cajas x 4 Sobres',
                40,
                null,
                83495.52,
                null,
                null,
                null,
                2087.39,
                '1 en 10',
                8.35,
                '',
                '',
                'Caja',
                'Caja completa - 10 cajas x 4 Sobres',
            ],
        ];
        $rowNum = 2;
        foreach ($examples as $ex) {
            $sheet->fromArray($ex, null, 'A' . $rowNum);
            $rowNum++;
        }

        $lastCol = 'R';
        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'name' => 'Calibri',
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1A6B3C'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $numFmt = '#,##0.00';
        foreach (['G', 'H', 'I', 'J', 'K', 'L', 'N'] as $c) {
            $sheet->getStyle($c . '2:' . $c . '4')->getNumberFormat()->setFormatCode($numFmt);
        }
        $sheet->getStyle('F2:F4')->getNumberFormat()->setFormatCode('0');

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $comment = $sheet->getComment('A1');
        $comment->getText()->createTextRun('Los precios deben ser sin IVA, tal como figuran en la lista de Seiq');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla-importacion-productos-limpia-oeste.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function import(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos/importar');
        }
        $db = Database::getInstance();
        $categoryId = (int) $this->input('category_id', 0);
        $mode = (string) $this->input('mode', 'both');
        if ($categoryId <= 0 || !isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Seleccioná categoría y archivo CSV o Excel (.xlsx).');
            redirect('/productos/importar');
        }
        $cat = $db->fetch('SELECT id, slug FROM categories WHERE id = ?', [$categoryId]);
        if (!$cat) {
            flash('error', 'Categoría inválida.');
            redirect('/productos/importar');
        }
        $tmp = $_FILES['csv']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            flash('error', 'Archivo inválido.');
            redirect('/productos/importar');
        }
        $origName = (string) ($_FILES['csv']['name'] ?? '');
        $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            flash('error', 'Formato no soportado. Usá CSV (separador ;) o Excel .xlsx.');
            redirect('/productos/importar');
        }

        try {
            $parsed = $this->parseImportFile($tmp, $ext);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo leer el archivo: ' . $e->getMessage());
            redirect('/productos/importar');
        }

        $headers = $parsed['headers'];
        $dataRows = $parsed['rows'];
        if ($headers === [] || $dataRows === []) {
            flash('error', 'Archivo vacío o sin datos.');
            redirect('/productos/importar');
        }

        $map = $this->mapCsvHeaders($headers);
        if (!isset($map['code'])) {
            flash('error', 'No se encontró columna de código (CODIGO / code / codigo).');
            redirect('/productos/importar');
        }

        $created = 0;
        $updated = 0;
        $errors = 0;

        foreach ($dataRows as $cols) {
            if ($this->isImportRowEmpty($cols)) {
                continue;
            }
            $row = [];
            foreach ($map as $dbCol => $idx) {
                if (isset($cols[$idx])) {
                    $v = $cols[$idx];
                    $row[$dbCol] = is_scalar($v) || $v === null
                        ? trim((string) $v)
                        : trim(json_encode($v));
                }
            }
            if (empty($row['code'])) {
                $errors++;
                continue;
            }
            $row['code'] = trim($row['code']);
            $numericFields = [
                'precio_lista_unitario', 'precio_lista_caja', 'precio_lista_bidon',
                'precio_lista_litro', 'precio_lista_bulto', 'precio_lista_sobre',
                'discount_override', 'markup_override', 'usage_cost', 'units_per_box', 'stock_units', 'sort_order',
            ];
            foreach ($numericFields as $nf) {
                if (isset($row[$nf]) && $row[$nf] !== '') {
                    $row[$nf] = $this->parseImportNumeric($row[$nf]);
                } elseif (isset($row[$nf]) && $row[$nf] === '') {
                    $row[$nf] = null;
                }
            }
            if (isset($row['is_active'])) {
                $row['is_active'] = in_array(strtolower((string) $row['is_active']), ['1', 'si', 'sí', 'yes', 'true'], true) ? 1 : 0;
            }
            if (isset($row['is_featured'])) {
                $row['is_featured'] = in_array(strtolower((string) $row['is_featured']), ['1', 'si', 'sí', 'yes', 'true'], true) ? 1 : 0;
            }

            $existing = $db->fetch('SELECT id FROM products WHERE code = ?', [$row['code']]);
            $payload = $this->buildProductPayloadFromImport($row, $categoryId);

            try {
                if ($existing) {
                    if ($mode === 'create') {
                        continue;
                    }
                    $db->update('products', $payload, 'id = :id', ['id' => (int) $existing['id']]);
                    $updated++;
                } else {
                    if ($mode === 'update') {
                        $errors++;
                        continue;
                    }
                    $db->insert('products', $payload);
                    $created++;
                }
            } catch (\Throwable) {
                $errors++;
            }
        }

        flash('success', "Importación: {$created} creados, {$updated} actualizados, {$errors} errores/omitidos.");
        redirect('/productos');
    }

    public function importMultiSheet(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/productos/importar');
        }
        if (!class_exists(IOFactory::class)) {
            flash('error', 'PhpSpreadsheet no está instalado. Ejecutá composer install.');
            redirect('/productos/importar');
        }
        if (!isset($_FILES['import_xlsx']) || $_FILES['import_xlsx']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Seleccioná un archivo Excel .xlsx para la importación masiva.');
            redirect('/productos/importar');
        }
        $tmp = $_FILES['import_xlsx']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            flash('error', 'Archivo inválido.');
            redirect('/productos/importar');
        }
        $ext = strtolower((string) pathinfo((string) ($_FILES['import_xlsx']['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            flash('error', 'La importación masiva solo acepta archivos .xlsx.');
            redirect('/productos/importar');
        }

        $allowCreate = isset($_POST['multi_create_categories']);
        $updateExisting = isset($_POST['multi_update_existing']);
        $deleteBefore = isset($_POST['multi_delete_before']);

        $db = Database::getInstance();
        $reportSheets = [];
        $newCategories = [];
        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $this->requireZipExtensionForXlsx('xlsx');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/productos/importar');
        }

        try {
            $spreadsheet = IOFactory::load($tmp);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo leer el Excel: ' . $e->getMessage());
            redirect('/productos/importar');
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetName = trim((string) $sheet->getTitle());
            if ($sheetName === '') {
                continue;
            }

            $rowStats = [
                'sheet' => $sheetName,
                'category' => $sheetName,
                'category_id' => 0,
                'category_state' => 'existía',
                'category_created' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'error_message' => null,
            ];

            $category = $this->findOrCreateCategoryForSheet($db, $sheetName, $allowCreate);
            if ($category === null) {
                $rowStats['category_state'] = 'error';
                $rowStats['error_message'] = 'Categoría no encontrada y no se permite crear nuevas.';
                $reportSheets[] = $rowStats;
                continue;
            }

            $rowStats['category'] = (string) $category['name'];
            $rowStats['category_id'] = (int) $category['id'];
            if (!empty($category['_was_created'])) {
                $rowStats['category_state'] = 'CREADA';
                $rowStats['category_created'] = true;
                $newCategories[(int) $category['id']] = [
                    'id' => (int) $category['id'],
                    'name' => (string) $category['name'],
                    'default_discount' => (float) ($category['default_discount'] ?? 35),
                ];
            }

            if ($deleteBefore) {
                $this->deleteProductsInCategoryClearingRelations($db, (int) $category['id']);
            }

            $headers = $this->getNormalizedHeaderRowFromSheet($sheet);
            if ($headers === [] || $this->isHeaderRowEffectivelyEmpty($headers)) {
                $reportSheets[] = $rowStats;
                continue;
            }
            $map = $this->mapCsvHeaders($headers);
            if (!isset($map['code']) || !isset($map['name'])) {
                $rowStats['errors']++;
                $rowStats['error_message'] = 'Faltan columnas CODIGO o DESCRIPCION en la fila 1.';
                $reportSheets[] = $rowStats;
                continue;
            }

            $highestRow = (int) $sheet->getHighestDataRow();
            for ($r = 2; $r <= $highestRow; $r++) {
                $row = [];
                foreach ($map as $dbCol => $idx) {
                    $row[$dbCol] = $this->readImportCellForMapping($sheet, (int) $idx, $r, $dbCol === 'ean13');
                }

                $code = trim((string) ($row['code'] ?? ''), " \t\n\r\0\x0B");
                $name = trim((string) ($row['name'] ?? ''));

                if ($this->isMultiImportRowSkippable($code, $name)) {
                    $rowStats['skipped']++;
                    continue;
                }
                $row['code'] = $code;
                $row['name'] = $name;

                if (isset($row['ean13']) && $row['ean13'] !== '') {
                    $e = preg_replace('/\s+/', '', (string) $row['ean13']);
                    $row['ean13'] = $e === '' ? null : mb_substr($e, 0, 13);
                }

                $numericFields = [
                    'precio_lista_unitario', 'precio_lista_caja', 'precio_lista_bidon',
                    'precio_lista_litro', 'precio_lista_bulto', 'precio_lista_sobre',
                    'discount_override', 'markup_override', 'usage_cost', 'units_per_box', 'stock_units', 'sort_order',
                ];
                foreach ($numericFields as $nf) {
                    if (isset($row[$nf]) && $row[$nf] !== '') {
                        $row[$nf] = $this->parseImportNumeric($row[$nf]);
                    } elseif (isset($row[$nf]) && $row[$nf] === '') {
                        $row[$nf] = null;
                    }
                }
                if (!isset($row['units_per_box']) || $row['units_per_box'] === null || $row['units_per_box'] === '') {
                    $row['units_per_box'] = 1;
                } else {
                    $row['units_per_box'] = max(1, (int) round((float) $row['units_per_box']));
                }

                if (isset($row['is_active'])) {
                    $row['is_active'] = in_array(strtolower((string) $row['is_active']), ['1', 'si', 'sí', 'yes', 'true'], true) ? 1 : 0;
                }
                if (isset($row['is_featured'])) {
                    $row['is_featured'] = in_array(strtolower((string) $row['is_featured']), ['1', 'si', 'sí', 'yes', 'true'], true) ? 1 : 0;
                }

                if (stripos($name, 'YA CON DESCUENTO') !== false) {
                    $row['discount_override'] = 0.0;
                }

                $payload = $this->buildProductPayloadFromImport($row, (int) $category['id']);

                try {
                    $existing = $db->fetch(
                        'SELECT id FROM products WHERE code = :code AND category_id = :cid',
                        ['code' => $payload['code'], 'cid' => (int) $category['id']]
                    );
                    if ($existing) {
                        if (!$updateExisting) {
                            $rowStats['skipped']++;
                            continue;
                        }
                        $db->update('products', $payload, 'id = :id', ['id' => (int) $existing['id']]);
                        $rowStats['updated']++;
                    } else {
                        $db->insert('products', $payload);
                        $rowStats['created']++;
                    }
                } catch (\Throwable) {
                    $rowStats['errors']++;
                }
            }

            $reportSheets[] = $rowStats;
            $totals['created'] += $rowStats['created'];
            $totals['updated'] += $rowStats['updated'];
            $totals['skipped'] += $rowStats['skipped'];
            $totals['errors'] += $rowStats['errors'];
        }

        $_SESSION['import_multi_report'] = [
            'sheets' => $reportSheets,
            'totals' => $totals,
            'new_categories' => array_values($newCategories),
        ];
        flash('success', 'Importación masiva finalizada. Revisá el resumen en esta pantalla.');
        redirect('/productos/importar');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOrCreateCategoryForSheet(Database $db, string $sheetName, bool $allowCreate): ?array
    {
        $knownMappings = [
            'Aerosoles ECOMAX' => 'aerosoles',
            'Bidones Institucional' => 'bidones',
            'Masivo' => 'masivo',
            'Sobres Concentrados' => 'sobres',
            'Linea Alimenticia' => 'alimenticia',
            'Línea Alimenticia' => 'alimenticia',
        ];

        if (isset($knownMappings[$sheetName])) {
            $cat = $db->fetch('SELECT * FROM categories WHERE slug = ?', [$knownMappings[$sheetName]]);
            if ($cat) {
                return $cat;
            }
        }

        $cat = $db->fetch('SELECT * FROM categories WHERE name = ?', [$sheetName]);
        if ($cat) {
            return $cat;
        }

        $likePattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $sheetName) . '%';
        $cat = $db->fetch('SELECT * FROM categories WHERE name LIKE ? LIMIT 1', [$likePattern]);
        if ($cat) {
            return $cat;
        }

        if (!$allowCreate) {
            return null;
        }

        $baseName = mb_substr($sheetName, 0, 100);
        $slug = mb_substr(slugify($baseName), 0, 100);
        $slug = $this->ensureUniqueCategorySlug($db, $slug);

        $id = $db->insert('categories', [
            'name' => $baseName,
            'slug' => $slug,
            'description' => null,
            'default_discount' => 35.00,
            'default_markup' => null,
            'presentation_info' => null,
            'sort_order' => 99,
            'is_active' => 1,
        ]);
        $cat = $db->fetch('SELECT * FROM categories WHERE id = ?', [$id]);
        if (!$cat) {
            return null;
        }
        $cat['_was_created'] = true;

        return $cat;
    }

    private function ensureUniqueCategorySlug(Database $db, string $baseSlug): string
    {
        $slug = mb_substr($baseSlug, 0, 100);
        if ($slug === '') {
            $slug = 'categoria';
        }
        $candidate = $slug;
        $n = 1;
        while ($db->fetch('SELECT id FROM categories WHERE slug = ?', [$candidate])) {
            $suffix = '-' . $n++;
            $maxBase = max(1, 100 - mb_strlen($suffix));
            $candidate = mb_substr($slug, 0, $maxBase) . $suffix;
        }

        return $candidate;
    }

    /** @param list<string> $headers */
    private function isHeaderRowEffectivelyEmpty(array $headers): bool
    {
        foreach ($headers as $h) {
            if (trim($h) !== '') {
                return false;
            }
        }

        return true;
    }

    private function getNormalizedHeaderRowFromSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $highestCol = $sheet->getHighestDataColumn(1);
        if ($highestCol === '') {
            return [];
        }
        $lastIdx = Coordinate::columnIndexFromString($highestCol);
        $headers = [];
        for ($i = 1; $i <= $lastIdx; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $raw = $sheet->getCell($letter . '1')->getValue();
            $headers[] = $this->normalizeImportHeader($raw);
        }

        return $headers;
    }

    private function readImportCellForMapping(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $columnIndex0,
        int $row,
        bool $useFormattedValue
    ): string {
        $letter = Coordinate::stringFromColumnIndex($columnIndex0 + 1);
        $cell = $sheet->getCell($letter . $row);
        if ($useFormattedValue) {
            return $this->cellToPlainString($cell->getFormattedValue());
        }

        return $this->cellToPlainString($cell->getValue());
    }

    private function cellToPlainString(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_scalar($v)) {
            return trim((string) $v);
        }
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            return trim($v->getPlainText());
        }

        return trim((string) $v);
    }

    /**
     * Borra productos de una categoría tras quitar referencias (FK sin ON DELETE CASCADE).
     */
    private function deleteProductsInCategoryClearingRelations(Database $db, int $categoryId): void
    {
        $p = ['cid' => $categoryId];
        $db->query(
            'DELETE FROM quote_items WHERE product_id IN (SELECT id FROM products WHERE category_id = :cid)',
            $p
        );
        $db->query(
            'DELETE FROM price_list_items WHERE product_id IN (SELECT id FROM products WHERE category_id = :cid)',
            $p
        );
        $db->delete('products', 'category_id = :cid', $p);
    }

    private function isMultiImportRowSkippable(string $code, string $name): bool
    {
        if ($code === '' || $name === '') {
            return true;
        }
        if (preg_match('/^lista\s*n[°ºo]?\s*/iu', $name)) {
            return true;
        }
        $up = mb_strtoupper($name);
        if (str_starts_with($up, 'IMPORTANTE:') || str_starts_with($up, 'IMPORTANTE :')) {
            return true;
        }

        return false;
    }

    /**
     * Los .xlsx son ZIP; PhpSpreadsheet requiere la extensión PHP zip (clase ZipArchive).
     *
     * @throws \RuntimeException
     */
    private function requireZipExtensionForXlsx(string $ext): void
    {
        if (strtolower($ext) !== 'xlsx') {
            return;
        }
        if (class_exists(\ZipArchive::class)) {
            return;
        }
        throw new \RuntimeException(
            'Falta la extensión PHP zip (ZipArchive), necesaria para archivos .xlsx. '
            . 'En Laragon: menú PHP → php.ini → buscá la línea `;extension=zip`, quitá el punto y coma del inicio, guardá y reiniciá Apache (o el servidor web). '
            . 'Alternativa: en `php.ini` activá `extension=php_zip.dll` según tu versión de PHP en Windows.'
        );
    }

    private function parseDecimal(string $raw): ?float
    {
        $s = trim($raw);
        $s = str_replace(['$', 'ARS', ' '], '', $s);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        return (float) $s;
    }

    private function parseImportNumeric(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return (float) $s;
        }

        return $this->parseDecimal($s);
    }

    /** @return array{headers: list<string>, rows: list<list<mixed>>} */
    private function parseImportFile(string $tmp, string $ext): array
    {
        return match ($ext) {
            'xlsx', 'xls' => $this->parseImportXlsx($tmp),
            default => $this->parseImportCsv($tmp),
        };
    }

    /** @return array{headers: list<string>, rows: list<list<mixed>>} */
    private function parseImportCsv(string $tmp): array
    {
        $content = file_get_contents($tmp);
        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el archivo.');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', $content);
        if ($lines === false || $lines === []) {
            return ['headers' => [], 'rows' => []];
        }
        $headerLine = array_shift($lines);
        $headerRaw = str_getcsv((string) $headerLine, ';');
        $headers = array_map(fn ($h) => $this->normalizeImportHeader($h), $headerRaw);
        $width = count($headers);
        $rows = [];
        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $cols = str_getcsv((string) $line, ';');
            if ($width > 0) {
                if (count($cols) < $width) {
                    $cols = array_pad($cols, $width, '');
                } elseif (count($cols) > $width) {
                    $cols = array_slice($cols, 0, $width);
                }
            }
            $rows[] = $cols;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return array{headers: list<string>, rows: list<list<mixed>>} */
    private function parseImportXlsx(string $tmp): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('PhpSpreadsheet no está instalado. Ejecutá composer install.');
        }
        $this->requireZipExtensionForXlsx('xlsx');
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        $sheet = $spreadsheet->getSheetByName('Productos');
        if ($sheet === null) {
            $sheet = $spreadsheet->getActiveSheet();
        }
        $data = $sheet->toArray();
        if ($data === []) {
            return ['headers' => [], 'rows' => []];
        }
        $headerRow = array_shift($data);
        $headers = [];
        foreach ($headerRow as $h) {
            $headers[] = $this->normalizeImportHeader($h);
        }
        $width = count($headers);
        $rows = [];
        foreach ($data as $line) {
            $line = array_values($line);
            if ($width > 0) {
                if (count($line) < $width) {
                    $line = array_pad($line, $width, '');
                } elseif (count($line) > $width) {
                    $line = array_slice($line, 0, $width);
                }
            }
            $rows[] = $line;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function normalizeImportHeader(mixed $h): string
    {
        $s = strtolower(trim((string) $h));
        $s = str_replace([' ', '-'], '_', $s);
        $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $s);

        return $s;
    }

    /** @param list<mixed> $cols */
    private function isImportRowEmpty(array $cols): bool
    {
        foreach ($cols as $c) {
            if ($c === null || $c === '') {
                continue;
            }
            if (is_string($c) && trim($c) === '') {
                continue;
            }
            if (is_numeric($c) && (float) $c === 0.0) {
                continue;
            }

            return false;
        }

        return true;
    }

    /** @param list<string> $headers */
    private function mapCsvHeaders(array $headers): array
    {
        $aliases = [
            'code' => ['code', 'codigo', 'código', 'sku'],
            'name' => ['name', 'nombre', 'descripcion', 'descripción'],
            'short_name' => ['short_name', 'nombre_corto'],
            'description' => ['description', 'detalle'],
            'content' => ['content', 'contenido'],
            'presentation' => ['presentation', 'presentacion', 'presentación'],
            'presentacion_minorista' => ['presentacion_minorista', 'presentación_minorista', 'presentacion minorista'],
            'units_per_box' => ['units_per_box', 'unidades_caja', 'unidades_por_caja'],
            'stock_units' => ['stock_units', 'stock', 'existencia', 'stock_actual'],
            'unit_volume' => ['unit_volume', 'volumen'],
            'equivalence' => ['equivalence', 'equivalencia'],
            'ean13' => ['ean13', 'ean'],
            'precio_lista_unitario' => [
                'precio_lista_unitario', 'precio unitario', 'precio_unitario',
            ],
            'precio_lista_caja' => ['precio_lista_caja', 'precio caja', 'precio_caja'],
            'precio_lista_bidon' => ['precio_lista_bidon', 'precio bidon', 'precio_bidon', 'bidón'],
            'precio_lista_litro' => ['precio_lista_litro', 'precio litro', 'precio_litro', 'precio_litro'],
            'precio_lista_bulto' => ['precio_lista_bulto', 'precio bulto', 'precio_bulto'],
            'precio_lista_sobre' => ['precio_lista_sobre', 'precio sobre', 'precio_sobre'],
            'sale_unit_label' => ['sale_unit_label', 'etiqueta_venta'],
            'sale_unit_description' => ['sale_unit_description', 'descripcion_venta', 'descripción_venta'],
            'discount_override' => ['discount_override', 'descuento_override'],
            'markup_override' => ['markup_override'],
            'dilution' => ['dilution', 'dilucion', 'dilución'],
            'usage_cost' => ['usage_cost', 'costo_uso', 'costo_de_uso'],
            'pallet_info' => ['pallet_info', 'pallet'],
            'sort_order' => ['sort_order', 'orden'],
            'is_active' => ['is_active', 'activo'],
            'is_featured' => ['is_featured', 'destacado'],
            'notes' => ['notes', 'notas'],
        ];
        $map = [];
        foreach ($aliases as $dbCol => $names) {
            foreach ($names as $n) {
                $i = array_search($n, $headers, true);
                if ($i !== false) {
                    $map[$dbCol] = $i;
                    break;
                }
            }
        }
        return $map;
    }

    /** @param array<string, string|null> $row */
    private function buildProductPayloadFromImport(array $row, int $categoryId): array
    {
        $saleUnitRaw = strtolower(trim((string) ($row['sale_unit_type'] ?? 'caja')));
        $saleUnitType = in_array($saleUnitRaw, ['caja', 'unidad'], true) ? $saleUnitRaw : 'caja';

        $defaults = [
            'category_id' => $categoryId,
            'code' => $row['code'] ?? '',
            'name' => $row['name'] ?? $row['code'],
            'short_name' => $row['short_name'] ?? null,
            'description' => $row['description'] ?? null,
            'content' => $row['content'] ?? null,
            'presentation' => $row['presentation'] ?? null,
            'presentacion_minorista' => $this->truncateNullable($row['presentacion_minorista'] ?? '', 50),
            'units_per_box' => isset($row['units_per_box']) && $row['units_per_box'] !== '' && $row['units_per_box'] !== null
                ? (int) $row['units_per_box'] : 1,
            'stock_units' => isset($row['stock_units']) && $row['stock_units'] !== '' && $row['stock_units'] !== null
                ? max(0, (int) $row['stock_units']) : 0,
            'unit_volume' => $row['unit_volume'] ?? null,
            'equivalence' => $row['equivalence'] ?? null,
            'ean13' => $row['ean13'] ?? null,
            'sale_unit_type' => $saleUnitType,
            'sale_unit_label' => trim((string) ($row['sale_unit_label'] ?? '')) !== '' ? trim((string) $row['sale_unit_label']) : 'Caja',
            'sale_unit_description' => $row['sale_unit_description'] ?? null,
            'precio_lista_unitario' => $row['precio_lista_unitario'] ?? null,
            'precio_lista_caja' => $row['precio_lista_caja'] ?? null,
            'precio_lista_bidon' => $row['precio_lista_bidon'] ?? null,
            'precio_lista_litro' => $row['precio_lista_litro'] ?? null,
            'precio_lista_bulto' => $row['precio_lista_bulto'] ?? null,
            'precio_lista_sobre' => $row['precio_lista_sobre'] ?? null,
            'discount_override' => $row['discount_override'] ?? null,
            'markup_override' => $row['markup_override'] ?? null,
            'dilution' => $row['dilution'] ?? null,
            'usage_cost' => $row['usage_cost'] ?? null,
            'pallet_info' => $row['pallet_info'] ?? null,
            'is_active' => isset($row['is_active']) ? (int) $row['is_active'] : 1,
            'is_featured' => isset($row['is_featured']) ? (int) $row['is_featured'] : 0,
            'sort_order' => isset($row['sort_order']) && $row['sort_order'] !== '' && $row['sort_order'] !== null
                ? (int) $row['sort_order'] : 0,
            'notes' => $row['notes'] ?? null,
        ];
        return $defaults;
    }

    /** @return array<string, mixed> */
    private function validateProduct(Database $db, ?int $ignoreId = null): array
    {
        $errors = [];
        $categoryId = (int) $this->input('category_id', 0);
        $cat = $db->fetch('SELECT id FROM categories WHERE id = ?', [$categoryId]);
        if (!$cat) {
            $errors[] = 'Categoría inválida.';
        }
        $code = trim((string) $this->input('code', ''));
        $name = trim((string) $this->input('name', ''));
        if ($code === '' || $name === '') {
            $errors[] = 'Código y nombre son obligatorios.';
        }
        $dup = $db->fetch('SELECT id FROM products WHERE code = ?' . ($ignoreId ? ' AND id != ?' : ''), $ignoreId ? [$code, $ignoreId] : [$code]);
        if ($dup) {
            $errors[] = 'Ya existe un producto con ese código.';
        }

        $slugInput = trim((string) $this->input('slug', ''));
        $slugBase = $slugInput !== '' ? slugify($slugInput) : slugify($name);
        if ($slugBase === '') {
            $slugBase = 'producto';
        }
        $slugBase = $this->truncateStr($slugBase, 250);
        $slug = $this->ensureUniqueProductSlug($db, $slugBase, $ignoreId);

        $num = fn (string $k) => $this->nullableFloat($this->input($k, ''));

        $disc = trim((string) $this->input('discount_override', ''));
        $mark = trim((string) $this->input('markup_override', ''));

        return array_merge([
            'errors' => $errors,
            'category_id' => $categoryId,
            'code' => $code,
            'name' => $name,
            'slug' => $slug,
            'short_name' => $this->emptyToNull($this->input('short_name', '')),
            'short_description' => $this->truncateNullable($this->input('short_description', ''), 255),
            'description' => $this->emptyToNull($this->input('description', '')),
            'full_description' => $this->emptyToNull($this->input('full_description', '')),
            'content' => $this->emptyToNull($this->input('content', '')),
            'presentation' => $this->emptyToNull($this->input('presentation', '')),
            'presentacion_minorista' => $this->truncateNullable($this->input('presentacion_minorista', ''), 50),
            'content_volume' => $this->truncateNullable($this->input('content_volume', ''), 50),
            'units_per_box' => max(1, (int) $this->input('units_per_box', 1)),
            'stock_units' => max(0, (int) $this->input('stock_units', 0)),
            'unit_volume' => $this->emptyToNull($this->input('unit_volume', '')),
            'equivalence' => $this->emptyToNull($this->input('equivalence', '')),
            'ean13' => $this->emptyToNull($this->input('ean13', '')),
            'sale_unit_type' => $this->input('sale_unit_type') === 'unidad' ? 'unidad' : 'caja',
            'sale_unit_label' => mb_substr(
                trim((string) $this->input('sale_unit_label', '')) !== ''
                    ? trim((string) $this->input('sale_unit_label', ''))
                    : 'Caja',
                0,
                50
            ),
            'sale_unit_description' => $this->truncateNullable($this->input('sale_unit_description', ''), 150),
            'precio_lista_unitario' => $num('precio_lista_unitario'),
            'precio_lista_caja' => $num('precio_lista_caja'),
            'precio_lista_bidon' => $num('precio_lista_bidon'),
            'precio_lista_litro' => $num('precio_lista_litro'),
            'precio_lista_bulto' => $num('precio_lista_bulto'),
            'precio_lista_sobre' => $num('precio_lista_sobre'),
            'discount_override' => $disc === '' ? null : (float) str_replace(',', '.', $disc),
            'markup_override' => $mark === '' ? null : (float) str_replace(',', '.', $mark),
            'dilution' => $this->emptyToNull($this->input('dilution', '')),
            'usage_cost' => $num('usage_cost'),
            'pallet_info' => $this->emptyToNull($this->input('pallet_info', '')),
            'is_active' => $this->input('is_active') ? 1 : 0,
            'is_featured' => $this->input('is_featured') ? 1 : 0,
            'is_published' => $this->input('is_published') ? 1 : 0,
            'sort_order' => (int) $this->input('sort_order', 0),
            'notes' => $this->emptyToNull($this->input('notes', '')),
        ]);
    }

    private function emptyToNull(mixed $v): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function truncateStr(string $s, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max);
        }

        return substr($s, 0, $max);
    }

    private function ensureUniqueProductSlug(Database $db, string $baseSlug, ?int $ignoreId): string
    {
        $slug = $baseSlug;
        $n = 1;
        for ($guard = 0; $guard < 5000; $guard++) {
            $sql = 'SELECT id FROM products WHERE slug = ?';
            $params = [$slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id != ?';
                $params[] = $ignoreId;
            }
            $dup = $db->fetch($sql, $params);
            if (!$dup) {
                return $slug;
            }
            $suffix = '-' . $n++;
            $slug = $this->truncateStr($baseSlug, max(1, 250 - strlen($suffix))) . $suffix;
        }

        return $this->truncateStr($baseSlug, 240) . '-' . bin2hex(random_bytes(4));
    }

    private function truncateNullable(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max);
        }

        return substr($s, 0, $max);
    }

    private function nullableFloat(mixed $v): ?float
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $s = str_replace(['$', ' '], '', $s);
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        if (!is_numeric($s)) {
            return null;
        }
        return (float) $s;
    }
}
