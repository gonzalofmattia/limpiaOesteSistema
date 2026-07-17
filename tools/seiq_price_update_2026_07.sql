-- Aumento SEIQ julio 2026 (acido sulfonico) - Lista de precios N 24
-- 23 productos / 54 campos. Cada UPDATE valida el valor anterior en el WHERE:
-- si produccion ya tiene otro valor en ese campo, esa fila no se toca (no pisa cambios manuales).
START TRANSACTION;

UPDATE products SET precio_lista_caja = 47371.99 WHERE code = '861022' AND precio_lista_caja = 41922.12;
UPDATE products SET precio_lista_bidon = 11843.00 WHERE code = '861022' AND precio_lista_bidon = 10480.53;
UPDATE products SET precio_lista_litro = 2368.60 WHERE code = '861022' AND precio_lista_litro = 2096.11;
UPDATE products SET precio_lista_caja = 43571.85 WHERE code = '8256' AND precio_lista_caja = 39610.78;
UPDATE products SET precio_lista_bidon = 10892.96 WHERE code = '8256' AND precio_lista_bidon = 9902.69;
UPDATE products SET precio_lista_litro = 2178.59 WHERE code = '8256' AND precio_lista_litro = 1980.54;
UPDATE products SET precio_lista_caja = 64954.44 WHERE code = '861017' AND precio_lista_caja = 57481.80;
UPDATE products SET precio_lista_bidon = 16238.61 WHERE code = '861017' AND precio_lista_bidon = 14370.45;
UPDATE products SET precio_lista_litro = 3247.72 WHERE code = '861017' AND precio_lista_litro = 2874.09;
UPDATE products SET precio_lista_caja = 64954.44 WHERE code = '2026F' AND precio_lista_caja = 57481.80;
UPDATE products SET precio_lista_bidon = 16238.61 WHERE code = '2026F' AND precio_lista_bidon = 14370.45;
UPDATE products SET precio_lista_litro = 3247.72 WHERE code = '2026F' AND precio_lista_litro = 2874.09;
UPDATE products SET precio_lista_caja = 31651.00 WHERE code = '398120' AND precio_lista_caja = 28009.73;
UPDATE products SET precio_lista_bidon = 7912.75 WHERE code = '398120' AND precio_lista_bidon = 7002.43;
UPDATE products SET precio_lista_litro = 1582.55 WHERE code = '398120' AND precio_lista_litro = 1400.49;
UPDATE products SET precio_lista_caja = 48810.50 WHERE code = '861018' AND precio_lista_caja = 43195.13;
UPDATE products SET precio_lista_bidon = 12202.62 WHERE code = '861018' AND precio_lista_bidon = 10798.78;
UPDATE products SET precio_lista_litro = 2440.52 WHERE code = '861018' AND precio_lista_litro = 2159.76;
UPDATE products SET precio_lista_caja = 37119.38 WHERE code = '861024' AND precio_lista_caja = 33744.89;
UPDATE products SET precio_lista_bidon = 9279.85 WHERE code = '861024' AND precio_lista_bidon = 8436.22;
UPDATE products SET precio_lista_litro = 1855.97 WHERE code = '861024' AND precio_lista_litro = 1687.24;
UPDATE products SET precio_lista_caja = 50394.91 WHERE code = '1001' AND precio_lista_caja = 44995.45;
UPDATE products SET precio_lista_bidon = 12598.73 WHERE code = '1001' AND precio_lista_bidon = 11248.86;
UPDATE products SET precio_lista_litro = 2519.75 WHERE code = '1001' AND precio_lista_litro = 2249.77;
UPDATE products SET precio_lista_caja = 69254.28 WHERE code = '262215' AND precio_lista_caja = 62958.44;
UPDATE products SET precio_lista_bidon = 17313.57 WHERE code = '262215' AND precio_lista_bidon = 15739.61;
UPDATE products SET precio_lista_litro = 3462.71 WHERE code = '262215' AND precio_lista_litro = 3147.92;
UPDATE products SET precio_lista_caja = 44390.39 WHERE code = '861009 A' AND precio_lista_caja = 39634.28;
UPDATE products SET precio_lista_bidon = 11097.60 WHERE code = '861009 A' AND precio_lista_bidon = 9908.57;
UPDATE products SET precio_lista_litro = 2219.52 WHERE code = '861009 A' AND precio_lista_litro = 1981.71;
UPDATE products SET precio_lista_caja = 79007.24 WHERE code = '861000' AND precio_lista_caja = 69917.91;
UPDATE products SET precio_lista_bidon = 19751.81 WHERE code = '861000' AND precio_lista_bidon = 17479.48;
UPDATE products SET precio_lista_litro = 3950.36 WHERE code = '861000' AND precio_lista_litro = 3495.90;
UPDATE products SET precio_lista_caja = 104736.78 WHERE code = '391739' AND precio_lista_caja = 93514.98;
UPDATE products SET precio_lista_sobre = 2618.42 WHERE code = '391739' AND precio_lista_sobre = 2337.87;
UPDATE products SET precio_lista_caja = 52728.57 WHERE code = '391746' AND precio_lista_caja = 47079.08;
UPDATE products SET precio_lista_sobre = 1318.21 WHERE code = '391746' AND precio_lista_sobre = 1176.98;
UPDATE products SET precio_lista_caja = 67255.83 WHERE code = '391747' AND precio_lista_caja = 60049.85;
UPDATE products SET precio_lista_sobre = 1681.40 WHERE code = '391747' AND precio_lista_sobre = 1501.25;
UPDATE products SET precio_lista_unitario = 3497.50 WHERE code = 'ECOAAI01' AND precio_lista_unitario = 3330.95;
UPDATE products SET precio_lista_unitario = 4059.84 WHERE code = 'ECOSP02' AND precio_lista_unitario = 3866.52;
UPDATE products SET precio_lista_unitario = 3113.46 WHERE code = 'ECOLTA06' AND precio_lista_unitario = 2965.20;
UPDATE products SET precio_lista_unitario = 3230.04 WHERE code = 'ECOALM07' AND precio_lista_unitario = 3076.23;
UPDATE products SET precio_lista_unitario = 2655.88 WHERE code = 'ECOA09' AND precio_lista_unitario = 2553.73;
UPDATE products SET precio_lista_unitario = 1703.26 WHERE code = 'ECOLL18' AND precio_lista_unitario = 1520.77;
UPDATE products SET precio_lista_caja = 20439.14 WHERE code = 'ECOLL18' AND precio_lista_caja = 18249.24;
UPDATE products SET precio_lista_unitario = 1703.26 WHERE code = 'ECOLAV19' AND precio_lista_unitario = 1520.77;
UPDATE products SET precio_lista_caja = 20439.14 WHERE code = 'ECOLAV19' AND precio_lista_caja = 18249.24;
UPDATE products SET precio_lista_caja = 72105.33 WHERE code = 'ALFRC2' AND precio_lista_caja = 62700.29;
UPDATE products SET precio_lista_bidon = 18026.33 WHERE code = 'ALFRC2' AND precio_lista_bidon = 15675.07;
UPDATE products SET precio_lista_litro = 3605.27 WHERE code = 'ALFRC2' AND precio_lista_litro = 3135.01;
UPDATE products SET precio_lista_caja = 98844.39 WHERE code = 'ALPWR4' AND precio_lista_caja = 85951.64;
UPDATE products SET precio_lista_bidon = 24711.10 WHERE code = 'ALPWR4' AND precio_lista_bidon = 21487.91;
UPDATE products SET precio_lista_litro = 4942.22 WHERE code = 'ALPWR4' AND precio_lista_litro = 4297.58;

-- Deberia reportar 54 filas afectadas en total (sumando el ROW_COUNT de cada UPDATE).
-- Si el numero de filas afectadas por algun UPDATE da 0, revisar ese producto a mano antes de hacer COMMIT.
COMMIT;
