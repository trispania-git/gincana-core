<?php
if ( ! defined('ABSPATH') ) exit;

add_action('rest_api_init', function(){

  // =========================================================
  // POST /wp-json/gincana/v1/progress/complete
  // Marca estación como superada (idempotente) y suma puntos
  // =========================================================
  register_rest_route('gincana/v1','/progress/complete',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      global $wpdb;

      $user_id     = get_current_user_id();
      $estacion_id = (int) $req->get_param('estacion_id');
      $time_ms     = max(0, (int) $req->get_param('time_ms'));

      if (!$user_id || !$estacion_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_params'], 400);
      }

      $post = get_post($estacion_id);
      if (!$post || $post->post_type !== 'estacion') {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_estacion'], 400);
      }

      $esc_raw      = get_post_meta($estacion_id, 'gc_escenario_ref', true);
      $escenario_id = (int) $esc_raw;
      if (!$escenario_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'escenario_not_found_from_estacion'], 400);
      }

      $progress_table = $wpdb->prefix . 'gincana_user_progress';

      $status = $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM $progress_table WHERE user_id=%d AND escenario_id=%d AND estacion_id=%d",
        $user_id, $escenario_id, $estacion_id
      ));
      if ($status === 'passed') {
        return new WP_REST_Response([
          'ok' => true,
          'already_passed' => true,
          'points_awarded' => 0
        ], 200);
      }

      $wpdb->query( $wpdb->prepare("
        INSERT INTO $progress_table (user_id, escenario_id, estacion_id, status, attempts, best_time_ms)
        VALUES (%d,%d,%d,'passed',1,%d)
        ON DUPLICATE KEY UPDATE
          status='passed',
          best_time_ms = LEAST(COALESCE(best_time_ms, %d), %d)
      ", $user_id, $escenario_id, $estacion_id, $time_ms, $time_ms, $time_ms ) );

      $prueba_id = (int) get_post_meta($estacion_id, 'gc_prueba_ref', true);

      $had_fail = false;
      if ($prueba_id) {
        $had_fail = (bool) $wpdb->get_var( $wpdb->prepare(
          "SELECT 1 FROM {$wpdb->prefix}gincana_attempts WHERE user_id=%d AND prueba_id=%d AND result='fail' LIMIT 1",
          $user_id, $prueba_id
        ));
      }

      $points_to_add = function_exists('gincana_points_calculate')
        ? gincana_points_calculate($user_id, $escenario_id, $estacion_id, $time_ms, ! $had_fail)
        : 0;

      if (!function_exists('gincana_points_add')) {
        return new WP_REST_Response(['ok'=>false,'error'=>'points_add_missing'], 500);
      }

      gincana_points_add($user_id, $escenario_id, $points_to_add, 'passed', $estacion_id, [
        'time_ms'   => $time_ms,
        'first_try' => ! $had_fail
      ]);

      return new WP_REST_Response([
        'ok'             => true,
        'already_passed' => false,
        'points_awarded' => (int) $points_to_add,
        'first_try'      => ! $had_fail
      ], 200);
    }
  ]);

  // =========================================================
  // POST /wp-json/gincana/v1/quiz/submit
  // Valida respuestas del quiz contra gc_preguntas + registra intento
  // =========================================================
  register_rest_route('gincana/v1','/quiz/submit',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      global $wpdb;

      $prueba_id = (int) $req->get_param('prueba_id');
      $answers   = (array) $req->get_param('answers');
      $time_ms   = (int) $req->get_param('time_ms');

      if (!$prueba_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_prueba_id'], 400);
      }

      $post = get_post($prueba_id);
      if (!$post || $post->post_type !== 'prueba') {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_prueba'], 400);
      }

      $pregs       = get_post_meta($prueba_id, 'gc_preguntas', true);
      $tipo_global = get_post_meta($prueba_id, 'gc_tipo', true);

      if (empty($pregs)) {
        return new WP_REST_Response(['ok'=>false,'error'=>'no_questions'], 200);
      }

      $norm = function($s){
        $s = wp_strip_all_tags((string)$s);
        $s = strtolower(trim($s));
        if (function_exists('remove_accents')) {
          $s = remove_accents($s);
        }
        return preg_replace('/\s+/', ' ', $s);
      };

      $all_ok = true;
      foreach ($pregs as $i => $p) {
        $tipo = !empty($p['tipo']) ? $p['tipo'] : $tipo_global;
        $ans  = array_key_exists($i, $answers) ? $answers[$i] : null;

        if ($tipo === 'texto') {
          $correcta = $norm($p['respuesta_texto_correcta'] ?? '');
          $user     = $norm($ans);
          if ($correcta === '' || $user === '' || $user !== $correcta) { $all_ok = false; break; }
        } else {
          $ops = $p['opciones'] ?? [];
          if (!is_numeric($ans) || !isset($ops[(int)$ans])) { $all_ok = false; break; }
          $is_ok = !empty($ops[(int)$ans]['es_correcta']);
          if (!$is_ok) { $all_ok = false; break; }
        }
      }

      $attempts_table = $wpdb->prefix . 'gincana_attempts';

      $estacion_id_from_prueba    = (int) get_post_meta($prueba_id, 'gc_estacion_ref', true);
      $escenario_id_from_estacion = $estacion_id_from_prueba ? (int) get_post_meta($estacion_id_from_prueba, 'gc_escenario_ref', true) : 0;

      $wpdb->insert($attempts_table, [
        'user_id'      => (int) get_current_user_id(),
        'prueba_id'    => (int) $prueba_id,
        'escenario_id' => (int) $escenario_id_from_estacion,
        'estacion_id'  => (int) $estacion_id_from_prueba,
        'result'       => $all_ok ? 'success' : 'fail',
        'time_ms'      => max(0, (int)$time_ms),
        'payload_json' => wp_json_encode(['answers'=>$answers]),
        'ip_hash'      => null,
        'ua_hash'      => null,
      ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s']);

      return new WP_REST_Response(['ok'=>$all_ok], 200);
    }
  ]);

  // =========================================================
  // POST /wp-json/gincana/v1/progress/skip
  // Marca estación como superada SIN puntos (uso QR / infantil)
  // Ahora acepta time_ms real opcional
  // =========================================================
  register_rest_route('gincana/v1','/progress/skip',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      global $wpdb;

      $user_id     = get_current_user_id();
      $estacion_id = (int) $req->get_param('estacion_id');
      $time_ms     = max(0, (int) $req->get_param('time_ms'));

      if (!$user_id || !$estacion_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_params'],400);
      }

      $post = get_post($estacion_id);
      if (!$post || $post->post_type !== 'estacion') {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_estacion'], 400);
      }

      $escenario_id = (int) get_post_meta($estacion_id, 'gc_escenario_ref', true);
      if (!$escenario_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'escenario_not_found_from_estacion'],400);
      }

      $progress_table = $wpdb->prefix . 'gincana_user_progress';

      $status = $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM $progress_table WHERE user_id=%d AND escenario_id=%d AND estacion_id=%d",
        $user_id, $escenario_id, $estacion_id
      ));
      if ($status === 'passed') {
        return new WP_REST_Response([
          'ok'=>true,
          'already_passed'=>true,
          'points_awarded'=>0
        ],200);
      }

      // Si no llega tiempo real, usamos un fallback alto
      $final_time_ms = $time_ms > 0 ? $time_ms : 31000;

      $wpdb->query( $wpdb->prepare("
        INSERT INTO $progress_table (user_id, escenario_id, estacion_id, status, attempts, best_time_ms)
        VALUES (%d,%d,%d,'passed',1,%d)
        ON DUPLICATE KEY UPDATE
          status='passed',
          best_time_ms = LEAST(COALESCE(best_time_ms,%d), %d)
      ", $user_id, $escenario_id, $estacion_id, $final_time_ms, $final_time_ms, $final_time_ms) );

      if (function_exists('gincana_points_add')) {
        gincana_points_add($user_id, $escenario_id, 0, 'skip_qr', $estacion_id, [
          'time_ms' => $final_time_ms
        ]);
      }

      return new WP_REST_Response([
        'ok'=>true,
        'already_passed'=>false,
        'points_awarded'=>0,
        'time_ms'=>$final_time_ms
      ],200);
    }
  ]);

});