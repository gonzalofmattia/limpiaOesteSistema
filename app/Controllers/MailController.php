<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\MailHelper;
use App\Models\Database;

final class MailController extends Controller
{
    public function sendForm(string $quoteId): void
    {
        $db = Database::getInstance();
        $id = (int) $quoteId;
        $quote = $db->fetch(
            'SELECT q.*, c.name AS client_name, c.email AS client_email
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id
             WHERE q.id = ?',
            [$id]
        );
        if (!$quote) {
            flash('error', 'Presupuesto no encontrado.');
            redirect('/presupuestos');
        }
        $invoiceAttachments = [];
        if ($db->fetchColumn("SHOW TABLES LIKE 'quote_attachments'")) {
            $invoiceAttachments = $db->fetchAll(
                "SELECT id, original_filename, created_at FROM quote_attachments
                 WHERE quote_id = ? AND type = 'factura' ORDER BY created_at DESC",
                [$id]
            );
        }
        $mailHistory = [];
        if ($db->fetchColumn("SHOW TABLES LIKE 'mail_log'")) {
            $mailHistory = $db->fetchAll(
                'SELECT * FROM mail_log WHERE quote_id = ? ORDER BY sent_at DESC',
                [$id]
            );
        }
        $defaultSubject = 'Factura — Limpia Oeste — Pedido #' . (string) ($quote['quote_number'] ?? $quoteId);
        $mailPreviewHtml = MailHelper::buildInvoiceEmailHtml(
            (string) ($quote['client_name'] ?? 'Cliente'),
            (string) ($quote['quote_number'] ?? $quoteId),
            null
        );
        $this->view('mail/send-form', [
            'title' => 'Enviar factura por mail',
            'quote' => $quote,
            'invoiceAttachments' => $invoiceAttachments,
            'mailHistory' => $mailHistory,
            'defaultSubject' => $defaultSubject,
            'mailPreviewHtml' => $mailPreviewHtml,
        ]);
    }

    public function send(string $quoteId): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/presupuestos/' . $quoteId . '/enviar-mail');
        }
        $db = Database::getInstance();
        $id = (int) $quoteId;
        $quote = $db->fetch(
            'SELECT q.*, c.name AS client_name, c.email AS client_email
             FROM quotes q
             LEFT JOIN clients c ON c.id = q.client_id
             WHERE q.id = ?',
            [$id]
        );
        if (!$quote) {
            flash('error', 'Presupuesto no encontrado.');
            redirect('/presupuestos');
        }
        $clientEmail = trim((string) ($quote['client_email'] ?? ''));
        if ($clientEmail === '' || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El cliente no tiene un email válido cargado.');
            redirect('/presupuestos/' . $id . '/enviar-mail');
        }
        $attachmentId = (int) $this->input('attachment_id', 0);
        if ($attachmentId <= 0) {
            flash('error', 'Seleccioná una factura adjunta.');
            redirect('/presupuestos/' . $id . '/enviar-mail');
        }
        $att = $db->fetch(
            "SELECT * FROM quote_attachments WHERE id = ? AND quote_id = ? AND type = 'factura'",
            [$attachmentId, $id]
        );
        if (!$att) {
            flash('error', 'Adjunto no válido.');
            redirect('/presupuestos/' . $id . '/enviar-mail');
        }
        $path = STORAGE_PATH . '/attachments/' . basename((string) $att['stored_filename']);
        if (!is_file($path)) {
            flash('error', 'El archivo adjunto no está disponible en el servidor.');
            redirect('/presupuestos/' . $id . '/enviar-mail');
        }

        $subject = trim((string) $this->input('subject', ''));
        if ($subject === '') {
            $subject = 'Factura — Limpia Oeste — Pedido #' . (string) ($quote['quote_number'] ?? $quoteId);
        }
        $customMsg = trim((string) $this->input('custom_message', ''));
        $customMsg = $customMsg !== '' ? $customMsg : null;

        $clientName = (string) ($quote['client_name'] ?? 'Cliente');
        $quoteNumber = (string) ($quote['quote_number'] ?? (string) $id);
        $html = MailHelper::buildInvoiceEmailHtml($clientName, $quoteNumber, $customMsg);

        $mailer = new MailHelper();
        $ok = $mailer->sendInvoice(
            $clientEmail,
            $clientName,
            $subject,
            $html,
            $path,
            (string) ($att['original_filename'] ?? basename($path))
        );

        $detail = $mailer->getLastError();
        $errorMsg = null;
        if (!$ok) {
            $errorMsg = $detail !== null && $detail !== ''
                ? $detail
                : 'No se pudo enviar el correo. Revisá MAIL_* en .env y el log de PHP.';
        }

        if ($db->fetchColumn("SHOW TABLES LIKE 'mail_log'")) {
            $db->insert('mail_log', [
                'quote_id' => $id,
                'attachment_id' => $attachmentId,
                'to_email' => $clientEmail,
                'to_name' => $clientName,
                'subject' => mb_substr($subject, 0, 255, 'UTF-8'),
                'status' => $ok ? 'sent' : 'failed',
                'error_message' => $errorMsg,
            ]);
        }

        if ($ok) {
            flash('success', 'Mail enviado correctamente.');
        } else {
            $flashErr = $errorMsg ?? 'Error al enviar.';
            if (mb_strlen($flashErr, 'UTF-8') > 450) {
                $flashErr = mb_substr($flashErr, 0, 450, 'UTF-8') . '…';
            }
            flash('error', $flashErr);
        }
        redirect('/presupuestos/' . $id);
    }
}
