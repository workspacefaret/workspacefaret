<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

requireModuleAccess('logistica');

// Redirección inmediata al portal de formularios
header('Location: https://solicitudes.faret.cl/app/formularios/');
exit;