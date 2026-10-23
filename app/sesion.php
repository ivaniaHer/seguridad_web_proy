<?php
/**
 * Manejo seguro de sesiones.
 *  - Cookie con Secure (solo HTTPS), HttpOnly (JS no la lee) y SameSite=Strict.
 *  - use_strict_mode: PHP rechaza IDs de sesión que él no generó.
 *  - ID nuevo al iniciar sesión (contra fijación de sesión).
 *  - Expiración por inactividad.
 */
function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(SESION_NOMBRE);
    session_set_cookie_params([
        'lifetime' => 0,          // se borra al cerrar el navegador
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && $ahora - $_SESSION['ultima_actividad'] > SESION_INACTIVIDAD) {
        $usuario = $_SESSION['usuario'] ?? null;
        destruir_sesion();
        session_id(session_create_id()); // sesión nueva y vacía con otro ID
        session_start();
        if ($usuario !== null) {
            registrar_evento('SESION_EXPIRADA', $usuario);
            $_SESSION['aviso'] = 'Tu sesión expiró por inactividad. Inicia sesión de nuevo.';
        }
    }
    $_SESSION['ultima_actividad'] = $ahora;
}

/** Se llama después de validar la contraseña. */
function iniciar_sesion_usuario(array $u): void
{
    session_regenerate_id(true); // nuevo ID y se elimina el anterior
    $_SESSION['usuario_id'] = (int) $u['id'];
    $_SESSION['usuario']    = $u['usuario'];
    $_SESSION['rol']        = $u['rol'];
    $_SESSION['inicio']     = time();
    csrf_regenerar();
}

/** Borra datos, cookie y archivo de sesión en el servidor. */
function destruir_sesion(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

function hay_sesion(): bool
{
    return !empty($_SESSION['usuario_id']);
}

/** Evita que el navegador guarde en caché páginas privadas (botón "Atrás" tras logout). */
function sin_cache(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/** Control de acceso: exige sesión iniciada. */
function requerir_login(): void
{
    sin_cache();
    if (!hay_sesion()) {
        $_SESSION['aviso'] = $_SESSION['aviso'] ?? 'Debes iniciar sesión para ver esa página.';
        redirigir('login.php');
    }
}

/** Control de acceso por rol. */
function requerir_rol(string $rol): void
{
    requerir_login();
    if (($_SESSION['rol'] ?? '') !== $rol) {
        registrar_evento('ACCESO_DENEGADO', $_SESSION['usuario'], $_SERVER['REQUEST_URI'] ?? '');
        mostrar_error(403, 'No tienes permisos para acceder a esta sección.');
    }
}
