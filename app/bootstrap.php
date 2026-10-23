<?php
/**
 * Punto de entrada común. La carpeta app/ queda fuera de la raíz web
 * (/var/www/fase3/public), así nginx nunca puede servir estos archivos.
 */
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ajustes.php';
require __DIR__ . '/db.php';
require __DIR__ . '/vista.php';
require __DIR__ . '/auditoria.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/sesion.php';
require __DIR__ . '/politica.php';
require __DIR__ . '/autenticacion.php';

iniciar_sesion_segura();
