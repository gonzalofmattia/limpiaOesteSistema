<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Clasifica una respuesta de prospecto y sugiere una contestacion via Claude.
 * El sistema NUNCA responde solo: esto solo arma una sugerencia editable que
 * el usuario aprueba a mano desde la bandeja.
 */
final class OutreachAiAssistant
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const TIMEOUT_SECONDS = 15;
    private const VALID_INTENTS = [
        'interesado', 'pregunta_precio', 'pregunta_producto', 'reagendar', 'rechazo', 'otro',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
Sos el asistente comercial de Limpia Oeste, distribuidora de productos de limpieza profesional en Zona Oeste GBA (Buenos Aires, Argentina).
Tono argentino (voseo), cordial, conciso, sin emojis.
Objetivo de toda respuesta: avanzar hacia una visita con muestra sin cargo. Nunca pases la lista de precios completa por mensaje.
Si preguntan un precio puntual: indicá que depende de la presentación y el volumen, y proponé pasar a dejarle la muestra y una cotización puntual.
No inventes precios, productos ni promociones que no te dieron como dato.
No prometas "entrega en 24hs" a clientes institucionales o de volumen desconocido — usá "envío prioritario".
Respondé ÚNICAMENTE con un JSON válido, sin texto antes ni después, con esta forma exacta:
{"intent": "interesado|pregunta_precio|pregunta_producto|reagendar|rechazo|otro", "reply": "texto de la respuesta sugerida"}
PROMPT;

    /**
     * @param array<string, mixed> $prospect Fila prospects (usa business_type, city, status)
     * @param list<array{direction:string, ts:mixed, body:string}> $thread Cronologico, direction 'in'|'out'
     * @return array{success: bool, intent: string, reply: string, error: string}
     */
    public static function classifyAndDraft(array $prospect, array $thread): array
    {
        $empty = ['success' => false, 'intent' => '', 'reply' => '', 'error' => ''];

        $apiKey = trim(Env::get('ANTHROPIC_API_KEY'));
        if ($apiKey === '') {
            $empty['error'] = 'ANTHROPIC_API_KEY no configurada en .env';

            return $empty;
        }

        $transcript = self::buildTranscript($thread);
        if ($transcript === '') {
            $empty['error'] = 'No hay hilo de conversación para analizar';

            return $empty;
        }

        $userMessage = sprintf(
            "Rubro: %s\nCiudad: %s\nEstado actual: %s\n\nHilo de la conversación (cronológico):\n%s",
            (string) ($prospect['business_type'] ?? 'otro'),
            (string) ($prospect['city'] ?? 'sin especificar'),
            (string) ($prospect['status'] ?? 'nuevo'),
            $transcript
        );

        $result = self::callClaude($apiKey, $userMessage);
        if (!$result['success']) {
            self::log('No se pudo clasificar: ' . $result['error']);
            $empty['error'] = $result['error'];

            return $empty;
        }

        $parsed = self::parseJsonResponse($result['text']);
        if ($parsed === null) {
            self::log('Respuesta no parseable como JSON: ' . $result['text']);
            $empty['error'] = 'Claude no devolvió un JSON válido';

            return $empty;
        }

        $intent = in_array($parsed['intent'] ?? '', self::VALID_INTENTS, true) ? (string) $parsed['intent'] : 'otro';
        $reply = trim((string) ($parsed['reply'] ?? ''));
        if ($reply === '') {
            $empty['error'] = 'Claude no sugirió una respuesta';

            return $empty;
        }

        return ['success' => true, 'intent' => $intent, 'reply' => $reply, 'error' => ''];
    }

    /** @param list<array{direction:string, ts:mixed, body:string}> $thread */
    private static function buildTranscript(array $thread): string
    {
        $lines = [];
        foreach ($thread as $msg) {
            $body = trim((string) ($msg['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $label = ($msg['direction'] ?? '') === 'out' ? 'Nosotros' : 'Prospecto';
            $lines[] = "{$label}: {$body}";
        }

        return implode("\n", $lines);
    }

    /** @return array{success: bool, text: string, error: string} */
    private static function callClaude(string $apiKey, string $userMessage): array
    {
        $body = json_encode([
            'model' => AnthropicConfig::outreachModel(),
            'max_tokens' => 400,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
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
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
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

    /** @param array<string, mixed> $decoded */
    private static function extractTextFromResponse(array $decoded): string
    {
        $content = $decoded['content'] ?? null;
        if (!is_array($content)) {
            return '';
        }
        $parts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $parts[] = trim((string) $block['text']);
            }
        }

        return trim(implode("\n", array_filter($parts)));
    }

    /** @return array{intent?: string, reply?: string}|null */
    private static function parseJsonResponse(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/```\s*$/', '', $clean) ?? $clean;
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents(STORAGE_PATH . '/logs/outreach_ai.log', $line, FILE_APPEND | LOCK_EX);
    }
}
