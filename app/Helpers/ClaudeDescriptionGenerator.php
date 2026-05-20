<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Genera descripciones de producto para MercadoLibre vía Claude API.
 */
final class ClaudeDescriptionGenerator
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-sonnet-4-20250514';
    private const DESCRIPTION_HEADER = "LIMPIA OESTE\nDistribuidora de productos de limpieza e insumos de higiene profesional";

    /**
     * @return array{
     *   success: bool,
     *   descripcion: string,
     *   full_description: string,
     *   short_description: string,
     *   error: string
     * }
     */
    public static function generateForProduct(int $productId): array
    {
        $empty = [
            'success' => false,
            'descripcion' => '',
            'full_description' => '',
            'short_description' => '',
            'error' => '',
        ];

        if ($productId <= 0) {
            $empty['error'] = 'product_id inválido';

            return $empty;
        }

        $apiKey = trim(Env::get('ANTHROPIC_API_KEY'));
        if ($apiKey === '') {
            $empty['error'] = 'ANTHROPIC_API_KEY no configurada en .env';

            return $empty;
        }

        $product = Database::getInstance()->fetch(
            'SELECT p.name, p.content, p.unit_volume, p.presentation, p.presentacion_minorista,
                    p.dilution, p.equivalence,
                    c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.is_active = 1',
            [$productId]
        );

        if (!$product) {
            $empty['error'] = 'Producto no encontrado';

            return $empty;
        }

        $datos = self::buildProductDataLines($product);
        if ($datos === '') {
            $empty['error'] = 'El producto no tiene datos suficientes para generar la descripción';

            return $empty;
        }

        $prompt = 'Generá una descripción para MercadoLibre de este producto de limpieza profesional. '
            . 'Tiene que ser clara, persuasiva y en español argentino. Máximo 600 palabras. '
            . 'IMPORTANTE: hablá siempre de la unidad de venta individual (bidón, botella, sobre, aerosol, etc.). '
            . 'Nunca menciones la caja, pack, presentación mayorista ni cantidad de unidades por bulto. '
            . 'No menciones precios, costos por litro ni costos de uso de ningún tipo. '
            . 'Si hay dato de dilución, presentalo como ventaja de rendimiento (por ejemplo que rinde más que un producto común) sin calcular ni comparar costos. '
            . 'Estructura: 1) Qué es y para qué sirve. 2) Ventajas frente a productos comunes de góndola. '
            . '3) Cómo se usa. 4) Rendimiento y economía solo si aplica por dilución, sin cifras de precio. '
            . 'No agregues cierres fijos ni frases de entrega o contacto al final; terminá con el contenido del producto. '
            . 'No uses markdown ni asteriscos, solo texto plano con párrafos. '
            . 'Datos disponibles del producto: ' . $datos;

        $longResult = self::callClaude($apiKey, $prompt, 1000);
        if (!$longResult['success']) {
            $empty['error'] = $longResult['error'];

            return $empty;
        }

        $body = $longResult['text'];
        $shortResult = self::generateShortDescription($apiKey, $product);
        if (!$shortResult['success']) {
            $empty['error'] = $shortResult['error'];

            return $empty;
        }

        return [
            'success' => true,
            'descripcion' => self::prependHeader($body),
            'full_description' => $body,
            'short_description' => $shortResult['text'],
            'error' => '',
        ];
    }

    /** Quita el encabezado ML si el texto fue pegado desde la descripción de listing. */
    public static function stripMlListingHeader(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (str_starts_with($text, self::DESCRIPTION_HEADER)) {
            return trim(substr($text, strlen(self::DESCRIPTION_HEADER)));
        }

        if (preg_match('/^LIMPIA OESTE\s*\r?\nDistribuidora[^\r\n]*\r?\n?/iu', $text)) {
            return trim((string) preg_replace('/^LIMPIA OESTE\s*\r?\nDistribuidora[^\r\n]*\r?\n?/iu', '', $text));
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $product
     * @return array{success: bool, text: string, error: string}
     */
    private static function generateShortDescription(string $apiKey, array $product): array
    {
        $name = trim((string) ($product['name'] ?? ''));
        $category = trim((string) ($product['category_name'] ?? ''));
        $dilution = trim((string) ($product['dilution'] ?? ''));
        if ($dilution === '') {
            $dilution = 'no especificado';
        }

        $prompt = 'Escribí una descripción corta de 1-2 oraciones para este producto de limpieza, '
            . 'en español argentino informal. Solo describí qué es y para qué sirve. '
            . 'Sin precios, sin marcas, sin tecnicismos. '
            . 'Producto: ' . $name . '. Categoría: ' . $category . '. Dilución: ' . $dilution . '.';

        $result = self::callClaude($apiKey, $prompt, 200);
        if (!$result['success']) {
            return $result;
        }

        $text = self::truncateShortDescription($result['text']);

        return ['success' => true, 'text' => $text, 'error' => ''];
    }

    private static function truncateShortDescription(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= 255) {
            return $text;
        }

        $cut = mb_substr($text, 0, 252);
        $lastPeriod = mb_strrpos($cut, '.');
        if ($lastPeriod !== false && $lastPeriod > 80) {
            return trim(mb_substr($cut, 0, $lastPeriod + 1));
        }

        return rtrim($cut) . '…';
    }

    /**
     * @return array{success: bool, text: string, error: string}
     */
    private static function callClaude(string $apiKey, string $prompt, int $maxTokens): array
    {
        $body = json_encode([
            'model' => self::MODEL,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            return ['success' => false, 'text' => '', 'error' => 'No se pudo armar la solicitud a Claude'];
        }

        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            return ['success' => false, 'text' => '', 'error' => 'No se pudo inicializar curl'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            return ['success' => false, 'text' => '', 'error' => 'Error de red: ' . $curlError];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            $msg = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? $response)
                : (string) $response;

            return ['success' => false, 'text' => '', 'error' => "Claude API ({$httpCode}): {$msg}"];
        }

        $text = self::extractTextFromResponse($decoded);
        if ($text === '') {
            return ['success' => false, 'text' => '', 'error' => 'Claude no devolvió texto en la respuesta'];
        }

        return ['success' => true, 'text' => $text, 'error' => ''];
    }

    private static function prependHeader(string $body): string
    {
        $body = trim($body);

        return self::DESCRIPTION_HEADER . ($body !== '' ? "\n\n" . $body : '');
    }

    /** @param array<string, mixed> $product */
    private static function buildProductDataLines(array $product): string
    {
        $lines = [];
        $unitVol = MercadoLibreService::unitVolumeTextForProduct($product);
        if ($unitVol !== '') {
            $lines[] = 'Volumen unitario: ' . $unitVol;
        }

        $map = [
            'name' => 'Nombre',
            'dilution' => 'Dilución',
            'equivalence' => 'Equivalencia',
            'category_name' => 'Categoría',
        ];

        foreach ($map as $key => $label) {
            $raw = $product[$key] ?? null;
            if ($raw === null) {
                continue;
            }
            $value = trim((string) $raw);
            if ($value === '') {
                continue;
            }
            $lines[] = "{$label}: {$value}";
        }

        return implode('; ', $lines);
    }

    /** @param array<string, mixed> $decoded */
    private static function extractTextFromResponse(array $decoded): string
    {
        $content = $decoded['content'] ?? null;
        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $parts[] = trim((string) $block['text']);
            }
        }

        return trim(implode("\n\n", array_filter($parts)));
    }
}
