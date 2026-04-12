<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Precio lista efectivo y textos de unidad para líneas de presupuesto (caja vs unidad).
 */
final class QuoteLinePricing
{
    /** Normaliza valores legacy del dropdown anterior a caja|unidad. */
    public static function normalizeUnitType(string $raw): string
    {
        $r = strtolower(trim($raw));
        if ($r === 'unidad') {
            return 'unidad';
        }
        if ($r === 'caja') {
            return 'caja';
        }
        if (in_array($r, ['bidon', 'bidón', 'litro', 'sobre', 'bulto'], true)) {
            return 'unidad';
        }

        return 'caja';
    }

    /**
     * @param array<string, mixed> $p Fila producto + category_slug
     * @return array{lista_seiq: float, price_field_used: string}
     */
    public static function resolveListaForQuote(array $p, string $slug, string $mode): array
    {
        $mode = $mode === 'unidad' ? 'unidad' : 'caja';
        $slug = strtolower($slug);

        if ($mode === 'caja') {
            return match ($slug) {
                'aerosoles' => self::aerosolPack($p),
                'bidones', 'masivo', 'sobres', 'alimenticia' => self::simpleField($p, 'precio_lista_caja'),
                default => self::defaultCaja($p),
            };
        }

        return match ($slug) {
            'aerosoles' => self::simpleField($p, 'precio_lista_unitario'),
            'bidones', 'alimenticia' => self::simpleField($p, 'precio_lista_bidon'),
            'masivo' => self::simpleField($p, 'precio_lista_unitario'),
            'sobres' => self::simpleField($p, 'precio_lista_sobre'),
            default => self::simpleField($p, 'precio_lista_unitario'),
        };
    }

    /**
     * @param array<string, mixed> $p
     * @return array{unit_label: string, unit_description: string}
     */
    public static function snapshotLabels(array $p, string $slug, string $mode): array
    {
        $mode = $mode === 'unidad' ? 'unidad' : 'caja';
        $slug = strtolower($slug);
        $labelPack = trim((string) ($p['sale_unit_label'] ?? ''));
        if ($labelPack === '') {
            $labelPack = 'Caja';
        }

        if ($mode === 'caja') {
            $desc = trim((string) ($p['sale_unit_description'] ?? ''));
            if ($desc === '') {
                $desc = trim((string) ($p['presentation'] ?? ''));
            }

            return ['unit_label' => $labelPack, 'unit_description' => $desc];
        }

        return [
            'unit_label' => 'Unidad',
            'unit_description' => self::unitRetailDetailText($slug, (string) ($p['content'] ?? '')),
        ];
    }

    public static function unitRetailDetailText(string $slug, string $content): string
    {
        $content = trim($content);
        $extra = $content !== '' ? $content : '';

        return match ($slug) {
            'aerosoles' => $extra !== '' ? 'Unidad — ' . $extra : 'Unidad — aerosol',
            'bidones', 'alimenticia' => $extra !== ''
                ? 'Bidón individual x 5L — ' . $extra
                : 'Bidón individual x 5L',
            'masivo' => $extra !== '' ? 'Unidad — ' . $extra : 'Unidad — envase',
            'sobres' => $extra !== '' ? 'Sobre individual — ' . $extra : 'Sobre individual',
            default => $extra !== '' ? 'Unidad — ' . $extra : 'Unidad',
        };
    }

    /**
     * Texto columna Detalle cuando no hay snapshot (presupuestos viejos).
     *
     * @param array<string, mixed> $it quote_items + joins
     */
    public static function fallbackDetalleDisplay(array $it): string
    {
        $desc = trim((string) ($it['unit_description'] ?? ''));
        if ($desc !== '') {
            return $desc;
        }
        $type = self::normalizeUnitType((string) ($it['unit_type'] ?? 'caja'));
        $slug = strtolower((string) ($it['category_slug'] ?? ''));
        if ($type === 'caja') {
            $d = trim((string) ($it['sale_unit_description'] ?? ''));
            if ($d !== '') {
                return $d;
            }

            return trim((string) ($it['presentation'] ?? '')) ?: '—';
        }

        return self::unitRetailDetailText($slug, (string) ($it['content'] ?? ''));
    }

    /**
     * @param array<string, mixed> $p
     * @return array{lista_seiq: float, price_field_used: string}
     */
    private static function aerosolPack(array $p): array
    {
        $u = isset($p['precio_lista_unitario']) && $p['precio_lista_unitario'] !== null && $p['precio_lista_unitario'] !== ''
            ? (float) $p['precio_lista_unitario']
            : 0.0;
        $n = max(1, (int) ($p['units_per_box'] ?? 1));

        return ['lista_seiq' => round($u * $n, 2), 'price_field_used' => 'precio_lista_unitario'];
    }

    /**
     * @param array<string, mixed> $p
     * @return array{lista_seiq: float, price_field_used: string}
     */
    private static function simpleField(array $p, string $field): array
    {
        $v = self::fieldFloat($p, $field);

        return ['lista_seiq' => $v, 'price_field_used' => $field];
    }

    /** @param array<string, mixed> $p */
    private static function fieldFloat(array $p, string $field): float
    {
        return isset($p[$field]) && $p[$field] !== null && $p[$field] !== ''
            ? (float) $p[$field]
            : 0.0;
    }

