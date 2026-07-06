<?php

declare(strict_types=1);

namespace App\Helpers;

final class AnthropicConfig
{
    public static function descriptionModel(): string
    {
        static $model = null;
        if ($model !== null) {
            return $model;
        }

        $file = dirname(__DIR__) . '/config/anthropic.php';
        $config = is_file($file) ? require $file : [];
        $model = is_array($config) && !empty($config['description_model'])
            ? (string) $config['description_model']
            : 'claude-haiku-4-5-20251001';

        return $model;
    }
}
