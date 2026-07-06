<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Motor de sincronización bidireccional ML <-> sistema: diff de tres vías (ML actual,
 * sistema actual, último valor sincronizado en común) para título/precio/stock/descripción/
 * categoría, más un chequeo de imágenes aparte (siempre PULL, nunca genera conflicto).
 */
final class MlSyncEngine
{
    public const NO_CHANGE = 'no_change';
    public const PULL_FROM_ML = 'pull_from_ml';
    public const PUSH_TO_ML = 'push_to_ml';
    public const CONFLICT = 'conflict';

    /**
     * Clasificación pura del diff de tres vías. Sin snapshot previo, cualquier diferencia entre
     * ML y sistema es CONFLICT (nunca se resuelve sola en una dirección arbitraria el primer día).
     */
    public static function resolveField(mixed $mlValue, mixed $systemValue, mixed $lastSyncValue): string
    {
        if ($lastSyncValue === null) {
            return $mlValue === $systemValue ? self::NO_CHANGE : self::CONFLICT;
        }

        $mlChanged = $mlValue !== $lastSyncValue;
        $systemChanged = $systemValue !== $lastSyncValue;

        if (!$mlChanged && !$systemChanged) {
            return self::NO_CHANGE;
        }
        if ($mlChanged && !$systemChanged) {
            return self::PULL_FROM_ML;
        }
        if (!$mlChanged && $systemChanged) {
            return self::PUSH_TO_ML;
        }

        return $mlValue === $systemValue ? self::NO_CHANGE : self::CONFLICT;
    }

