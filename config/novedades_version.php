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
    'id' => '2026-11-05',
    'slides' => [
        [
            'titulo' => 'Extintores: listas más ágiles',
            'icono' => 'bi-list-ol',
            'puntos' => [
                ['modulo' => 'prevencion_riesgos', 't' => 'El panel y el inventario ahora muestran las filas por páginas (25, 50 o 100, tú eliges). El Excel sigue exportando todos los resultados filtrados.'],
                ['modulo' => 'prevencion_riesgos', 't' => 'En el teléfono, la lista de extintores carga de a 20 con el botón «Mostrar más».'],
            ],
        ],
        [
            'titulo' => 'Extintores: gestión de hallazgos y cumplimiento',
            'icono' => 'bi-shield-check',
            'puntos' => [
                ['modulo' => 'prevencion_riesgos', 't' => 'Cada hallazgo puede planificarse, darse por corregido o cerrarse como conforme con observaciones, sin modificar la inspección original.'],
                ['modulo' => 'prevencion_riesgos', 't' => 'Nuevo indicador mensual por brigadista: extintores asignados frente a inspeccionados. En el mes en curso lo pendiente figura como «En curso».'],
            ],
        ],
    ],
];
