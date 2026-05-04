-- Entrega parcial de presupuestos: qty_delivered y estado partially_delivered

-- Agregar qty_delivered a quote_items
ALTER TABLE quote_items
ADD COLUMN qty_delivered DECIMAL(10,2) NOT NULL DEFAULT 0
AFTER quantity;

-- Agregar partially_delivered al ENUM de quotes.status
ALTER TABLE quotes
MODIFY COLUMN status ENUM('draft','sent','accepted','rejected','expired','delivered','partially_delivered')
DEFAULT 'draft';

-- Inicializar qty_delivered en ítems de presupuestos ya entregados
UPDATE quote_items qi
JOIN quotes q ON qi.quote_id = q.id
SET qi.qty_delivered = qi.quantity
WHERE q.status = 'delivered';
