<?php

declare(strict_types=1);

namespace App\Helpers;

final class PricingEngine
{
    public static function getEffectiveDiscount(array $product): float
    {
        if (array_key_exists('discount_override', $product)
            && $product['discount_override'] !== null
            && $product['discount_override'] !== '') {
            return (float) $product['discount_override'];
        }
        if (isset($product['default_discount']) && (float) $product['default_discount'] > 0) {
            return (float) $product['default_discount'];
        }
        if (array_key_exists('parent_discount', $product)
            && $product['parent_discount'] !== null
            && $product['parent_discount'] !== '') {
            return (float) $product['parent_discount'];
        }

        return 0.0;
    }

    public static function getEffectiveMarkup(array $product, ?float $overrideMarkup = null): float
    {
        if ($overrideMarkup !== null) {
            return $overrideMarkup;
        }
        if (array_key_exists('markup_override', $product)
            && $product['markup_override'] !== null
            && $product['markup_override'] !== '') {
            return (float) $product['markup_override'];
        }
        if (array_key_exists('category_default_markup', $product)
            && $product['category_default_markup'] !== null
            && $product['category_default_markup'] !== '') {
            return (float) $product['category_default_markup'];
        }
        if (array_key_exists('parent_default_markup', $product)
            && $product['parent_default_markup'] !== null
            && $product['parent_default_markup'] !== '') {
            return (float) $product['parent_default_markup'];
        }
        $g = setting('default_markup', '60');
        return (float) ($g ?? 60);
    }

    /**
     * Slug de categoría para reglas de precio (bidón, aerosol, etc.): el del padre si es subcategoría.
     *
     * @param array<string, mixed> $product Debe incluir category_slug y opcionalmente parent_slug
     */
    public static function getEffectiveCategorySlug(array $product): string
    {
        $p = $product['parent_slug'] ?? null;
        if ($p !== null && $p !== '') {
            return (string) $p;
        }

        return (string) ($product['category_slug'] ?? '');
    }

    public static function calculateCost(float $precioLista, float $discount): float
    {
        return round($precioLista * (1 - $discount / 100), 2);
    }

    public static function calculateSalePrice(float $costo, float $markup): float
    {
        return round($costo * (1 + $markup / 100), 2);
    }

    public static function addIVA(float $price, ?float $rate = null): float
    {
        if ($rate === null) {
            $r = setting('iva_rate', '21');
            $rate = (float) ($r ?? 21);
        }
        return round($price * (1 + $rate / 100), 2);
    }

    /**
     * $product debe incluir: campos de precio, discount_override, default_discount,
     * markup_override, category_default_markup, y category_slug para defaults de campo.
     *
     * @return array{
     *   precio_lista_seiq: float,
     *   discount_percent: float,
     *   costo: float,
     *   markup_percent: float,
     *   precio_venta: float,
     *   precio_con_iva: ?float,
     *   margen_pesos: float
     * }
     */
    public static function calculate(
        array $product,
        string $priceField,
        ?float $overrideMarkup = null,
        bool $includeIVA = false
    ): array {
        $field = $priceField;
        $lista = isset($product[$field]) && $product[$field] !== null && $product[$field] !== ''
            ? (float) $product[$field]
            : 0.0;
        if ($lista <= 0) {
            foreach (['precio_lista_caja', 'precio_lista_unitario', 'precio_lista_bidon'] as $alt) {
                if (isset($product[$alt]) && $product[$alt] !== null && $product[$alt] !== '' && (float) $product[$alt] > 0) {
                    $field = $alt;
                    $lista = (float) $product[$alt];
                    break;
                }
            }
        }

        return self::calculateWithListaSeiq($lista, $product, $overrideMarkup, $includeIVA);
    }

    /**
     * Igual que calculate(), pero el importe lista Seiq viene dado (p. ej. pack aerosol = unitario × unidades).
     *
     * @return array{
     *   precio_lista_seiq: float,
     *   discount_percent: float,
     *   costo: float,
     *   markup_percent: float,
     *   precio_venta: float,
     *   precio_con_iva: ?float,
     *   margen_pesos: float
     * }
     */
    public static function calculateWithListaSeiq(
        float $precioListaSeiq,
        array $product,
        ?float $overrideMarkup = null,
        bool $includeIVA = false
    ): array {
        $discount = self::getEffectiveDiscount($product);
        $costo = self::calculateCost($precioListaSeiq, $discount);
        $markup = self::getEffectiveMarkup($product, $overrideMarkup);
        $venta = self::calculateSalePrice($costo, $markup);
        $conIva = $includeIVA ? self::addIVA($venta) : null;

        return [
            'precio_lista_seiq' => round($precioListaSeiq, 2),
            'discount_percent' => $discount,
            'costo' => $costo,
            'markup_percent' => $markup,
            'precio_venta' => $venta,
            'precio_con_iva' => $conIva,
            'margen_pesos' => round($venta - $costo, 2),
        ];
    }

    public static function getPrimaryPriceField(string $categorySlug): string
    {
        return match ($categorySlug) {
            'aerosoles' => 'precio_lista_unitario',
            'bidones' => 'precio_lista_bidon',
            'masivo' => 'precio_lista_unitario',
            'sobres' => 'precio_lista_sobre',
            'alimenticia' => 'precio_lista_caja',
            'hig-toallas-intercaladas', 'fac-toallas-intercaladas' => 'precio_lista_unitario',
            default => 'precio_lista_caja',
        };
    }

    /** @return list<string> */
    public static function getAvailablePriceFields(string $categorySlug): array
    {
        return match ($categorySlug) {
            'aerosoles' => ['precio_lista_unitario', 'precio_lista_caja'],
            'bidones' => ['precio_lista_bidon', 'precio_lista_caja', 'precio_lista_litro'],
            'masivo' => ['precio_lista_unitario', 'precio_lista_caja'],
            'sobres' => ['precio_lista_sobre', 'precio_lista_caja'],
            'alimenticia' => ['precio_lista_caja', 'precio_lista_bidon'],
            default => [
                'precio_lista_unitario',
                'precio_lista_caja',
                'precio_lista_bidon',
                'precio_lista_litro',
                'precio_lista_bulto',
                'precio_lista_sobre',
            ],
        };
    }

    public static function priceFieldLabel(string $field): string
    {
        return match ($field) {
            'precio_lista_unitario' => 'Unitario',
            'precio_lista_caja' => 'Caja',
            'precio_lista_bidon' => 'Bidón',
            'precio_lista_litro' => 'Litro',
            'precio_lista_bulto' => 'Bulto',
            'precio_lista_sobre' => 'Sobre',
            default => $field,
        };
    }
}
