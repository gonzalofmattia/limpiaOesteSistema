<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CategoryHierarchy;
use App\Models\Database;

final class VisualCatalogController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();

        $categoryRows = $db->fetchAll(
            'SELECT c.*, pc.name AS parent_name
             FROM categories c
             LEFT JOIN categories pc ON c.parent_id = pc.id
             ORDER BY COALESCE(pc.sort_order, c.sort_order), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
        $categoryTree = CategoryHierarchy::buildTree($categoryRows);
        $categoryFilterOptions = CategoryHierarchy::flatOptionsForSelect($categoryTree);
        $categoryFilterMap = [];
        foreach ($categoryFilterOptions as $opt) {
            $categoryFilterMap[(int) $opt['id']] = CategoryHierarchy::expandFilterCategoryIds($db, (int) $opt['id']);
        }

        $rows = $db->fetchAll(
            'SELECT p.id, p.code, p.name, p.content, p.content_volume, p.category_id,
                    c.name AS category_name,
                    pc.name AS parent_category_name,
                    cov.filename AS cover_filename
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON c.parent_id = pc.id
             LEFT JOIN product_images cov ON cov.id = (
                 SELECT pi.id FROM product_images pi
                 WHERE pi.product_id = p.id AND pi.is_cover = 1
                 ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1
             )
             WHERE p.is_active = 1
             ORDER BY COALESCE(pc.sort_order, c.sort_order), c.parent_id IS NOT NULL, c.sort_order, c.name, p.sort_order, p.name'
        );

        $grouped = [];
        $total = 0;
        $withPhoto = 0;

        foreach ($rows as $row) {
            $catId = (int) $row['category_id'];
            $hasPhoto = !empty($row['cover_filename']);
            $total++;
            if ($hasPhoto) {
                $withPhoto++;
            }

            $volume = trim((string) ($row['content_volume'] ?? ''));
            if ($volume === '') {
                $volume = trim((string) ($row['content'] ?? ''));
            }

            $product = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'volume' => $volume,
                'category_id' => $catId,
                'has_photo' => $hasPhoto,
                'image_url' => $hasPhoto
                    ? productImageUrl((int) $row['id'], (string) $row['cover_filename'])
                    : null,
            ];

            if (!isset($grouped[$catId])) {
                $catLabel = (string) $row['category_name'];
                if (!empty($row['parent_category_name'])) {
                    $catLabel = (string) $row['parent_category_name'] . ' › ' . $catLabel;
                }
                $grouped[$catId] = [
                    'id' => $catId,
                    'name' => $catLabel,
                    'products' => [],
                ];
            }
            $grouped[$catId]['products'][] = $product;
        }

        $this->view('catalogo/visual', [
            'title' => 'Catálogo visual',
            'subtitle' => 'Vista interna de productos activos con fotos',
            'categories' => array_values($grouped),
            'categoryFilterOptions' => $categoryFilterOptions,
            'categoryFilterMap' => $categoryFilterMap,
            'stats' => [
                'total' => $total,
                'with_photo' => $withPhoto,
                'without_photo' => $total - $withPhoto,
            ],
        ]);
    }
}
