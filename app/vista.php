<?php
/**
 * Utilidades de presentación. Toda salida de datos pasa por e() para
 * convertir < > " ' & en entidades HTML (anti XSS).
 */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Lee un campo POST como texto; si llega un arreglo u otro tipo devuelve ''. */
function campo_post(string $nombre): string
{
    $v = $_POST[$nombre] ?? '';
    return is_string($v) ? $v : '';
}

function redirigir(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Devuelve y borra el aviso pendiente en la sesión. */
function tomar_aviso(): ?string
{
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);
    return $aviso;
}

function encabezado(string $titulo): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Portal Seguro</title>
    <link rel="stylesheet" href="/css/estilos.css">
    <script src="/js/validacion.js" defer></script>
</head>
<body>
<header class="barra">
    <span class="marca">Portal Seguro</span>
    <?php if (hay_sesion()): ?>
        <nav>
            <a href="/panel.php">Panel</a>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
                <a href="/admin.php">Administración</a>
            <?php endif; ?>
            <form method="post" action="/logout.php" class="en-linea">
                <?= csrf_campo() ?>
                <button type="submit" class="boton-secundario">Cerrar sesión (<?= e($_SESSION['usuario']) ?>)</button>
            </form>
        </nav>
    <?php endif; ?>
</header>
<main>
    <?php
}

function pie(): void
{
    ?>
</main>
<footer>Universidad Doctor Andrés Bello · Diseño de Seguridad de Redes</footer>
</body>
</html>
    <?php
}

/** Muestra una página de error con su código HTTP y detiene la ejecución. */
function mostrar_error(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    sin_cache();
    encabezado('Error ' . $codigo);
    ?>
    <section class="tarjeta">
        <h1>Error <?= $codigo ?></h1>
        <p><?= e($mensaje) ?></p>
        <p><a href="/">Volver al inicio</a></p>
    </section>
    <?php
    pie();
    exit;
}
