<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Envío SMTP (PHPMailer). No declarar propiedad tipada PHPMailer: al cargar esta clase
 * PHP resolvería la clase y exigiría vendor aunque solo se use el HTML estático en otro helper.
 */
class MailHelper
{
    /** @var \PHPMailer\PHPMailer\PHPMailer */
    private $mailer;

    /** @var ?string Último error SMTP (tras sendInvoice fallido) */
    private ?string $lastError = null;

    public function __construct()
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class, true)) {
            throw new \RuntimeException(
                'Falta PHPMailer. En el servidor ejecutá: composer install (o subí la carpeta vendor/ con phpmailer).'
            );
        }
        $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
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
        $this->mailer->SMTPSecure = $enc === 'tls' ? 'tls' : 'ssl';
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
            // 4 = nivel máximo de PHPMailer\PHPMailer\SMTP::DEBUG_LOWLEVEL
            $this->mailer->SMTPDebug = min(max($debug, 1), 4);
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

            $logoPath = dirname(__DIR__, 2) . '/public/assets/img/logoLimpiaOeste.png';
            $logoCid = 'lo_logo';
            if (is_readable($logoPath)) {
                $this->mailer->addEmbeddedImage($logoPath, $logoCid, 'logoLimpiaOeste.png');
                $bodyHtml = preg_replace(
                    '#\ssrc="https?://[^"]*logoLimpiaOeste\.png"#i',
                    ' src="cid:' . $logoCid . '"',
                    $bodyHtml,
                    1
                ) ?? $bodyHtml;
            }

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
}
