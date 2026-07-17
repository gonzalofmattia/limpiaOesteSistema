<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Transición canónica y guardada de un presupuesto/venta a 'delivered'.
 *
 * Antes esta guarda (chequear delivery_stock_applied, elegir markDelivered vs.
 * markRemainingDeliveredFromPartial, setear status+flag) estaba reimplementada por
 * separado en QuoteController::changeStatus(), SeiqOrderController::markQuotesDelivered()
 * y MercadoLibreController::markDelivered() — la venta ML fue justamente el caso donde
 * esa reimplementación faltó por completo. Los tres puntos de entrada del sistema que
 * marcan algo como entregado (presupuestos, recepción de pedido a proveedor, ventas ML)
 * deben pasar por acá.
 */
final class QuoteStatusTransitions
{
    /**
     * @return bool true si se aplicó la entrega ahora, false si no correspondía
     *              (ya estaba entregada o el estado no lo permite).
     */
    public static function deliver(Database $db, int $quoteId, string $currentStatus, bool $deliveryAlreadyApplied): bool
    {
        if ($deliveryAlreadyApplied || $currentStatus === 'delivered') {
            return false;
        }
        if (!in_array($currentStatus, ['accepted', 'partially_delivered'], true)) {
            return false;
        }

        if ($currentStatus === 'partially_delivered') {
            QuoteDeliveryStock::markRemainingDeliveredFromPartial($db, $quoteId);
        } else {
            QuoteDeliveryStock::markDelivered($db, $quoteId);
        }
        $db->update(
            'quotes',
            ['status' => 'delivered', 'delivery_stock_applied' => 1],
            'id = :id',
            ['id' => $quoteId]
        );

        return true;
    }
}
