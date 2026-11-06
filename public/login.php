<?php
require __DIR__ . '/../app/bootstrap.php';

sin_cache();
if (hay_sesion()) {
    redirigir('panel.php');
}

$error = '';
$bloqueado = false;
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $usuario = trim(campo_post('usuario'));
    $contrasena = campo_post('contrasena');

    // Validación del lado del servidor (la del navegador se puede saltar).
    if (!preg_match(USUARIO_REGEX, $usuario) || $contrasena === '' || strlen($contrasena) > 72) {
        registrar_evento('LOGIN_FALLIDO', $usuario === '' ? null : $usuario, 'formato inválido');
        $error = 'Usuario o contraseña incorrectos.';
    } else {
        $resultado = autenticar($usuario, $contrasena);
        if ($resultado['ok']) {
            iniciar_sesion_usuario($resultado['usuario']);
            redirigir('panel.php');
        }
        // Mensaje genérico: no se indica si falló el usuario o la contraseña.
        $bloqueado = $resultado['motivo'] === 'bloqueado';
        $error = $bloqueado
            ? 'Cuenta bloqueada temporalmente por demasiados intentos fallidos. Intenta en ' . BLOQUEO_MINUTOS . ' minutos.'
            : 'Usuario o contraseña incorrectos.';
    }
}

$aviso = tomar_aviso();
if (isset($_GET['salida'])) {
    $aviso = 'Cerraste sesión correctamente.';
}

encabezado('Iniciar sesión');
?>
<div class="login-grid">
    <aside class="lado-marca" aria-label="Información de la intranet">
        <svg class="logo" viewBox="0 0 64 64" aria-hidden="true">
            <path d="M32 4 56 14v18c0 14-10 24-24 28C18 56 8 46 8 32V14z" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round"/>
            <path d="M22 33l7 7 14-15" fill="none" stroke="#a9d3db" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h2>Nexora Intranet</h2>
        <p>Tu espacio de trabajo: tareas, anuncios y documentos de la oficina en un solo lugar.</p>
        <ul class="insignias">
            <li>Conexión cifrada</li>
            <li>Acceso monitoreado</li>
        </ul>
        <p class="ayuda">¿Problemas para ingresar? Escribe a soporte@nexora.example</p>
    </aside>

    <section class="tarjeta">
        <h1>Iniciar sesión</h1>

        <?php if ($aviso): ?><p class="aviso" role="status"><?= e($aviso) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error<?= $bloqueado ? ' bloqueo' : '' ?>" role="alert"><?= e($error) ?></p><?php endif; ?>

        <form method="post" action="login.php" autocomplete="on" novalidate data-validar>
            <?= csrf_campo() ?>
            <label for="usuario">Usuario</label>
            <div class="campo">
                <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                <input id="usuario" name="usuario" type="text" required
                       minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}"
                       autocomplete="username" value="<?= e($usuario) ?>" autofocus>
            </div>
            <small class="mensaje-campo" data-para="usuario"></small>

            <label for="contrasena">Contraseña</label>
            <div class="campo con-ojo">
                <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <input id="contrasena" name="contrasena" type="password" required
                       maxlength="72" autocomplete="current-password">
                <button type="button" class="ojo" data-ojo="contrasena" aria-label="Mostrar contraseña" aria-pressed="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <small class="mayus" data-mayus hidden>Bloq Mayús está activado</small>
            <small class="mensaje-campo" data-para="contrasena"></small>

            <button type="submit" data-texto-carga="Verificando...">Entrar</button>
        </form>
        <p class="secundario">¿Primera vez? <a href="registro.php">Activa tu cuenta</a></p>
    </section>
</div>
<script src="extra.js" defer></script>
<?php
pie();
