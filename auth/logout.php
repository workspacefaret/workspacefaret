<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

cerrarSesionUsuario();

header('Location: /auth/login.php');
exit;
