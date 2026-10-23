<?php
/*
 * ⚠ CÓDIGO INSEGURO A PROPÓSITO (Fase 4): XSS reflejado.
 * Cookie de demostración SIN HttpOnly: JavaScript sí puede leerla.
 * La cookie real del sitio (F3SESSID) es HttpOnly y no aparece en document.cookie.
 */
setcookie('lab_sesion', 'token-secreto-de-demostracion', ['path' => '/laboratorio/', 'secure' => true]);
$q = $_GET['q'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buscador vulnerable · Laboratorio</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body>
<header class="barra"><span class="marca">⚠ Laboratorio: XSS reflejado</span><nav><a href="index.php">Laboratorio</a></nav></header>
<main>
    <section class="tarjeta">
        <h1>Buscador</h1>
        <form method="get">
            <label for="q">Buscar</label>
            <input id="q" name="q" type="text">
            <button type="submit">Buscar</button>
        </form>
        <?php if ($q !== ''): ?>
            <!-- VULNERABLE: se imprime sin htmlspecialchars() -->
            <p>Resultados para: <?= $q ?></p>
            <p>No se encontraron resultados.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
