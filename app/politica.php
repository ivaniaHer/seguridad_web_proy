<?php
/**
 * Política de contraseñas. No depende de otros archivos para que
 * instalar.sh también pueda usarla al crear el administrador.
 */
const CONTRASENAS_COMUNES = [
    'password', 'contrasena', 'contraseña', '12345678', '123456789', 'qwerty123',
    'admin123', 'administrador', 'bienvenido', 'iloveyou', 'password1', 'abc12345',
];

/** Devuelve la lista de reglas que no se cumplen (vacía = válida). */
function validar_politica_contrasena(string $contrasena, string $usuario = ''): array
{
    $errores = [];
    if (mb_strlen($contrasena) < 8) {
        $errores[] = 'Debe tener al menos 8 caracteres.';
    }
    // bcrypt solo usa los primeros 72 bytes; se rechaza lo que exceda.
    if (strlen($contrasena) > 72) {
        $errores[] = 'No puede superar 72 bytes.';
    }
    if (!preg_match('/[A-Z]/', $contrasena)) {
        $errores[] = 'Debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[a-z]/', $contrasena)) {
        $errores[] = 'Debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/[0-9]/', $contrasena)) {
        $errores[] = 'Debe incluir al menos un número.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $contrasena)) {
        $errores[] = 'Debe incluir al menos un símbolo.';
    }
    if ($usuario !== '' && stripos($contrasena, $usuario) !== false) {
        $errores[] = 'No puede contener el nombre de usuario.';
    }
    $base = strtolower(preg_replace('/[^A-Za-z0-9ñ]/u', '', $contrasena) ?? '');
    foreach (CONTRASENAS_COMUNES as $comun) {
        if ($base === preg_replace('/[^a-z0-9ñ]/u', '', $comun) || strpos($base, $comun) === 0) {
            $errores[] = 'Es demasiado común o predecible.';
            break;
        }
    }
    return $errores;
}
