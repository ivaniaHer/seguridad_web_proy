<?php
require __DIR__ . '/../app/bootstrap.php';

requerir_login();

$st = db()->prepare('SELECT usuario, rol, ultimo_acceso, creado_en FROM usuarios WHERE id = ?');
$st->execute([$_SESSION['usuario_id']]);
$u = $st->fetch();
if (!$u) { // la cuenta fue eliminada mientras la sesión seguía abierta
    destruir_sesion();
    redirigir('login.php');
}

encabezado('Panel');
?>
<section class="tarjeta">
    <h1>Bienvenido, <?= e($u['usuario']) ?></h1>
    <p>Esta página está protegida: solo se puede ver con una sesión iniciada.</p>

    <dl class="datos">
        <dt>Rol</dt><dd><span class="etiqueta"><?= e($u['rol']) ?></span></dd>
        <dt>Sesión iniciada</dt><dd><?= e(date('d/m/Y H:i:s', $_SESSION['inicio'])) ?></dd>
        <dt>Cuenta creada</dt><dd><?= e($u['creado_en']) ?></dd>
        <dt>Cierre por inactividad</dt><dd><?= SESION_INACTIVIDAD / 60 ?> minutos</dd>
    </dl>
</section>

<section class="tarjeta">
    <h2>Medidas de seguridad activas</h2>
    <ul class="lista-check">
        <li>Conexión cifrada con HTTPS (certificado de la Fase 2).</li>
        <li>Contraseña almacenada como hash <strong>bcrypt</strong> (costo <?= BCRYPT_COSTO ?>).</li>
        <li>Cookie de sesión <code>Secure</code>, <code>HttpOnly</code> y <code>SameSite=Strict</code>.</li>
        <li>ID de sesión regenerado al iniciar sesión.</li>
        <li>Tokens CSRF en todos los formularios.</li>
        <li>Bloqueo tras <?= MAX_INTENTOS ?> intentos fallidos durante <?= BLOQUEO_MINUTOS ?> minutos.</li>
        <li>Consultas preparadas contra inyección SQL y salida escapada contra XSS.</li>
    </ul>
</section>
<?php
pie();
