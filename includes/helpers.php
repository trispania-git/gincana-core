<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * ============================================================
 * Helpers de Gincana Core
 * ============================================================
 */

/**
 * Resuelve un valor de post_meta que puede ser un ID numérico,
 * un objeto WP_Post o un array con clave 'ID' (compatibilidad ACF).
 * Devuelve siempre un int (0 si no se puede resolver).
 */
if ( ! function_exists('gc_resolve_meta_id') ) {
  function gc_resolve_meta_id($raw) {
    if ( is_numeric($raw) ) return (int) $raw;
    if ( is_object($raw) && isset($raw->ID) ) return (int) $raw->ID;
    if ( is_array($raw) && isset($raw['ID']) ) return (int) $raw['ID'];
    if ( is_array($raw) ) {
      $first = reset($raw);
      if ( is_numeric($first) ) return (int) $first;
      if ( is_object($first) && isset($first->ID) ) return (int) $first->ID;
      if ( is_array($first) && isset($first['ID']) ) return (int) $first['ID'];
    }
    return 0;
  }
}

/**
 * Detector de Divi (Theme/Visual) Builder
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
 * - Puntos por tiempo (intervalos de 5 s, hasta 30 s)
 * - +10 SOLO si es primer intento.
 * - CAP a 100.
 */
if ( ! function_exists('gincana_points_calculate') ) {
  function gincana_points_calculate($user_id, $escenario_id, $estacion_id, $time_ms, $is_first_try) {

    $t = max(0, (int) $time_ms);
    $time_rules = apply_filters('gincana_time_bonus_rules', [
      ['lte_ms' =>  4999, 'add' => 90],
      ['lte_ms' =>  9999, 'add' => 75],
      ['lte_ms' => 14999, 'add' => 60],
      ['lte_ms' => 19999, 'add' => 45],
      ['lte_ms' => 24999, 'add' => 30],
      ['lte_ms' => 30000, 'add' => 15],
    ]);

    $points_time = 0;
    foreach ($time_rules as $rule) {
      if ($t <= (int) $rule['lte_ms']) { $points_time = (int) $rule['add']; break; }
    }

    $bonus_try = $is_first_try ? 10 : 0;
    $total = min(100, max(0, $points_time + $bonus_try));

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
 */
if ( ! function_exists('gincana_points_add') ) {
  function gincana_points_add($user_id, $escenario_id, $points, $reason = 'passed', $estacion_id = null, $meta = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'gincana_points_log';

    $user_id      = (int) $user_id;
    $escenario_id = (int) $escenario_id;
    $estacion_id  = $estacion_id ? (int) $estacion_id : null;
    $points       = (int) $points;
    $reason       = $reason ? (string) $reason : 'passed';
    $meta_json    = ! empty($meta) ? wp_json_encode($meta) : null;

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
    $orden_actual = (int) get_post_meta($estacion_id, 'gc_orden', true);
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

    $escenario_id = (int) get_post_meta($estacion_id, 'gc_escenario_ref', true);
    if (!$escenario_id) return false;

    $orden = (int) get_post_meta($estacion_id, 'gc_orden', true);
    if ($orden <= 1) return true;

    $prev_id = gincana_prev_estacion_id($escenario_id, $estacion_id);
    if (!$prev_id) return true;

    return gincana_user_passed($user_id, $prev_id);
  }
}

if ( ! function_exists('gincana_next_estacion_id') ) {
  function gincana_next_estacion_id($escenario_id, $estacion_id){
    $next_raw = get_post_meta($estacion_id, 'gc_siguiente_ref', true);
    if ($next_raw) {
      $next_id = gc_resolve_meta_id($next_raw);
      if ($next_id) return $next_id;
    }

    $orden_actual = (int) get_post_meta($estacion_id, 'gc_orden', true);
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
