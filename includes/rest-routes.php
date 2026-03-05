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

      // 1) Datos de entrada
      $user_id     = get_current_user_id();
      $estacion_id = (int) $req->get_param('estacion_id');
      $time_ms     = max(0, (int) $req->get_param('time_ms')); // opcional

      if (!$user_id || !$estacion_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_params'], 400);
      }

      // 1.a) Defensa: validar que es una Estación válida
      $post = get_post($estacion_id);
      if (!$post || $post->post_type !== 'estacion') {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_estacion'], 400);
      }

      // 1.b) Recalcular SIEMPRE el escenario desde la estación (normalizando ID)
      if (!function_exists('get_field')) {
        return new WP_REST_Response(['ok'=>false,'error'=>'acf_missing'], 500);
      }
      $esc_raw      = get_field('gc_escenario_ref', $estacion_id);
      $escenario_id = 0;
      if (is_numeric($esc_raw)) {
        $escenario_id = (int) $esc_raw;
      } elseif (is_object($esc_raw) && isset($esc_raw->ID)) {
        $escenario_id = (int) $esc_raw->ID;
      } elseif (is_array($esc_raw) && isset($esc_raw['ID'])) {
        $escenario_id = (int) $esc_raw['ID'];
      }
      if (!$escenario_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'escenario_not_found_from_estacion'], 400);
      }

      // 2) Tabla con prefijo correcto
      $progress_table = $wpdb->prefix . 'gincana_user_progress';

      // 3) Idempotencia: ¿ya estaba pasada?
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

      // 4) Upsert: marca como passed y guarda el mejor tiempo
      $wpdb->query( $wpdb->prepare("
        INSERT INTO $progress_table (user_id, escenario_id, estacion_id, status, attempts, best_time_ms)
        VALUES (%d,%d,%d,'passed',1,%d)
        ON DUPLICATE KEY UPDATE
          status='passed',
          best_time_ms = LEAST(COALESCE(best_time_ms, %d), %d)
      ", $user_id, $escenario_id, $estacion_id, $time_ms, $time_ms, $time_ms ) );

      // 5) Calcular y registrar puntos SOLO si no estaba pasada
      //    - Bonus 1er intento si no hubo fallos previos en esta prueba
      $prueba_raw = get_field('gc_prueba_ref', $estacion_id);
      $prueba_id  = 0;
      if (is_numeric($prueba_raw)) {
        $prueba_id = (int)$prueba_raw;
      } elseif (is_object($prueba_raw) && isset($prueba_raw->ID)) {
        $prueba_id = (int)$prueba_raw->ID;
      } elseif (is_array($prueba_raw) && isset($prueba_raw['ID'])) {
        $prueba_id = (int)$prueba_raw['ID'];
      }

      $had_fail = false;
      if ($prueba_id) {
        $had_fail = (bool) $wpdb->get_var( $wpdb->prepare(
          "SELECT 1 FROM {$wpdb->prefix}gincana_attempts WHERE user_id=%d AND prueba_id=%d AND result='fail' LIMIT 1",
          $user_id, $prueba_id
        ));
      }

      if (!function_exists('gincana_points_calculate')) {
        $points_to_add = 0; // si no está la helper, mejor que no puntúe
      } else {
        $points_to_add = gincana_points_calculate(
          $user_id, $escenario_id, $estacion_id, $time_ms, ! $had_fail
        );
      }

      if (!function_exists('gincana_points_add')) {
        return new WP_REST_Response(['ok'=>false,'error'=>'points_add_missing'], 500);
      }

      gincana_points_add($user_id, $escenario_id, $points_to_add, 'passed', $estacion_id, [
        'time_ms'   => $time_ms,
        'first_try' => ! $had_fail
      ]);

      // 6) Respuesta limpia (sin debug)
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
  // Valida respuestas del quiz contra ACF + registra intento
  // =========================================================
  register_rest_route('gincana/v1','/quiz/submit',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      global $wpdb;

      $prueba_id = (int) $req->get_param('prueba_id');
      $answers   = (array) $req->get_param('answers'); // array por índice
      $time_ms   = (int) $req->get_param('time_ms');   // opcional (para auditoría)

      if (!$prueba_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_prueba_id'], 400);
      }

      // Defensa: validar que es una Prueba válida
      $post = get_post($prueba_id);
      if (!$post || $post->post_type !== 'prueba') {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_prueba'], 400);
      }

      // Cargar estructura ACF
      if (!function_exists('get_field')) {
        return new WP_REST_Response(['ok'=>false,'error'=>'acf_missing'], 500);
      }

      $pregs       = get_field('gc_preguntas', $prueba_id);
      $tipo_global = get_field('gc_tipo', $prueba_id); // 'multiple' | 'vf' | 'texto'

      if (empty($pregs)) {
        // Sin preguntas: no aprobar
        return new WP_REST_Response(['ok'=>false,'error'=>'no_questions'], 200);
      }

      // Normalizador texto libre
      $norm = function($s){
        $s = wp_strip_all_tags((string)$s);
        $s = strtolower(trim($s));
        $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
        return preg_replace('/\s+/', ' ', $s);
      };

      // Validación (todas correctas = OK)
      $all_ok = true;
      foreach ($pregs as $i => $p) {
        $tipo = !empty($p['tipo']) ? $p['tipo'] : $tipo_global;
        $ans  = array_key_exists($i, $answers) ? $answers[$i] : null;

        if ($tipo === 'texto') {
          $correcta = $norm($p['respuesta_texto_correcta'] ?? '');
          $user     = $norm($ans);
          if ($correcta === '' || $user === '' || $user !== $correcta) { $all_ok = false; break; }

        } else {
          // multiple | vf: esperamos índice de opción (0..n)
          $ops = $p['opciones'] ?? [];
          if (!is_numeric($ans) || !isset($ops[(int)$ans])) { $all_ok = false; break; }
          $is_ok = !empty($ops[(int)$ans]['es_correcta']);
          if (!$is_ok) { $all_ok = false; break; }
        }
      }

      // Registrar intento (auditoría)
      $attempts_table = $wpdb->prefix . 'gincana_attempts';

      // Deducir estación/escenario desde la prueba si el campo existe
      $estacion_id_from_prueba     = (int) get_field('gc_estacion_ref', $prueba_id); // si no lo usas, quedará 0
      $escenario_id_from_estacion  = $estacion_id_from_prueba ? (int) get_field('gc_escenario_ref', $estacion_id_from_prueba) : 0;

      $wpdb->insert($attempts_table, [
        'user_id'      => (int) get_current_user_id(),
        'prueba_id'    => (int) $prueba_id,
        'escenario_id' => (int) $escenario_id_from_estacion,
        'estacion_id'  => (int) $estacion_id_from_prueba,
        'result'       => $all_ok ? 'success' : 'fail',
        'time_ms'      => $time_ms ?: null,
        'payload_json' => wp_json_encode(['answers'=>$answers]),
        'ip_hash'      => null,
        'ua_hash'      => null,
      ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s']);

      return new WP_REST_Response(['ok'=>$all_ok], 200);
    }
  ]);

  // =========================================================
  // POST /wp-json/gincana/v1/progress/skip
  // Marca estación como superada SIN puntos (uso QR / salto físico)
  // =========================================================
  register_rest_route('gincana/v1','/progress/skip',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      global $wpdb;

      $user_id     = get_current_user_id();
      $estacion_id = (int) $req->get_param('estacion_id');
      if (!$user_id || !$estacion_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_params'],400);
      }

      // Defensa: validar que es una Estación válida
      $post = get_post($estacion_id);
      if (!$post || $post->post_type !== 'estacion') {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_estacion'], 400);
      }

      if (!function_exists('get_field')) {
        return new WP_REST_Response(['ok'=>false,'error'=>'acf_missing'],500);
      }
      // Recalcular escenario desde estación
      $esc_raw      = get_field('gc_escenario_ref', $estacion_id);
      $escenario_id = 0;
      if (is_numeric($esc_raw)) {
        $escenario_id = (int) $esc_raw;
      } elseif (is_object($esc_raw) && isset($esc_raw->ID)) {
        $escenario_id = (int) $esc_raw->ID;
      } elseif (is_array($esc_raw) && isset($esc_raw['ID'])) {
        $escenario_id = (int) $esc_raw['ID'];
      }
      if (!$escenario_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'escenario_not_found_from_estacion'],400);
      }

      $progress_table = $wpdb->prefix . 'gincana_user_progress';

      // Idempotencia
      $status = $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM $progress_table WHERE user_id=%d AND escenario_id=%d AND estacion_id=%d",
        $user_id, $escenario_id, $estacion_id
      ));
      if ($status === 'passed') {
        return new WP_REST_Response(['ok'=>true,'already_passed'=>true,'points_awarded'=>0],200);
      }

      // Marcar pasada con tiempo "alto" (no real) y 0 puntos
      $fake_time = 31000;
      $wpdb->query( $wpdb->prepare("
        INSERT INTO $progress_table (user_id, escenario_id, estacion_id, status, attempts, best_time_ms)
        VALUES (%d,%d,%d,'passed',1,%d)
        ON DUPLICATE KEY UPDATE status='passed', best_time_ms = LEAST(COALESCE(best_time_ms,%d), %d)
      ", $user_id, $escenario_id, $estacion_id, $fake_time, $fake_time, $fake_time) );

      if (function_exists('gincana_points_add')) {
        gincana_points_add($user_id, $escenario_id, 0, 'skip_qr', $estacion_id, []);
      }

      return new WP_REST_Response(['ok'=>true,'already_passed'=>false,'points_awarded'=>0],200);
    }
  ]);

});