    /**
     * Evalúa un listing: trae estado ML, estado sistema y snapshot, y devuelve la acción
     * resuelta por campo + imágenes. No aplica nada (dry-run puro).
     *
     * @param array<int, int> $unitsInTransit SeiqOrderBuilder::unitsInTransit(), calculado una
     *        sola vez por corrida y pasado a cada evaluate() para no recalcularlo por listing.
     * @return array{
     *     listing_id: int, product_id: int, ml_item_id: string, blocked: bool, block_reason: string,
     *     fields: array<string, array{action: string, ml_value: mixed, system_value: mixed, last_sync_value: mixed}>,
     *     images: array{action: string, ml_pictures: list<array{id: string, secure_url: string}>}
     * }|null null si el listing no existe o no tiene ml_item_id (nada que evaluar)
     */
    public static function evaluate(int $listingId, array $unitsInTransit): ?array
    {
        $db = Database::getInstance();
        $listing = $db->fetch('SELECT * FROM ml_listings WHERE id = ?', [$listingId]);
        if ($listing === null) {
            return null;
        }

        $mlItemId = trim((string) ($listing['ml_item_id'] ?? ''));
        if ($mlItemId === '') {
            return null;
        }

        $blockedResult = static fn (int $productId, string $reason): array => [
            'listing_id' => $listingId,
            'product_id' => $productId,
            'ml_item_id' => $mlItemId,
            'blocked' => true,
            'block_reason' => $reason,
            'fields' => [],
            'images' => ['action' => self::NO_CHANGE, 'ml_pictures' => []],
        ];

        $productId = (int) ($listing['product_id'] ?? 0);
        if ($productId <= 0) {
            return $blockedResult(0, 'Listing de combo (sin product_id): el motor de sync todavía no lo soporta.');
        }

        $product = $db->fetch('SELECT * FROM products WHERE id = ?', [$productId]);
        if ($product === null) {
            return $blockedResult($productId, 'Producto no encontrado.');
        }

        $mlState = MercadoLibreService::fetchCurrentState($mlItemId);
        if ($mlState === null) {
            return $blockedResult($productId, 'No se pudo leer el ítem desde ML (GET fallido o no existe).');
        }

        $snapshot = $db->fetch('SELECT * FROM ml_sync_snapshots WHERE ml_listing_id = ?', [$listingId]);

        $fields = [];

        $mlTitle = self::normalizeText($mlState['title']);
        $sysTitle = self::normalizeText((string) ($listing['title'] ?? ''));
        $lastTitle = self::snapshotColumnValue($snapshot, 'title');
        $fields['title'] = [
            'action' => self::resolveField($mlTitle, $sysTitle, $lastTitle),
            'ml_value' => $mlTitle,
            'system_value' => $sysTitle,
            'last_sync_value' => $lastTitle,
        ];

        $mlPrice = self::normalizeMoney($mlState['price']);
        $markup = isset($listing['ml_markup']) && $listing['ml_markup'] !== null && $listing['ml_markup'] !== ''
            ? (float) $listing['ml_markup']
            : null;
        $sysPrice = self::normalizeMoney(MercadoLibreService::calculateMlPrice($productId, $markup));
        $lastPrice = self::snapshotColumnValue($snapshot, 'price');
        $fields['price'] = [
            'action' => self::resolveField($mlPrice, $sysPrice, $lastPrice !== null ? self::normalizeMoney($lastPrice) : null),
            'ml_value' => $mlPrice,
            'system_value' => $sysPrice,
            'last_sync_value' => $lastPrice,
        ];

        $mlQty = self::normalizeInt($mlState['available_quantity']);
        $sysQty = self::normalizeInt(MercadoLibreService::resolveQuantity($listing, $unitsInTransit));
        $lastQty = self::snapshotColumnValue($snapshot, 'available_quantity');
        $fields['available_quantity'] = [
            'action' => self::resolveField($mlQty, $sysQty, $lastQty !== null ? self::normalizeInt($lastQty) : null),
            'ml_value' => $mlQty,
            'system_value' => $sysQty,
            'last_sync_value' => $lastQty,
        ];

        $mlDesc = trim((string) $mlState['description_text']);
        $sysDesc = MercadoLibreService::buildDescription($product);
        $mlDescHash = md5(self::normalizeText($mlDesc));
        $sysDescHash = md5(self::normalizeText($sysDesc));
        $lastDescHash = self::snapshotColumnValue($snapshot, 'description_hash');
        $descAction = self::resolveField($mlDescHash, $sysDescHash, $lastDescHash);
        if ($descAction === self::PUSH_TO_ML) {
            $bannedTerms = TextSafetyChecker::containsBannedMercadoEnviosTerms($sysDesc);
            if ($bannedTerms !== []) {
                $descAction = self::CONFLICT;
                $sysDesc = '[Términos bloqueados para ML: ' . implode(', ', $bannedTerms) . '] ' . $sysDesc;
            }
        }
        $fields['description'] = [
            'action' => $descAction,
            'ml_value' => $mlDesc,
            'system_value' => $sysDesc,
            'last_sync_value' => null, // solo se guarda el hash, no el texto crudo
        ];

        $mlCategory = self::normalizeText($mlState['category_id']);
        $sysCategory = self::normalizeText((string) ($listing['ml_category_id'] ?? ''));
        $lastCategory = self::snapshotColumnValue($snapshot, 'category_id');
        $fields['category_id'] = [
            'action' => self::resolveField($mlCategory, $sysCategory, $lastCategory),
            'ml_value' => $mlCategory,
            'system_value' => $sysCategory,
            'last_sync_value' => $lastCategory,
        ];

        $localPictureIds = array_map(
            static fn (array $row): string => (string) $row['ml_picture_id'],
            $db->fetchAll(
                'SELECT ml_picture_id FROM product_images
                 WHERE product_id = ? AND ml_picture_id IS NOT NULL
                 ORDER BY sort_order ASC, id ASC',
                [$productId]
            )
        );

        return [
            'listing_id' => $listingId,
            'product_id' => $productId,
            'ml_item_id' => $mlItemId,
            'blocked' => false,
            'block_reason' => '',
            'fields' => $fields,
            'images' => [
                'action' => self::evaluateImagesAction($mlState['pictures'], $localPictureIds),
                'ml_pictures' => $mlState['pictures'],
            ],
        ];
    }

