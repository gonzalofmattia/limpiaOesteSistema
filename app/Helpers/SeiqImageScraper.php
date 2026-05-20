<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class SeiqImageScraper
{
    private const USER_AGENT = 'LimpiaOeste-Seiq-ImageScraper/1.0';
    private const SIMILARITY_THRESHOLD = 60;

    /** @var list<string> */
    private const MATCH_ABBREVIATIONS = [
        'desengras.',
        'limp.',
        'limpiad.',
        'desinfec.',
        'gr.',
        'gral.',
    ];

    /** @var list<string> */
    public const URLS = [
        'https://seiqgroupsa.com.ar/seiq/automotor/',
        'https://seiqgroupsa.com.ar/seiq/aromatizantes/',
        'https://seiqgroupsa.com.ar/seiq/cocina/',
        'https://seiqgroupsa.com.ar/seiq/cuidado-de-manos/',
        'https://seiqgroupsa.com.ar/seiq/desengrasantes/',
        'https://seiqgroupsa.com.ar/seiq/desinfectantes/',
        'https://seiqgroupsa.com.ar/seiq/pisos/',
        'https://seiqgroupsa.com.ar/seiq/insecticidas/',
        'https://seiqgroupsa.com.ar/seiq/lavanderia/',
        'https://seiqgroupsa.com.ar/seiq/piscinas/',
        'https://seiqgroupsa.com.ar/ecomax/alimenticia-2/',
        'https://seiqgroupsa.com.ar/ecomax/sobres/',
        'https://seiqgroupsa.com.ar/ecomax/aerosoles/',
        'https://seiqgroupsa.com.ar/ecomax/alimenticia/',
    ];

    private Database $db;
    private ImageUploader $uploader;
    private string $logFile;

    /** @var list<array{id:int, name:string}> */
    private array $systemProducts = [];

    /** @var array<int, bool> */
    private array $productsWithPhotos = [];

    public function __construct(?Database $db = null, ?ImageUploader $uploader = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->uploader = $uploader ?? new ImageUploader();
        $this->logFile = rtrim((string) STORAGE_PATH, '/') . '/logs/seiq_image_import.log';
    }

    /**
     * @param callable(array<string, mixed>): void|null $onProgress
     * @param list<string>|null $urls
     * @return array{imported:int, matched:int, skipped:int, no_match:int, errors:int, urls_processed:int}
     */
    public function run(?callable $onProgress = null, ?array $urls = null, bool $persist = true): array
    {
        $this->loadSystemProducts();
        $urls = $urls ?? self::URLS;
        $stats = [
            'imported' => 0,
            'matched' => 0,
            'skipped' => 0,
            'no_match' => 0,
            'errors' => 0,
            'urls_processed' => 0,
        ];

        $emit = static function (?callable $cb, array $payload): void {
            if ($cb !== null) {
                $cb($payload);
            }
        };

        $emit($onProgress, [
            'type' => 'start',
            'total' => count($urls),
        ]);

        foreach ($urls as $index => $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            $emit($onProgress, [
                'type' => 'url_start',
                'index' => $index + 1,
                'total' => count($urls),
                'url' => $url,
            ]);

            try {
                $html = $this->fetchHtml($url);
                $extracted = $this->extractProductsFromHtml($html);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->logEntry($url, '', '', null, 0.0, 'error_fetch: ' . $e->getMessage());
                $emit($onProgress, [
                    'type' => 'url_error',
                    'url' => $url,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }

            $urlImported = 0;
            $urlMatched = 0;
            $urlFound = count($extracted);

            foreach ($extracted as $item) {
                $seiqName = (string) ($item['name'] ?? '');
                $imageUrl = (string) ($item['image_url'] ?? '');
                $match = $this->findBestMatch($seiqName);

                if ($match === null) {
                    $stats['no_match']++;
                    $this->logEntry($url, $seiqName, $imageUrl, null, 0.0, 'sin_match');
                    $emit($onProgress, [
                        'type' => 'item',
                        'url' => $url,
                        'seiq_name' => $seiqName,
                        'image_url' => $imageUrl,
                        'status' => 'sin_match',
                        'similarity' => 0,
                        'message' => 'Sin match ≥ ' . self::SIMILARITY_THRESHOLD . '%',
                    ]);
                    continue;
                }

                $stats['matched']++;
                $urlMatched++;
                $productId = (int) $match['id'];
                $similarity = (float) $match['similarity'];
                $productName = (string) $match['name'];
                $strategy = (string) ($match['strategy'] ?? '');

                if ($this->productHasPhoto($productId)) {
                    $stats['skipped']++;
                    $this->logEntry($url, $seiqName, $imageUrl, $match, $similarity, 'skipped_ya_tiene_foto');
                    $emit($onProgress, [
                        'type' => 'item',
                        'url' => $url,
                        'seiq_name' => $seiqName,
                        'image_url' => $imageUrl,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'similarity' => $similarity,
                        'strategy' => $strategy,
                        'status' => 'skipped',
                        'message' => 'Match pero ya tiene foto',
                    ]);
                    continue;
                }

                if (!$persist) {
                    $this->logEntry($url, $seiqName, $imageUrl, $match, $similarity, 'preview_match');
                    $emit($onProgress, [
                        'type' => 'item',
                        'url' => $url,
                        'seiq_name' => $seiqName,
                        'image_url' => $imageUrl,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'similarity' => $similarity,
                        'strategy' => $strategy,
                        'status' => 'preview',
                        'message' => 'Match (preview, sin importar)',
                    ]);
                    continue;
                }

                try {
                    $this->downloadAndSave($productId, $imageUrl);
                    $stats['imported']++;
                    $urlImported++;
                    $this->productsWithPhotos[$productId] = true;
                    $this->logEntry($url, $seiqName, $imageUrl, $match, $similarity, 'ok');
                    $emit($onProgress, [
                        'type' => 'item',
                        'url' => $url,
                        'seiq_name' => $seiqName,
                        'image_url' => $imageUrl,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'similarity' => $similarity,
                        'strategy' => $strategy,
                        'status' => 'ok',
                        'message' => 'Imagen importada',
                    ]);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->logEntry($url, $seiqName, $imageUrl, $match, $similarity, 'error: ' . $e->getMessage());
                    $emit($onProgress, [
                        'type' => 'item',
                        'url' => $url,
                        'seiq_name' => $seiqName,
                        'image_url' => $imageUrl,
                        'product_id' => $productId,
                        'product_name' => $productName,
                        'similarity' => $similarity,
                        'strategy' => (string) ($match['strategy'] ?? ''),
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $stats['urls_processed']++;
            $emit($onProgress, [
                'type' => 'url_done',
                'url' => $url,
                'found' => $urlFound,
                'matched' => $urlMatched,
                'imported' => $urlImported,
            ]);
        }

        $emit($onProgress, [
            'type' => 'done',
            'imported' => $stats['imported'],
            'matched' => $stats['matched'],
            'skipped' => $stats['skipped'],
            'no_match' => $stats['no_match'],
            'errors' => $stats['errors'],
            'urls_processed' => $stats['urls_processed'],
        ]);

        return $stats;
    }

    /**
     * @return list<array{name:string, image_url:string}>
     */
    public function extractProductsFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $imgNodes = $xpath->query("//span[contains(@class,'fusion-imageframe')]//img | //div[contains(@class,'fusion-imageframe')]//img");
        if ($imgNodes === false) {
            return [];
        }

        $products = [];
        $seen = [];

        foreach ($imgNodes as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }

            $imageUrl = $this->resolveImageUrl($img);
            if ($imageUrl === null || $this->shouldIgnoreImageUrl($imageUrl)) {
                continue;
            }

            $block = $this->findFusionFullwidthAncestor($img);
            if ($block === null) {
                continue;
            }

            $headingNodes = (new DOMXPath($dom))->query(".//h2[contains(@class,'content-box-heading')]", $block);
            if ($headingNodes === false || $headingNodes->length === 0) {
                continue;
            }

            $nameNode = $headingNodes->item(0);
            if (!$nameNode instanceof DOMNode) {
                continue;
            }

            $name = trim(html_entity_decode($nameNode->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name) . '|' . $imageUrl;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $products[] = [
                'name' => $name,
                'image_url' => $imageUrl,
            ];
        }

        return $products;
    }

    private function fetchHtml(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            throw new \RuntimeException('HTTP ' . $code . ($err !== '' ? ': ' . $err : ''));
        }

        return (string) $body;
    }

    private function resolveImageUrl(DOMElement $img): ?string
    {
        $srcset = trim($img->getAttribute('srcset'));
        if ($srcset !== '') {
            foreach (explode(',', $srcset) as $part) {
                $part = trim($part);
                if (!preg_match('/^(\S+)/', $part, $m)) {
                    continue;
                }
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (!preg_match('/-\d+x\d+\./', $url)) {
                    return $url;
                }
            }
        }

        $src = trim(html_entity_decode($img->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src === '') {
            return null;
        }

        return preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $src) ?? $src;
    }

    private function shouldIgnoreImageUrl(string $url): bool
    {
        $lower = mb_strtolower($url, 'UTF-8');

        return str_contains($lower, 'icono_') || str_contains($lower, 'logo');
    }

    private function findFusionFullwidthAncestor(DOMNode $node): ?DOMElement
    {
        while ($node instanceof DOMElement) {
            $class = ' ' . $node->getAttribute('class') . ' ';
            if (str_contains($class, ' fusion-fullwidth ')) {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private function loadSystemProducts(): void
    {
        $this->systemProducts = $this->db->fetchAll(
            'SELECT id, name FROM products WHERE is_active = 1 ORDER BY id ASC'
        );

        $photoRows = $this->db->fetchAll(
            'SELECT DISTINCT product_id FROM product_images'
        );
        $this->productsWithPhotos = [];
        foreach ($photoRows as $row) {
            $this->productsWithPhotos[(int) ($row['product_id'] ?? 0)] = true;
        }
    }

    private function productHasPhoto(int $productId): bool
    {
        return ($this->productsWithPhotos[$productId] ?? false) === true;
    }

    /**
     * @return array{id:int, name:string, similarity:float, strategy:string}|null
     */
    private function findBestMatch(string $seiqName): ?array
    {
        $needle = $this->normalizeForMatch($seiqName);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestPct = 0.0;

        foreach ($this->systemProducts as $product) {
            $haystack = $this->normalizeForMatch((string) ($product['name'] ?? ''));
            if ($haystack === '') {
                continue;
            }

            $score = $this->scoreMatch($needle, $haystack);
            if ($score['similarity'] > $bestPct) {
                $bestPct = $score['similarity'];
                $best = [
                    'id' => (int) ($product['id'] ?? 0),
                    'name' => (string) ($product['name'] ?? ''),
                    'similarity' => $score['similarity'],
                    'strategy' => $score['strategy'],
                ];
            }
        }

        if ($best === null || $bestPct < self::SIMILARITY_THRESHOLD) {
            return null;
        }

        return $best;
    }

    /**
     * @return array{similarity:float, strategy:string}
     */
    private function scoreMatch(string $needle, string $haystack): array
    {
        $similarPct = 0.0;
        similar_text($needle, $haystack, $similarPct);

        $bestPct = round($similarPct, 1);
        $strategy = 'similar_text';

        if (mb_strlen($needle) >= 3 && str_contains($haystack, $needle)) {
            if (90.0 > $bestPct) {
                $bestPct = 90.0;
                $strategy = 'contains';
            }
        }

        return [
            'similarity' => $bestPct,
            'strategy' => $strategy,
        ];
    }

    private function normalizeForMatch(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\([^)]*\)/u', '', $value) ?? $value;
        $value = preg_replace('/\bp\s*\/\s*/iu', ' ', $value) ?? $value;
        $value = preg_replace('/\bc\s*\/\s*/iu', ' ', $value) ?? $value;

        $value = mb_strtolower($value, 'UTF-8');

        foreach (self::MATCH_ABBREVIATIONS as $abbr) {
            $value = str_replace($abbr, ' ', $value);
        }

        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function downloadAndSave(int $productId, string $imageUrl): void
    {
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300 || $body === '') {
            throw new \RuntimeException('No se pudo descargar la imagen (HTTP ' . $code . ')' . ($err !== '' ? ': ' . $err : ''));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'seiqimg_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal.');
        }
        file_put_contents($tmp, $body);

        $mime = $this->detectImageMime($tmp);
        if ($mime === '') {
            @unlink($tmp);
            throw new \RuntimeException('El archivo descargado no es una imagen válida.');
        }

        $timestamp = time();
        $filename = 'seiq_import_' . $timestamp . '.jpg';
        $origDir = rtrim((string) STORAGE_PATH, '/') . '/products/originals/' . $productId;
        if (!is_dir($origDir) && !mkdir($origDir, 0755, true)) {
            @unlink($tmp);
            throw new \RuntimeException('No se pudo crear la carpeta de originales.');
        }

        $dest = $origDir . '/' . $filename;
        if ($mime === 'image/jpeg') {
            if (!rename($tmp, $dest)) {
                @unlink($tmp);
                throw new \RuntimeException('No se pudo guardar la imagen.');
            }
        } else {
            if (!$this->convertToJpeg($tmp, $dest)) {
                @unlink($tmp);
                throw new \RuntimeException('No se pudo convertir la imagen a JPEG.');
            }
            @unlink($tmp);
            $mime = 'image/jpeg';
        }

        $this->uploader->ensureThumbFromOriginal($productId, $filename);

        $this->db->insert('product_images', [
            'product_id' => $productId,
            'filename' => $filename,
            'original_name' => 'seiq_import_' . $timestamp . '.jpg',
            'mime_type' => $mime,
            'file_size' => (int) filesize($dest),
            'sort_order' => 0,
            'is_cover' => 1,
            'alt_text' => null,
        ]);
    }

    private function detectImageMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $detected = finfo_file($f, $path);
                finfo_close($f);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    return $detected;
                }
            }
        }
        $info = @getimagesize($path);
        if (is_array($info) && isset($info['mime']) && str_starts_with((string) $info['mime'], 'image/')) {
            return (string) $info['mime'];
        }

        return '';
    }

    private function convertToJpeg(string $sourcePath, string $destPath): bool
    {
        $info = @getimagesize($sourcePath);
        if (!is_array($info)) {
            return false;
        }
        $src = match ((string) ($info['mime'] ?? '')) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if ($src === false) {
            return false;
        }
        $ok = imagejpeg($src, $destPath, 90);
        imagedestroy($src);

        return $ok;
    }

    /**
     * @param array{id:int, name:string, similarity:float, strategy?:string}|null $match
     */
    private function logEntry(
        string $sourceUrl,
        string $seiqName,
        string $imageUrl,
        ?array $match,
        float $similarity,
        string $result
    ): void {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $systemName = $match !== null ? (string) ($match['name'] ?? '') : '';
        $strategy = $match !== null ? (string) ($match['strategy'] ?? '') : '';

        $line = sprintf(
            "[%s] url=%s seiq=%s sistema=%s estrategia=%s score=%.1f%% imagen=%s resultado=%s\n",
            date('Y-m-d H:i:s'),
            $this->logField($sourceUrl),
            $this->logField($seiqName),
            $this->logField($systemName),
            $this->logField($strategy !== '' ? $strategy : '-'),
            $similarity,
            $this->logField($imageUrl),
            $this->logField($result)
        );
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function logField(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        if ($value === '' || str_contains($value, ' ') || str_contains($value, '=')) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
