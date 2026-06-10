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

// ====== 0) Acción: limpiar pruebas vacías (sin preguntas, sin estación, sin uso como pool) ======

/**
 * Considera una prueba "vacía" si NO cumple ninguna de estas condiciones:
 *  - Tiene al menos una pregunta con contenido (enunciado, respuesta de texto,
 *    o alguna opción con texto/imagen).
 *  - Está enlazada a una estación.
 *  - Es usada como pool en algún escenario.
 */
function gc_prueba_esta_vacia($prueba_id) {
    $prueba_id = (int) $prueba_id;
    if ($prueba_id <= 0) return false;
    if (get_post_type($prueba_id) !== 'prueba') return false;

    // ¿Tiene alguna pregunta con contenido real?
    $preguntas = get_post_meta($prueba_id, 'gc_preguntas', true);
    if (is_array($preguntas)) {
        foreach ($preguntas as $p) {
            if (!is_array($p)) continue;
            // Enunciado
            if (!empty(trim((string) ($p['enunciado'] ?? '')))) return false;
            // Respuesta de texto (texto / anagrama / cifrado_cesar)
            if (!empty(trim((string) ($p['respuesta_texto_correcta'] ?? '')))) return false;
            // Opciones (multiple / multiple_imagen / vf): texto o imagen
            $ops = $p['opciones'] ?? [];
            if (is_array($ops)) {
                foreach ($ops as $opt) {
                    if (!is_array($opt)) continue;
                    if (!empty(trim((string) ($opt['texto'] ?? '')))) return false;
                    if (!empty($opt['imagen'] ?? '')) return false;
                }
            }
        }
    }

    // ¿Estación enlazada (en cualquiera de los dos sentidos)?
    $est = (int) get_post_meta($prueba_id, 'gc_estacion_ref', true);
    if ($est > 0 && get_post_status($est)) {
        return false;
    }
    $back_est = get_posts([
        'post_type'      => 'estacion',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => 'gc_prueba_ref', 'value' => $prueba_id, 'compare' => '=']],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    if (!empty($back_est)) return false;

    // ¿Algún escenario la usa como pool?
    $pool = get_posts([
        'post_type'      => 'escenario',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => 'gc_pool_prueba_ref', 'value' => $prueba_id, 'compare' => '=']],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    if (!empty($pool)) return false;

    return true;
}

/**
 * Devuelve los IDs de pruebas vacías (publicadas, draft, auto-draft, etc.).
 */
function gc_get_pruebas_vacias() {
    $ids = get_posts([
        'post_type'      => 'prueba',
        'post_status'    => ['publish', 'draft', 'pending', 'private', 'auto-draft'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $vacias = [];
    foreach ($ids as $pid) {
        if (gc_prueba_esta_vacia($pid)) $vacias[] = (int) $pid;
    }
    return $vacias;
}

// Handler de la acción de limpieza (vía POST desde el listado)
add_action('admin_post_gc_clean_pruebas_vacias', function () {
    if (!current_user_can('manage_options')) wp_die('No autorizado');
    check_admin_referer('gc_clean_pruebas_vacias');
    $vacias = gc_get_pruebas_vacias();
    $deleted = 0;
    foreach ($vacias as $pid) {
        if (wp_trash_post($pid)) $deleted++;
    }
    set_transient('gc_clean_pruebas_result', $deleted, 30);
    wp_safe_redirect(add_query_arg('post_type', 'prueba', admin_url('edit.php')));
    exit;
});

// Aviso tras la limpieza
add_action('admin_notices', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-prueba') return;
    $deleted = get_transient('gc_clean_pruebas_result');
    if ($deleted === false) return;
    delete_transient('gc_clean_pruebas_result');
    if ((int) $deleted > 0) {
        echo '<div class="notice notice-success is-dismissible"><p>Se han enviado a la papelera <strong>' . (int) $deleted . '</strong> prueba' . ((int) $deleted === 1 ? '' : 's') . ' vacía' . ((int) $deleted === 1 ? '' : 's') . '.</p></div>';
    } else {
        echo '<div class="notice notice-info is-dismissible"><p>No había pruebas vacías que limpiar.</p></div>';
    }
});

/**
 * Acción de restauración: una prueba que esté en papelera por el auto-cleanup
 * pero que en realidad tiene contenido (con la lógica actualizada de
 * gc_prueba_esta_vacia) se puede restaurar de un click desde la papelera.
 */
add_action('admin_post_gc_restore_prueba', function () {
    if (!current_user_can('edit_posts')) wp_die('No autorizado');
    check_admin_referer('gc_restore_prueba');
    $pid = isset($_REQUEST['post']) ? (int) $_REQUEST['post'] : 0;
    if ($pid > 0 && get_post_type($pid) === 'prueba' && get_post_status($pid) === 'trash') {
        wp_untrash_post($pid);
    }
    wp_safe_redirect( get_edit_post_link($pid, 'redirect') ?: admin_url('edit.php?post_type=prueba') );
    exit;
});

/**
 * Aviso en la pantalla de edición cuando intentas abrir una prueba que está
 * en la papelera. Ofrece un botón para restaurarla.
 */
add_action('admin_notices', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'prueba') return;
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($post_id <= 0) return;
    if (get_post_status($post_id) !== 'trash') return;
    $url = wp_nonce_url(admin_url('admin-post.php?action=gc_restore_prueba&post=' . $post_id), 'gc_restore_prueba');
    echo '<div class="notice notice-warning"><p>Esta prueba está en la papelera. <a class="button button-primary" href="' . esc_url($url) . '" style="margin-left:8px;">Restaurar y editar</a></p></div>';
});

