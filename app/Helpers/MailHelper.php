<?php

declare(strict_types=1);

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailHelper
{
    private PHPMailer $mailer;

    /** @var ?string Último error SMTP (tras sendInvoice fallido) */
    private ?string $lastError = null;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function configureSMTP(): void
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = Env::get('MAIL_HOST', 'mail.limpiaoeste.com.ar');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = Env::get('MAIL_USERNAME', '');
        $this->mailer->Password = Env::get('MAIL_PASSWORD', '');
        $enc = strtolower(Env::get('MAIL_ENCRYPTION', 'ssl'));
        $this->mailer->SMTPSecure = $enc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port = (int) Env::get('MAIL_PORT', '465');
        $this->mailer->CharSet = 'UTF-8';

        $timeout = (int) Env::get('MAIL_TIMEOUT', '30');
        $this->mailer->Timeout = $timeout > 0 ? $timeout : 30;

        $appUrl = rtrim(Env::get('APP_URL', ''), '/');
        $ehlo = Env::get('MAIL_EHLO_HOST', '');
        if ($ehlo === '' && $appUrl !== '') {
            $parsed = parse_url($appUrl, PHP_URL_HOST);
            $ehlo = is_string($parsed) ? $parsed : '';
        }
        if ($ehlo !== '') {
            $this->mailer->Hostname = $ehlo;
        }

        if (Env::get('MAIL_SSL_VERIFY_PEER', 'true') !== 'true') {
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $debug = (int) Env::get('MAIL_DEBUG', '0');
        if ($debug > 0) {
            $this->mailer->SMTPDebug = min(max($debug, 1), SMTP::DEBUG_LOWLEVEL);
            $this->mailer->Debugoutput = static function (string $str, int $level): void {
                error_log('[SMTP debug ' . $level . '] ' . trim($str));
            };
        }

        $this->mailer->setFrom(
            Env::get('MAIL_FROM_ADDRESS', 'gonzalo@limpiaoeste.com.ar'),
            Env::get('MAIL_FROM_NAME', 'Limpia Oeste')
        );
    }

    /**
     * Enviar factura al cliente
     */
    public function sendInvoice(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        ?string $attachmentPath = null,
        ?string $attachmentName = null
    ): bool {
        $this->lastError = null;

        if (Env::get('MAIL_PRETEND', 'false') === 'true') {
            error_log('[MAIL_PRETEND] to=' . $toEmail . ' subject=' . $subject . ' attach=' . ($attachmentPath ?? ''));
            return true;
        }

        if (trim(Env::get('MAIL_PASSWORD', '')) === '') {
            $this->lastError = 'Falta MAIL_PASSWORD en .env (o está vacía).';
            error_log('MailHelper: ' . $this->lastError);
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $bodyHtml;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));

            if ($attachmentPath !== null && $attachmentPath !== '' && file_exists($attachmentPath)) {
                $this->mailer->addAttachment($attachmentPath, $attachmentName ?? basename($attachmentPath));
            }

            $this->mailer->send();
            return true;
        } catch (\Throwable $e) {
            $this->lastError = trim($this->mailer->ErrorInfo . ' | ' . $e->getMessage());
            if ($this->lastError === '') {
                $this->lastError = $e->getMessage();
            }
            error_log('MailHelper: ' . $this->lastError);
            return false;
        }
    }

    /**
     * Generar el HTML del mail de factura
     */
    public static function buildInvoiceEmailHtml(
        string $clientName,
        string $quoteNumber,
        ?string $customMessage = null
    ): string {
        $logoUrl = rtrim(Env::get('APP_URL', ''), '/') . '/assets/img/logoLimpiaOeste.png';
        $message = $customMessage !== null && $customMessage !== ''
            ? $customMessage
            : 'Te enviamos la factura correspondiente al pedido #' . $quoteNumber . '.';

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Poppins,Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

                            <!-- Header con logo -->
                            <tr>
                                <td style="background-color:#1A6B3C;padding:24px 32px;text-align:center;">
                                    <img src="' . $logoUrl . '" alt="Limpia Oeste" style="max-height:60px;max-width:200px;" />
                                </td>
                            </tr>

                            <!-- Cuerpo -->
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="color:#1A6B3C;margin:0 0 16px 0;font-size:20px;">¡Hola ' . htmlspecialchars($clientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '!</h2>
                                    <p style="color:#333333;font-size:15px;line-height:1.6;margin:0 0 16px 0;">'
                                        . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) .
                                    '</p>
                                    <p style="color:#333333;font-size:15px;line-height:1.6;margin:0 0 8px 0;">
                                        Encontrás el documento adjunto en este mail.
                                    </p>
                                    <p style="color:#333333;font-size:15px;line-height:1.6;margin:0;">
                                        Ante cualquier consulta no dudes en escribirnos.
                                    </p>
                                </td>
                            </tr>

                            <!-- Datos de contacto -->
                            <tr>
                                <td style="padding:0 32px 32px 32px;">
                                    <table width="100%" cellpadding="12" cellspacing="0" style="background-color:#f0f7f2;border-radius:6px;">
                                        <tr>
                                            <td style="color:#1A6B3C;font-size:14px;line-height:1.5;">
                                                <strong>Limpia Oeste — Distribuidora</strong><br>
                                                📱WhatsApp: 2323-535220<br>
                                                Instagram: @limpiaOeste<br>
                                                gonzalo@limpiaoeste.com.ar
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background-color:#f8f8f8;padding:16px 32px;text-align:center;border-top:1px solid #eeeeee;">
                                    <p style="color:#999999;font-size:12px;margin:0;">
                                        Limpia Oeste — Productos de limpieza profesional · Zona Oeste GBA
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
}
