<?php
/*
 * ⚠ CÓDIGO INSEGURO A PROPÓSITO (Fase 4): inyección SQL.
 * Compárese con public/login.php + app/autenticacion.php.
 */
require __DIR__ . '/../../app/config.php';
require __DIR__ . '/../../app/db.php';

$consulta = null;
$filas = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string) ($_POST['usuario'] ?? '');
    $p = (string) ($_POST['contrasena'] ?? '');

    // VULNERABLE: los datos del usuario se pegan directamente en el texto SQL.
    $consulta = "SELECT id, usuario, correo FROM lab_usuarios WHERE usuario = '$u' AND contrasena = '$p'";
    try {
        $filas = db()->query($consulta)->fetchAll();
    } catch (PDOException $ex) {
        $error = $ex->getMessage(); // VULNERABLE: muestra el error interno de la BD
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login vulnerable · Laboratorio</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body>
<header class="barra"><span class="marca">⚠ Laboratorio: inyección SQL</span><nav><a href="index.php">Laboratorio</a></nav></header>
<main>
    <section class="tarjeta angosta">
        <h1>Login vulnerable</h1>
        <form method="post">
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" type="text">
            <label for="contrasena">Contraseña</label>
            <input id="contrasena" name="contrasena" type="text">
            <button type="submit">Entrar</button>
        </form>
    </section>

    <?php if ($consulta !== null): ?>
        <section class="tarjeta">
            <h2>Consulta ejecutada</h2>
            <p><code><?= htmlspecialchars($consulta) ?></code></p>
            <?php if ($error): ?>
                <p class="error">Error SQL: <?= htmlspecialchars($error) ?></p>
            <?php elseif ($filas): ?>
                <p class="error">Acceso concedido. Filas devueltas: <?= count($filas) ?></p>
                <div class="tabla-scroll"><table>
                    <tr><th>id</th><th>usuario</th><th>correo / dato extraído</th></tr>
                    <?php foreach ($filas as $f): ?>
                        <tr><td><?= htmlspecialchars((string) $f['id']) ?></td><td><?= htmlspecialchars((string) $f['usuario']) ?></td><td><?= htmlspecialchars((string) $f['correo']) ?></td></tr>
                    <?php endforeach; ?>
                </table></div>
            <?php else: ?>
                <p>Credenciales incorrectas.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
