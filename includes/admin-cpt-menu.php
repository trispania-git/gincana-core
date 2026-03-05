<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Mueve los CPT de Gincana bajo el menú "Gincana Core"
 * y evita que aparezcan como menús sueltos en el lateral.
 */
add_filter('register_post_type_args', function($args, $post_type){
  $targets = ['escenario','estacion','prueba'];
  if ( in_array($post_type, $targets, true) ) {
    // Asegura que el UI esté visible y cuelgue del menú padre "gincana-core"
    $args['show_ui']    = true;
    $args['show_in_menu'] = 'gincana-core'; // <- clave: cuelga del top-level de nuestro plugin
    // Opcional: orden en el submenú (no todos los WP respetan reorder aquí)
    // $args['menu_position'] = null;
  }
  return $args;
}, 10, 2);
