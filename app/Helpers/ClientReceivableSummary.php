<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Saldo cliente desde movimientos: facturas − cobros + ajustes.
 * No depende de la columna clients.balance (puede quedar desactualizada tras migraciones).
 */
final class ClientReceivableSummary
{
    /** Subconsulta: una fila por cliente con saldo neto. */
    public static function sqlNetByClientSubquery(): string
    {
        return "SELECT account_id,
                    SUM(CASE WHEN transaction_type = 'invoice' THEN amount ELSE 0 END)
                  - SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END)
                  + SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END) AS net
                FROM account_transactions
                WHERE account_type = 'client'
                GROUP BY account_id";
    }

    public static function totalReceivable(Database $db): float
    {
        $sql = 'SELECT COALESCE(SUM(b.net), 0) FROM (' . self::sqlNetByClientSubquery() . ') b WHERE b.net > 0';

        return (float) $db->fetchColumn($sql);
    }

    public static function countClientsWithDebt(Database $db): int
    {
        $sql = 'SELECT COUNT(*) FROM (' . self::sqlNetByClientSubquery() . ') b WHERE b.net > 0';

        return (int) $db->fetchColumn($sql);
    }

    /**
     * Expresión correlacionada: saldo neto del cliente c (usar dentro de SELECT ... FROM clients c).
     */
    public static function sqlCorrelatedNetForClientAlias(string $clientAlias = 'c'): string
    {
        return '(SELECT COALESCE(
                    SUM(CASE WHEN at.transaction_type = \'invoice\' THEN at.amount ELSE 0 END), 0
                ) - COALESCE(
                    SUM(CASE WHEN at.transaction_type = \'payment\' THEN at.amount ELSE 0 END), 0
                ) + COALESCE(
                    SUM(CASE WHEN at.transaction_type = \'adjustment\' THEN at.amount ELSE 0 END), 0
                )
                FROM account_transactions at
                WHERE at.account_type = \'client\' AND at.account_id = ' . $clientAlias . '.id)';
    }
}
