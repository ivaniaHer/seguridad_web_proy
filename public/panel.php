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

$esAdmin = $u['rol'] === 'admin';
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

// ---- Contenido de ejemplo (editen estos arreglos para cambiar lo que se muestra) ----
$tareas = [
    ['Revisar el informe mensual de gastos', 'Vence hoy', 'En curso'],
    ['Enviar propuesta al cliente Hernández', 'Vence mañana', 'Pendiente'],
    ['Actualizar el inventario de equipo de oficina', 'Vence el viernes', 'Pendiente'],
    ['Reunión de seguimiento con Contabilidad', 'Completada ayer', 'Hecha'],
];
$anuncios = [
    ['Mantenimiento programado del sistema', 'El sábado de 10:00 p. m. a 12:00 a. m. la intranet no estará disponible.'],
    ['Recordatorio de seguridad', 'Cambia tu contraseña cada 90 días y nunca la compartas, ni con el personal de TI.'],
    ['Nuevo procedimiento de solicitud de permisos', 'Ahora se tramitan desde el formulario de Recursos Humanos.'],
];
$abiertas = count(array_filter($tareas, function ($t) { return $t[2] !== 'Hecha'; }));

// Medidas de seguridad (se muestran como tarjeta secundaria).
$ico = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
$medidas = [
    ['HTTPS', 'Conexión cifrada (certificado de la Fase 2).'],
    ['Contraseña con bcrypt', 'Costo ' . BCRYPT_COSTO . '; nunca se guarda en claro.'],
    ['Cookie de sesión segura', 'Secure, HttpOnly y SameSite=Strict.'],
    ['Sesión renovada al entrar', 'El ID se regenera al iniciar sesión.'],
    ['Tokens CSRF', 'En todos los formularios.'],
    ['Bloqueo de cuenta', MAX_INTENTOS . ' intentos fallidos = ' . BLOQUEO_MINUTOS . ' minutos.'],
    ['Consultas preparadas', 'Contra inyección SQL.'],
    ['Salida escapada', 'Contra XSS.'],
];

encabezado('Panel');
?>
<div class="saludo">
    <div>
        <h1><?= e($saludo) ?>, <?= e($u['usuario']) ?></h1>
        <p>Esto es lo que tienes hoy en Nexora.</p>
    </div>
    <span class="fecha"><?= e(date('d/m/Y')) ?></span>
</div>

<div class="metricas">
    <div class="metrica"><span>Tareas abiertas</span><strong><?= (int) $abiertas ?></strong><small>1 vence hoy</small></div>
    <div class="metrica"><span>Reuniones hoy</span><strong>2</strong><small>La próxima, a las 2:30 p. m.</small></div>
    <div class="metrica"><span>Anuncios nuevos</span><strong><?= count($anuncios) ?></strong><small>Esta semana</small></div>
</div>

<div class="panel-grid">
    <div>
        <section class="tarjeta">
            <h2>Mis tareas</h2>
            <ul class="lista">
                <?php foreach ($tareas as [$titulo, $fecha, $estado]): ?>
                    <li<?= $estado === 'Hecha' ? ' class="hecha"' : '' ?>>
                        <span><strong><?= e($titulo) ?></strong><small><?= e($fecha) ?></small></span>
                        <span class="etiqueta <?= $estado === 'Hecha' ? 'ok' : ($estado === 'En curso' ? 'ambar' : '') ?>"><?= e($estado) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="tarjeta">
            <h2>Anuncios de la empresa</h2>
            <ul class="anuncios">
                <?php foreach ($anuncios as [$titulo, $texto]): ?>
                    <li><strong><?= e($titulo) ?></strong><small><?= e($texto) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <div>
        <?php if ($esAdmin): ?>
            <section class="tarjeta">
                <h2>Administración</h2>
                <p class="secundario" style="text-align:left">Gestiona las cuentas de usuario y revisa el registro de actividad.</p>
                <p><a class="enlace-boton" href="admin.php">Ir a administración</a></p>
            </section>
        <?php endif; ?>

        <section class="tarjeta">
            <h2>Tu cuenta</h2>
            <p class="secundario" style="text-align:left">Tu sesión se cierra por inactividad en</p>
            <p class="reloj" data-cuenta="<?= (int) SESION_INACTIVIDAD ?>" role="timer" aria-live="off">
                <?= sprintf('%02d:%02d', intdiv(SESION_INACTIVIDAD, 60), SESION_INACTIVIDAD % 60) ?>
            </p>
            <dl class="datos">
                <dt>Usuario</dt><dd><?= e($u['usuario']) ?></dd>
                <dt>Rol</dt><dd><span class="etiqueta"><?= e($u['rol']) ?></span></dd>
                <dt>Sesión</dt><dd><?= e(date('d/m/Y H:i', $_SESSION['inicio'])) ?></dd>
                <dt>Creada</dt><dd><?= e($u['creado_en']) ?></dd>
            </dl>
        </section>

        <section class="tarjeta">
            <h2>Seguridad de tu cuenta</h2>
            <ul class="medidas">
                <?php foreach ($medidas as [$titulo, $detalle]): ?>
                    <li>
                        <span class="ico"><?= $ico ?></span>
                        <span><strong><?= e($titulo) ?></strong><small><?= e($detalle) ?></small></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
<script src="extra.js" defer></script>
<?php
pie();
