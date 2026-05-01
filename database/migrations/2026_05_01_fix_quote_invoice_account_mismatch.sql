-- Corrige invoices cuyo account_id no coincide con quotes.client_id
-- (típico: se editó el cliente del presupuesto en estado accepted/sent/draft editable
--  y el cargo invoice en CC quedó colgado del cliente anterior).
-- El backfill 2026_04_30 usa siempre q.client_id y no causa este desvío.

UPDATE account_transactions at
INNER JOIN quotes q ON at.reference_type = 'quote' AND at.reference_id = q.id
SET at.account_id = q.client_id
WHERE at.account_type = 'client'
  AND at.transaction_type = 'invoice'
  AND q.client_id IS NOT NULL
  AND q.client_id > 0
  AND at.account_id <> q.client_id;

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
