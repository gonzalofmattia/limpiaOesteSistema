<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

final class MlImageImporter
{
    private const ML_SITE = 'MLA';
    private const USER_AGENT = 'LimpiaOeste-ML-ImageImport/1.0';
    private const REQUEST_DELAY_US = 500_000;

    /** @var array<string, string> */
    private const ABBREVIATIONS = [
        'abrill.' => 'abrillantador',
        'abrill' => 'abrillantador',
        'sup.' => 'superficies',
        'maq.' => 'maquina',
        'limpiad.' => 'limpiador',
        'desengras.' => 'desengrasante',
        'bact.' => 'bactericida',
    ];

    /** @var list<string> */
    private const STOP_WORDS = [
        'de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'o', 'u', 'para', 'con', 'en', 'a', 'seiq',
    ];

    private Database $db;
    private ImageUploader $uploader;
    private string $logFile;

    public function __construct(?Database $db = null, ?ImageUploader $uploader = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->uploader = $uploader ?? new ImageUploader();
        $this->logFile = rtrim((string) STORAGE_PATH, '/') . '/logs/ml_image_import.log';
    }

    /** @return list<array{id:int, name:string}> */
    public function getActiveProductsWithoutPhotos(?int $limit = null): array
    {
        $sql = 'SELECT p.id, p.name
             FROM products p
             WHERE p.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM product_images pi WHERE pi.product_id = p.id
               )
             ORDER BY p.name ASC, p.id ASC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return $this->db->fetchAll($sql);
    }

    public function countActiveWithoutPhotos(): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*)
             FROM products p
             WHERE p.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM product_images pi WHERE pi.product_id = p.id
               )'
        );
    }

    /** @return list<array{id:int, name:string, has_photo:int}> */
    public function getActiveProductsWithPhotoStatus(): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.name,
                    CASE WHEN EXISTS (
                        SELECT 1 FROM product_images pi WHERE pi.product_id = p.id
                    ) THEN 1 ELSE 0 END AS has_photo
             FROM products p
             WHERE p.is_active = 1
             ORDER BY has_photo ASC, p.name ASC, p.id ASC'
        );
    }

    /**
     * @param array{id:int|string, name:string} $product
     * @return array{status:string, search_term:string, image_url:string, message:string, image_id?:int}
     */
    public function importProduct(array $product): array
    {
        $productId = (int) ($product['id'] ?? 0);
        $productName = trim((string) ($product['name'] ?? ''));

        if ($productId <= 0 || $productName === '') {
            $result = [
                'status' => 'error',
                'search_term' => '',
                'image_url' => '',
                'message' => 'Producto inválido',
            ];
            $this->logImport($productId, $productName, '', '', 'error');
            return $result;
        }

        $existing = $this->db->fetchColumn(
            'SELECT COUNT(*) FROM product_images WHERE product_id = ?',
            [$productId]
        );
        if ((int) $existing > 0) {
            $result = [
                'status' => 'skipped',
                'search_term' => '',
                'image_url' => '',
                'message' => 'El producto ya tiene fotos',
            ];
            $this->logImport($productId, $productName, '', '', 'skipped');
            return $result;
        }

        $searchResult = $this->findImageUrlForProduct($productName);
        $searchTerm = $searchResult['search_term'];
        $imageUrl = $searchResult['image_url'];

        if ($imageUrl === '') {
            $result = [
                'status' => 'no_encontrado',
                'search_term' => $searchTerm,
                'image_url' => '',
                'message' => 'Sin coincidencias con imágenes en ML',
            ];
            $this->logImport($productId, $productName, $searchTerm, '', 'no_encontrado');
            return $result;
        }

        try {
            $saved = $this->downloadAndSave($productId, $imageUrl);
            $imageId = $this->db->insert('product_images', [
                'product_id' => $productId,
                'filename' => $saved['filename'],
                'original_name' => 'ml_import_' . $saved['timestamp'] . '.jpg',
                'mime_type' => $saved['mime_type'],
                'file_size' => $saved['file_size'],
                'sort_order' => 0,
                'is_cover' => 1,
                'alt_text' => null,
            ]);

            $result = [
                'status' => 'ok',
                'search_term' => $searchTerm,
                'image_url' => $imageUrl,
                'message' => 'Imagen importada',
                'image_id' => (int) $imageId,
            ];
            $this->logImport($productId, $productName, $searchTerm, $imageUrl, 'ok');
            return $result;
        } catch (\Throwable $e) {
            $result = [
                'status' => 'error',
                'search_term' => $searchTerm,
                'image_url' => $imageUrl,
                'message' => $e->getMessage(),
            ];
            $this->logImport($productId, $productName, $searchTerm, $imageUrl, 'error: ' . $e->getMessage());
            return $result;
        }
    }

    /**
     * @return array{search_term:string, image_url:string}
     */
    private function findImageUrlForProduct(string $productName): array
    {
        $cleaned = $this->cleanProductName($productName);
        $primaryTerm = $this->appendSeiq($cleaned);
        $imageUrl = $this->findImageUrl($primaryTerm);

        if ($imageUrl !== '') {
            return [
                'search_term' => $primaryTerm,
                'image_url' => $imageUrl,
            ];
        }

        $fallbackTerm = $this->buildFallbackSearchTerm($cleaned);
        if ($this->normalizeSearchTerm($fallbackTerm) !== $this->normalizeSearchTerm($primaryTerm)) {
            $imageUrl = $this->findImageUrl($fallbackTerm);
            if ($imageUrl !== '') {
                return [
                    'search_term' => $fallbackTerm,
                    'image_url' => $imageUrl,
                ];
            }

            return [
                'search_term' => $primaryTerm . ' | ' . $fallbackTerm,
                'image_url' => '',
            ];
        }

        return [
            'search_term' => $primaryTerm,
            'image_url' => '',
        ];
    }

    private function cleanProductName(string $name): string
    {
        $name = preg_replace('/\([^)]*\)/u', '', $name) ?? $name;
        $name = preg_replace('/\bp\s*\/\s*/iu', 'para ', $name) ?? $name;

        $lower = mb_strtolower(trim($name), 'UTF-8');
        foreach (self::ABBREVIATIONS as $abbr => $expansion) {
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($abbr, '/') . '(?![\p{L}\p{N}])/u';
            $lower = preg_replace($pattern, $expansion, $lower) ?? $lower;
        }

        $lower = preg_replace('/[^\p{L}\p{N}\s.\/]/u', ' ', $lower) ?? $lower;
        $lower = preg_replace('/\s+/u', ' ', $lower) ?? $lower;

        return trim($lower);
    }

    private function appendSeiq(string $term): string
    {
        $term = trim($term);
        if ($term === '') {
            return 'seiq';
        }
        if (preg_match('/\bseiq\b/u', $term) === 1) {
            return $term;
        }

        return $term . ' seiq';
    }

    private function buildFallbackSearchTerm(string $cleanedName): string
    {
        $words = preg_split('/\s+/u', trim($cleanedName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significant = [];

        foreach ($words as $word) {
            $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '';
            if ($normalized === '' || mb_strlen($normalized) < 2) {
                continue;
            }
            if (in_array($normalized, self::STOP_WORDS, true)) {
                continue;
            }
            $significant[] = $normalized;
            if (count($significant) >= 3) {
                break;
            }
        }

        if ($significant === []) {
            $significant = array_slice($words, 0, min(3, count($words)));
        }

        return $this->appendSeiq(implode(' ', $significant));
    }

    private function normalizeSearchTerm(string $term): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($term), 'UTF-8')) ?? '';
    }

    private function findImageUrl(string $searchTerm): string
    {
        $searchUrl = 'https://api.mercadolibre.com/sites/' . self::ML_SITE . '/search?q='
            . rawurlencode($searchTerm) . '&limit=3';
        $searchData = $this->mlGet($searchUrl);
        if ($searchData === null) {
            return '';
        }

        foreach ($searchData['results'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemId = trim((string) ($item['id'] ?? ''));
            $thumbnail = trim((string) ($item['thumbnail'] ?? ''));
            if ($itemId === '' && $thumbnail === '') {
                continue;
            }

            $imageUrl = $thumbnail;
            if ($itemId !== '') {
                $detail = $this->mlGet('https://api.mercadolibre.com/items/' . rawurlencode($itemId));
                if (is_array($detail)) {
                    $pictures = $detail['pictures'] ?? [];
                    if (is_array($pictures) && isset($pictures[0]) && is_array($pictures[0])) {
                        $secure = trim((string) ($pictures[0]['secure_url'] ?? ''));
                        if ($secure !== '') {
                            $imageUrl = $secure;
                        }
                    }
                }
            }

            if ($imageUrl !== '') {
                return $imageUrl;
            }
        }

        return '';
    }

    /**
     * @return array{filename:string, mime_type:string, file_size:int, timestamp:int}
     */
    private function downloadAndSave(int $productId, string $imageUrl): array
    {
        $body = $this->httpGetBinary($imageUrl);
        if ($body === null || $body === '') {
            throw new \RuntimeException('No se pudo descargar la imagen.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mlimg_');
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
        $filename = 'ml_import_' . $timestamp . '.jpg';
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
            $converted = $this->convertToJpeg($tmp, $dest);
            @unlink($tmp);
            if (!$converted) {
                throw new \RuntimeException('No se pudo convertir la imagen a JPEG.');
            }
            $mime = 'image/jpeg';
        }

        $this->uploader->ensureThumbFromOriginal($productId, $filename);

        return [
            'filename' => $filename,
            'mime_type' => $mime,
            'file_size' => (int) filesize($dest),
            'timestamp' => $timestamp,
        ];
    }

    /** @return array<string, mixed>|null */
    private function mlGet(string $url): ?array
    {
        $raw = $this->httpGet($url);
        usleep(self::REQUEST_DELAY_US);
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ]);
        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code < 200 || $code >= 300) {
            return null;
        }

        return (string) $response;
    }

    private function httpGetBinary(string $url): ?string
    {
        $body = $this->httpGet($url);
        usleep(self::REQUEST_DELAY_US);
        return $body;
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

    private function logImport(int $productId, string $productName, string $searchTerm, string $imageUrl, string $result): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $line = sprintf(
            "[%s] product_id=%d producto=%s termino=%s url=%s resultado=%s\n",
            date('Y-m-d H:i:s'),
            $productId,
            $this->logField($productName),
            $this->logField($searchTerm),
            $this->logField($imageUrl),
            $this->logField($result)
        );
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function logField(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        if (str_contains($value, ' ') || str_contains($value, '=')) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
