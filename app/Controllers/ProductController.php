<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\PricingEngine;
use App\Models\Database;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ProductController extends Controller
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $catFilter = $this->query('category_id', '');
        $q = trim((string) $this->query('q', ''));
        $status = $this->query('status', '');

        $where = ['1=1'];
        $params = [];
        if ($catFilter !== '' && is_numeric($catFilter)) {
            $where[] = 'p.category_id = :cid';
            $params['cid'] = (int) $catFilter;
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

        $whereSql = implode(' AND ', $where);
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM products p WHERE {$whereSql}",
            $params
        );
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = "SELECT p.*, c.slug AS category_slug, c.name AS category_name, c.default_discount,
                       c.default_markup AS category_default_markup
                FROM products p
                JOIN categories c ON c.id = p.category_id
                WHERE {$whereSql}
                ORDER BY c.sort_order, p.sort_order, p.name
                LIMIT " . self::PER_PAGE . " OFFSET " . (int) $offset;

        $rows = $db->fetchAll($sql, $params);

        $rowsWithPricing = [];
        foreach ($rows as $row) {
            $field = PricingEngine::getPrimaryPriceField($row['category_slug']);
            $calc = PricingEngine::calculate($row, $field, null, false);
            $row['_pricing'] = $calc;
            $row['_price_field'] = $field;
            $rowsWithPricing[] = $row;
        }

        $categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name');

        $this->view('products/index', [
            'title' => 'Productos',
            'products' => $rowsWithPricing,
            'categories' => $categories,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [
                'category_id' => $catFilter,
                'q' => $q,
                'status' => $status,
            ],
        ]);
    }

    public function create(): void
    {
        $db = Database::getInstance();
        $categories = $db->fetchAll('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        $this->view('products/form', [
            'title' => 'Nuevo producto',
            'product' => null,
            'categories' => $categories,
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
            'SELECT p.*, c.slug AS category_slug, c.default_markup AS category_default_markup, c.default_discount
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?',
            [(int) $id]
        );
        if (!$product) {
            flash('error', 'Producto no encontrado.');
            redirect('/productos');
        }
        $categories = $db->fetchAll('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
        $this->view('products/form', [
            'title' => 'Editar producto',
            'product' => $product,
            'categories' => $categories,
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
                'discount_override', 'markup_override', 'usage_cost', 'units_per_box', 'sort_order',
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
                    'discount_override', 'markup_override', 'usage_cost', 'units_per_box', 'sort_order',
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
            'units_per_box' => ['units_per_box', 'unidades_caja', 'unidades_por_caja'],
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
            'units_per_box' => isset($row['units_per_box']) && $row['units_per_box'] !== '' && $row['units_per_box'] !== null
                ? (int) $row['units_per_box'] : 1,
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

        $num = fn (string $k) => $this->nullableFloat($this->input($k, ''));

        $disc = trim((string) $this->input('discount_override', ''));
        $mark = trim((string) $this->input('markup_override', ''));

        return array_merge([
            'errors' => $errors,
            'category_id' => $categoryId,
            'code' => $code,
            'name' => $name,
            'short_name' => $this->emptyToNull($this->input('short_name', '')),
            'description' => $this->emptyToNull($this->input('description', '')),
            'content' => $this->emptyToNull($this->input('content', '')),
            'presentation' => $this->emptyToNull($this->input('presentation', '')),
            'units_per_box' => max(1, (int) $this->input('units_per_box', 1)),
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
            'sort_order' => (int) $this->input('sort_order', 0),
            'notes' => $this->emptyToNull($this->input('notes', '')),
        ]);
    }

    private function emptyToNull(mixed $v): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : $s;
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
