<?php
/**
 * Autenticación con bcrypt y bloqueo por intentos fallidos.
 */
function hash_contrasena(string $contrasena): string
{
    return password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => BCRYPT_COSTO]);
}

/**
 * Hash de relleno: si el usuario no existe se verifica igual contra este hash,
 * así la respuesta tarda lo mismo y no revela qué usuarios existen.
 */
function hash_ficticio(): string
{
    static $h = null;
    return $h ?? ($h = hash_contrasena(bin2hex(random_bytes(16))));
}

/**
 * Devuelve ['ok' => bool, 'motivo' => string, 'usuario' => ?array].
 * Motivos: ok, credenciales, bloqueado.
 */
function autenticar(string $usuario, string $contrasena): array
{
    $st = db()->prepare(
        'SELECT id, usuario, contrasena_hash, rol, activo, intentos_fallidos,
                (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado
           FROM usuarios WHERE usuario = ?'
    );
    $st->execute([$usuario]);
    $u = $st->fetch();

    if (!$u) {
        password_verify($contrasena, hash_ficticio());
        registrar_evento('LOGIN_FALLIDO', $usuario, 'usuario inexistente');
        return ['ok' => false, 'motivo' => 'credenciales', 'usuario' => null];
    }

    if ((int) $u['bloqueado']) {
        registrar_evento('LOGIN_BLOQUEADO', $u['usuario'], 'intento durante bloqueo');
        return ['ok' => false, 'motivo' => 'bloqueado', 'usuario' => null];
    }

    if (!password_verify($contrasena, $u['contrasena_hash']) || !(int) $u['activo']) {
        registrar_fallo((int) $u['id']);
        $intentos = (int) $u['intentos_fallidos'] + 1;
        if ($intentos >= MAX_INTENTOS) {
            registrar_evento('CUENTA_BLOQUEADA', $u['usuario'], MAX_INTENTOS . ' intentos fallidos');
            return ['ok' => false, 'motivo' => 'bloqueado', 'usuario' => null];
        }
        registrar_evento('LOGIN_FALLIDO', $u['usuario'], "intento $intentos de " . MAX_INTENTOS);
        return ['ok' => false, 'motivo' => 'credenciales', 'usuario' => null];
    }

    // Éxito: se reinician los contadores.
    db()->prepare(
        'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?'
    )->execute([$u['id']]);

    // Si se sube BCRYPT_COSTO, los hashes viejos se actualizan solos al iniciar sesión.
    if (password_needs_rehash($u['contrasena_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COSTO])) {
        db()->prepare('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?')
            ->execute([hash_contrasena($contrasena), $u['id']]);
    }

    registrar_evento('LOGIN_EXITOSO', $u['usuario']);
    return ['ok' => true, 'motivo' => 'ok', 'usuario' => $u];
}

/** Suma un intento fallido; al llegar a MAX_INTENTOS bloquea la cuenta y reinicia el contador. */
function registrar_fallo(int $id): void
{
    // En MariaDB las asignaciones del UPDATE se evalúan en orden; ambas usan el valor original.
    $sql = 'UPDATE usuarios
               SET bloqueado_hasta   = IF(intentos_fallidos + 1 >= ' . MAX_INTENTOS . ',
                                          NOW() + INTERVAL ' . BLOQUEO_MINUTOS . ' MINUTE, bloqueado_hasta),
                   intentos_fallidos = IF(intentos_fallidos + 1 >= ' . MAX_INTENTOS . ', 0, intentos_fallidos + 1)
             WHERE id = ?';
    db()->prepare($sql)->execute([$id]);
}
