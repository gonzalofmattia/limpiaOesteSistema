<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class CategoryController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT c.*, COUNT(p.id) AS product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id
             GROUP BY c.id
             ORDER BY c.sort_order, c.name'
        );
        $this->view('categories/index', ['title' => 'Categorías', 'categories' => $rows]);
    }

    public function create(): void
    {
        $this->view('categories/form', ['title' => 'Nueva categoría', 'category' => null]);
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
        $slug = slugify($data['name']);
        $existing = $db->fetch('SELECT id FROM categories WHERE slug = ?', [$slug]);
        if ($existing) {
            $slug .= '-' . substr(uniqid(), -4);
        }
        $db->insert('categories', [
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
        $this->view('categories/form', ['title' => 'Editar categoría', 'category' => $cat]);
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
        $slug = slugify($data['name']);
        $other = $db->fetch('SELECT id FROM categories WHERE slug = ? AND id != ?', [$slug, (int) $id]);
        if ($other) {
            $slug .= '-' . substr(uniqid(), -4);
        }
        $db->update('categories', [
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

    /** @return array{errors: list<string>, name: string, description: string, default_discount: float, default_markup: ?float, presentation_info: string, sort_order: int, is_active: int} */
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

        return [
            'errors' => $errors,
            'name' => $name,
            'description' => $desc,
            'default_discount' => $discF,
            'default_markup' => $markup,
            'presentation_info' => $pres,
            'sort_order' => $sort,
            'is_active' => $active,
        ];
    }
}
