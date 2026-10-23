<?php
require __DIR__ . '/../app/bootstrap.php';

sin_cache();
if (hay_sesion()) {
    redirigir('panel.php');
}

$error = '';
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
        $error = $resultado['motivo'] === 'bloqueado'
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
<section class="tarjeta angosta">
    <h1>Iniciar sesión</h1>

    <?php if ($aviso): ?><p class="aviso"><?= e($aviso) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form method="post" action="login.php" autocomplete="on" novalidate data-validar>
        <?= csrf_campo() ?>
        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" type="text" required
               minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}"
               autocomplete="username" value="<?= e($usuario) ?>" autofocus>
        <small class="mensaje-campo" data-para="usuario"></small>

        <label for="contrasena">Contraseña</label>
        <input id="contrasena" name="contrasena" type="password" required
               maxlength="72" autocomplete="current-password">
        <small class="mensaje-campo" data-para="contrasena"></small>

        <button type="submit">Entrar</button>
    </form>
    <p class="secundario">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
</section>
<?php
pie();
