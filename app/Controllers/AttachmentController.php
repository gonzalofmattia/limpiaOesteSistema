<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class AttachmentController extends Controller
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> ext (sin punto) => mime esperado */
    private const ALLOWED = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function upload(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos');
        }
        $quoteId = (int) $this->input('quote_id', 0);
        $type = (string) $this->input('type', '');
        $notes = trim((string) $this->input('notes', ''));
        if ($quoteId <= 0 || !in_array($type, ['remito', 'factura'], true)) {
            flash('error', 'Datos inválidos.');
            redirect('/presupuestos');
        }
        $db = Database::getInstance();
        $quote = $db->fetch('SELECT id FROM quotes WHERE id = ?', [$quoteId]);
        if (!$quote) {
            flash('error', 'Presupuesto no encontrado.');
            redirect('/presupuestos');
        }
        if (!isset($_FILES['file']) || !is_array($_FILES['file']) || (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'No se recibió el archivo o hubo un error de subida.');
            redirect('/presupuestos/' . $quoteId);
        }
        $file = $_FILES['file'];
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            flash('error', 'Archivo inválido.');
            redirect('/presupuestos/' . $quoteId);
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            flash('error', 'El archivo supera el máximo de 10 MB o está vacío.');
            redirect('/presupuestos/' . $quoteId);
        }
        $origName = (string) ($file['name'] ?? 'adjunto');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            flash('error', 'Solo se permiten PDF, JPG, JPEG o PNG.');
            redirect('/presupuestos/' . $quoteId);
        }
        $mime = $this->detectMime($tmp);
        if ($mime === null || !in_array($mime, array_values(self::ALLOWED), true)) {
            flash('error', 'Tipo de archivo no permitido.');
            redirect('/presupuestos/' . $quoteId);
        }
        if ($mime !== self::ALLOWED[$ext] && !($ext === 'jpg' && $mime === 'image/jpeg')) {
            flash('error', 'La extensión no coincide con el contenido del archivo.');
            redirect('/presupuestos/' . $quoteId);
        }

        $dir = STORAGE_PATH . '/attachments';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            flash('error', 'No se pudo crear la carpeta de adjuntos.');
            redirect('/presupuestos/' . $quoteId);
        }
        $stored = sprintf(
            '%s_%d_%s_%s.%s',
            $type,
            $quoteId,
            date('YmdHis'),
            bin2hex(random_bytes(4)),
            $ext
        );
        $dest = $dir . '/' . $stored;
        if (!move_uploaded_file($tmp, $dest)) {
            flash('error', 'No se pudo guardar el archivo.');
            redirect('/presupuestos/' . $quoteId);
        }

        $db->insert('quote_attachments', [
            'quote_id' => $quoteId,
            'type' => $type,
            'original_filename' => mb_substr($origName, 0, 255, 'UTF-8'),
            'stored_filename' => $stored,
            'mime_type' => $mime,
            'file_size' => $size,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        flash('success', 'Archivo subido correctamente.');
        redirect('/presupuestos/' . $quoteId);
    }

    public function download(string $id): void
    {
        $this->serveAttachment((int) $id, false);
    }

    /** Adjunto en el navegador (Content-Disposition: inline). */
    public function inline(string $id): void
    {
        $this->serveAttachment((int) $id, true);
    }

    public function delete(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos');
        }
        $aid = (int) $id;
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM quote_attachments WHERE id = ?', [$aid]);
        if (!$row) {
            flash('error', 'Adjunto no encontrado.');
            redirect('/presupuestos');
        }
        $quoteId = (int) $row['quote_id'];
        $path = STORAGE_PATH . '/attachments/' . basename((string) $row['stored_filename']);
        $db->delete('quote_attachments', 'id = :id', ['id' => $aid]);
        if (is_file($path)) {
            @unlink($path);
        }
        flash('success', 'Adjunto eliminado.');
        redirect('/presupuestos/' . $quoteId);
    }

    private function serveAttachment(int $id, bool $inline): void
    {
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM quote_attachments WHERE id = ?', [$id]);
        if (!$row) {
            flash('error', 'Adjunto no encontrado.');
            redirect('/presupuestos');
        }
        $path = STORAGE_PATH . '/attachments/' . basename((string) $row['stored_filename']);
        if (!is_file($path)) {
            flash('error', 'El archivo ya no está en el servidor.');
            redirect('/presupuestos/' . (int) $row['quote_id']);
        }
        $mime = (string) ($row['mime_type'] ?: 'application/octet-stream');
        $orig = basename((string) $row['original_filename']);
        $orig = preg_replace('/[^\p{L}\p{N}\._\-\s\(\)]/u', '_', $orig) ?: 'adjunto';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        $disp = $inline ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disp . '; filename="' . str_replace('"', '', $orig) . '"');
        readfile($path);
        exit;
    }

    private function detectMime(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? $mime : null;
    }
}
