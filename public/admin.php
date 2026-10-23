<?php
require __DIR__ . '/../app/bootstrap.php';

requerir_rol('admin');

// Acción: desbloquear una cuenta.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $id = filter_var(campo_post('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id !== false) {
        $st = db()->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() > 0) {
            registrar_evento('DESBLOQUEO', $_SESSION['usuario'], "usuario id $id");
            $_SESSION['aviso'] = 'Cuenta desbloqueada.';
        }
    }
    redirigir('admin.php');
}

$usuarios = db()->query(
    'SELECT id, usuario, rol, activo, intentos_fallidos, bloqueado_hasta, ultimo_acceso,
            (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado
       FROM usuarios ORDER BY id'
)->fetchAll();

$eventos = db()->query(
    'SELECT fecha, evento, usuario, ip, detalle FROM auditoria ORDER BY id DESC LIMIT 50'
)->fetchAll();

$aviso = tomar_aviso();
encabezado('Administración');
?>
<section class="tarjeta">
    <h1>Administración</h1>
    <p>Solo los usuarios con rol <span class="etiqueta">admin</span> pueden ver esta página.</p>
    <?php if ($aviso): ?><p class="aviso"><?= e($aviso) ?></p><?php endif; ?>

    <h2>Usuarios</h2>
    <div class="tabla-scroll">
        <table>
            <thead>
            <tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Intentos</th><th>Último acceso</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= (int) $u['id'] ?></td>
                    <td><?= e($u['usuario']) ?></td>
                    <td><?= e($u['rol']) ?></td>
                    <td>
                        <?php if ((int) $u['bloqueado']): ?>
                            <span class="etiqueta peligro">Bloqueado hasta <?= e($u['bloqueado_hasta']) ?></span>
                        <?php elseif (!(int) $u['activo']): ?>
                            <span class="etiqueta">Inactivo</span>
                        <?php else: ?>
                            <span class="etiqueta ok">Activo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $u['intentos_fallidos'] ?></td>
                    <td><?= e($u['ultimo_acceso'] ?? '—') ?></td>
                    <td>
                        <?php if ((int) $u['bloqueado']): ?>
                            <form method="post" action="admin.php" class="en-linea">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="boton-secundario">Desbloquear</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tarjeta">
    <h2>Registro de auditoría (últimos 50 eventos)</h2>
    <div class="tabla-scroll">
        <table>
            <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>IP</th><th>Detalle</th></tr></thead>
            <tbody>
            <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td><?= e($ev['fecha']) ?></td>
                    <td><span class="etiqueta evento-<?= e(strtolower($ev['evento'])) ?>"><?= e($ev['evento']) ?></span></td>
                    <td><?= e($ev['usuario'] ?? '—') ?></td>
                    <td><?= e($ev['ip']) ?></td>
                    <td><?= e($ev['detalle']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
pie();