    /**
     * Importe lista Seiq de una unidad suelta (1 aerosol, 1 bidón, 1 sobre, etc.).
     */
    public static function individualListaSeiq(array $p, string $slug): float
    {
        $slug = strtolower($slug);

        return round(match ($slug) {
            'aerosoles', 'masivo' => self::fieldFloat($p, 'precio_lista_unitario'),
            'bidones', 'alimenticia' => self::fieldFloat($p, 'precio_lista_bidon'),
            'sobres' => self::fieldFloat($p, 'precio_lista_sobre'),
            default => self::fieldFloat($p, 'precio_lista_unitario'),
        }, 2);
    }

    /**
     * Para listas de precios: P. unitario (venta) y precio caja/bulto = unitario × units_per_box.
     * Si no hay lista unitaria (p. ej. solo precio caja en Seiq), se deriva del cálculo por campo de lista.
     *
     * @param array<string, mixed> $p Producto + category_slug
     * @param array<string, mixed> $calcListaPrincipal Resultado de PricingEngine::calculate() con el campo de lista elegido
     * @return array{individual_venta: float, pack_net: float, pack_con_iva: ?float, pack_display: float, units_in_pack: int}
     */
    public static function priceListUnitAndPack(
        array $p,
        string $slug,
        ?float $customMarkup,
        bool $includeIva,
        array $calcListaPrincipal
    ): array {
        $slug = strtolower($slug);
        $n = max(1, (int) ($p['units_per_box'] ?? 1));
        $unitLista = self::individualListaSeiq($p, $slug);

        $net = static fn (float $lista): array => PricingEngine::calculateWithListaSeiq($lista, $p, $customMarkup, false);
        $iva = static fn (float $lista): array => PricingEngine::calculateWithListaSeiq($lista, $p, $customMarkup, true);

        if ($unitLista > 0) {
            $cn = $net($unitLista);
            $ci = $iva($unitLista);
            $unitNet = (float) $cn['precio_venta'];
            $unitIva = $ci['precio_con_iva'] !== null ? (float) $ci['precio_con_iva'] : null;
            $individualVenta = $includeIva && $unitIva !== null ? $unitIva : $unitNet;
            $packNet = round($unitNet * $n, 2);
            $packIva = $unitIva !== null ? round($unitIva * $n, 2) : null;
            $packDisplay = $includeIva && $packIva !== null ? $packIva : $packNet;

            return [
                'individual_venta' => $individualVenta,
                'pack_net' => $packNet,
                'pack_con_iva' => $packIva,
                'pack_display' => $packDisplay,
                'units_in_pack' => $n,
            ];
        }

        $pv = (float) $calcListaPrincipal['precio_venta'];
        $pc = $calcListaPrincipal['precio_con_iva'] !== null ? (float) $calcListaPrincipal['precio_con_iva'] : null;
        $packNet = round($pv, 2);
        $packIva = $pc !== null ? round($pc, 2) : null;
        $packDisplay = $includeIva && $packIva !== null ? $packIva : $packNet;
        $individualVenta = $n > 0
            ? round(($includeIva && $packIva !== null ? $packIva : $packNet) / $n, 2)
            : $packDisplay;

        return [
            'individual_venta' => $individualVenta,
            'pack_net' => $packNet,
            'pack_con_iva' => $packIva,
            'pack_display' => $packDisplay,
            'units_in_pack' => $n,
        ];
    }

    /**
     * Precio de venta de 1 unidad individual (mismo descuento, markup e IVA que la línea del presupuesto).
     *
     * @param array<string, mixed> $p Producto con datos de categoría para PricingEngine
     */
    public static function individualUnitSellingPrice(
        array $p,
        string $slug,
        ?float $customMarkup,
        bool $includeIva
    ): float {
        $lista = self::individualListaSeiq($p, $slug);
        if ($lista <= 0) {
            return 0.0;
        }
        $calcNet = PricingEngine::calculateWithListaSeiq($lista, $p, $customMarkup, false);
        $calcLine = PricingEngine::calculateWithListaSeiq($lista, $p, $customMarkup, $includeIva);
        if ($includeIva && $calcLine['precio_con_iva'] !== null) {
            return (float) $calcLine['precio_con_iva'];
        }

        return (float) $calcNet['precio_venta'];
    }

    /**
     * @param array<string, mixed> $p
     * @return array{lista_seiq: float, price_field_used: string}
     */
    private static function defaultCaja(array $p): array
    {
        if (isset($p['precio_lista_caja']) && $p['precio_lista_caja'] !== null && $p['precio_lista_caja'] !== '') {
            return ['lista_seiq' => (float) $p['precio_lista_caja'], 'price_field_used' => 'precio_lista_caja'];
        }
        $u = isset($p['precio_lista_unitario']) && $p['precio_lista_unitario'] !== null && $p['precio_lista_unitario'] !== ''
            ? (float) $p['precio_lista_unitario']
            : 0.0;
        $n = max(1, (int) ($p['units_per_box'] ?? 1));

        return ['lista_seiq' => round($u * $n, 2), 'price_field_used' => 'precio_lista_unitario'];
    }
}
