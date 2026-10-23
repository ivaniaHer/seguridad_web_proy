# Implementación integral de seguridad en sitios web · Fases 3 y 4

Universidad Doctor Andrés Bello · Ingeniería en Sistemas · Diseño de Seguridad de Redes

Portal con autenticación segura sobre **nginx + HTTPS** (Fases 1 y 2), **PHP-FPM** y **MariaDB**,
más un laboratorio vulnerable y pruebas para el análisis de la Fase 4.

```
Navegador ──HTTPS──> nginx ──FastCGI──> PHP-FPM ──> MariaDB
            (redirección 80→443,
             cabeceras de seguridad)
```

## Instalación en la VM Ubuntu

Requisito: nginx con el certificado autofirmado de la Fase 2 ya funcionando.

```bash
sudo apt install -y git
git clone https://github.com/ivaniaHer/seguridad_web_proy.git
cd seguridad_web_proy
sudo bash instalar.sh                 # solo el sitio seguro
sudo bash instalar.sh --laboratorio   # + páginas vulnerables para la Fase 4
```

El instalador:

1. Instala `php-fpm`, `php-mysql`, `php-mbstring` y `mariadb-server`.
2. Endurece PHP (`expose_php=Off`, `display_errors=Off`, cookies seguras).
3. Crea la base `fase3` y el usuario `fase3_app` con **solo** SELECT, INSERT y UPDATE.
4. Copia el sitio a `/var/www/fase3` (dueño `root`, grupo `www-data`, permisos 750/640).
5. Pide usuario y contraseña del **administrador** (valida la política y guarda el hash bcrypt).
6. Detecta el certificado de la Fase 2, respalda los sitios activos de nginx en
   `/etc/nginx/respaldo-fase3-FECHA/` y activa `/etc/nginx/sites-available/fase3`.
7. Verifica la redirección HTTP→HTTPS y que el login responda.

Para actualizar después de cambios: `git pull && sudo bash instalar.sh` (la BD y el admin se conservan).

## Fase 3 · Qué se implementó y dónde

| Requisito | Implementación | Archivo |
|---|---|---|
| Formulario de login con validación | Validación en navegador (HTML5 + JS) y en servidor (regex, longitud, tipos) | `public/login.php`, `public/js/validacion.js` |
| Cifrado de contraseñas | **bcrypt** costo 12 con `password_hash` / `password_verify`; rehash automático | `app/autenticacion.php` |
| Cierre de sesión seguro | Solo POST + token CSRF; borra `$_SESSION`, cookie y archivo de sesión; `no-store`; `Clear-Site-Data` | `public/logout.php`, `app/sesion.php` |
| Sesión segura | Cookie `Secure`, `HttpOnly`, `SameSite=Strict`; `use_strict_mode`; ID regenerado al entrar; expira a los 15 min sin actividad | `app/sesion.php` |
| Control de acceso | Páginas protegidas y roles `admin` / `usuario` | `public/panel.php`, `public/admin.php` |
| Política de contraseñas | 8+ caracteres, mayúscula, minúscula, número, símbolo, sin el usuario, sin contraseñas comunes, máx. 72 bytes | `app/politica.php`, `public/registro.php` |
| Anti fuerza bruta | 5 intentos fallidos → bloqueo de 15 min; el admin puede desbloquear | `app/autenticacion.php`, `public/admin.php` |
| Anti enumeración de usuarios | Mensaje genérico y verificación contra hash ficticio (mismo tiempo de respuesta) | `app/autenticacion.php` |
| Anti CSRF | Token de 256 bits por sesión, comparado con `hash_equals` | `app/csrf.php` |
| Anti inyección SQL | PDO con consultas preparadas reales (`EMULATE_PREPARES=false`) | `app/db.php` |
| Anti XSS | `htmlspecialchars` en toda salida + CSP sin scripts en línea | `app/vista.php`, `nginx/fase3.conf` |
| Auditoría | Tabla `auditoria` con login, fallos, bloqueos, logout, CSRF, accesos denegados | `app/auditoria.php` |
| Cabeceras de seguridad | HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy | `nginx/fase3.conf` |

**¿Por qué bcrypt y no SHA-256?** SHA-256 está hecho para ser rápido: una GPU calcula miles de millones
por segundo, y sin sal dos usuarios con la misma contraseña tienen el mismo hash (tablas arcoíris). bcrypt
incluye sal aleatoria por contraseña y un costo ajustable (2^12 iteraciones) que lo vuelve deliberadamente lento.

## Fase 4 · Análisis de vulnerabilidades

Ver **[docs/FASE4_GUIA.md](docs/FASE4_GUIA.md)**: Nikto, OWASP ZAP, pruebas manuales de SQLi, XSS y CSRF
contra el laboratorio y el sitio seguro, y plantilla de hallazgos.

```bash
sudo bash pruebas/pruebas_seguridad.sh https://localhost   # ~35 verificaciones automáticas
```

## Estructura

```
app/            Lógica (fuera de la raíz web; nginx no puede servirla)
public/         Raíz web: login, registro, panel, admin, logout, CSS y JS
laboratorio/    Páginas vulnerables a propósito (solo con --laboratorio)
sql/            Esquema de la BD y datos del laboratorio
nginx/          Configuración del sitio (plantilla)
pruebas/        Script de pruebas y página atacante CSRF
docs/           Guía de la Fase 4
instalar.sh     Instalador
```

## Guion sugerido para la exposición

1. Mostrar `http://IP` → redirige a `https://IP` (Fases 1 y 2).
2. Registro con contraseña débil (rechazada) y luego robusta; mostrar el hash en la BD:
   `sudo mysql fase3 -e "SELECT usuario, contrasena_hash FROM usuarios"`.
3. Login; en F12 → Cookies mostrar las banderas de `F3SESSID` y que el valor cambia al entrar.
4. Usuario normal intenta `/admin.php` → 403. Admin sí entra y ve la auditoría.
5. 5 intentos fallidos → bloqueo; el admin lo desbloquea.
6. Logout → botón "Atrás" no muestra el panel.
7. Laboratorio: SQLi, XSS y CSRF funcionan; los mismos ataques fallan en el sitio seguro.
8. Resultados de `pruebas_seguridad.sh`, Nikto y ZAP.
