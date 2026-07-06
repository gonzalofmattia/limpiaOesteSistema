<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ClaudeDescriptionGenerator;
use App\Models\Database;

final class CatalogDescriptionController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();

        $stats = $db->fetch(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN COALESCE(TRIM(full_description), \'\') <> \'\' THEN 1 ELSE 0 END) AS with_description,
                SUM(CASE WHEN COALESCE(TRIM(full_description), \'\') = \'\' THEN 1 ELSE 0 END) AS without_description
             FROM products
             WHERE is_active = 1'
        );

        $products = $db->fetchAll(
            'SELECT p.id, p.name, p.short_description,
                    c.name AS category_name,
                    CASE WHEN COALESCE(TRIM(p.full_description), \'\') <> \'\' THEN 1 ELSE 0 END AS has_description
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1
             ORDER BY p.name'
        );

        $this->view('catalogo/generar_descripciones', [
            'title' => 'Generar descripciones IA',
            'subtitle' => 'Generación masiva de descripciones con Claude',
            'stats' => [
                'total' => (int) ($stats['total'] ?? 0),
                'with_description' => (int) ($stats['with_description'] ?? 0),
                'without_description' => (int) ($stats['without_description'] ?? 0),
            ],
            'products' => $products,
        ]);
    }

    public function execute(): void
    {
        if (!verifyCsrf()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);

            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        @ob_end_clean();
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', '0');
        set_time_limit(300);

        $mode = trim((string) $this->input('mode', 'missing'));
        if (!in_array($mode, ['missing', 'all'], true)) {
            $mode = 'missing';
        }

        $limitRaw = trim((string) $this->input('limit', ''));
        $limit = $limitRaw !== '' ? max(1, (int) $limitRaw) : null;

        $db = Database::getInstance();
        $sql = 'SELECT p.id, p.name
                FROM products p
                WHERE p.is_active = 1';
        if ($mode === 'missing') {
            $sql .= ' AND COALESCE(TRIM(p.full_description), \'\') = \'\'';
        }
        $sql .= ' ORDER BY p.name';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }

        $products = $db->fetchAll($sql);
        $total = count($products);
        $generated = 0;
        $errors = 0;

        $emit = static function (array $lineData): void {
            echo json_encode($lineData, JSON_UNESCAPED_UNICODE) . "\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        };

        $emit([
            'type' => 'start',
            'total' => $total,
            'mode' => $mode,
            'limit' => $limit,
        ]);

        foreach ($products as $index => $product) {
            $productId = (int) ($product['id'] ?? 0);
            $productName = (string) ($product['name'] ?? '');

            if ($index > 0) {
                usleep(500000);
            }

            try {
                $result = ClaudeDescriptionGenerator::generateForProduct($productId);

                if (!$result['success']) {
                    $errors++;
                    $errorMsg = $result['error'];
                    if (!empty($result['banned_terms'])) {
                        $errorMsg .= ' [' . implode(', ', $result['banned_terms']) . ']';
                    }
                    $emit([
                        'type' => 'progress',
                        'index' => $index + 1,
                        'total' => $total,
                        'product_id' => $productId,
                        'name' => $productName,
                        'status' => 'error',
                        'error' => $errorMsg,
                    ]);

                    continue;
                }

                $rows = $db->update(
                    'products',
                    [
                        'full_description' => $result['full_description'],
                        'short_description' => mb_substr($result['short_description'], 0, 255),
                    ],
                    'id = :id',
                    ['id' => $productId]
                );

                self::logUpdateRows($productId, $rows);

                $shortPreview = mb_substr(trim($result['short_description']), 0, 80);
                $generated++;
                $emit([
                    'type' => 'progress',
                    'index' => $index + 1,
                    'total' => $total,
                    'product_id' => $productId,
                    'name' => $productName,
                    'status' => 'ok',
                    'short' => $shortPreview,
                ]);
            } catch (\Throwable $e) {
                $errors++;
                $emit([
                    'type' => 'progress',
                    'index' => $index + 1,
                    'total' => $total,
                    'product_id' => $productId,
                    'name' => $productName,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $emit([
            'type' => 'done',
            'generated' => $generated,
            'errors' => $errors,
            'total' => $total,
        ]);
    }

    private static function logUpdateRows(int $productId, int $rows): void
    {
        $logDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/logs'
            : dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            '[%s] INFO catalog_desc UPDATE product_id=%d rows_affected=%d',
            date('Y-m-d H:i:s'),
            $productId,
            $rows
        );

        @error_log($line . PHP_EOL, 3, $logDir . '/ml_errors.log');
    }
}
