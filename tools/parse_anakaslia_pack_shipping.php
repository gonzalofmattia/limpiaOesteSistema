<?php

declare(strict_types=1);

$path = dirname(__DIR__) . '/storage/logs/ml_errors_prod.log';
if (!is_file($path)) {
    fwrite(STDERR, "No existe {$path}. Ejecutá: php tools/fetch_prod_ml_log.php\n");
    exit(1);
}

$rows = [];
foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
    if (!str_contains($line, 'DIAG ANAKASLIA 2026-05-28 raw_json=')) {
        continue;
    }
    $prefix = 'DIAG ANAKASLIA 2026-05-28 raw_json=';
    $pos = strpos($line, $prefix);
    if ($pos === false) {
        continue;
    }
    $json = substr($line, $pos + strlen($prefix));
    $order = json_decode($json, true);
    if (!is_array($order)) {
        continue;
    }
    $orderId = (string) ($order['id'] ?? '');
    $packId = $order['pack_id'] ?? null;
    $shipping = $order['shipping'] ?? null;
    $shippingId = is_array($shipping) ? ($shipping['id'] ?? null) : null;

    $rows[] = [
        'order_id' => $orderId,
        'pack_id' => $packId,
        'shipping.id' => $shippingId,
    ];
}

if ($rows === []) {
    fwrite(STDERR, "No se encontraron líneas DIAG ANAKASLIA raw_json en el log.\n");
    exit(1);
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$packIds = array_values(array_unique(array_map(static fn (array $r) => json_encode($r['pack_id']), $rows)));
$shippingIds = array_values(array_unique(array_map(static fn (array $r) => json_encode($r['shipping.id']), $rows)));

echo "pack_id distintos: " . count($packIds) . "\n";
echo "shipping.id distintos: " . count($shippingIds) . "\n";
echo "pack_id en común: " . (count($packIds) === 1 ? 'SÍ → ' . ($rows[0]['pack_id'] ?? 'null') : 'NO') . "\n";
echo "shipping.id en común: " . (count($shippingIds) === 1 ? 'SÍ → ' . ($rows[0]['shipping.id'] ?? 'null') : 'NO') . "\n";
