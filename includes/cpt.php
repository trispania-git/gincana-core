<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Registro de Custom Post Types propios del plugin.
 * Sustituye a CPT UI — ya no es necesario tener ese plugin activo.
 */
add_action('init', function(){

  // ── Escenario ──────────────────────────────────────────────
  register_post_type('escenario', [
    'labels' => [
      'name'               => 'Escenarios',
      'singular_name'      => 'Escenario',
      'add_new'            => 'Añadir escenario',
      'add_new_item'       => 'Añadir nuevo escenario',
      'edit_item'          => 'Editar escenario',
      'new_item'           => 'Nuevo escenario',
      'view_item'          => 'Ver escenario',
      'search_items'       => 'Buscar escenarios',
      'not_found'          => 'No se encontraron escenarios',
      'not_found_in_trash' => 'No hay escenarios en la papelera',
      'all_items'          => 'Escenarios',
    ],
    'public'             => true,
    'has_archive'        => false,
    'show_ui'            => true,
    'show_in_menu'       => 'gincana-core',
    'show_in_rest'       => true,
    'menu_icon'          => 'dashicons-location-alt',
    'supports'           => ['title', 'editor', 'thumbnail'],
    'rewrite'            => ['slug' => 'escenario', 'with_front' => false],
  ]);

  // ── Estación ───────────────────────────────────────────────
  register_post_type('estacion', [
    'labels' => [
      'name'               => 'Estaciones',
      'singular_name'      => 'Estación',
      'add_new'            => 'Añadir estación',
      'add_new_item'       => 'Añadir nueva estación',
      'edit_item'          => 'Editar estación',
      'new_item'           => 'Nueva estación',
      'view_item'          => 'Ver estación',
      'search_items'       => 'Buscar estaciones',
      'not_found'          => 'No se encontraron estaciones',
      'not_found_in_trash' => 'No hay estaciones en la papelera',
      'all_items'          => 'Estaciones',
    ],
    'public'             => true,
    'has_archive'        => false,
    'show_ui'            => true,
    'show_in_menu'       => 'gincana-core',
    'show_in_rest'       => true,
    'menu_icon'          => 'dashicons-flag',
    'supports'           => ['title', 'editor', 'thumbnail'],
    'rewrite'            => ['slug' => 'estacion', 'with_front' => false],
  ]);

  // ── Prueba ─────────────────────────────────────────────────
  register_post_type('prueba', [
    'labels' => [
      'name'               => 'Pruebas',
      'singular_name'      => 'Prueba',
      'add_new'            => 'Añadir prueba',
      'add_new_item'       => 'Añadir nueva prueba',
      'edit_item'          => 'Editar prueba',
      'new_item'           => 'Nueva prueba',
      'view_item'          => 'Ver prueba',
      'search_items'       => 'Buscar pruebas',
      'not_found'          => 'No se encontraron pruebas',
      'not_found_in_trash' => 'No hay pruebas en la papelera',
      'all_items'          => 'Pruebas',
    ],
    'public'             => false,
    'show_ui'            => true,
    'show_in_menu'       => 'gincana-core',
    'show_in_rest'       => true,
    'menu_icon'          => 'dashicons-welcome-learn-more',
    'supports'           => ['title'],
    'rewrite'            => false,
  ]);

}, 5); // Prioridad 5 para que se registren antes de que otros hooks los necesiten
