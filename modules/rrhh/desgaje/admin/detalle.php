<?php
// Ruta antigua: Desgaje es un módulo independiente desde 2026-11-25. El destino exige el permiso 'desgaje'.
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /modules/desgaje/admin/detalle.php' . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
