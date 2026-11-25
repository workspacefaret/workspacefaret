<?php

/*
 * Aviso "Novedades de la versión" (modal que se muestra UNA vez por usuario y versión al entrar a Operación).
 *
 * Al publicar una actualización: cambiar 'id' (p. ej. la fecha) y revisar 'slides' (máximo 3 ventanas).
 * Cada punto puede llevar 'modulo' (clave de config/modulos.php): solo lo ve quien tenga acceso a ese módulo.
 * Sin 'modulo' lo ve todo usuario autenticado. Una ventana sin puntos visibles no se muestra.
 * Para volver a verlo en pruebas: localStorage.removeItem('ws_novedades_visto_<idUsuario>') desde la consola.
 */

return [
    'id' => '2026-11-25',
    'slides' => [
        [
            'titulo' => 'Desgaje ahora es un módulo propio',
            'icono' => 'bi-scissors',
            'puntos' => [
                ['modulo' => 'desgaje', 't' => 'Desgaje salió de RRHH: ahora tiene su propio acceso en el menú lateral y en los accesos rápidos de Operación.'],
                ['modulo' => 'desgaje', 't' => 'El registro y el panel funcionan igual que antes. Los enlaces antiguos redirigen solos a las nuevas direcciones.'],
            ],
        ],
    ],
];