    /**
     * Corre el motor sobre un set de listings (vacío = todos los activos/pausados vinculados).
     * En modo dry-run ($apply=false) no toca la base: ni ml_sync_snapshots ni ml_sync_conflicts.
     *
     * @param list<int> $listingIds
     * @param (callable(array<string, mixed>): void)|null $onProgress llamado una vez por listing procesado
     * @return array{pulled: int, pushed: int, conflicts: int, no_change: int, blocked: int, errors: list<array{listing_id: int, error: string}>, details: list<array<string, mixed>>}
     */
    public static function run(array $listingIds, bool $apply, ?callable $onProgress = null): array
    {
        $db = Database::getInstance();

        if ($listingIds === []) {
            $rows = $db->fetchAll(
                "SELECT id FROM ml_listings
                 WHERE status IN ('active','paused') AND ml_item_id IS NOT NULL AND TRIM(ml_item_id) <> ''
                 ORDER BY id"
            );
            $listingIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        }

        $unitsInTransit = SeiqOrderBuilder::unitsInTransit($db);

        $pulled = 0;
        $pushed = 0;
        $conflicts = 0;
        $noChange = 0;
        $blocked = 0;
        $errors = [];
        $details = [];

        foreach ($listingIds as $listingId) {
            try {
                $evaluation = self::evaluate((int) $listingId, $unitsInTransit);
                if ($evaluation === null) {
                    continue;
                }

                if ($evaluation['blocked']) {
                    $blocked++;
                    $item = [
                        'listing_id' => $listingId,
                        'blocked' => true,
                        'block_reason' => $evaluation['block_reason'],
                    ];
                    $details[] = $item;
                    if ($onProgress !== null) {
                        $onProgress($item);
                    }
                    continue;
                }

                $snapshotUpdates = [];
                $fieldActions = [];

                foreach ($evaluation['fields'] as $field => $info) {
                    $action = $info['action'];
                    $fieldActions[$field] = $action;

                    if ($action === self::CONFLICT) {
                        $conflicts++;
                        if ($apply) {
                            self::upsertConflict($listingId, $field, $info['ml_value'], $info['system_value'], $info['last_sync_value']);
                        }
                        continue;
                    }

                    if ($action === self::NO_CHANGE) {
                        $noChange++;
                        $authoritative = $info['ml_value'];
                    } elseif ($action === self::PULL_FROM_ML) {
                        $pulled++;
                        $authoritative = $info['ml_value'];
                        if ($apply) {
                            self::applyField($listingId, $evaluation['product_id'], $field, $action, $info['ml_value']);
                        }
                    } else { // PUSH_TO_ML
                        $pushed++;
                        $authoritative = $info['system_value'];
                        if ($apply) {
                            self::applyField($listingId, $evaluation['product_id'], $field, $action, $info['system_value']);
                        }
                    }

                    if ($apply) {
                        $snapshotUpdates[self::snapshotColumnForField($field)] = self::snapshotValueForField($field, $authoritative);
                    }
                }

                $imagesAction = $evaluation['images']['action'];
                $fieldActions['images'] = $imagesAction;
                if ($imagesAction === self::PULL_FROM_ML) {
                    $pulled++;
                    if ($apply) {
                        $importer = new MlImageImporter($db);
                        $importer->syncProductImagesFromMl($evaluation['product_id'], $evaluation['images']['ml_pictures']);
                        $snapshotUpdates['images_id_list'] = json_encode(
                            array_map(static fn (array $p): string => $p['id'], $evaluation['images']['ml_pictures']),
                            JSON_UNESCAPED_UNICODE
                        );
                    }
                } else {
                    $noChange++;
                }

                if ($apply && $snapshotUpdates !== []) {
                    self::upsertSnapshot($listingId, $snapshotUpdates);
                }

                $item = ['listing_id' => $listingId, 'blocked' => false, 'fields' => $fieldActions];
                $details[] = $item;
                if ($onProgress !== null) {
                    $onProgress($item);
                }
            } catch (\Throwable $e) {
                $errors[] = ['listing_id' => (int) $listingId, 'error' => $e->getMessage()];
                self::logSync('run', "listing_id={$listingId}", 'ERROR: ' . $e->getMessage());
            }
        }

        self::logSync(
            'run',
            'resumen',
            'listings=' . count($listingIds) . " pulled={$pulled} pushed={$pushed} conflicts={$conflicts}"
            . " no_change={$noChange} blocked={$blocked} errores=" . count($errors)
            . ' modo=' . ($apply ? 'apply' : 'dry-run')
        );

        return [
            'pulled' => $pulled,
            'pushed' => $pushed,
            'conflicts' => $conflicts,
            'no_change' => $noChange,
            'blocked' => $blocked,
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /** Usado por la tabla interactiva de conflictos ("usar ML" / "usar sistema" por fila). */
    public static function applyResolution(int $conflictId, string $resolution): array
    {
        if (!in_array($resolution, ['ml', 'sistema'], true)) {
            return ['success' => false, 'error' => 'Resolución inválida.'];
        }

        $db = Database::getInstance();
        $conflict = $db->fetch(
            'SELECT c.*, l.product_id AS listing_product_id
             FROM ml_sync_conflicts c
             JOIN ml_listings l ON l.id = c.ml_listing_id
             WHERE c.id = ? AND c.resolved_at IS NULL',
            [$conflictId]
        );
        if ($conflict === null) {
            return ['success' => false, 'error' => 'Conflicto no encontrado o ya resuelto.'];
        }

        $listingId = (int) $conflict['ml_listing_id'];
        $productId = (int) ($conflict['listing_product_id'] ?? 0);
        $field = (string) $conflict['field'];

        if ($resolution === 'ml') {
            $value = $conflict['ml_value'];
            self::applyField($listingId, $productId, $field, self::PULL_FROM_ML, $value);
            self::upsertSnapshot($listingId, [self::snapshotColumnForField($field) => self::snapshotValueForField($field, $value)]);
        } else {
            self::applyField($listingId, $productId, $field, self::PUSH_TO_ML, null);
            $freshValue = self::currentSystemValue($listingId, $productId, $field);
            self::upsertSnapshot($listingId, [self::snapshotColumnForField($field) => self::snapshotValueForField($field, $freshValue)]);
        }

        $db->update('ml_sync_conflicts', [
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution' => $resolution,
            'resolved_by' => trim((string) ($_SESSION['admin_username'] ?? 'admin')),
        ], 'id = :id', ['id' => $conflictId]);

        self::logSync('applyResolution', "conflict_id={$conflictId} listing_id={$listingId} field={$field}", "resuelto como {$resolution}");

        return ['success' => true, 'error' => ''];
    }

    private static function applyField(int $listingId, int $productId, string $field, string $action, mixed $value): void
    {
        $db = Database::getInstance();

        if ($action === self::PUSH_TO_ML) {
            match ($field) {
                'title' => MercadoLibreService::syncItemTitle($listingId),
                'price' => MercadoLibreService::syncItemPrice($listingId),
                'available_quantity' => MercadoLibreService::syncItemQuantity($listingId),
                'description' => MercadoLibreService::syncItemDescription($listingId),
                default => null,
            };

            return;
        }

        switch ($field) {
            case 'title':
                $db->update('ml_listings', ['title' => mb_substr((string) $value, 0, 60)], 'id = :id', ['id' => $listingId]);
                break;
            case 'price':
                $db->update('ml_listings', ['price' => round((float) $value, 2)], 'id = :id', ['id' => $listingId]);
                break;
            case 'available_quantity':
                $db->update('ml_listings', ['available_quantity_override' => (int) $value], 'id = :id', ['id' => $listingId]);
                break;
            case 'description':
                $db->update('products', ['full_description' => (string) $value], 'id = :id', ['id' => $productId]);
                break;
            case 'category_id':
                if (MercadoLibreService::isCategoryLeaf((string) $value)) {
                    $db->update('ml_listings', ['ml_category_id' => (string) $value], 'id = :id', ['id' => $listingId]);
                } else {
                    self::logSync('applyField', "listing_id={$listingId}", "category_id pull omitido: {$value} no es categoría hoja");
                }
                break;
        }
    }

    private static function currentSystemValue(int $listingId, int $productId, string $field): mixed
    {
        $db = Database::getInstance();
        $listing = $db->fetch('SELECT * FROM ml_listings WHERE id = ?', [$listingId]);
        $product = $productId > 0 ? $db->fetch('SELECT * FROM products WHERE id = ?', [$productId]) : null;

        return match ($field) {
            'title' => self::normalizeText((string) ($listing['title'] ?? '')),
            'price' => self::normalizeMoney(MercadoLibreService::calculateMlPrice(
                $productId,
                isset($listing['ml_markup']) && $listing['ml_markup'] !== null && $listing['ml_markup'] !== ''
                    ? (float) $listing['ml_markup']
                    : null
            )),
            'available_quantity' => $listing !== null ? self::normalizeInt(MercadoLibreService::resolveQuantity($listing)) : 0,
            'description' => $product !== null ? MercadoLibreService::buildDescription($product) : '',
            'category_id' => self::normalizeText((string) ($listing['ml_category_id'] ?? '')),
            default => null,
        };
    }

    /**
     * @param list<array{id: string, secure_url: string}> $mlPictures orden actual en ML
     * @param list<string> $localPictureIds ml_picture_id ya guardados localmente, en sort_order
     *
     * Tolera hasta 1 id "extra" del lado ML sin marcarlo como cambio real: buildPictures() siempre
     * agrega la imagen de badge ML como una foto más del listing, y esa foto nunca se guarda como
     * product_images local (se descarta por hash al aplicar el pull, ver MlImageImporter). Más de
     * un id extra, o cualquier id local ausente en ML, sí es un cambio real.
     */
    private static function evaluateImagesAction(array $mlPictures, array $localPictureIds): string
    {
        $mlIds = array_values(array_map(static fn (array $p): string => (string) $p['id'], $mlPictures));

        $missingLocally = array_diff($localPictureIds, $mlIds);
        if ($missingLocally !== []) {
            return self::PULL_FROM_ML;
        }

        $extraInMl = array_values(array_diff($mlIds, $localPictureIds));
        if (count($extraInMl) > 1) {
            return self::PULL_FROM_ML;
        }

        $mlIdsWithoutExtra = array_values(array_diff($mlIds, $extraInMl));

        return $mlIdsWithoutExtra === $localPictureIds ? self::NO_CHANGE : self::PULL_FROM_ML;
    }

    private static function snapshotColumnForField(string $field): string
    {
        return $field === 'description' ? 'description_hash' : $field;
    }

    private static function snapshotValueForField(string $field, mixed $rawValue): mixed
    {
        return match ($field) {
            'title' => self::normalizeText((string) $rawValue),
            'price' => self::normalizeMoney((float) $rawValue),
            'available_quantity' => self::normalizeInt((int) $rawValue),
            'description' => md5(self::normalizeText((string) $rawValue)),
            'category_id' => self::normalizeText((string) $rawValue),
            default => $rawValue,
        };
    }

    private static function snapshotColumnValue(?array $snapshot, string $column): mixed
    {
        if ($snapshot === null || !array_key_exists($column, $snapshot) || $snapshot[$column] === null) {
            return null;
        }

        return $snapshot[$column];
    }

    /** @param array<string, mixed> $fields */
    private static function upsertSnapshot(int $listingId, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $db = Database::getInstance();
        $existing = $db->fetch('SELECT ml_listing_id FROM ml_sync_snapshots WHERE ml_listing_id = ?', [$listingId]);
        $fields['last_synced_at'] = date('Y-m-d H:i:s');

        if ($existing !== null) {
            $db->update('ml_sync_snapshots', $fields, 'ml_listing_id = :id', ['id' => $listingId]);
        } else {
            $fields['ml_listing_id'] = $listingId;
            $db->insert('ml_sync_snapshots', $fields);
        }
    }

    private static function upsertConflict(int $listingId, string $field, mixed $mlValue, mixed $systemValue, mixed $lastSyncValue): void
    {
        $db = Database::getInstance();
        $existing = $db->fetch(
            'SELECT id FROM ml_sync_conflicts WHERE ml_listing_id = ? AND field = ? AND resolved_at IS NULL',
            [$listingId, $field]
        );

        $data = [
            'ml_value' => self::stringifyForStorage($mlValue),
            'system_value' => self::stringifyForStorage($systemValue),
            'last_sync_value' => self::stringifyForStorage($lastSyncValue),
        ];

        if ($existing !== null) {
            $data['detected_at'] = date('Y-m-d H:i:s');
            $db->update('ml_sync_conflicts', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $data['ml_listing_id'] = $listingId;
            $data['field'] = $field;
            $db->insert('ml_sync_conflicts', $data);
        }
    }

    private static function stringifyForStorage(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function normalizeText(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', trim((string) $value)));
    }

    private static function normalizeMoney(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private static function normalizeInt(mixed $value): int
    {
        return (int) $value;
    }

    private static function logSync(string $context, string $extra, string $message): void
    {
        $logDir = defined('STORAGE_PATH')
            ? rtrim((string) STORAGE_PATH, '/') . '/logs'
            : dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            '[%s] %s %s: %s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            $context,
            $extra,
            str_replace(["\r", "\n"], ' ', $message)
        );

        @file_put_contents($logDir . '/ml_sync.log', $line, FILE_APPEND | LOCK_EX);
    }
}
