<?php

declare(strict_types=1);

/**
 * Prueba local del scraper Seiq — solo desengrasantes, sin importar imágenes.
 * Uso: php tools/test_seiq_scraper.php
 */
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Models/Database.php';
require_once APP_PATH . '/Helpers/ImageUploader.php';
require_once APP_PATH . '/Helpers/SeiqImageScraper.php';

$url = 'https://seiqgroupsa.com.ar/seiq/desengrasantes/';
$scraper = new \App\Helpers\SeiqImageScraper();

echo "=== Test SeiqImageScraper (preview, sin importar) ===\n";
echo "URL: {$url}\n\n";

$stats = $scraper->run(
    static function (array $event): void {
        $type = (string) ($event['type'] ?? '');
        if ($type === 'item') {
            echo sprintf(
                "  [%s] Seiq: %-28s | Sistema: %-35s | %s %.1f%%\n         Img: %s\n",
                $event['status'] ?? '',
                $event['seiq_name'] ?? '',
                $event['product_name'] ?? '(sin match)',
                $event['strategy'] ?? '-',
                (float) ($event['similarity'] ?? 0),
                $event['image_url'] ?? ''
            );
        } elseif ($type === 'url_done') {
            echo sprintf(
                "\nURL finalizada: %s | encontrados=%d matched=%d\n\n",
                $event['url'] ?? '',
                (int) ($event['found'] ?? 0),
                (int) ($event['matched'] ?? 0)
            );
        } elseif ($type === 'done') {
            echo "\n=== Resumen ===\n";
            echo 'Matched: ' . ($event['matched'] ?? 0) . "\n";
            echo 'Sin match: ' . ($event['no_match'] ?? 0) . "\n";
            echo 'Log: storage/logs/seiq_image_import.log' . "\n";
        }
    },
    [$url],
    false
);

echo "\nStats: " . json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
