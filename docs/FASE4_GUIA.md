# Fase 4 · Guía de análisis de vulnerabilidades

Objetivo: escanear el sitio con **Nikto** y **OWASP ZAP**, probar a mano **inyección SQL, XSS y CSRF**, y
documentar hallazgos y soluciones. La idea para el informe y la exposición es **comparar** el
laboratorio vulnerable (`/laboratorio/`) con el sitio seguro.

> Todo se ejecuta dentro de la VM contra `https://localhost` o la IP de la VM. No escanees sitios ajenos.

## 0. Preparación

```bash
cd ~/proyecto-seguridad-web
git pull
sudo bash instalar.sh --laboratorio
mkdir -p resultados
```

## 1. Pruebas automáticas propias (evidencia rápida)

```bash
sudo bash pruebas/pruebas_seguridad.sh https://localhost
```

Genera `resultados/pruebas-FECHA.txt` con unas 35 verificaciones `[PASA]/[FALLA]` (HTTPS, cabeceras, cookies,
control de acceso, CSRF, SQLi, XSS, política de contraseñas, bcrypt, logout y bloqueo). Captura de pantalla
del resumen para el informe.

## 2. Nikto

```bash
sudo apt install -y nikto
# Sitio seguro
nikto -h https://localhost -ssl -o resultados/nikto-seguro.html -Format htm
# Laboratorio
nikto -h https://localhost/laboratorio/ -ssl -o resultados/nikto-laboratorio.html -Format htm
```

Hallazgos esperables en el sitio seguro y cómo explicarlos:

| Hallazgo de Nikto | Interpretación |
|---|---|
| Certificado autofirmado / emisor no confiable | Esperado en laboratorio. En producción usar Let's Encrypt. |
| Cookie `lab_sesion` sin HttpOnly (solo laboratorio) | Vulnerabilidad intencional de la demo. |
| Pocas o ninguna cabecera faltante | Las agregamos en `nginx/fase3.conf`. |

## 3. OWASP ZAP

Instalación (elige una):

```bash
sudo snap install zaproxy --classic     # interfaz gráfica
```

**Escaneo con la interfaz gráfica**

1. Abre ZAP, elige *No, I do not want to persist this session*.
2. *Automated Scan* → URL: `https://localhost/` → marca *Use traditional spider* → *Attack*.
3. Repite con `https://localhost/laboratorio/buscar.php?q=prueba` y `https://localhost/laboratorio/login.php`.
4. *Report → Generate Report* → HTML → guárdalo en `resultados/zap-reporte.html`.

**Escaneo autenticado (opcional, suma puntos):** en *Contexts* define el formulario `login.php`
(campos `usuario`, `contrasena`) y el indicador de sesión `Cerrar sesión`. ZAP detecta el token `csrf`
automáticamente (*Options → Anti-CSRF Tokens*, agrega `csrf` si no aparece).

Resultados esperados:

| Alerta de ZAP | Laboratorio | Sitio seguro |
|---|---|---|
| Cross Site Scripting (Reflected) | **Alta** en `buscar.php` | No aparece |
| SQL Injection | **Alta** en `login.php` | No aparece |
| Absence of Anti-CSRF Tokens | En `cambiar_correo.php` y `login.php` del laboratorio | No aparece |
| CSP Header Not Set | En el laboratorio | No aparece |
| Cookie without HttpOnly / SameSite | `lab_sesion` | No aparece |

## 4. Pruebas manuales

### 4.1 Inyección SQL

| Paso | Laboratorio `/laboratorio/login.php` | Sitio seguro `/login.php` |
|---|---|---|
| Usuario `' OR 1=1 #`, contraseña cualquiera | **Entra** y lista todos los usuarios | "Usuario o contraseña incorrectos" |
| Usuario `' UNION SELECT id, usuario, contrasena_hash FROM usuarios #` | Muestra los **hashes bcrypt** de la tabla real | Rechazado |
| Usuario `'` (comilla sola) | Muestra el error SQL interno | Mensaje genérico |

Punto para la exposición: aunque el atacante robe los hashes con el UNION, siguen siendo **bcrypt costo 12**;
no puede leer las contraseñas y crackearlas es muy lento.

