<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Database;

/**
 * Saldo a mostrar / totalizar para clientes:
 * - Si hay cargos tipo invoice en CC: facturas − cobros + ajustes (solo movimientos).
 * - Si no hay invoices (datos viejos o sin sincronizar): total presupuestos aceptados/entregados − cobros + ajustes.
 *
 * Los JOINs deben usar alias `tx` (agregado por cliente) y `q` (totales de presupuestos).
 */
final class ClientReceivableSummary
{
    /**
     * @return array{status:string,label:string,color:string,balance:float,detail:?string}
     */
    public static function getClientDisplayStatus(float $balance, float $tolerance = 800.0): array
    {
        $tolerance = max(0.0, $tolerance);
        if (abs($balance) <= $tolerance) {
            return [
                'status' => 'al_dia',
                'label' => 'Al día',
                'color' => 'green',
                'balance' => $balance,
                'detail' => $balance !== 0.0
                    ? ($balance > 0
                        ? 'Pendiente menor: $' . number_format(abs($balance), 2, ',', '.')
                        : 'Menor a favor: $' . number_format(abs($balance), 2, ',', '.'))
                    : null,
            ];
        }
        if ($balance > 0) {
            return [
                'status' => 'con_deuda',
                'label' => 'Debe',
                'color' => 'red',
                'balance' => $balance,
                'detail' => null,
            ];
        }

        return [
            'status' => 'saldo_favor',
            'label' => 'A favor',
            'color' => 'blue',
            'balance' => $balance,
            'detail' => null,
        ];
    }

    /** Columnas: account_id, inv, pay, adj, net (= inv − pay + adj). */
    public static function sqlTxAggByClientSubquery(): string
    {
        return "SELECT account_id,
                    SUM(CASE WHEN transaction_type = 'invoice' THEN amount ELSE 0 END) AS inv,
                    SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS pay,
                    SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END) AS adj,
                    SUM(CASE WHEN transaction_type = 'invoice' THEN amount ELSE 0 END)
                  - SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END)
                  + SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END) AS net
                FROM account_transactions
                WHERE account_type = 'client'
                GROUP BY account_id";
    }

    public static function sqlQuotesAcceptedByClientSubquery(): string
    {
        return "SELECT client_id,
                    SUM(
                        CASE
                            WHEN COALESCE(is_mercadolibre, 0) = 1 THEN COALESCE(ml_net_amount, 0)
                            ELSE COALESCE(total, 0)
                        END
                    ) AS quotes_total
                FROM quotes
                WHERE status IN ('accepted', 'delivered')
                GROUP BY client_id";
    }

    /**
     * Expresión SQL (usa alias tx, q y c). Debe ir junto a:
     * LEFT JOIN (sqlTxAgg...) tx ON tx.account_id = c.id
     * LEFT JOIN (sqlQuotes...) q ON q.client_id = c.id
     */
    public static function sqlCaseHybridBalance(): string
    {
        return 'CASE WHEN COALESCE(tx.inv, 0) > 0 THEN COALESCE(tx.net, 0)
                    ELSE COALESCE(q.quotes_total, 0) - COALESCE(tx.pay, 0) + COALESCE(tx.adj, 0) END';
    }

    public static function totalReceivable(Database $db): float
    {
        $tx = self::sqlTxAggByClientSubquery();
        $q = self::sqlQuotesAcceptedByClientSubquery();
        $case = self::sqlCaseHybridBalance();
        $sql = "SELECT COALESCE(SUM(bal), 0) FROM (
                    SELECT c.id, ({$case}) AS bal
                    FROM clients c
                    LEFT JOIN ({$tx}) tx ON tx.account_id = c.id
                    LEFT JOIN ({$q}) q ON q.client_id = c.id
                ) z WHERE z.bal > 0";

        return (float) $db->fetchColumn($sql);
    }

    public static function countClientsWithDebt(Database $db, float $tolerance = 0.0): int
    {
        $tolerance = max(0.0, $tolerance);
        $tx = self::sqlTxAggByClientSubquery();
        $q = self::sqlQuotesAcceptedByClientSubquery();
        $case = self::sqlCaseHybridBalance();
        $sql = "SELECT COUNT(*) FROM (
                    SELECT c.id, ({$case}) AS bal
                    FROM clients c
                    LEFT JOIN ({$tx}) tx ON tx.account_id = c.id
                    LEFT JOIN ({$q}) q ON q.client_id = c.id
                ) z WHERE z.bal > ?";

        return (int) $db->fetchColumn($sql, [$tolerance]);
    }

    /** Misma regla híbrida que en listados (una consulta compacta por cliente). */
    public static function hybridBalanceForClient(Database $db, int $clientId): float
    {
        $inv = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'invoice'",
            [$clientId]
        );
        $pay = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'payment'",
            [$clientId]
        );
        $adj = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'adjustment'",
            [$clientId]
        );
        $quotes = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN COALESCE(is_mercadolibre, 0) = 1 THEN COALESCE(ml_net_amount, 0)
                    ELSE COALESCE(total, 0)
                END
            ), 0) FROM quotes
             WHERE client_id = ? AND status IN ('accepted', 'delivered')",
            [$clientId]
        );
        $net = $inv > 0.0 ? ($inv - $pay + $adj) : ($quotes - $pay + $adj);

        return round($net, 2);
    }

    public static function openingBalanceForClient(Database $db, int $clientId): float
    {
        $inv = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM account_transactions
             WHERE account_type = 'client' AND account_id = ? AND transaction_type = 'invoice'",
            [$clientId]
        );
        if ($inv > 0.0) {
            return 0.0;
        }

        $quotes = (float) $db->fetchColumn(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN COALESCE(is_mercadolibre, 0) = 1 THEN COALESCE(ml_net_amount, 0)
                    ELSE COALESCE(total, 0)
                END
            ), 0) FROM quotes
             WHERE client_id = ? AND status IN ('accepted', 'delivered')",
            [$clientId]
        );

        return round($quotes, 2);
    }
}
