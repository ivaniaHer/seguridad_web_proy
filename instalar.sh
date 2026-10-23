#!/usr/bin/env bash
# =============================================================================
#  Instalador Fase 3 - Control de acceso y autenticación
#  Requiere: Ubuntu con nginx y HTTPS ya configurados (Fases 1 y 2).
#
#  Uso:  sudo bash instalar.sh                 # sitio seguro
#        sudo bash instalar.sh --laboratorio   # + páginas vulnerables para la Fase 4
#
#  Se puede volver a ejecutar sin problema (por ejemplo, después de un git pull).
# =============================================================================
set -euo pipefail

DESTINO=/var/www/fase3
BD=fase3
BD_USUARIO=fase3_app
ORIGEN="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LABORATORIO=0

for arg in "$@"; do
    case "$arg" in
        --laboratorio) LABORATORIO=1 ;;
        -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
        *) echo "Opción desconocida: $arg"; exit 1 ;;
    esac
done

paso()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()    { printf '\033[1;32m    ✔ %s\033[0m\n' "$*"; }
falla() { printf '\033[1;31m    ✘ %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || falla "Ejecuta con sudo: sudo bash instalar.sh"
command -v nginx >/dev/null || falla "nginx no está instalado (Fase 1)."

# -----------------------------------------------------------------------------
paso "1/7 Instalando PHP-FPM y MariaDB"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq php-fpm php-cli php-mysql php-mbstring mariadb-server openssl curl >/dev/null
systemctl enable --now mariadb >/dev/null 2>&1
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
ok "PHP ${PHP_VER} y MariaDB listos"

# -----------------------------------------------------------------------------
paso "2/7 Endureciendo la configuración de PHP"
cat > "/etc/php/${PHP_VER}/fpm/conf.d/99-fase3.ini" <<'INI'
; Fase 3 - endurecimiento de PHP
expose_php = Off              ; no revela la versión de PHP en las cabeceras
display_errors = Off          ; los errores no se muestran al visitante
log_errors = On
session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
INI
systemctl enable "php${PHP_VER}-fpm" >/dev/null 2>&1
systemctl restart "php${PHP_VER}-fpm"
ok "/etc/php/${PHP_VER}/fpm/conf.d/99-fase3.ini"

# -----------------------------------------------------------------------------
paso "3/7 Creando base de datos y usuario con privilegios mínimos"
DB_PASS=""
if [[ -f "$DESTINO/app/config.php" ]]; then
    DB_PASS=$(grep -oP "DB_PASS = '\K[^']+" "$DESTINO/app/config.php" || true)
fi
[[ -n "$DB_PASS" ]] || DB_PASS=$(openssl rand -hex 24)

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$BD\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$BD_USUARIO'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$BD_USUARIO'@'localhost' IDENTIFIED BY '$DB_PASS';
-- La aplicación no necesita DELETE, DROP ni ALTER.
GRANT SELECT, INSERT, UPDATE ON \`$BD\`.* TO '$BD_USUARIO'@'localhost';
FLUSH PRIVILEGES;
SQL
mysql "$BD" < "$ORIGEN/sql/esquema.sql"
if [[ $LABORATORIO -eq 1 ]]; then
    mysql "$BD" < "$ORIGEN/sql/laboratorio.sql"
fi
ok "Base '$BD', usuario '$BD_USUARIO' (solo SELECT, INSERT, UPDATE)"

# -----------------------------------------------------------------------------
paso "4/7 Copiando el sitio a $DESTINO"
mkdir -p "$DESTINO"
rm -rf "$DESTINO/app" "$DESTINO/public"
cp -r "$ORIGEN/app" "$ORIGEN/public" "$DESTINO/"
rm -f "$DESTINO/app/config.example.php"
if [[ $LABORATORIO -eq 1 ]]; then
    cp -r "$ORIGEN/laboratorio" "$DESTINO/public/laboratorio"
fi

cat > "$DESTINO/app/config.php" <<PHP
<?php
// Generado por instalar.sh el $(date '+%Y-%m-%d %H:%M'). No subir a Git.
const DB_HOST = 'localhost';
const DB_NAME = '$BD';
const DB_USER = '$BD_USUARIO';
const DB_PASS = '$DB_PASS';
PHP

# root es dueño; www-data (nginx y PHP) solo puede leer.
chown -R root:www-data "$DESTINO"
find "$DESTINO" -type d -exec chmod 750 {} +
find "$DESTINO" -type f -exec chmod 640 {} +
ok "Permisos: root:www-data, carpetas 750, archivos 640"
[[ $LABORATORIO -eq 1 ]] && ok "Laboratorio vulnerable instalado en /laboratorio/"

# -----------------------------------------------------------------------------
paso "5/7 Usuario administrador"
ADMINS=$(mysql -N "$BD" -e "SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")
if [[ "$ADMINS" -eq 0 ]]; then
    read -rp "    Nombre del administrador [admin]: " ADMIN_USUARIO
    ADMIN_USUARIO=${ADMIN_USUARIO:-admin}
    [[ "$ADMIN_USUARIO" =~ ^[A-Za-z0-9_.]{3,30}$ ]] || falla "Usuario inválido (3-30: letras, números, punto, guion bajo)."
    echo "    La contraseña debe tener 8+ caracteres, mayúscula, minúscula, número y símbolo."
    while true; do
        read -rsp "    Contraseña: " P1; echo
        read -rsp "    Repite la contraseña: " P2; echo
        if [[ "$P1" != "$P2" ]]; then echo "    No coinciden, intenta de nuevo."; continue; fi
        # Se valida con la misma política que usa el sitio (app/politica.php).
        ERRORES=$(POLITICA="$DESTINO/app/politica.php" ADMIN_PASS="$P1" ADMIN_USUARIO="$ADMIN_USUARIO" php -r '
            require getenv("POLITICA");
            foreach (validar_politica_contrasena(getenv("ADMIN_PASS"), getenv("ADMIN_USUARIO")) as $e) {
                echo "      - $e\n";
            }')
        [[ -z "$ERRORES" ]] && break
        echo "    La contraseña no cumple la política:"; echo "$ERRORES"
    done
    # La contraseña viaja por variable de entorno, no como argumento visible en "ps".
    HASH=$(ADMIN_PASS="$P1" php -r 'echo password_hash(getenv("ADMIN_PASS"), PASSWORD_BCRYPT, ["cost" => 12]);')
    unset P1 P2
    mysql "$BD" -e "INSERT INTO usuarios (usuario, contrasena_hash, rol) VALUES ('$ADMIN_USUARIO', '$HASH', 'admin')"
    ok "Administrador '$ADMIN_USUARIO' creado (hash bcrypt)"
else
    ok "Ya existe un administrador, se conserva"
fi

# -----------------------------------------------------------------------------
paso "6/7 Configurando nginx"
# Se intenta reutilizar el certificado de la Fase 2.
CERT=$(grep -rhoP '^\s*ssl_certificate\s+\K[^;\s]+' /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ /etc/nginx/snippets/ 2>/dev/null | grep -v '__CERT__' | head -n1 || true)
KEY=$(grep -rhoP '^\s*ssl_certificate_key\s+\K[^;\s]+' /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ /etc/nginx/snippets/ 2>/dev/null | grep -v '__KEY__' | head -n1 || true)
CERT=${CERT:-/etc/ssl/certs/nginx-selfsigned.crt}
KEY=${KEY:-/etc/ssl/private/nginx-selfsigned.key}
read -rp "    Certificado de la Fase 2 [$CERT]: " R; CERT=${R:-$CERT}
read -rp "    Llave privada            [$KEY]: " R; KEY=${R:-$KEY}
[[ -f "$CERT" ]] || falla "No existe $CERT"
[[ -f "$KEY" ]]  || falla "No existe $KEY"

# Respaldo de los sitios activos (incluido el de la Fase 2).
RESPALDO="/etc/nginx/respaldo-fase3-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$RESPALDO"
for f in /etc/nginx/sites-enabled/*; do
    [[ -e "$f" || -L "$f" ]] || continue
    [[ "$(basename "$f")" == "fase3" ]] && continue
    mv "$f" "$RESPALDO/"
done

sed -e "s|__CERT__|$CERT|g" -e "s|__KEY__|$KEY|g" -e "s|__PHP_SOCK__|$PHP_SOCK|g" \
    "$ORIGEN/nginx/fase3.conf" > /etc/nginx/sites-available/fase3
ln -sf /etc/nginx/sites-available/fase3 /etc/nginx/sites-enabled/fase3

if nginx -t 2>/dev/null; then
    systemctl reload nginx
    ok "Sitio activo: /etc/nginx/sites-available/fase3"
    ok "Sitios anteriores respaldados en $RESPALDO"
else
    nginx -t || true
    rm -f /etc/nginx/sites-enabled/fase3
    for f in "$RESPALDO"/*; do [[ -e "$f" || -L "$f" ]] && mv "$f" /etc/nginx/sites-enabled/; done
    falla "La configuración de nginx tiene errores; se restauró la anterior."
fi

# -----------------------------------------------------------------------------
paso "7/7 Verificación"
COD_HTTP=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/ || true)
COD_HTTPS=$(curl -sk -o /dev/null -w '%{http_code}' https://localhost/login.php || true)
[[ "$COD_HTTP" == "301" ]] && ok "HTTP redirige a HTTPS (301)" || echo "    ! HTTP respondió $COD_HTTP"
[[ "$COD_HTTPS" == "200" ]] && ok "Login disponible por HTTPS (200)" || echo "    ! HTTPS respondió $COD_HTTPS (revisa: sudo tail /var/log/nginx/error.log)"

IP=$(hostname -I | awk '{print $1}')
printf '\n\033[1mListo.\033[0m Abre en el navegador: https://%s/\n' "$IP"
[[ $LABORATORIO -eq 1 ]] && printf 'Laboratorio (Fase 4): https://%s/laboratorio/\n' "$IP"
echo "Pruebas automáticas (Fase 4): bash pruebas/pruebas_seguridad.sh https://localhost"
