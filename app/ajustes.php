<?php
/**
 * Parámetros de seguridad de la aplicación (no secretos).
 * Los secretos (credenciales de BD) están en config.php, que genera instalar.sh.
 */

// Costo de bcrypt: 2^12 iteraciones. Más alto = más lento para un atacante.
const BCRYPT_COSTO = 12;

// Bloqueo por fuerza bruta.
const MAX_INTENTOS    = 5;   // intentos fallidos permitidos
const BLOQUEO_MINUTOS = 15;  // duración del bloqueo

// Cierre automático de sesión por inactividad (segundos).
const SESION_INACTIVIDAD = 900; // 15 minutos

// Nombre de la cookie de sesión (se evita el nombre por defecto PHPSESSID).
const SESION_NOMBRE = 'F3SESSID';

// Usuarios: letras, números, punto y guion bajo; 3 a 30 caracteres.
const USUARIO_REGEX = '/^[A-Za-z0-9_.]{3,30}$/';
