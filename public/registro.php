<?php
require __DIR__ . '/../app/bootstrap.php';

sin_cache();
if (hay_sesion()) {
    redirigir('panel.php');
}

$errores = [];
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $usuario = trim(campo_post('usuario'));
    $contrasena = campo_post('contrasena');
    $confirmacion = campo_post('confirmacion');

    if (!preg_match(USUARIO_REGEX, $usuario)) {
        $errores[] = 'El usuario debe tener de 3 a 30 caracteres: letras, números, punto o guion bajo.';
    }
    $errores = array_merge($errores, validar_politica_contrasena($contrasena, $usuario));
    if (!hash_equals($contrasena, $confirmacion)) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        try {
            db()->prepare('INSERT INTO usuarios (usuario, contrasena_hash, rol) VALUES (?, ?, ?)')
                ->execute([$usuario, hash_contrasena($contrasena), 'usuario']);
            registrar_evento('REGISTRO', $usuario);
            $_SESSION['aviso'] = 'Cuenta creada. Ya puedes iniciar sesión.';
            redirigir('login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') { // 23000 = clave duplicada
                throw $ex;
            }
            $errores[] = 'Ese nombre de usuario no está disponible.';
        }
    }
}

encabezado('Activar cuenta');
?>
<div class="login-grid">
    <aside class="lado-marca" aria-label="Información de la intranet">
        <svg class="logo" viewBox="0 0 64 64" aria-hidden="true">
            <path d="M32 4 56 14v18c0 14-10 24-24 28C18 56 8 46 8 32V14z" fill="none" stroke="#fff" stroke-width="3" stroke-linejoin="round"/>
            <path d="M22 33l7 7 14-15" fill="none" stroke="#a9d3db" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h2>Activa tu cuenta</h2>
        <p>Crea tu acceso a la intranet de Nexora. Tu contraseña se guarda cifrada y nadie, ni siquiera TI, puede verla.</p>
        <ul class="insignias">
            <li>Contraseña cifrada</li>
            <li>Acceso monitoreado</li>
        </ul>
    </aside>

    <section class="tarjeta">
        <h1>Activar cuenta</h1>

        <?php if ($errores): ?>
            <div class="error" role="alert">
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="registro.php" novalidate data-validar data-registro>
            <?= csrf_campo() ?>
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" type="text" required
                   minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}"
                   autocomplete="username" value="<?= e($usuario) ?>" autofocus>
            <small class="mensaje-campo" data-para="usuario"></small>

            <label for="contrasena">Contraseña</label>
            <input id="contrasena" name="contrasena" type="password" required
                   minlength="8" maxlength="72" autocomplete="new-password">
            <ul class="requisitos" id="requisitos">
                <li data-regla="largo">Mínimo 8 caracteres</li>
                <li data-regla="mayuscula">Una letra mayúscula</li>
                <li data-regla="minuscula">Una letra minúscula</li>
                <li data-regla="numero">Un número</li>
                <li data-regla="simbolo">Un símbolo (!@#$%...)</li>
                <li data-regla="usuario">No contiene el usuario</li>
            </ul>

            <label for="confirmacion">Confirmar contraseña</label>
            <input id="confirmacion" name="confirmacion" type="password" required
                   maxlength="72" autocomplete="new-password">
            <small class="mensaje-campo" data-para="confirmacion"></small>

            <button type="submit">Activar cuenta</button>
        </form>
        <p class="secundario">¿Ya activaste tu cuenta? <a href="login.php">Inicia sesión</a></p>
    </section>
</div>
<?php
pie();
