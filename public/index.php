<?php
require __DIR__ . '/../app/bootstrap.php';

redirigir(hay_sesion() ? 'panel.php' : 'login.php');
