<?php

declare(strict_types=1);

/**
 * Entrada si el docroot apunta al repo (no a /public).
 * Evita 404 al abrir solo la carpeta del proyecto.
 */
header('Location: public/', true, 302);
exit;
