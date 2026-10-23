<?php
/**
 * Protección CSRF con token sincronizado: cada sesión tiene un token aleatorio
 * que debe venir en todo formulario POST. Un sitio atacante no puede leerlo,
 * por lo que no puede falsificar la solicitud.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_regenerar(): void
{
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Detiene la petición con 403 si el token falta o no coincide. */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    $esperado = $_SESSION['csrf'] ?? '';
    // hash_equals compara en tiempo constante (evita ataques de temporización).
    if (!is_string($enviado) || $esperado === '' || !hash_equals($esperado, $enviado)) {
        registrar_evento('CSRF_INVALIDO', $_SESSION['usuario'] ?? null, $_SERVER['REQUEST_URI'] ?? '');
        mostrar_error(403, 'La solicitud no es válida o el formulario caducó. Vuelve a intentarlo.');
    }
}
