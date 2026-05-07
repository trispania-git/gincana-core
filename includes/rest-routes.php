<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Devuelve el estado actual del intento del usuario en una prueba/estación:
 *   - started_at (timestamp en segundos, 0 si aún no ha arrancado)
 *   - failed_attempts (nº de intentos fallidos previos)
 *   - max_attempts (configurado en la prueba)
 *   - time_max_s (configurado en la prueba)
 *   - time_left_s (segundos restantes; -1 si no aplica)
 *   - blocked (bool: true si no puede seguir intentando por tiempo o intentos)
 *   - blocked_reason ('time' | 'attempts' | '')
 *   - passed (bool: ya superó la estación)
 */
function gc_quiz_user_state($user_id, $prueba_id, $estacion_id) {
    global $wpdb;
    $user_id     = (int) $user_id;
    $prueba_id   = (int) $prueba_id;
    $estacion_id = (int) $estacion_id;

    $max_attempts = (int) get_post_meta($prueba_id, 'gc_intentos_max', true);
    if ($max_attempts < 1) $max_attempts = 2;
    $time_max_s   = (int) get_post_meta($prueba_id, 'gc_tiempo_max_s', true);
    if ($time_max_s < 0) $time_max_s = 0;

    $state_key = 'gc_quiz_state_' . $prueba_id . '_' . $estacion_id;
    $started_at = (int) get_user_meta($user_id, $state_key . '_started', true);

    $attempts_table = $wpdb->prefix . 'gincana_attempts';
    $failed = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $attempts_table WHERE user_id=%d AND prueba_id=%d AND estacion_id=%d AND result='fail'",
        $user_id, $prueba_id, $estacion_id
    ));

    $progress_table = $wpdb->prefix . 'gincana_user_progress';
    $passed = false;
    if ($estacion_id > 0) {
        $row = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM $progress_table WHERE user_id=%d AND estacion_id=%d",
            $user_id, $estacion_id
        ));
        $passed = ($row === 'passed');
    }

    $time_left = -1;
    if ($time_max_s > 0 && $started_at > 0) {
        $elapsed = time() - $started_at;
        $time_left = max(0, $time_max_s - $elapsed);
    } elseif ($time_max_s > 0) {
        $time_left = $time_max_s;
    }

    $blocked = false;
    $blocked_reason = '';
    if (!$passed) {
        if ($failed >= $max_attempts) { $blocked = true; $blocked_reason = 'attempts'; }
        elseif ($time_max_s > 0 && $started_at > 0 && $time_left <= 0) { $blocked = true; $blocked_reason = 'time'; }
    }

    return [
        'started_at'      => $started_at,
        'failed_attempts' => $failed,
        'max_attempts'    => $max_attempts,
        'attempts_left'   => max(0, $max_attempts - $failed),
        'time_max_s'      => $time_max_s,
        'time_left_s'     => $time_left,
        'blocked'         => $blocked,
        'blocked_reason'  => $blocked_reason,
        'passed'          => $passed,
        'state_key'       => $state_key,
    ];
}

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
  // POST /wp-json/gincana/v1/quiz/start
  // Marca el inicio del intento del usuario en una prueba/estación
  // (registra started_at en user_meta si no existía aún).
  // =========================================================
  register_rest_route('gincana/v1','/quiz/start',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      $user_id     = get_current_user_id();
      $prueba_id   = (int) $req->get_param('prueba_id');
      $estacion_id = (int) $req->get_param('estacion_id');
      if (!$user_id || !$prueba_id || !$estacion_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_params'], 400);
      }
      $state = gc_quiz_user_state($user_id, $prueba_id, $estacion_id);
      if ($state['started_at'] === 0 && !$state['passed']) {
        update_user_meta($user_id, $state['state_key'] . '_started', time());
        // Recalcular para devolver el estado real
        $state = gc_quiz_user_state($user_id, $prueba_id, $estacion_id);
      }
      return new WP_REST_Response(['ok'=>true, 'state'=>$state], 200);
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
      $q_index   = $req->get_param('q_index'); // null si no viene (modo normal)

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

      // Si viene q_index, solo validar ESA pregunta (modo pool)
      // Si no, validar todas (modo normal por estación)
      $pregs_to_check = $pregs;
      $answers_to_check = $answers;
      if ($q_index !== null && is_numeric($q_index)) {
        $qi = (int)$q_index;
        if (!isset($pregs[$qi])) {
          return new WP_REST_Response(['ok'=>false,'error'=>'invalid_q_index'], 400);
        }
        $pregs_to_check = [$pregs[$qi]];
        $answers_to_check = [isset($answers[0]) ? $answers[0] : null];
      }

      $all_ok = true;
      foreach ($pregs_to_check as $i => $p) {
        $tipo = !empty($p['tipo']) ? $p['tipo'] : $tipo_global;
        $ans  = array_key_exists($i, $answers_to_check) ? $answers_to_check[$i] : null;

        // Tipos de respuesta libre (string normalizado)
        if ( in_array($tipo, ['texto', 'cifrado_cesar', 'anagrama'], true) ) {
          $correcta = $norm($p['respuesta_texto_correcta'] ?? '');
          $user     = $norm($ans);
          if ($correcta === '' || $user === '' || $user !== $correcta) { $all_ok = false; break; }
        } else {
          // multiple, multiple_imagen, vf → comprobar índice de opciones
          $ops = $p['opciones'] ?? [];
          if (!is_numeric($ans) || !isset($ops[(int)$ans])) { $all_ok = false; break; }
          $is_ok = !empty($ops[(int)$ans]['es_correcta']);
          if (!$is_ok) { $all_ok = false; break; }
        }
      }

      $attempts_table = $wpdb->prefix . 'gincana_attempts';

      $estacion_id_from_prueba    = (int) get_post_meta($prueba_id, 'gc_estacion_ref', true);
      $escenario_id_from_estacion = $estacion_id_from_prueba ? (int) get_post_meta($estacion_id_from_prueba, 'gc_escenario_ref', true) : 0;

      // Validar estado server-side: tiempo agotado o intentos agotados → fail forzado
      $current_uid = (int) get_current_user_id();
      $state_pre = gc_quiz_user_state($current_uid, $prueba_id, $estacion_id_from_prueba);
      if ($state_pre['blocked']) {
        return new WP_REST_Response([
          'ok'             => false,
          'blocked'        => true,
          'blocked_reason' => $state_pre['blocked_reason'], // 'time' | 'attempts'
          'state'          => $state_pre,
        ], 200);
      }

      $wpdb->insert($attempts_table, [
        'user_id'      => $current_uid,
        'prueba_id'    => (int) $prueba_id,
        'escenario_id' => (int) $escenario_id_from_estacion,
        'estacion_id'  => (int) $estacion_id_from_prueba,
        'result'       => $all_ok ? 'success' : 'fail',
        'time_ms'      => max(0, (int)$time_ms),
        'payload_json' => wp_json_encode(['answers'=>$answers]),
        'ip_hash'      => null,
        'ua_hash'      => null,
      ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s']);

      // Estado tras este intento (para que el front actualice contadores)
      $state_post = gc_quiz_user_state($current_uid, $prueba_id, $estacion_id_from_prueba);
      return new WP_REST_Response([
        'ok'    => $all_ok,
        'state' => $state_post,
      ], 200);
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

  // =========================================================
  // POST /wp-json/gincana/v1/photo/upload
  // Sube la foto final del jugador para un escenario
  // =========================================================
  register_rest_route('gincana/v1','/photo/upload',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      $user_id      = get_current_user_id();
      $escenario_id = (int) $req->get_param('escenario_id');

      if (!$escenario_id) {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_escenario_id'], 400);
      }

      // Verificar que el escenario requiere foto
      $accion = get_post_meta($escenario_id, 'gc_accion_final', true);
      if ($accion !== 'subir_foto') {
        return new WP_REST_Response(['ok'=>false,'error'=>'foto_not_required'], 400);
      }

      // Verificar que ya completó todas las estaciones
      global $wpdb;
      $progress_table = $wpdb->prefix . 'gincana_user_progress';

      $total_est = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'gc_escenario_ref' AND pm.meta_value = %d
         WHERE p.post_type = 'estacion' AND p.post_status = 'publish'",
        $escenario_id
      ));

      $completed = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $progress_table WHERE user_id = %d AND escenario_id = %d AND status = 'passed'",
        $user_id, $escenario_id
      ));

      if ($completed < $total_est) {
        return new WP_REST_Response(['ok'=>false,'error'=>'not_all_completed','completed'=>$completed,'total'=>$total_est], 400);
      }

      // Verificar que no haya subido foto ya
      if (function_exists('gc_user_has_final_photo') && gc_user_has_final_photo($user_id, $escenario_id)) {
        return new WP_REST_Response(['ok'=>true,'already_uploaded'=>true], 200);
      }

      // Procesar la foto
      $files = $req->get_file_params();
      if (empty($files['photo'])) {
        return new WP_REST_Response(['ok'=>false,'error'=>'no_file'], 400);
      }

      $file = $files['photo'];

      // Validar tipo
      $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
      if (!in_array($file['type'], $allowed, true)) {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_type','allowed'=>$allowed], 400);
      }

      // Validar tamaño (max 10MB)
      if ($file['size'] > 10 * 1024 * 1024) {
        return new WP_REST_Response(['ok'=>false,'error'=>'file_too_large','max_mb'=>10], 400);
      }

      require_once ABSPATH . 'wp-admin/includes/image.php';
      require_once ABSPATH . 'wp-admin/includes/file.php';
      require_once ABSPATH . 'wp-admin/includes/media.php';

      // Subir a media library
      $user = get_user_by('id', $user_id);
      $esc_title = get_the_title($escenario_id);
      $display_name = $user ? $user->display_name : 'Usuario ' . $user_id;

      $_FILES['photo'] = $file;
      $attachment_id = media_handle_upload('photo', 0, [
        'post_title' => 'Foto final — ' . $display_name . ' — ' . $esc_title,
      ]);

      if (is_wp_error($attachment_id)) {
        return new WP_REST_Response(['ok'=>false,'error'=>'upload_failed','message'=>$attachment_id->get_error_message()], 500);
      }

      // Marcar con meta para identificar
      update_post_meta($attachment_id, '_gc_foto_final_escenario', (int)$escenario_id);
      update_post_meta($attachment_id, '_gc_foto_final_user', (int)$user_id);

      // Asignar autor
      wp_update_post(['ID' => $attachment_id, 'post_author' => $user_id]);

      $thumb_url = wp_get_attachment_image_url($attachment_id, 'medium');

      return new WP_REST_Response([
        'ok'            => true,
        'attachment_id'  => $attachment_id,
        'thumbnail_url'  => $thumb_url,
      ], 200);
    }
  ]);

});