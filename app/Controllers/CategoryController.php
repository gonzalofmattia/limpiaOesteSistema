<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\CategoryHierarchy;
use App\Models\Database;

final class CategoryController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) $this->query('page', 1));
        $perPage = (int) $this->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $search = trim((string) $this->query('search', ''));
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE c.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM categories c {$where}", $params);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = $db->fetchAll(
            'SELECT c.*, COUNT(p.id) AS product_count, pc.name AS parent_name, pc.default_discount AS parent_default_discount
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
             LEFT JOIN categories pc ON c.parent_id = pc.id
             ' . $where . '
             GROUP BY c.id
             ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );
        $tree = CategoryHierarchy::buildTree($rows);
        $this->view('categories/index', [
            'title' => 'Categorías',
            'categoryTree' => $tree,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]);
    }

    public function create(): void
    {
        $db = Database::getInstance();
        $rootCategories = $db->fetchAll(
            'SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name'
        );
        $this->view('categories/form', [
            'title' => 'Nueva categoría',
            'category' => null,
            'rootCategories' => $rootCategories,
        ]);
    }

    public function store(): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/categorias/crear');
        }
        $data = $this->validateCategoryInput();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/categorias/crear');
        }
        $db = Database::getInstance();
        $parentErr = $this->validateParentId($db, $data['parent_id'], null);
        if ($parentErr) {
            flash('error', $parentErr);
            redirect('/categorias/crear');
        }
        $slug = slugify($data['name']);
        $existing = $db->fetch('SELECT id FROM categories WHERE slug = ?', [$slug]);
        if ($existing) {
            $slug .= '-' . substr(uniqid(), -4);
        }
        $db->insert('categories', [
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?: null,
            'default_discount' => $data['default_discount'],
            'default_markup' => $data['default_markup'],
            'presentation_info' => $data['presentation_info'] ?: null,
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ]);
        flash('success', 'Categoría creada.');
        redirect('/categorias');
    }

    public function edit(string $id): void
    {
        $db = Database::getInstance();
        $cat = $db->fetch('SELECT * FROM categories WHERE id = ?', [(int) $id]);
        if (!$cat) {
            flash('error', 'Categoría no encontrada.');
            redirect('/categorias');
        }
        $rootCategories = $db->fetchAll(
            'SELECT id, name FROM categories WHERE parent_id IS NULL AND id != ? ORDER BY sort_order, name',
            [(int) $id]
        );
        $this->view('categories/form', [
            'title' => 'Editar categoría',
            'category' => $cat,
            'rootCategories' => $rootCategories,
        ]);
    }

    public function update(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/categorias/' . $id . '/editar');
        }
        $db = Database::getInstance();
        $cat = $db->fetch('SELECT * FROM categories WHERE id = ?', [(int) $id]);
        if (!$cat) {
            flash('error', 'Categoría no encontrada.');
            redirect('/categorias');
        }
        $data = $this->validateCategoryInput();
        if ($data['errors']) {
            flash('error', implode(' ', $data['errors']));
            redirect('/categorias/' . $id . '/editar');
        }
        $parentErr = $this->validateParentId($db, $data['parent_id'], (int) $id);
        if ($parentErr) {
            flash('error', $parentErr);
            redirect('/categorias/' . $id . '/editar');
        }
        $slug = slugify($data['name']);
        $other = $db->fetch('SELECT id FROM categories WHERE slug = ? AND id != ?', [$slug, (int) $id]);
        if ($other) {
            $slug .= '-' . substr(uniqid(), -4);
        }
        $db->update('categories', [
            'parent_id' => $data['parent_id'],
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?: null,
            'default_discount' => $data['default_discount'],
            'default_markup' => $data['default_markup'],
            'presentation_info' => $data['presentation_info'] ?: null,
            'sort_order' => $data['sort_order'],
            'is_active' => $data['is_active'],
        ], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Categoría actualizada.');
        redirect('/categorias');
    }

    public function toggle(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/categorias');
        }
        $db = Database::getInstance();
        $cat = $db->fetch('SELECT id, is_active FROM categories WHERE id = ?', [(int) $id]);
        if (!$cat) {
            flash('error', 'No encontrada.');
            redirect('/categorias');
        }
        $db->update('categories', ['is_active' => $cat['is_active'] ? 0 : 1], 'id = :id', ['id' => (int) $id]);
        flash('success', 'Estado actualizado.');
        redirect('/categorias');
    }

    /** @return array{errors: list<string>, name: string, description: string, default_discount: float, default_markup: ?float, presentation_info: string, sort_order: int, is_active: int, parent_id: ?int} */
    private function validateCategoryInput(): array
    {
        $errors = [];
        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        $desc = trim((string) $this->input('description', ''));
        $disc = str_replace(',', '.', (string) $this->input('default_discount', '0'));
        $discF = is_numeric($disc) ? (float) $disc : null;
        if ($discF === null) {
            $errors[] = 'Descuento inválido.';
            $discF = 0.0;
        }
        $markupRaw = trim((string) $this->input('default_markup', ''));
        $markup = $markupRaw === '' ? null : (is_numeric(str_replace(',', '.', $markupRaw)) ? (float) str_replace(',', '.', $markupRaw) : null);
        if ($markupRaw !== '' && $markup === null) {
            $errors[] = 'Markup inválido.';
        }
        $pres = trim((string) $this->input('presentation_info', ''));
        $sort = (int) $this->input('sort_order', 0);
        $active = $this->input('is_active') ? 1 : 0;
        $parentRaw = trim((string) $this->input('parent_id', ''));
        $parentId = $parentRaw === '' ? null : (int) $parentRaw;

        return [
            'errors' => $errors,
            'name' => $name,
            'description' => $desc,
            'default_discount' => $discF,
            'default_markup' => $markup,
            'presentation_info' => $pres,
            'sort_order' => $sort,
            'is_active' => $active,
            'parent_id' => $parentId,
        ];
    }

    private function validateParentId(Database $db, ?int $parentId, ?int $editingId): ?string
    {
        if ($parentId === null) {
            return null;
        }
        if ($editingId !== null && $parentId === $editingId) {
            return 'La categoría no puede ser padre de sí misma.';
        }
        $p = $db->fetch('SELECT id, parent_id FROM categories WHERE id = ?', [$parentId]);
        if (!$p) {
            return 'Categoría padre no válida.';
        }
        if ($p['parent_id'] !== null && $p['parent_id'] !== '') {
            return 'Solo se puede elegir una categoría principal como padre (un nivel de subcategorías).';
        }
        if ($editingId !== null) {
            $hasKids = $db->fetch('SELECT id FROM categories WHERE parent_id = ? LIMIT 1', [$editingId]);
            if ($hasKids && $parentId !== null) {
                return 'Una categoría con subcategorías no puede convertirse en subcategoría.';
            }
        }

        return null;
    }
}
