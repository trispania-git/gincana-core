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
    }
  }
  return $new;
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
}, 10, 2);

// Hacer la columna "Orden" ordenable
add_filter('manage_edit-estacion_sortable_columns', function($cols){
  $cols['gc_orden'] = 'gc_orden';
  return $cols;
});
