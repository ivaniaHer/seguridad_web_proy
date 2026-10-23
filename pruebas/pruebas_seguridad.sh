#!/usr/bin/env bash
# =============================================================================
#  Pruebas automáticas de seguridad (Fase 4)
#  Verifica con curl las defensas del sitio y guarda el resultado como evidencia.
#
#  Uso:  bash pruebas/pruebas_seguridad.sh [https://localhost]
#        sudo bash pruebas/pruebas_seguridad.sh   # además revisa el hash en la BD
#
#  Crea un usuario de prueba "prueba_<número>" que queda bloqueado al final.
# =============================================================================
set -uo pipefail

BASE="${1:-https://localhost}"
BASE="${BASE%/}"
HTTP_BASE="http://${BASE#https://}"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

mkdir -p resultados
SALIDA="resultados/pruebas-$(date +%Y%m%d-%H%M%S).txt"
exec > >(tee "$SALIDA") 2>&1

PASA=0
FALLA=0

seccion() { printf '\n== %s ==\n' "$*"; }
resultado() { # resultado <0|1> <descripción>
    if [[ $1 -eq 0 ]]; then printf '  [PASA]  %s\n' "$2"; PASA=$((PASA + 1))
    else printf '  [FALLA] %s\n' "$2"; FALLA=$((FALLA + 1)); fi
}
es() { [[ "$1" == "$2" ]]; }

# peticion <cookies> <GET|POST> <ruta> [campo=valor ...]  -> imprime el código HTTP
peticion() {
    local jar=$1 metodo=$2 ruta=$3; shift 3
    local datos=()
    for d in "$@"; do datos+=(--data-urlencode "$d"); done
    curl -sk -X "$metodo" -c "$jar" -b "$jar" -D "$TMP/cab" -o "$TMP/cuerpo" \
         -w '%{http_code}' "${datos[@]}" "$BASE/$ruta"
}
ubicacion()  { grep -i '^location:' "$TMP/cab" | tr -d '\r' | awk '{print $2}'; }
cuerpo_tiene() { grep -qF -- "$1" "$TMP/cuerpo"; }
token_csrf() { # token_csrf <cookies> <ruta>
    curl -sk -c "$1" -b "$1" "$BASE/$2" | grep -oP 'name="csrf" value="\K[0-9a-f]{64}' | head -n1
}
id_sesion() { grep 'F3SESSID' "$1" | awk '{print $7}' | tail -n1; }

echo "Pruebas de seguridad sobre $BASE - $(date '+%Y-%m-%d %H:%M:%S')"

# -----------------------------------------------------------------------------
seccion "1. HTTPS y cabeceras"
COD=$(curl -s -o /dev/null -D "$TMP/cab" -w '%{http_code}' "$HTTP_BASE/")
[[ "$COD" == "301" ]] && grep -qi '^location: https://' "$TMP/cab"
resultado $? "HTTP redirige a HTTPS (código $COD)"

curl -skI "$BASE/login.php" | tr -d '\r' > "$TMP/cab_login"
for cab in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Content-Security-Policy Referrer-Policy; do
    grep -qi "^$cab:" "$TMP/cab_login"
    resultado $? "Cabecera $cab presente"
done
! grep -qiP '^server: nginx/\d' "$TMP/cab_login"
resultado $? "nginx no revela su versión (server_tokens off)"
! grep -qi '^x-powered-by:' "$TMP/cab_login"
resultado $? "PHP no revela su versión (sin X-Powered-By)"

COOKIE=$(grep -i '^set-cookie: F3SESSID' "$TMP/cab_login")
echo "$COOKIE" | grep -qi 'secure'          ; resultado $? "Cookie de sesión con Secure"
echo "$COOKIE" | grep -qi 'httponly'        ; resultado $? "Cookie de sesión con HttpOnly"
echo "$COOKIE" | grep -qi 'samesite=strict' ; resultado $? "Cookie de sesión con SameSite=Strict"

COD=$(curl -sk -o /dev/null -w '%{http_code}' "$BASE/.git/config")
[[ "$COD" == "403" || "$COD" == "404" ]]; resultado $? "Archivos ocultos bloqueados (/.git/config -> $COD)"
COD=$(curl -sk -o /dev/null -w '%{http_code}' "$BASE/app/config.php")
[[ "$COD" == "404" ]]; resultado $? "config.php fuera de la raíz web (-> $COD)"

# -----------------------------------------------------------------------------
seccion "2. Control de acceso sin sesión"
J="$TMP/anonimo"
COD=$(peticion "$J" GET panel.php)
[[ "$COD" == "303" && "$(ubicacion)" == *login.php ]]; resultado $? "panel.php sin sesión redirige al login ($COD)"
COD=$(peticion "$J" GET admin.php)
[[ "$COD" == "303" && "$(ubicacion)" == *login.php ]]; resultado $? "admin.php sin sesión redirige al login ($COD)"

# -----------------------------------------------------------------------------
seccion "3. CSRF"
COD=$(peticion "$J" POST login.php usuario=admin contrasena=x)
es "$COD" 403; resultado $? "Login sin token CSRF rechazado ($COD)"
COD=$(peticion "$J" POST login.php usuario=admin contrasena=x csrf=0000000000000000000000000000000000000000000000000000000000000000)
es "$COD" 403; resultado $? "Login con token CSRF falso rechazado ($COD)"

