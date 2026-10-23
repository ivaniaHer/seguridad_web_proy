<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laboratorio vulnerable · Fase 4</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>
<body>
<header class="barra"><span class="marca">⚠ Laboratorio vulnerable (solo demostración)</span><nav><a href="/">Sitio seguro</a></nav></header>
<main>
    <section class="tarjeta">
        <h1>Laboratorio de vulnerabilidades</h1>
        <p class="error">Estas páginas son inseguras <strong>a propósito</strong> para comparar contra el sitio seguro
            durante la Fase 4. Para quitarlas, vuelve a ejecutar <code>sudo bash instalar.sh</code> sin <code>--laboratorio</code>.</p>

        <div class="tabla-scroll">
            <table>
                <thead><tr><th>Página</th><th>Vulnerabilidad</th><th>Carga de prueba</th><th>Defensa en el sitio seguro</th></tr></thead>
                <tbody>
                <tr>
                    <td><a href="login.php">login.php</a></td>
                    <td>Inyección SQL</td>
                    <td><code>' OR 1=1 #</code></td>
                    <td>Consultas preparadas (PDO)</td>
                </tr>
                <tr>
                    <td><a href="login.php">login.php</a></td>
                    <td>Inyección SQL (UNION)</td>
                    <td><code>' UNION SELECT id, usuario, contrasena_hash FROM usuarios #</code></td>
                    <td>Consultas preparadas + hashes bcrypt</td>
                </tr>
                <tr>
                    <td><a href="buscar.php">buscar.php</a></td>
                    <td>XSS reflejado</td>
                    <td><code>&lt;script&gt;alert(document.cookie)&lt;/script&gt;</code></td>
                    <td><code>htmlspecialchars</code> + CSP + cookie HttpOnly</td>
                </tr>
                <tr>
                    <td><a href="cambiar_correo.php">cambiar_correo.php</a></td>
                    <td>CSRF</td>
                    <td><code>pruebas/ataque_csrf.html</code></td>
                    <td>Token CSRF + SameSite=Strict</td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
