-- Plantillas de primer contacto por rubro (texto generico, para reemplazar despues).
-- 'parrilla' ya existia. Se agrega una por cada business_type restante mas un
-- fallback 'todos' generico (sin mencionar rubro) para prospectos sin rubro
-- normalizado o cuyo rubro no tenga plantilla propia.

INSERT INTO outreach_templates (name, business_type, stage, body, active) VALUES
('Primer contacto hotel', 'hotel', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Para hotelería y cabañas manejamos línea de lavandería industrial y desinfección de habitaciones y áreas comunes, pensada para uso diario. Trabajamos con envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1),
('Primer contacto panadería', 'panaderia', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Para panaderías manejamos desengrasantes y desinfectantes de superficies pensados para hornos, mesadas y utensilios de uso diario. Trabajamos con envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1),
('Primer contacto restaurante', 'restaurante', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Para restaurantes con atención al público manejamos una línea completa: desengrasante de cocina, limpieza de baños y salón, y productos para vidrios y superficies, para que todo el local quede impecable. Trabajamos con envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1),
('Primer contacto bar', 'bar', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Para bares manejamos productos para barra, vasos y superficies de atención al público, además de desinfección de baños. Trabajamos con envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1),
('Primer contacto clínica', 'clinica', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Para clínicas y consultorios manejamos línea de desinfección profesional pensada para uso diario en áreas de atención al público. Trabajamos con envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1),
('Primer contacto escuela', 'escuela', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Para instituciones educativas manejamos productos de higiene y desinfección pensados para uso intensivo en aulas y baños. Trabajamos con envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1),
('Primer contacto revendedor', 'revendedor', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional. Trabajamos con revendedores con precios mayoristas y despacho directo en {{ciudad}} y zona — si te interesa sumar una línea de limpieza profesional a tu oferta, te paso los detalles.',
 1),
('Primer contacto genérico', 'todos', 'primer_contacto',
 'Hola {{nombre}}! Te escribimos desde Limpia Oeste, distribuidores de productos de limpieza profesional en {{ciudad}} y zona. Trabajamos con una línea completa pensada para uso comercial diario, envíos prioritarios y precios por volumen. ¿Te copa que te acerquemos una muestra sin cargo para que la pruebes?',
 1);