# -----------------------------------------------------------------------------
seccion "4. Inyección SQL"
T=$(token_csrf "$J" login.php)
COD=$(peticion "$J" POST login.php "csrf=$T" "usuario=admin' OR 1=1 #" "contrasena=x")
[[ "$COD" == "200" ]] && cuerpo_tiene "incorrectos"; resultado $? "SQLi en usuario (admin' OR 1=1 #) no da acceso ($COD)"
COD=$(peticion "$J" POST login.php "csrf=$T" "usuario=admin" "contrasena=' OR '1'='1")
[[ "$COD" == "200" ]] && cuerpo_tiene "incorrectos"; resultado $? "SQLi en contraseña (' OR '1'='1) no da acceso ($COD)"
! cuerpo_tiene "SQLSTATE"; resultado $? "No se muestran errores de la base de datos"

# -----------------------------------------------------------------------------
seccion "5. XSS"
PAYLOAD='<script>alert(1)</script>'
T=$(token_csrf "$J" registro.php)
peticion "$J" POST registro.php "csrf=$T" "usuario=$PAYLOAD" "contrasena=x" "confirmacion=x" >/dev/null
! cuerpo_tiene "$PAYLOAD" && cuerpo_tiene '&lt;script&gt;'
resultado $? "Entrada con <script> se devuelve escapada (&lt;script&gt;)"

# -----------------------------------------------------------------------------
seccion "6. Registro y política de contraseñas"
USR="prueba_$RANDOM$RANDOM"
CLAVE='Prueba#2026x'
COD=$(peticion "$J" POST registro.php "csrf=$T" "usuario=$USR" "contrasena=12345678" "confirmacion=12345678")
[[ "$COD" == "200" ]] && cuerpo_tiene "mayúscula"; resultado $? "Contraseña débil (12345678) rechazada"
COD=$(peticion "$J" POST registro.php "csrf=$T" "usuario=$USR" "contrasena=$CLAVE" "confirmacion=$CLAVE")
[[ "$COD" == "303" ]]; resultado $? "Contraseña robusta aceptada, usuario $USR creado ($COD)"

if [[ $EUID -eq 0 ]] && command -v mysql >/dev/null; then
    HASH=$(mysql -N fase3 -e "SELECT contrasena_hash FROM usuarios WHERE usuario = '$USR'")
    echo "      hash guardado: $HASH"
    [[ "$HASH" == '$2y$12$'* ]]; resultado $? "Contraseña guardada como hash bcrypt costo 12"
fi

# -----------------------------------------------------------------------------
seccion "7. Inicio de sesión y sesión"
J="$TMP/usuario"
T=$(token_csrf "$J" login.php)
ANTES=$(id_sesion "$J")
COD=$(peticion "$J" POST login.php "csrf=$T" "usuario=$USR" "contrasena=$CLAVE")
[[ "$COD" == "303" && "$(ubicacion)" == *panel.php ]]; resultado $? "Login correcto redirige al panel ($COD)"
DESPUES=$(id_sesion "$J")
[[ -n "$ANTES" && "$ANTES" != "$DESPUES" ]]; resultado $? "ID de sesión regenerado al iniciar sesión (anti fijación)"

COD=$(peticion "$J" GET panel.php)
[[ "$COD" == "200" ]] && cuerpo_tiene "Bienvenido"; resultado $? "Panel accesible con sesión ($COD)"
grep -qi '^cache-control:.*no-store' "$TMP/cab"; resultado $? "Panel con Cache-Control: no-store"
COD=$(peticion "$J" GET admin.php)
es "$COD" 403; resultado $? "Usuario normal no entra a admin.php ($COD)"

# -----------------------------------------------------------------------------
seccion "8. Cierre de sesión"
COD=$(peticion "$J" GET logout.php)
es "$COD" 405; resultado $? "Logout por GET rechazado ($COD)"
COD=$(peticion "$J" POST logout.php)
es "$COD" 403; resultado $? "Logout sin token CSRF rechazado ($COD)"

cp "$J" "$TMP/sesion_vieja"
T=$(token_csrf "$J" panel.php)
COD=$(peticion "$J" POST logout.php "csrf=$T")
[[ "$COD" == "303" && "$(ubicacion)" == *salida=1 ]]; resultado $? "Logout con token correcto ($COD)"
COD=$(peticion "$J" GET panel.php)
es "$COD" 303; resultado $? "Después del logout el panel ya no es accesible ($COD)"
COD=$(peticion "$TMP/sesion_vieja" GET panel.php)
es "$COD" 303; resultado $? "La cookie de sesión anterior quedó inválida ($COD)"

# -----------------------------------------------------------------------------
seccion "9. Fuerza bruta"
J="$TMP/fuerza"
T=$(token_csrf "$J" login.php)
for i in 1 2 3 4 5; do
    peticion "$J" POST login.php "csrf=$T" "usuario=$USR" "contrasena=Incorrecta#$i" >/dev/null
done
cuerpo_tiene "bloqueada"; resultado $? "Cuenta bloqueada tras 5 intentos fallidos"
COD=$(peticion "$J" POST login.php "csrf=$T" "usuario=$USR" "contrasena=$CLAVE")
[[ "$COD" == "200" ]] && cuerpo_tiene "bloqueada"; resultado $? "Durante el bloqueo ni la contraseña correcta funciona"

# -----------------------------------------------------------------------------
printf '\nResumen: %d pruebas pasaron, %d fallaron.\n' "$PASA" "$FALLA"
echo "Evidencia guardada en $SALIDA"
[[ $FALLA -eq 0 ]]
