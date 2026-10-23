<?php
/**
 * Cierre de sesión seguro.
 *  - Solo acepta POST con token CSRF: un enlace o <img> de otro sitio no puede cerrar la sesión.
 *  - Borra los datos de la sesión, la cookie y el archivo de sesión en el servidor.
 */
require __DIR__ . '/../app/bootstrap.php';

sin_cache();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    mostrar_error(405, 'El cierre de sesión solo se permite mediante el botón "Cerrar sesión".');
}

csrf_verificar();

if (hay_sesion()) {
    registrar_evento('LOGOUT', $_SESSION['usuario']);
}
destruir_sesion();

// Pide al navegador borrar cookies y caché de este sitio (navegadores compatibles).
header('Clear-Site-Data: "cache", "cookies"');
redirigir('login.php?salida=1');
