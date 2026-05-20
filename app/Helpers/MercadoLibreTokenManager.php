<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Gestión de tokens OAuth de MercadoLibre (access + refresh).
 * Lee credenciales de app desde settings o .env; persiste tokens en settings.
 */
final class MercadoLibreTokenManager
{
    private const TOKEN_URL = 'https://api.mercadolibre.com/oauth/token';

    /** @var list<string> */
    private const TOKEN_SETTING_KEYS = [
        'ml_access_token',
        'ml_refresh_token',
        'ml_user_id',
        'ml_token_expires_at',
    ];

    public static function getValidAccessToken(): string
    {
        $accessToken = trim(setting('ml_access_token', '') ?? '');
        $expiresAt = (int) (setting('ml_token_expires_at', '0') ?? '0');

        if ($accessToken !== '' && (time() + 300) < $expiresAt) {
            return $accessToken;
        }

        return self::refreshAccessToken();
    }

    /** @param array<string, mixed> $data Respuesta OAuth de ML (access_token, refresh_token, expires_in, user_id) */
    public static function saveTokens(array $data): void
    {
        $accessToken = trim((string) ($data['access_token'] ?? ''));
        $refreshToken = trim((string) ($data['refresh_token'] ?? ''));
        $userId = trim((string) ($data['user_id'] ?? ''));
        $expiresIn = (int) ($data['expires_in'] ?? 0);

        if ($accessToken === '') {
            throw new \InvalidArgumentException('La respuesta OAuth no incluye access_token.');
        }

        if ($refreshToken === '') {
            $refreshToken = trim(setting('ml_refresh_token', '') ?? '');
        }

        $expiresAt = $expiresIn > 0
            ? (string) (time() + $expiresIn)
            : (string) (int) (setting('ml_token_expires_at', '0') ?? '0');

        self::updateSetting('ml_access_token', $accessToken);
        self::updateSetting('ml_refresh_token', $refreshToken);
        self::updateSetting('ml_user_id', $userId);
        self::updateSetting('ml_token_expires_at', $expiresAt);
        SettingsCache::forget();
    }

    public static function isConnected(): bool
    {
        $accessToken = trim(setting('ml_access_token', '') ?? '');
        $refreshToken = trim(setting('ml_refresh_token', '') ?? '');

        return $accessToken !== '' && $refreshToken !== '';
    }

    public static function revokeTokens(): void
    {
        foreach (self::TOKEN_SETTING_KEYS as $key) {
            self::updateSetting($key, '');
        }
        SettingsCache::forget();
    }

    private static function refreshAccessToken(): string
    {
        $refreshToken = trim(setting('ml_refresh_token', '') ?? '');
        if ($refreshToken === '') {
            self::revokeTokens();
            throw new \RuntimeException('La conexión con MercadoLibre venció. Reconectá desde el panel ML.');
        }

        try {
            $response = self::requestTokenRefresh($refreshToken);
            self::saveTokens($response);

            return trim((string) ($response['access_token'] ?? ''));
        } catch (\Throwable) {
            self::revokeTokens();
            throw new \RuntimeException('La conexión con MercadoLibre venció. Reconectá desde el panel ML.');
        }
    }

    /** @return array<string, mixed> */
    private static function requestTokenRefresh(string $refreshToken): array
    {
        $clientId = self::getClientId();
        $clientSecret = self::getClientSecret();
        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Credenciales ML no configuradas (App ID / Client Secret).');
        }

        $postFields = http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $ch = curl_init(self::TOKEN_URL);
        if ($ch === false) {
            throw new \RuntimeException('No se pudo inicializar curl para OAuth ML.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            throw new \RuntimeException('Error de red al refrescar token ML: ' . $curlError);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if ($httpCode >= 400 || !is_array($decoded)) {
            $msg = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['error'] ?? $decoded['error_description'] ?? $body)
                : (string) $body;
            throw new \RuntimeException("OAuth refresh falló ({$httpCode}): {$msg}");
        }

        return $decoded;
    }

    private static function getClientId(): string
    {
        $fromSettings = trim(setting('ml_app_id', '') ?? '');
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim(Env::get('ML_APP_ID'));
    }

    private static function getClientSecret(): string
    {
        $fromSettings = trim(setting('ml_client_secret', '') ?? '');
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        return trim(Env::get('ML_CLIENT_SECRET'));
    }

    private static function updateSetting(string $key, string $value): void
    {
        Database::getInstance()->query(
            'UPDATE settings SET setting_value = ? WHERE setting_key = ?',
            [$value, $key]
        );
    }
}