**Solución aplicada:** consultas preparadas con PDO (`app/autenticacion.php`), `ATTR_EMULATE_PREPARES=false`,
usuario de BD con privilegios mínimos (sin DELETE/DROP), errores ocultos (`display_errors=Off`).

### 4.2 XSS

| Paso | Laboratorio `/laboratorio/buscar.php` | Sitio seguro |
|---|---|---|
| Buscar `<script>alert(document.cookie)</script>` | Aparece la alerta con `lab_sesion=...` | En `/registro.php` el texto se muestra escapado |
| Observar `F3SESSID` en la alerta | No aparece: es **HttpOnly** | — |

**Solución aplicada:** `htmlspecialchars` en toda salida (función `e()` en `app/vista.php`), CSP
`script-src 'self'` en nginx (bloquea scripts en línea aunque se cuelen), cookie HttpOnly.

### 4.3 CSRF

1. Abre `https://IP/laboratorio/cambiar_correo.php` (sesión de "maria").
2. Abre `pruebas/ataque_csrf.html` con doble clic en el mismo navegador, escribe la IP y pulsa
   *Reclamar premio*. El correo de maria cambia a `atacante@malicioso.com`. **Vulnerable.**
3. Inicia sesión como admin en `https://IP/`, luego en la página atacante pulsa los botones del sitio seguro:
   el servidor responde **403** o pide iniciar sesión, y la sesión sigue abierta. **Protegido.**
4. En *Administración*, el registro de auditoría muestra el evento `CSRF_INVALIDO`.

**Solución aplicada:** token CSRF aleatorio de 256 bits por sesión verificado con `hash_equals`
(`app/csrf.php`), cookie `SameSite=Strict`, logout solo por POST.

### 4.4 Fuerza bruta y sesión

- Falla 5 veces el login de un usuario → *Cuenta bloqueada temporalmente*. Evento `CUENTA_BLOQUEADA`.
- En el navegador (F12 → Application → Cookies) muestra `F3SESSID` con Secure, HttpOnly, SameSite=Strict.
- Compara el valor de `F3SESSID` antes y después de iniciar sesión: cambia (anti fijación de sesión).
- Cierra sesión y pulsa "Atrás": no se muestra el panel (cabeceras `no-store`).

## 5. Plantilla de hallazgos para el informe

| # | Vulnerabilidad | Dónde | Herramienta | Severidad | Evidencia | Solución propuesta / aplicada |
|---|---|---|---|---|---|---|
| 1 | Inyección SQL | /laboratorio/login.php | ZAP + manual | Alta | captura | Consultas preparadas (PDO) |
| 2 | XSS reflejado | /laboratorio/buscar.php | ZAP + manual | Alta | captura | `htmlspecialchars` + CSP |
| 3 | CSRF | /laboratorio/cambiar_correo.php | Manual | Media | captura | Token CSRF + SameSite |
| 4 | Contraseñas en texto plano | lab_usuarios | Manual (UNION) | Alta | captura | bcrypt costo 12 |
| 5 | Certificado autofirmado | Servidor | Nikto | Baja | reporte | Let's Encrypt en producción |
| 6 | Fuga de errores SQL | /laboratorio/login.php | Manual | Media | captura | `display_errors=Off`, mensajes genéricos |

## 6. Recomendaciones adicionales (mejoras futuras)

- Certificado de una CA reconocida (Let's Encrypt) y HSTS con `preload`.
- Autenticación de dos factores (TOTP) para administradores.
- Limitar intentos también por IP (`limit_req` de nginx o fail2ban leyendo la tabla de auditoría),
  para evitar que un atacante bloquee cuentas ajenas a propósito.
- Firewall (`ufw allow 443/tcp`, cerrar puertos no usados) y actualizaciones automáticas (`unattended-upgrades`).
- Copias de seguridad cifradas de la base de datos (`mysqldump` + `gpg`) y pruebas de restauración.
- Enviar el registro de auditoría a un sistema central (syslog/SIEM) y alertar ante `CUENTA_BLOQUEADA` o `CSRF_INVALIDO`.
