<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin – listado de Pruebas:
 * - Filtro por Escenario y por Estación
 * - Columnas: Escenario, Estación (derivadas de la Estación que referencia esta Prueba vía gc_prueba_ref)
 *
 * Requisitos post_meta:
 * - En "estacion": gc_escenario_ref (ID del escenario)
 * - En "estacion": gc_prueba_ref    (ID de la prueba)
 */

// ====== 1) Filtros en el admin (Escenario / Estación) ======
add_action('restrict_manage_posts', function($post_type){
  if ($post_type !== 'prueba') return;

  // Valor seleccionado en GET
  $sel_esc = isset($_GET['filter_escenario_pr']) ? (int) $_GET['filter_escenario_pr'] : 0;
  $sel_est = isset($_GET['filter_estacion_pr'])  ? (int) $_GET['filter_estacion_pr']  : 0;

  // Escenarios publicados
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

  // Estaciones (si hay escenario seleccionado, filtradas por ese escenario)
  $est_args = [
    'post_type'       => 'estacion',
    'post_status'     => 'any',
    'numberposts'     => -1,
    'orderby'         => 'title',
    'order'           => 'ASC',
    'fields'          => 'ids',
    'no_found_rows'   => true,
    'suppress_filters'=> true,
  ];
  if ($sel_esc) {
    $est_args['meta_query'] = [[
      'key'     => 'gc_escenario_ref',
      'value'   => $sel_esc,
      'compare' => '=',
    ]];
  }
  $estaciones = get_posts($est_args);

  // Selector Escenario
  echo '<label for="filter_escenario_pr" class="screen-reader-text">Filtrar por escenario</label>';
  echo '<select name="filter_escenario_pr" id="filter_escenario_pr" style="max-width:220px;">';
  echo '<option value="">— Filtrar por escenario —</option>';
  foreach ($escenarios as $esc_id) {
    $t = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
    printf('<option value="%d"%s>%s</option>',
      (int)$esc_id,
      selected($sel_esc, $esc_id, false),
      esc_html($t)
    );
  }
  echo '</select>';

  // Selector Estación (opcional)
  echo '&nbsp;';
  echo '<label for="filter_estacion_pr" class="screen-reader-text">Filtrar por estación</label>';
  echo '<select name="filter_estacion_pr" id="filter_estacion_pr" style="max-width:280px;">';
  echo '<option value="">— Filtrar por estación —</option>';
  foreach ($estaciones as $est_id) {
    $t = get_the_title($est_id) ?: ('Estación #'.$est_id);
    printf('<option value="%d"%s>%s</option>',
      (int)$est_id,
      selected($sel_est, $est_id, false),
      esc_html($t)
    );
  }
  echo '</select>';

  // Pequeño JS: si cambias de escenario, resetea estación (para no dejar combos incongruentes)
  ?>
  <script>
    (function(){
      const esc = document.getElementById('filter_escenario_pr');
      const est = document.getElementById('filter_estacion_pr');
      if (esc && est) {
        esc.addEventListener('change', function(){
          // al cambiar escenario, limpiamos selección de estación
          est.selectedIndex = 0;
          // enviamos formulario automáticamente
          esc.form && esc.form.submit();
        });
      }
    })();
  </script>
  <?php
});

// ====== 2) Aplicar filtros a la query principal ======
add_action('pre_get_posts', function($q){
  if ( ! is_admin() || ! $q->is_main_query() ) return;
  if ( $q->get('post_type') !== 'prueba' ) return;

  $sel_esc = isset($_GET['filter_escenario_pr']) ? (int) $_GET['filter_escenario_pr'] : 0;
  $sel_est = isset($_GET['filter_estacion_pr'])  ? (int) $_GET['filter_estacion_pr']  : 0;

  // Caso A: filtramos por Estación concreta -> traer solo la Prueba referenciada por esa Estación
  if ($sel_est) {
    $prueba_id = (int) get_post_meta($sel_est, 'gc_prueba_ref', true);
    if ($prueba_id > 0) {
      $q->set('post__in', [$prueba_id]);
    } else {
      // No hay prueba vinculada; devolvemos vacío
      $q->set('post__in', [0]);
    }
    return;
  }

  // Caso B: filtramos por Escenario -> recopilar todas las Pruebas referenciadas por las Estaciones de ese Escenario
  if ($sel_esc) {
    $ests = get_posts([
      'post_type'       => 'estacion',
      'post_status'     => 'any',
      'numberposts'     => -1,
      'fields'          => 'ids',
      'meta_query'      => [[
        'key'     => 'gc_escenario_ref',
        'value'   => $sel_esc,
        'compare' => '=',
      ]],
      'no_found_rows'   => true,
      'suppress_filters'=> true,
    ]);
    if (!empty($ests)) {
      $ids = [];
      foreach ($ests as $e) {
        $pid = (int) get_post_meta($e, 'gc_prueba_ref', true);
        if ($pid > 0) $ids[] = $pid;
      }
      $ids = array_values(array_unique(array_filter($ids)));
      if (!empty($ids)) {
        $q->set('post__in', $ids);
      } else {
        $q->set('post__in', [0]); // sin resultados
      }
    } else {
      $q->set('post__in', [0]);
    }
  }
});

// ====== 3) Columnas personalizadas (Escenario / Estación) ======
add_filter('manage_edit-prueba_columns', function($cols){
  // insertamos tras el título
  $new = [];
  foreach ($cols as $key => $label) {
    $new[$key] = $label;
    if ($key === 'title') {
      $new['gc_estacion_ref_by'] = 'Estación';
      $new['gc_escenario_ref_by'] = 'Escenario';
    }
  }
  return $new;
});

add_action('manage_prueba_posts_custom_column', function($col, $post_id){
  if ($col === 'gc_estacion_ref_by' || $col === 'gc_escenario_ref_by') {

    // Buscar la Estación que referencia esta Prueba vía gc_prueba_ref
    $est = get_posts([
      'post_type'       => 'estacion',
      'post_status'     => 'any',
      'numberposts'     => 1,
      'fields'          => 'ids',
      'meta_query'      => [[
        'key'     => 'gc_prueba_ref',
        'value'   => (int)$post_id,
        'compare' => '=',
      ]],
      'no_found_rows'   => true,
      'suppress_filters'=> true,
    ]);

    if (!empty($est)) {
      $est_id = (int)$est[0];

      if ($col === 'gc_estacion_ref_by') {
        $t = get_the_title($est_id) ?: ('Estación #'.$est_id);
        $link = get_edit_post_link($est_id);
        echo $link ? '<a href="'.esc_url($link).'">'.esc_html($t).'</a>' : esc_html($t);
        return;
      }

      if ($col === 'gc_escenario_ref_by') {
        $esc_id = (int) get_post_meta($est_id, 'gc_escenario_ref', true);
        if ($esc_id) {
          $t = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
          $link = get_edit_post_link($esc_id);
          echo $link ? '<a href="'.esc_url($link).'">'.esc_html($t).'</a>' : esc_html($t);
        } else {
          echo '<span style="color:#999">—</span>';
        }
        return;
      }
    }

    // Sin estación asociada
    echo '<span style="color:#999">—</span>';
  }
}, 10, 2);

// (Opcional) hacer ordenable alguna columna si tuvieses un meta en "prueba"
// Aquí no marcamos ordenable porque "Escenario/Estación" se resuelven inversamente desde Estación.
