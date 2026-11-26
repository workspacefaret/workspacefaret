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
    'id' => '2026-11-26',
    'slides' => [
        [
            'titulo' => 'Estructural: foto obligatoria en Perfil',
            'icono' => 'bi-camera-fill',
            'puntos' => [
                ['modulo' => 'desarrollo', 't' => 'El formulario de solicitud estructural pregunta ahora si es una solicitud de Perfil.'],
                ['modulo' => 'desarrollo', 't' => 'Si la respuesta es Sí, se debe adjuntar al menos una fotografía (JPG, PNG, WEBP, GIF o BMP). Un PDF o documento no la reemplaza.'],
            ],
        ],
        [
            'titulo' => 'Estructural: responsables y reapertura',
            'icono' => 'bi-people-fill',
            'puntos' => [
                ['modulo' => 'desarrollo', 't' => 'Cada solicitud puede registrar Asignador de plano y Desarrollador estructural. Ambos se ven en el detalle, el listado, el Excel y el PDF.'],
                ['modulo' => 'desarrollo', 't' => 'Al reabrir una solicitud estructural queda En Proceso, con el mismo código, adjuntos e historial. Desarrollo Gráfico no cambia.'],
            ],
        ],
    ],
];
