<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

final class SupplierResolver
{
    /**
     * @param array<string,mixed> $product
     * @return array<string,mixed>|null
     */
    public static function getProductSupplier(array $product): ?array
    {
        $categoryId = (int) ($product['category_id'] ?? 0);
        if ($categoryId <= 0) {
            return null;
        }
        $db = Database::getInstance();
        $supplierId = $db->fetchColumn(
            'SELECT COALESCE(c.supplier_id, pc.supplier_id)
             FROM categories c
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE c.id = ?',
            [$categoryId]
        );
        if (!$supplierId) {
            return null;
        }

        return $db->fetch('SELECT * FROM suppliers WHERE id = ?', [(int) $supplierId]);
    }
}
