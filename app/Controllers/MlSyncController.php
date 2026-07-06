<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\MlSyncEngine;
use App\Models\Database;

final class MlSyncController extends Controller
{
    public function conflicts(): void
    {
        $rows = Database::getInstance()->fetchAll(
            'SELECT c.*, l.title AS listing_title, l.ml_item_id, l.ml_permalink
             FROM ml_sync_conflicts c
             JOIN ml_listings l ON l.id = c.ml_listing_id
             WHERE c.resolved_at IS NULL
             ORDER BY l.id ASC, c.field ASC'
        );

        $byListing = [];
        foreach ($rows as $row) {
            $listingId = (int) $row['ml_listing_id'];
            $byListing[$listingId]['listing_title'] ??= (string) ($row['listing_title'] ?? '');
            $byListing[$listingId]['ml_item_id'] ??= (string) ($row['ml_item_id'] ?? '');
            $byListing[$listingId]['ml_permalink'] ??= (string) ($row['ml_permalink'] ?? '');
            $byListing[$listingId]['conflicts'][] = $row;
        }

        $this->view('mercadolibre/sync_conflicts', [
            'title' => 'Conflictos de sincronización ML',
            'byListing' => $byListing,
            'count' => count($rows),
        ]);
    }

    public function resolveConflict(string $id): void
    {
        if (!verifyCsrf()) {
            flash('error', 'Token inválido.');
            redirect('/mercadolibre/sync/conflictos');

            return;
        }

        $resolution = trim((string) $this->input('resolution', ''));
        $result = MlSyncEngine::applyResolution((int) $id, $resolution);

        if ($result['success']) {
            flash('success', 'Conflicto resuelto usando ' . ($resolution === 'ml' ? 'el valor de ML.' : 'el valor del sistema.'));
        } else {
            flash('error', 'No se pudo resolver: ' . $result['error']);
        }

        redirect('/mercadolibre/sync/conflictos');
    }
}
