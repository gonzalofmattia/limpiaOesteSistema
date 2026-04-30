<?php
declare(strict_types=1);

if (!function_exists('loStatusClasses')) {
    function loStatusClasses(string $tone): string
    {
        return match ($tone) {
            'success' => 'bg-emerald-50 text-emerald-700',
            'warning' => 'bg-amber-50 text-amber-700',
            'danger' => 'bg-red-50 text-red-700',
            'blue' => 'bg-sky-50 text-sky-700',
            'violet' => 'bg-violet-50 text-violet-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
