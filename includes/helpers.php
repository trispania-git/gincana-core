<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ============================================================
 * Helpers de Gincana Core
 * - gincana_is_divi_builder(): detecta si Divi (Theme/Visual) Builder está activo
 * - gincana_points_calculate(): calcula la puntuación final
 * - gincana_points_add(): registra puntos en la tabla *_gincana_points_log
 * - Helpers de progreso/orden: gincana_user_passed, _prev/_next, _can_access
 * - Filtros:
 *     - gincana_time_bonus_rules -> permite ajustar tramos/bonus de tiempo
 *     - gincana_points_total     -> permite ajustar el total final calculado
 * ============================================================
 */

/**
 * Detector de Divi (Theme/Visual) Builder
 * Devuelve true cuando el Theme Builder / Visual Builder está renderizando la página.
 */
if ( ! function_exists('gincana_is_divi_builder') ) {
  function gincana_is_divi_builder() : bool {
    $qs = $_GET ?? [];
    $flags = [
      'et_fb','et_bfb','et_tb','et_tb_preview','et_pb_preview',
      'et_builder_module_render','et_is_builder','et_builder_load'
    ];
    foreach ($flags as $k) {
      if ( isset($qs[$k]) && $qs[$k] !== '' && $qs[$k] !== '0' ) return true;
    }
    if ( defined('ET_CORE_VERSION') && ( isset($qs['et_builder_load']) || isset($qs['et_builder_module_render']) ) ) {
      return true;
    }
    return false;
  }
}

/**
 * Regla vigente de puntos:
 * - Puntos por tiempo (intervalos de 5 s, hasta 30 s) SIEMPRE que se acierte:
 *     0.00–4.99s  => 90
 *     5.00–9.99s  => 75
 *     10.00–14.99s=> 60
 *     15.00–19.99s=> 45
 *     20.00–24.99s=> 30
 *     25.00–30.00s=> 15
 *     >30s        => 0
 * - +10 SOLO si es primer intento.
 * - CAP a 100 (min(100, tiempo + bonus_1er_intento)).
 */
if ( ! function_exists('gincana_points_calculate') ) {
  function gincana_points_calculate($user_id, $escenario_id, $estacion_id, $time_ms, $is_first_try) {

    // 1) Puntos por tiempo (intervalos 5s hasta 30s; >30s => 0)
    $t = max(0, (int) $time_ms);
    $time_rules = apply_filters('gincana_time_bonus_rules', [
      ['lte_ms' =>  4999, 'add' => 90], // 0.00–4.99s
      ['lte_ms' =>  9999, 'add' => 75], // 5.00–9.99s
      ['lte_ms' => 14999, 'add' => 60], // 10.00–14.99s
      ['lte_ms' => 19999, 'add' => 45], // 15.00–19.99s
      ['lte_ms' => 24999, 'add' => 30], // 20.00–24.99s
      ['lte_ms' => 30000, 'add' => 15], // 25.00–30.00s
    ]);

    $points_time = 0;
    foreach ($time_rules as $rule) {
      if ($t <= (int) $rule['lte_ms']) { $points_time = (int) $rule['add']; break; }
    }

    // 2) +10 solo si es primer intento; si no, +0
    $bonus_try = $is_first_try ? 10 : 0;

    // 3) Total (cap a 100)
    $total = min(100, max(0, $points_time + $bonus_try));

    // Filtro final por si quieres ajustar globalmente por código
    return (int) apply_filters('gincana_points_total', $total, [
      'user_id'      => $user_id,
      'escenario_id' => $escenario_id,
      'estacion_id'  => $estacion_id,
      'time_ms'      => $t,
      'is_first_try' => (bool) $is_first_try,
      'bonus_time'   => $points_time,
      'bonus_try'    => $bonus_try,
    ]);
  }
}

/**
 * Registra puntos en la tabla *_gincana_points_log
 * Campos esperados:
 *  - user_id (int), escenario_id (int), estacion_id (int|null),
 *    points (int), reason (string), meta_json (json|null), created_at (auto DB)
 */
