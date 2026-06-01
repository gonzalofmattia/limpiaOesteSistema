<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';

use App\Helpers\MlPriceIntelligence;

$listings = MlPriceIntelligence::fetchActiveListings();
echo "=== Primeros 3 listings (orden product name ASC) ===\n";
foreach (array_slice($listings, 0, 3) as $i => $l) {
    echo ($i + 1) . ". listing_id=" . ($l['id'] ?? '?') . " product=" . ($l['product_name'] ?? '') . "\n";
    echo "   query esperado: " . MlPriceIntelligence::buildSearchQuery((string) ($l['product_name'] ?? '')) . "\n";
}