// Botón en la cabecera del listado (junto a "Añadir nueva") — vía POST con nonce fresco
add_action('admin_head-edit.php', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-prueba') return;
    $count = count(gc_get_pruebas_vacias());
    if ($count <= 0) return; // No mostrar el botón si no hay nada que limpiar
    $form_action = admin_url('admin-post.php');
    $nonce       = wp_create_nonce('gc_clean_pruebas_vacias');
    $confirm     = "¿Enviar a la papelera {$count} prueba" . ($count === 1 ? '' : 's') . " vacía" . ($count === 1 ? '' : 's') . " (sin preguntas, sin estación enlazada y no usadas como pool)?";
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var addNew = document.querySelector('.wrap .page-title-action');
        if (!addNew) return;
        var form = document.createElement('form');
        form.method = 'post';
        form.action = <?php echo wp_json_encode($form_action); ?>;
        form.style.display = 'inline-block';
        form.style.marginLeft = '6px';
        form.innerHTML =
            '<input type="hidden" name="action" value="gc_clean_pruebas_vacias">' +
            '<input type="hidden" name="_wpnonce" value="<?php echo esc_js($nonce); ?>">' +
            '<button type="submit" class="page-title-action" style="background:#fef2f2;border-color:#fca5a5;color:#991b1b;">🧹 Limpiar pruebas vacías (<?php echo (int) $count; ?>)</button>';
        form.addEventListener('submit', function (e) {
            if (!confirm(<?php echo wp_json_encode($confirm); ?>)) e.preventDefault();
        });
        addNew.parentNode.insertBefore(form, addNew.nextSibling);
    });
    </script>
    <?php
});

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

  // Helper: pruebas que apuntan a una estación vía gc_estacion_ref (vínculo moderno)
  $pruebas_de_estacion = function($est_id) {
    $est_id = (int) $est_id;
    if (!$est_id) return [];
    $pq = get_posts([
      'post_type'       => 'prueba',
      'post_status'     => 'any',
      'numberposts'     => -1,
      'fields'          => 'ids',
      'meta_query'      => [['key' => 'gc_estacion_ref', 'value' => $est_id, 'compare' => '=']],
      'no_found_rows'   => true,
      'suppress_filters'=> true,
    ]);
    return array_map('intval', (array) $pq);
  };

  // Caso A: filtramos por Estación concreta -> la prueba de esa estación
  if ($sel_est) {
    $ids = [];
    $pid = (int) get_post_meta($sel_est, 'gc_prueba_ref', true); // legacy
    if ($pid > 0) $ids[] = $pid;
    $ids = array_merge($ids, $pruebas_de_estacion($sel_est)); // moderno
    $ids = array_values(array_unique(array_filter($ids)));
    $q->set('post__in', !empty($ids) ? $ids : [0]);
    return;
  }

  // Caso B: filtramos por Escenario -> pool del escenario + pruebas de sus estaciones
  if ($sel_esc) {
    $ids = [];

    // 1) Prueba usada como POOL por el escenario (vínculo escenario -> prueba)
    $pool_pid = (int) get_post_meta($sel_esc, 'gc_pool_prueba_ref', true);
    if ($pool_pid > 0) $ids[] = $pool_pid;

    // 2) Pruebas de cada estación del escenario (legacy + moderno)
    $ests = get_posts([
      'post_type'       => 'estacion',
      'post_status'     => 'any',
      'numberposts'     => -1,
      'fields'          => 'ids',
      'meta_query'      => [['key' => 'gc_escenario_ref', 'value' => $sel_esc, 'compare' => '=']],
      'no_found_rows'   => true,
      'suppress_filters'=> true,
    ]);
    foreach ((array) $ests as $e) {
      $pid = (int) get_post_meta($e, 'gc_prueba_ref', true);
      if ($pid > 0) $ids[] = $pid;
      $ids = array_merge($ids, $pruebas_de_estacion($e));
    }

    $ids = array_values(array_unique(array_filter($ids)));
    $q->set('post__in', !empty($ids) ? $ids : [0]);
  }
});

