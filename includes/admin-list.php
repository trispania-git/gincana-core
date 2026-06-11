<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin – listado de Estaciones:
 * - Filtro por Escenario (post_meta: gc_escenario_ref)
 * - Columnas: Escenario, Orden (gc_orden)
 * - Ordenar por Orden
 */

// === Filtro por Escenario en la lista de Estaciones ===
add_action('restrict_manage_posts', function($post_type){
  if ($post_type !== 'estacion') return;

  // Cargar escenarios publicados (para el selector)
  $escenarios = get_posts([
    'post_type'       => 'escenario',
    'post_status'     => 'publish',
    'numberposts'     => -1,
    'orderby'         => 'title',
    'order'           => 'ASC',
    'fields'          => 'ids',
    'no_found_rows'   => true,
    'suppress_filters'=> true,
  ]);

  $selected = isset($_GET['filter_escenario']) ? (int) $_GET['filter_escenario'] : 0;

  echo '<label for="filter_escenario" class="screen-reader-text">Filtrar por escenario</label>';
  echo '<select name="filter_escenario" id="filter_escenario">';
  echo '<option value="">— Filtrar por escenario —</option>';
  foreach ($escenarios as $esc_id) {
    $title = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
    printf(
      '<option value="%d"%s>%s</option>',
      (int)$esc_id,
      selected($selected, $esc_id, false),
      esc_html($title)
    );
  }
  echo '</select>';
});

// Aplicar el filtro en la query del admin
add_action('pre_get_posts', function($q){
  if ( ! is_admin() || ! $q->is_main_query() ) return;
  if ( $q->get('post_type') !== 'estacion' ) return;

  // Filtrado por escenario
  if ( ! empty($_GET['filter_escenario']) ) {
    $escenario_id = (int) $_GET['filter_escenario'];
    $meta = (array) $q->get('meta_query');
    $meta[] = [
      'key'     => 'gc_escenario_ref',
      'value'   => $escenario_id,
      'compare' => '=',
    ];
    $q->set('meta_query', $meta);
  }

  // Ordenar por Orden (gc_orden) si el usuario pulsa en la cabecera
  if ( $q->get('orderby') === 'gc_orden' ) {
    $q->set('meta_key', 'gc_orden');
    $q->set('orderby', 'meta_value_num');
  }
});

// === Columnas personalizadas ===
add_filter('manage_edit-estacion_columns', function($cols){
  // Inserta columnas nuevas después del título
  $new = [];
  foreach ($cols as $key => $label) {
    $new[$key] = $label;
    if ($key === 'title') {
      $new['gc_escenario'] = 'Escenario';
      $new['gc_orden']     = 'Orden';
      $new['gc_prueba']    = 'Prueba';
      $new['gc_estado']    = 'Estado';
    }
  }
  return $new;
});

// Marcar visualmente las estaciones deshabilitadas en la lista
add_filter('post_class', function($classes, $class, $post_id){
  if (get_post_type($post_id) === 'estacion' && get_post_meta($post_id, 'gc_deshabilitada', true) === '1') {
    $classes[] = 'gc-estacion-deshabilitada';
  }
  return $classes;
}, 10, 3);

