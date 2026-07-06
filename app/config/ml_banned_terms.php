<?php

declare(strict_types=1);

/**
 * Términos que en descripciones comerciales ML pueden disparar clasificación de riesgo
 * y pérdida de elegibilidad para Mercado Envíos.
 *
 * Cada entrada es un patrón regex (case-insensitive, UTF-8).
 */
return [
    'ventilaci[oó]n',
    'ventilar',
    '[áa]rea\s+ventilada',
    'riesgo\s+de\s+incendio',
    'inflamable',
    'inflamabilidad',
    'corrosiv[oa]',
    'corrosividad',
    't[oó]xic[oa]',
    'toxicidad',
    'vapores\s+nocivos',
    'vapores\s+t[oó]xicos',
    'reactividad\s+de\s+superficie',
    'reacciona\s+con',
    'peligros[oa]',
    'peligrosidad',
    'evitar\s+contacto\s+con\s+(?:los\s+)?ojos',
    'evitar\s+contacto\s+con\s+la\s+piel',
    'no\s+inhal',
    'usar\s+(?:con\s+)?(?:m[aá]scara|guantes|protecci[oó]n\s+respiratoria)',
    'mantener\s+alejado\s+de\s+(?:fuentes\s+de\s+)?(?:calor|llama|chispas)',
    'punto\s+de\s+inflamaci[oó]n',
    'material\s+peligroso',
    'hoja\s+de\s+seguridad',
    'ficha\s+de\s+seguridad',
];
