# Contexto para redactar la Política de Seguridad (Fase 5)

Este documento resume lo que ya está implementado en el proyecto para que la política
describa **lo que realmente hace el sitio**, no medidas genéricas.

## Qué pide la rúbrica

> *Política de seguridad: borrador que incluya contraseñas/usuarios, copias de seguridad y respuesta ante incidentes.* (2 pt)

Son tres secciones obligatorias. Basta un borrador claro (unas 3 a 5 páginas).

## El sistema en una frase

Portal web con inicio de sesión en una máquina virtual **Ubuntu**, servido por **nginx** con **HTTPS**
(certificado autofirmado, CN=localhost), programado en **PHP 8 (PHP-FPM)** con base de datos **MariaDB**.

| Elemento | Ubicación en el servidor |
|---|---|
| Sitio web (raíz pública) | `/var/www/fase3/public` |
| Lógica y configuración privada | `/var/www/fase3/app` (fuera de la raíz web) |
| Credenciales de la BD | `/var/www/fase3/app/config.php` (generado al instalar, no está en Git) |
| Configuración de nginx | `/etc/nginx/sites-available/fase3` |
| Certificado y llave | `/etc/nginx/ssl/nginx-selfsigned.crt` y `.key` |
| Configuración de PHP | `/etc/php/<versión>/fpm/conf.d/99-fase3.ini` |
| Base de datos | `fase3` (tablas `usuarios` y `auditoria`) |
| Logs de nginx | `/var/log/nginx/access.log` y `error.log` |
| Código fuente | https://github.com/ivaniaHer/seguridad_web_proy |

## Sección 1: Manejo de contraseñas y usuarios

Medidas **ya implementadas** que la política debe establecer como norma:

**Contraseñas**
- Se almacenan con **bcrypt, costo 12** (`password_hash` de PHP). Nunca en texto plano.
  Cada hash lleva una **sal aleatoria**: dos usuarios con la misma contraseña tienen hashes distintos.
- Se eligió bcrypt en lugar de SHA-256 porque SHA-256 es muy rápido (facilita la fuerza bruta) y no lleva sal.
- Requisitos mínimos (validados en el navegador y en el servidor):
  - Al menos 8 caracteres y como máximo 72 bytes (límite de bcrypt).
  - Al menos una mayúscula, una minúscula, un número y un símbolo.
  - No puede contener el nombre de usuario.
  - Se rechazan contraseñas comunes (`password`, `12345678`, `admin123`, etc.).
- Si en el futuro se sube el costo de bcrypt, los hashes se actualizan solos al iniciar sesión.

**Usuarios y acceso**
- Dos roles: **admin** (ve el registro de auditoría y puede desbloquear cuentas) y **usuario**.
- Nombres de usuario de 3 a 30 caracteres: letras, números, punto y guion bajo.
- **Bloqueo de 15 minutos tras 5 intentos fallidos**; el admin puede desbloquear antes.
- El mensaje de error es genérico ("Usuario o contraseña incorrectos") para no revelar qué usuarios existen.

**Sesiones**
- Cookie de sesión con `Secure` (solo HTTPS), `HttpOnly` (JavaScript no la lee) y `SameSite=Strict`.
- Se genera un ID de sesión nuevo al iniciar sesión (evita la fijación de sesión).
- **Cierre automático tras 15 minutos de inactividad.**
- El cierre de sesión solo funciona con el botón (POST con token CSRF) y destruye la sesión en el servidor.

**Principio de mínimo privilegio**
- El usuario de la BD que usa la aplicación (`fase3_app`) solo tiene permisos SELECT, INSERT y UPDATE
  (no puede borrar ni modificar tablas).
- Los archivos del sitio pertenecen a `root`; el servidor web (`www-data`) solo puede leerlos.

**Para agregar como norma organizativa** (no está automatizado, pero va en la política):
- Cuentas personales, no compartidas; dar de baja las cuentas de quien deje el proyecto.
- Contraseña del admin distinta a la de cualquier otro servicio; cambio inmediato ante sospecha.
- Revisión periódica de usuarios y roles (por ejemplo, mensual).
- Recomendación: autenticación de dos factores para administradores (mejora futura).

