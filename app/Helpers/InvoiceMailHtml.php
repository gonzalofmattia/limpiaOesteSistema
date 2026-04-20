<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * HTML del mail de factura (sin PHPMailer).
 * Así el formulario "Enviar mail" no carga vendor/phpmailer en el GET.
 */
final class InvoiceMailHtml
{
    public static function buildInvoiceEmailHtml(
        string $clientName,
        string $quoteNumber,
        ?string $customMessage = null
    ): string {
        // Misma URL que usa el sitio (APP_URL). En el envío real el logo se incrusta por CID en MailHelper.
        $logoUrl = \baseUrl('assets/img/logoLimpiaOeste.png');
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
                                <td style="padding:24px 32px;text-align:center;">
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
                                                WhatsApp: 2323-535220<br>
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
