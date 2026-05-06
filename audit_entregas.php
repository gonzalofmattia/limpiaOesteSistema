<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage');
}

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(BASE_PATH . '/.env');
require_once APP_PATH . '/Models/Database.php';

use App\Models\Database;

function auditEntregasStatusLabel(string $status): string
{
    return match ($status) {
        'accepted' => 'Aceptado',
        'partially_delivered' => 'Entrega parcial',
        default => $status,
    };
}

function auditEntregasPad(string $text, int $len): string
{
    $current = mb_strlen($text, 'UTF-8');
    if ($current >= $len) {
        return $text;
    }
    return $text . str_repeat(' ', $len - $current);
}

function auditEntregasBuildReport(): string
{
    $db = Database::getInstance();

    $quotes = $db->fetchAll(
        "SELECT q.id, q.quote_number, q.status, q.created_at, c.name AS client_name
         FROM quotes q
         LEFT JOIN clients c ON c.id = q.client_id
         WHERE q.status IN ('accepted', 'partially_delivered')
         ORDER BY q.created_at ASC, q.id ASC"
    );

    $inTransitRows = $db->fetchAll(
        "SELECT soi.product_id, SUM(soi.boxes_to_order * soi.units_per_box) AS in_transit_units
         FROM seiq_order_items soi
         JOIN seiq_orders so ON so.id = soi.seiq_order_id
         WHERE so.status = 'sent'
         GROUP BY soi.product_id"
    );
    $inTransitByProduct = [];
    foreach ($inTransitRows as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        $units = max(0, (int) ($row['in_transit_units'] ?? 0));
        if ($pid > 0) {
            $inTransitByProduct[$pid] = $units;
        }
    }

    $productStockRows = $db->fetchAll(
        "SELECT id, code, name, COALESCE(stock_units, 0) AS stock_units
         FROM products"
    );
    $productInfo = [];
    $availablePool = [];
    foreach ($productStockRows as $row) {
        $pid = (int) ($row['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $stockFisico = max(0, (int) ($row['stock_units'] ?? 0));
        $enCamino = max(0, (int) ($inTransitByProduct[$pid] ?? 0));
        $productInfo[$pid] = [
            'name' => (string) ($row['name'] ?? 'Producto'),
            'code' => (string) ($row['code'] ?? ''),
        ];
        $availablePool[$pid] = $stockFisico + $enCamino;
    }

    $out = '';
    $out .= '=== AUDITORÍA DE ENTREGAS — [' . date('Y-m-d H:i:s') . "] ===\n\n";

    $totalAuditedQuotes = 0;
    $fullyCoveredQuotes = 0;
    $quotesWithShortage = 0;
    $globalShortageByProduct = [];

    foreach ($quotes as $quote) {
        $quoteId = (int) ($quote['id'] ?? 0);
        if ($quoteId <= 0) {
            continue;
        }

        $pendingItems = $db->fetchAll(
            "SELECT qi.product_id,
                    SUM(GREATEST(0, COALESCE(qi.quantity, 0) - COALESCE(qi.qty_delivered, 0))) AS pending_units
             FROM quote_items qi
             WHERE qi.quote_id = ?
               AND qi.product_id IS NOT NULL
             GROUP BY qi.product_id
             HAVING pending_units > 0
             ORDER BY qi.product_id",
            [$quoteId]
        );

        if ($pendingItems === []) {
            continue;
        }

        $totalAuditedQuotes++;
        $hasShortage = false;

        $dateRaw = (string) ($quote['created_at'] ?? '');
        $date = $dateRaw !== '' ? substr($dateRaw, 0, 10) : '-';
        $out .= '--- '
            . (string) ($quote['quote_number'] ?? ('#' . $quoteId))
            . ' | '
            . (string) ($quote['client_name'] ?? '-')
            . ' | '
            . auditEntregasStatusLabel((string) ($quote['status'] ?? ''))
            . ' | '
            . $date
            . " ---\n";

        foreach ($pendingItems as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $need = max(0, (int) ($item['pending_units'] ?? 0));
            if ($pid <= 0 || $need <= 0) {
                continue;
            }
            $info = $productInfo[$pid] ?? ['name' => 'Producto #' . $pid, 'code' => (string) $pid];
            $available = max(0, (int) ($availablePool[$pid] ?? 0));

            $allocated = min($need, $available);
            $shortage = $need - $allocated;
            $availablePool[$pid] = $available - $allocated;

            if ($shortage > 0) {
                $hasShortage = true;
                $globalShortageByProduct[$pid] = ($globalShortageByProduct[$pid] ?? 0) + $shortage;
            }

            $left = auditEntregasPad((string) $info['name'] . ' (' . (string) $info['code'] . ')', 30);
            if ($shortage > 0) {
                $out .= '  X  '
                    . $left
                    . '  necesita ' . $need . 'u'
                    . '  |  disponible ' . $available . 'u'
                    . '  |  FALTAN ' . $shortage . "u\n";
            } else {
                $out .= '  OK '
                    . $left
                    . '  necesita ' . $need . 'u'
                    . '  |  disponible ' . $available . 'u'
                    . "  |  CUBIERTO\n";
            }
        }

        $out .= "\n";
        if ($hasShortage) {
            $quotesWithShortage++;
        } else {
            $fullyCoveredQuotes++;
        }
    }

    $out .= "=== RESUMEN FINAL ===\n";
    $out .= '  Presupuestos 100% cubiertos: ' . $fullyCoveredQuotes . ' de ' . $totalAuditedQuotes . "\n";
    $out .= '  Presupuestos con faltantes:  ' . $quotesWithShortage . "\n";
    $out .= "  Productos con faltante (globales):\n";
    if ($globalShortageByProduct === []) {
        $out .= "    - Ninguno\n";
    } else {
        foreach ($globalShortageByProduct as $pid => $missing) {
            $info = $productInfo[(int) $pid] ?? ['name' => 'Producto #' . $pid, 'code' => (string) $pid];
            $out .= '    - ' . (string) $info['name'] . ' (' . (string) $info['code'] . '): faltan ' . (int) $missing . "u en total\n";
        }
    }

    return $out;
}

try {
    $report = auditEntregasBuildReport();
    if (PHP_SAPI === 'cli') {
        echo $report;
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Auditoría de entregas</title></head><body>';
        echo '<pre style="font:14px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space:pre-wrap;">'
            . htmlspecialchars($report, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</pre>';
        echo '</body></html>';
    }
} catch (\Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Error al ejecutar auditoría: " . $e->getMessage() . "\n");
    } else {
        http_response_code(500);
        echo 'Error al ejecutar auditoría: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    exit(1);
}

