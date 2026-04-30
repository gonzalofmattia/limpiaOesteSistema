<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Database;

final class SearchController extends Controller
{
    public function index(): void
    {
        $q = trim((string) $this->query('q', ''));
        $results = $this->search($q);
        $this->view('search/results', [
            'title' => 'Resultados de búsqueda',
            'query' => $q,
            'results' => $results,
        ]);
    }

    public function apiSearch(): void
    {
        $q = trim((string) $this->query('q', ''));
        if (mb_strlen($q) < 2) {
            $this->json([
                'success' => true,
                'query' => $q,
                'results' => ['products' => [], 'clients' => [], 'quotes' => [], 'categories' => []],
            ]);
            return;
        }

        $this->json([
            'success' => true,
            'query' => $q,
            'results' => $this->search($q),
        ]);
    }

    /** @return array{products:list<array<string,mixed>>,clients:list<array<string,mixed>>,quotes:list<array<string,mixed>>,categories:list<array<string,mixed>>} */
    private function search(string $q): array
    {
        if ($q === '') {
            return ['products' => [], 'clients' => [], 'quotes' => [], 'categories' => []];
        }

        $db = Database::getInstance();
        $like = '%' . $q . '%';

        $products = $db->fetchAll(
            "SELECT id, code, name
             FROM products
             WHERE code LIKE :q OR name LIKE :q
             ORDER BY name
             LIMIT 5",
            ['q' => $like]
        );
        foreach ($products as &$p) {
            $p['url'] = url('/productos?search=' . urlencode((string) ($p['code'] ?? '')));
        }
        unset($p);

        $clients = $db->fetchAll(
            "SELECT id, name, business_name, phone
             FROM clients
             WHERE name LIKE :q OR business_name LIKE :q OR phone LIKE :q
             ORDER BY name
             LIMIT 5",
            ['q' => $like]
        );
        foreach ($clients as &$c) {
            $c['url'] = url('/clientes/' . (int) ($c['id'] ?? 0) . '/editar');
        }
        unset($c);

        $quotes = $db->fetchAll(
            "SELECT id, quote_number, sale_number, total
             FROM quotes
             WHERE quote_number LIKE :q OR COALESCE(sale_number,'') LIKE :q
             ORDER BY created_at DESC
             LIMIT 5",
            ['q' => $like]
        );
        foreach ($quotes as &$qt) {
            $qt['url'] = url('/presupuestos/' . (int) ($qt['id'] ?? 0));
        }
        unset($qt);

        $categories = $db->fetchAll(
            "SELECT id, name
             FROM categories
             WHERE name LIKE :q
             ORDER BY name
             LIMIT 5",
            ['q' => $like]
        );
        foreach ($categories as &$cat) {
            $cat['url'] = url('/categorias?search=' . urlencode((string) ($cat['name'] ?? '')));
        }
        unset($cat);

        return [
            'products' => $products,
            'clients' => $clients,
            'quotes' => $quotes,
            'categories' => $categories,
        ];
    }
}
