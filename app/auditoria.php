<?php
/**
 * Registro de auditoría: guarda eventos de seguridad en la tabla `auditoria`.
 * Sirve como evidencia y como insumo para la respuesta ante incidentes.
 *
 * Eventos usados: LOGIN_EXITOSO, LOGIN_FALLIDO, LOGIN_BLOQUEADO, CUENTA_BLOQUEADA,
 * LOGOUT, SESION_EXPIRADA, CSRF_INVALIDO, ACCESO_DENEGADO, REGISTRO, DESBLOQUEO.
 */
function ip_cliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

function registrar_evento(string $evento, ?string $usuario = null, string $detalle = ''): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO auditoria (evento, usuario, ip, user_agent, detalle) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([
            $evento,
            $usuario === null ? null : mb_substr($usuario, 0, 50),
            ip_cliente(),
            mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            mb_substr($detalle, 0, 255),
        ]);
    } catch (Throwable $e) {
        // Si la BD falla, el evento queda al menos en el log de PHP-FPM.
        error_log("[auditoria] $evento usuario=" . ($usuario ?? '-') . ' ip=' . ip_cliente() . ' error=' . $e->getMessage());
    }
}