add_action('admin_head-edit.php', function(){
  $screen = get_current_screen();
  if (!$screen || $screen->post_type !== 'estacion') return;
  echo '<style>
    .wp-list-table tr.gc-estacion-deshabilitada td { background:#fef2f2 !important; }
    .wp-list-table tr.gc-estacion-deshabilitada td.column-title strong a { color:#991b1b !important; text-decoration:line-through; }
  </style>';
});

add_action('manage_estacion_posts_custom_column', function($col, $post_id){
  if ($col === 'gc_escenario') {
    $esc_raw = get_post_meta($post_id, 'gc_escenario_ref', true);
    $esc_id  = (int) $esc_raw;
    if ($esc_id) {
      $t = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
      $link = get_edit_post_link($esc_id);
      echo $link ? '<a href="'.esc_url($link).'">'.esc_html($t).'</a>' : esc_html($t);
    } else {
      echo '<span style="color:#999">—</span>';
    }
  }

  if ($col === 'gc_orden') {
    $orden = get_post_meta($post_id, 'gc_orden', true);
    echo $orden !== '' ? (int)$orden : '<span style="color:#999">—</span>';
  }

  if ($col === 'gc_prueba') {
    // Reflejar lo que REALMENTE se usa en el juego:
    // - El vínculo válido hoy es gc_estacion_ref (prueba -> estación).
    // - En modo POOL, la estación usa el pool salvo que tenga una prueba propia
    //   por gc_estacion_ref (distinta de la prueba-pool). El gc_prueba_ref legacy
    //   queda ignorado (puede ser una referencia antigua y confusa).
    $esc_id   = (int) get_post_meta($post_id, 'gc_escenario_ref', true);
    $origen   = ($esc_id && function_exists('gc_get_origen_preguntas')) ? gc_get_origen_preguntas($esc_id) : 'por_estacion';
    $pool_pid = $esc_id ? (int) get_post_meta($esc_id, 'gc_pool_prueba_ref', true) : 0;

    // Prueba propia vinculada por gc_estacion_ref (excluyendo la prueba-pool)
    $pid = 0;
    $pq = get_posts([
      'post_type'      => 'prueba',
      'post_status'    => 'any',
      'posts_per_page' => 1,
      'meta_query'     => [['key' => 'gc_estacion_ref', 'value' => $post_id, 'compare' => '=']],
      'post__not_in'   => $pool_pid ? [$pool_pid] : [],
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    if (!empty($pq)) $pid = (int) $pq[0];

    // Solo en modo por_estacion aceptamos el vínculo legacy gc_prueba_ref.
    if (!$pid && $origen !== 'pool') {
      $legacy = (int) get_post_meta($post_id, 'gc_prueba_ref', true);
      if ($legacy && get_post_status($legacy)) $pid = $legacy;
    }

    if ($pid) {
      $labels = [
        'multiple'        => 'Opción múltiple',
        'multiple_imagen' => 'Opción múltiple (imágenes)',
        'vf'              => 'Verdadero / Falso',
        'texto'           => 'Respuesta de texto',
        'cifrado_cesar'   => 'Cifrado César',
        'anagrama'        => 'Anagrama',
        'ahorcado'        => 'Ahorcado',
        'ahorcado_light'  => 'Ahorcado light',
        'sopa_letras'     => 'Sopa de letras',
        'jeroglifico'     => 'Jeroglífico',
        'lista_libre'     => 'Lista libre',
        'accion_qr'       => 'Acción externa por QR',
      ];
      $tipo  = (string) get_post_meta($pid, 'gc_tipo', true);
      $tlbl  = isset($labels[$tipo]) ? $labels[$tipo] : ($tipo ?: '');
      $t     = get_the_title($pid) ?: ('Prueba #' . $pid);
      $link  = get_edit_post_link($pid);
      echo $link ? '<a href="' . esc_url($link) . '">' . esc_html($t) . '</a>' : esc_html($t);
      if ($tlbl) echo '<br><span style="display:inline-block;margin-top:2px;padding:1px 7px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:600;">' . esc_html($tlbl) . '</span>';
    } else {
      $esc_id = (int) get_post_meta($post_id, 'gc_escenario_ref', true);
      $origen = ($esc_id && function_exists('gc_get_origen_preguntas')) ? gc_get_origen_preguntas($esc_id) : '';
      if ($origen === 'pool') {
        echo '<span style="display:inline-block;padding:1px 7px;border-radius:999px;background:#f5f3ff;color:#7c3aed;font-size:11px;font-weight:600;">Pool aleatorio</span>';
      } else {
        echo '<span style="color:#999">—</span>';
      }
    }
  }

  if ($col === 'gc_estado') {
    $deshabilitada = get_post_meta($post_id, 'gc_deshabilitada', true) === '1';
    if ($deshabilitada) {
      echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;">Deshabilitada</span>';
    } else {
      echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;">Activa</span>';
    }
  }
}, 10, 2);

// Hacer la columna "Orden" ordenable
add_filter('manage_edit-estacion_sortable_columns', function($cols){
  $cols['gc_orden'] = 'gc_orden';
  return $cols;
});
