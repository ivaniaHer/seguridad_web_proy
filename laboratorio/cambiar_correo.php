<?php
/*
 * ⚠ CÓDIGO INSEGURO A PROPÓSITO (Fase 4): CSRF.
 *  - El formulario no lleva token CSRF.
 *  - La cookie de sesión usa SameSite=None, así que el navegador la envía
 *    aunque la petición venga de otro sitio (pruebas/ataque_csrf.html).
 * Para simplificar, al entrar se "inicia sesión" automáticamente como maria.
 */
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/db.php';

session_name('LABSESSID');
session_set_cookie_params(['path' => '/laboratorio/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
session_start();
$_SESSION['lab_usuario'] = $_SESSION['lab_usuario'] ?? 'maria';

$mensaje = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['correo'])) {
    // VULNERABLE: no se verifica de dónde viene la petición.
    db()->prepare('UPDATE lab_usuarios SET correo = ? WHERE usuario = ?')
        ->execute([(string) $_POST['correo'], $_SESSION['lab_usuario']]);
    $mensaje = 'Correo actualizado.';
}

$st = db()->prepare('SELECT correo FROM lab_usuarios WHERE usuario = ?');
$st->execute([$_SESSION['lab_usuario']]);
$correo = (string) $st->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil vulnerable · Laboratorio</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body>
<header class="barra"><span class="marca">⚠ Laboratorio: CSRF</span><nav><a href="index.php">Laboratorio</a></nav></header>
<main>
    <section class="tarjeta angosta">
        <h1>Perfil de <?= htmlspecialchars($_SESSION['lab_usuario']) ?></h1>
        <?php if ($mensaje): ?><p class="aviso"><?= htmlspecialchars($mensaje) ?></p><?php endif; ?>
        <p>Correo actual: <strong><?= htmlspecialchars($correo) ?></strong></p>
        <form method="post">
            <label for="correo">Nuevo correo</label>
            <input id="correo" name="correo" type="text" value="<?= htmlspecialchars($correo) ?>">
            <button type="submit">Guardar</button>
        </form>
    </section>
</main>
</body>
</html>