if ( ! function_exists('gincana_points_add') ) {
  function gincana_points_add($user_id, $escenario_id, $points, $reason = 'passed', $estacion_id = null, $meta = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'gincana_points_log';

    // Sanitizar/normalizar
    $user_id      = (int) $user_id;
    $escenario_id = (int) $escenario_id;
    $estacion_id  = $estacion_id ? (int) $estacion_id : null;
    $points       = (int) $points;
    $reason       = $reason ? (string) $reason : 'passed';
    $meta_json    = ! empty($meta) ? wp_json_encode($meta) : null;

    // Insert
    $wpdb->insert(
      $table,
      [
        'user_id'      => $user_id,
        'escenario_id' => $escenario_id,
        'estacion_id'  => $estacion_id,
        'points'       => $points,
        'reason'       => $reason,
        'meta_json'    => $meta_json,
      ],
      ['%d','%d','%d','%d','%s','%s']
    );

    return (int) $points;
  }
}

// === Helpers de progreso / orden ===
if ( ! function_exists('gincana_user_passed') ) {
  function gincana_user_passed($user_id, $estacion_id){
    global $wpdb;
    $table = $wpdb->prefix.'gincana_user_progress';
    $status = $wpdb->get_var( $wpdb->prepare(
      "SELECT status FROM $table WHERE user_id=%d AND estacion_id=%d", (int)$user_id, (int)$estacion_id
    ));
    return $status === 'passed';
  }
}

if ( ! function_exists('gincana_prev_estacion_id') ) {
  function gincana_prev_estacion_id($escenario_id, $estacion_id){
    $orden_actual = (int) get_field('gc_orden', $estacion_id);
    if ($orden_actual <= 1) return 0;

    $q = new WP_Query([
      'post_type'      => 'estacion',
      'posts_per_page' => 1,
      'meta_query'     => [
        ['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'='],
        ['key'=>'gc_orden','value'=>$orden_actual-1,'compare'=>'=','type'=>'NUMERIC'],
      ],
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    return $q->have_posts() ? (int)$q->posts[0] : 0;
  }
}

if ( ! function_exists('gincana_can_access_estacion') ) {
  function gincana_can_access_estacion($user_id, $estacion_id){
    if (!$user_id) return false;

    $esc_raw = get_field('gc_escenario_ref', $estacion_id);
    $escenario_id = is_numeric($esc_raw) ? (int)$esc_raw : ( (is_object($esc_raw)&&isset($esc_raw->ID)) ? (int)$esc_raw->ID : ( (is_array($esc_raw)&&isset($esc_raw['ID'])) ? (int)$esc_raw['ID'] : 0 ) );
    if (!$escenario_id) return false;

    $orden = (int) get_field('gc_orden', $estacion_id);
    if ($orden <= 1) return true; // primera estación del escenario

    $prev_id = gincana_prev_estacion_id($escenario_id, $estacion_id);
    if (!$prev_id) return true;

    return gincana_user_passed($user_id, $prev_id);
  }
}

if ( ! function_exists('gincana_next_estacion_id') ) {
  function gincana_next_estacion_id($escenario_id, $estacion_id){
    // 1) Si has definido gc_siguiente_ref, úsalo.
    $next_raw = get_field('gc_siguiente_ref', $estacion_id);
    if ($next_raw) {
      if (is_numeric($next_raw)) return (int)$next_raw;
      if (is_object($next_raw)&&isset($next_raw->ID)) return (int)$next_raw->ID;
      if (is_array($next_raw)&&isset($next_raw['ID'])) return (int)$next_raw['ID'];
    }
    // 2) Si no, el de orden +1 dentro del mismo escenario
    $orden_actual = (int) get_field('gc_orden', $estacion_id);
    if ($orden_actual <= 0) return 0;

    $q = new WP_Query([
      'post_type'      => 'estacion',
      'posts_per_page' => 1,
      'meta_query'     => [
        ['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'='],
        ['key'=>'gc_orden','value'=>$orden_actual+1,'compare'=>'=','type'=>'NUMERIC'],
      ],
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    return $q->have_posts() ? (int)$q->posts[0] : 0;
  }
}