## Sección 2: Copias de seguridad

**Nada de esto está automatizado todavía**: hay que proponerlo. Qué respaldar:

| Qué | Por qué | Comando de ejemplo |
|---|---|---|
| Base de datos `fase3` | Usuarios, hashes y auditoría | `sudo mariadb-dump fase3 > fase3-$(date +%F).sql` |
| Configuración de nginx | Reconstruir el servidor | `sudo tar czf nginx-$(date +%F).tar.gz /etc/nginx` |
| Certificado y llave | HTTPS | incluidos en `/etc/nginx/ssl` |
| `config.php` y `99-fase3.ini` | Credenciales y endurecimiento de PHP | `sudo tar czf app-config.tar.gz /var/www/fase3/app/config.php /etc/php/*/fpm/conf.d/99-fase3.ini` |
| Código | Ya está versionado | GitHub |

Puntos que la política debe definir:
- **Frecuencia:** por ejemplo, BD diaria y configuración semanal o después de cada cambio.
- **Regla 3-2-1:** 3 copias, en 2 medios distintos, 1 fuera del servidor.
- **Cifrado** de los respaldos, porque contienen hashes y credenciales (por ejemplo `gpg -c archivo.sql`).
- **Retención:** por ejemplo, 7 diarias y 4 semanales.
- **Prueba de restauración** periódica: `sudo mariadb fase3 < fase3-FECHA.sql`.
- Responsable y registro de cada respaldo.
- Ejemplo de automatización: una tarea `cron` que ejecute el `mariadb-dump` todos los días.

## Sección 3: Respuesta ante incidentes

**Fuente de detección ya implementada:** la tabla `auditoria` registra fecha, evento, usuario, IP y navegador.
El admin la ve en `https://localhost/admin.php`, o por consola:

```bash
sudo mariadb fase3 -e "SELECT fecha, evento, usuario, ip, detalle FROM auditoria ORDER BY id DESC LIMIT 50;"
```

| Evento registrado | Qué puede indicar |
|---|---|
| `LOGIN_FALLIDO` repetido | Intento de adivinar contraseñas |
| `CUENTA_BLOQUEADA` / `LOGIN_BLOQUEADO` | Ataque de fuerza bruta en curso |
| `CSRF_INVALIDO` | Intento de falsificar peticiones desde otro sitio |
| `ACCESO_DENEGADO` | Usuario intentando entrar a zonas de admin |
| `LOGIN_EXITOSO` desde una IP extraña | Posible cuenta comprometida |
| `REGISTRO` masivo | Creación automatizada de cuentas |

La política debe describir las **fases** (modelo NIST/SANS), aterrizadas a este sitio:

1. **Preparación:** responsables y contactos, respaldos al día, acceso a la auditoría y a los logs.
2. **Detección y análisis:** revisar la auditoría y `/var/log/nginx/access.log`; clasificar la gravedad (baja, media, alta).
3. **Contención:** bloquear la cuenta o la IP afectada (por ejemplo `sudo ufw deny from <IP>`),
   forzar el cambio de contraseña y, si es grave, sacar el sitio de línea.
4. **Erradicación:** corregir la vulnerabilidad, cambiar credenciales (contraseña de la BD en `config.php`
   y del admin) y regenerar el certificado si la llave se vio comprometida.
5. **Recuperación:** restaurar desde respaldo si hubo daño, reinstalar con `instalar.sh` y vigilar la auditoría.
6. **Lecciones aprendidas:** informe del incidente (qué pasó, impacto, causa y medidas nuevas).

Conviene incluir 2 o 3 escenarios de ejemplo: ataque de fuerza bruta, credencial de admin filtrada
y defacement (modificación del sitio).

## Datos útiles de las pruebas (Fase 4), por si se quieren citar

- 35 pruebas automáticas de seguridad: todas aprobadas.
- Nikto: se detectó y corrigió un certificado mal configurado (CN "Ubuntu" en lugar de localhost);
  un aviso de "cookie sin HttpOnly" resultó ser un falso positivo, verificado con `curl`.
- Defensas comprobadas: consultas preparadas (inyección SQL), escape de salida y CSP (XSS),
  tokens CSRF y cookie SameSite (CSRF).
