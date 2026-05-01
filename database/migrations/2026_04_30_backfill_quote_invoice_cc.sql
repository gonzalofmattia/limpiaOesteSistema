-- Presupuestos accepted/delivered sin movimiento invoice en cuenta corriente
-- (antes solo se generaba el cargo al pasar a accepted, no al entregar directamente).
-- Idempotente: solo inserta si no existe la fila quote+invoice.

INSERT INTO account_transactions (
    account_type,
    account_id,
    transaction_type,
    reference_type,
    reference_id,
    amount,
    description,
    transaction_date
)
SELECT
    'client',
    q.client_id,
    'invoice',
    'quote',
    q.id,
    q.total,
    CONCAT('Presupuesto ', q.quote_number),
    DATE(COALESCE(q.sent_at, q.updated_at, q.created_at))
FROM quotes q
WHERE q.status IN ('accepted', 'delivered')
  AND q.client_id IS NOT NULL
  AND q.client_id > 0
  AND q.total > 0
  AND NOT EXISTS (
      SELECT 1
      FROM account_transactions at
      WHERE at.reference_type = 'quote'
        AND at.reference_id = q.id
        AND at.transaction_type = 'invoice'
  );

-- Recalcular balance de clientes desde movimientos
UPDATE clients c
LEFT JOIN (
    SELECT account_id,
           SUM(CASE WHEN transaction_type = 'invoice' THEN amount ELSE 0 END)
         - SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END)
         + SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END) AS bal
    FROM account_transactions
    WHERE account_type = 'client'
    GROUP BY account_id
) t ON t.account_id = c.id
SET c.balance = ROUND(COALESCE(t.bal, 0), 2);
