<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Categorías raíz (parent_id NULL) con hasta un nivel de subcategorías.
 */
final class CategoryHierarchy
{
    /**
     * Incluye el id dado y, si tiene hijas, todas las hijas (para filtros IN).
     *
     * @return list<int>
     */
    public static function expandFilterCategoryIds(Database $db, int $categoryId): array
    {
        $children = $db->fetchAll('SELECT id FROM categories WHERE parent_id = ?', [$categoryId]);
        if ($children !== []) {
            return array_merge([$categoryId], array_map(static fn ($r) => (int) $r['id'], $children));
        }

        return [$categoryId];
    }

    /**
     * @param list<array<string, mixed>> $flatRows Filas con id, parent_id, etc.
     * @return list<array<string, mixed>> Raíces con clave children (subcategorías ordenadas)
     */
    public static function buildTree(array $flatRows): array
    {
        $childrenOf = [];
        foreach ($flatRows as $row) {
            $pid = $row['parent_id'] ?? null;
            if ($pid !== null && $pid !== '') {
                $childrenOf[(int) $pid][] = $row;
            }
        }
        foreach ($childrenOf as &$list) {
            usort($list, static function ($a, $b) {
                return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
                    ?: strcmp((string) $a['name'], (string) $b['name']);
            });
        }
        unset($list);

        $roots = [];
        foreach ($flatRows as $row) {
            $pid = $row['parent_id'] ?? null;
            if ($pid === null || $pid === '') {
                $id = (int) $row['id'];
                $row['children'] = $childrenOf[$id] ?? [];
                $roots[] = $row;
            }
        }
        usort($roots, static function ($a, $b) {
            return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
                ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return $roots;
    }

    /**
     * Opciones para &lt;select&gt;: raíz y subcategorías con prefijo visual.
     *
     * @return list<array{id:int,label:string,is_parent:bool}>
     */
    public static function flatOptionsForSelect(array $tree): array
    {
        $opts = [];
        foreach ($tree as $root) {
            $opts[] = [
                'id' => (int) $root['id'],
                'label' => (string) $root['name'],
                'is_parent' => $root['children'] !== [],
            ];
            foreach ($root['children'] as $ch) {
                $opts[] = [
                    'id' => (int) $ch['id'],
                    'label' => '  └ ' . (string) $ch['name'],
                    'is_parent' => false,
                ];
            }
        }

        return $opts;
    }
}
