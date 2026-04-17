<?php

declare(strict_types=1);

namespace App\Helpers;

final class ImageUploader
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private int $maxBytes;

    public function __construct(?int $maxBytes = null)
    {
        $this->maxBytes = $maxBytes ?? (5 * 1024 * 1024);
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file entrada $_FILES['images'][n] o estructura equivalente
     * @return array{filename: string, original_name: string, mime_type: string, file_size: int}
     */
    public function upload(array $file, int $productId): array
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error de subida del archivo.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Archivo temporal inválido.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxBytes) {
            throw new \RuntimeException('El archivo supera el tamaño máximo permitido.');
        }

        $mime = $this->detectMime($tmp, (string) ($file['type'] ?? ''));
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new \RuntimeException('Tipo de imagen no permitido.');
        }

        $ext = self::ALLOWED_MIMES[$mime];
        $filename = $this->uuidV4() . '.' . $ext;
        $origDir = $this->originalDir($productId);
        $thumbDir = $this->thumbDir($productId);
        if (!is_dir($origDir) && !mkdir($origDir, 0755, true)) {
            throw new \RuntimeException('No se pudo crear la carpeta de originales.');
        }
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true)) {
            throw new \RuntimeException('No se pudo crear la carpeta de miniaturas.');
        }

        $destOriginal = $origDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $destOriginal)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }

        try {
            $this->generateThumb($destOriginal, $mime, $thumbDir . '/' . $filename);
        } catch (\Throwable $e) {
            @unlink($destOriginal);
            throw new \RuntimeException('No se pudo generar la miniatura.');
        }

        $originalName = (string) ($file['name'] ?? 'imagen');
        $originalName = preg_replace('/[^\p{L}\p{N}\s._\-]/u', '', $originalName) ?: 'imagen';
        if (function_exists('mb_substr')) {
            $originalName = mb_substr($originalName, 0, 200);
        } else {
            $originalName = substr($originalName, 0, 200);
        }

        return [
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'file_size' => (int) filesize($destOriginal),
        ];
    }

    public function deleteFiles(int $productId, string $filename): void
    {
        $filename = basename($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return;
        }
        $o = $this->originalDir($productId) . '/' . $filename;
        $t = $this->thumbDir($productId) . '/' . $filename;
        if (is_file($o)) {
            @unlink($o);
        }
        if (is_file($t)) {
            @unlink($t);
        }
    }

    public function originalPath(int $productId, string $filename): string
    {
        return $this->originalDir($productId) . '/' . basename($filename);
    }

    public function thumbPath(int $productId, string $filename): string
    {
        return $this->thumbDir($productId) . '/' . basename($filename);
    }

    private function originalDir(int $productId): string
    {
        return rtrim((string) STORAGE_PATH, '/') . '/products/originals/' . $productId;
    }

    private function thumbDir(int $productId): string
    {
        return rtrim((string) STORAGE_PATH, '/') . '/products/thumbs/' . $productId;
    }

    private function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function detectMime(string $path, string $clientType): string
    {
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $detected = finfo_file($f, $path);
                finfo_close($f);
                if (is_string($detected) && $detected !== '' && isset(self::ALLOWED_MIMES[$detected])) {
                    return $detected;
                }
            }
        }
        if (isset(self::ALLOWED_MIMES[$clientType])) {
            return $clientType;
        }
        $info = @getimagesize($path);
        if (is_array($info) && isset($info['mime']) && isset(self::ALLOWED_MIMES[(string) $info['mime']])) {
            return (string) $info['mime'];
        }

        return '';
    }

    private function generateThumb(string $sourcePath, string $mime, string $destPath): void
    {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if ($src === false) {
            throw new \RuntimeException('No se pudo leer la imagen.');
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw <= 0 || $sh <= 0) {
            imagedestroy($src);
            throw new \RuntimeException('Dimensiones inválidas.');
        }

        $box = 400;
        $scale = min($box / $sw, $box / $sh);
        $nw = max(1, (int) round($sw * $scale));
        $nh = max(1, (int) round($sh * $scale));

        $scaled = imagescale($src, $nw, $nh, IMG_BILINEAR_FIXED);
        imagedestroy($src);
        if ($scaled === false) {
            throw new \RuntimeException('Fallo al escalar.');
        }

        $canvas = imagecreatetruecolor($box, $box);
        if ($canvas === false) {
            imagedestroy($scaled);
            throw new \RuntimeException('Fallo al crear lienzo.');
        }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        $ox = (int) floor(($box - $nw) / 2);
        $oy = (int) floor(($box - $nh) / 2);
        imagecopy($canvas, $scaled, $ox, $oy, 0, 0, $nw, $nh);
        imagedestroy($scaled);

        $ext = strtolower((string) pathinfo($destPath, PATHINFO_EXTENSION));
        $ok = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($canvas, $destPath, 88),
            'png' => imagepng($canvas, $destPath, 6),
            'webp' => function_exists('imagewebp') ? imagewebp($canvas, $destPath, 88) : false,
            default => false,
        };
        imagedestroy($canvas);
        if ($ok !== true) {
            throw new \RuntimeException('Fallo al escribir miniatura.');
        }
    }
}