// ====== 3) Columnas personalizadas (Escenario / Estación) ======
add_filter('manage_edit-prueba_columns', function($cols){
  // insertamos tras el título
  $new = [];
  foreach ($cols as $key => $label) {
    $new[$key] = $label;
    if ($key === 'title') {
      $new['gc_num_preguntas'] = 'Preguntas';
      $new['gc_estacion_ref_by'] = 'Estación';
      $new['gc_escenario_ref_by'] = 'Escenario';
    }
  }
  return $new;
});

add_action('manage_prueba_posts_custom_column', function($col, $post_id){
  if ($col === 'gc_num_preguntas') {
    $preguntas = get_post_meta($post_id, 'gc_preguntas', true);
    $count = is_array($preguntas) ? count($preguntas) : 0;
    echo '<strong>' . (int) $count . '</strong>';
    return;
  }

  if ($col === 'gc_estacion_ref_by' || $col === 'gc_escenario_ref_by') {

    $est_id = 0;

    // 1) Vía directa: meta gc_estacion_ref en la prueba
    $direct = (int) get_post_meta($post_id, 'gc_estacion_ref', true);
    if ($direct > 0 && get_post_status($direct)) {
      $est_id = $direct;
    }

    // 2) Fallback inverso: estación que referencia esta prueba vía gc_prueba_ref
    if (!$est_id) {
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
      if (!empty($est)) $est_id = (int) $est[0];
    }

    if ($est_id) {
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

    // Sin estación asociada — puede ser pool
    $pool_use = get_posts([
      'post_type'       => 'escenario',
      'post_status'     => 'any',
      'numberposts'     => 1,
      'fields'          => 'ids',
      'meta_query'      => [['key' => 'gc_pool_prueba_ref', 'value' => (int)$post_id, 'compare' => '=']],
      'no_found_rows'   => true,
      'suppress_filters'=> true,
    ]);
    if (!empty($pool_use)) {
      if ($col === 'gc_estacion_ref_by') {
        echo '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#f5f3ff;color:#5b21b6;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.3px;">Pool</span>';
        return;
      }
      if ($col === 'gc_escenario_ref_by') {
        $esc_id = (int) $pool_use[0];
        $t = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
        $link = get_edit_post_link($esc_id);
        echo $link ? '<a href="'.esc_url($link).'">'.esc_html($t).'</a>' : esc_html($t);
        return;
      }
    }

    echo '<span style="color:#999">—</span>';
  }
}, 10, 2);

// (Opcional) hacer ordenable alguna columna si tuvieses un meta en "prueba"
// Aquí no marcamos ordenable porque "Escenario/Estación" se resuelven inversamente desde Estación.
