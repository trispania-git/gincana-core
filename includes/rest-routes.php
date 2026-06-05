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

    // Tiempo e intentos máximos son OPCIONALES. 0 = sin límite.
    $max_attempts_raw = get_post_meta($prueba_id, 'gc_intentos_max', true);
    $max_attempts = ($max_attempts_raw === '' || $max_attempts_raw === null) ? 0 : (int) $max_attempts_raw;
    if ($max_attempts < 0) $max_attempts = 0;
    $time_max_s_raw = get_post_meta($prueba_id, 'gc_tiempo_max_s', true);
    $time_max_s = ($time_max_s_raw === '' || $time_max_s_raw === null) ? 0 : (int) $time_max_s_raw;
    if ($time_max_s < 0) $time_max_s = 0;

    $state_key = 'gc_quiz_state_' . $prueba_id . '_' . $estacion_id;
    $started_at = (int) get_user_meta($user_id, $state_key . '_started', true);

    $attempts_table = $wpdb->prefix . 'gincana_attempts';
    $failed = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $attempts_table WHERE user_id=%d AND prueba_id=%d AND estacion_id=%d AND result='fail'",
        $user_id, $prueba_id, $estacion_id
    ));

    // Auto-reset defensivo: si el usuario no tiene fallos registrados pero sí
    // un started_at huérfano (porque se limpió el progreso pero quedaron user_meta),
    // limpiamos el started_at y el estado del ahorcado para empezar desde cero.
    if ($started_at > 0 && $failed === 0) {
        $progress_table_chk = $wpdb->prefix . 'gincana_user_progress';
        $is_passed = $estacion_id > 0 ? $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM $progress_table_chk WHERE user_id=%d AND estacion_id=%d AND status='passed'",
            $user_id, $estacion_id
        )) : null;
        if (!$is_passed) {
            delete_user_meta($user_id, $state_key . '_started');
            delete_user_meta($user_id, 'gc_ahorcado_revealed_' . $prueba_id . '_' . $estacion_id);
            delete_user_meta($user_id, 'gc_ahorcado_miss_' . $prueba_id . '_' . $estacion_id);
            $started_at = 0;
        }
    }

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
        if ($max_attempts > 0 && $failed >= $max_attempts) { $blocked = true; $blocked_reason = 'attempts'; }
        elseif ($time_max_s > 0 && $started_at > 0 && $time_left <= 0) { $blocked = true; $blocked_reason = 'time'; }
    }

    // attempts_left: -1 si no hay límite, número >=0 si lo hay
    $attempts_left = ($max_attempts <= 0) ? -1 : max(0, $max_attempts - $failed);

    return [
        'started_at'      => $started_at,
        'failed_attempts' => $failed,
        'max_attempts'    => $max_attempts, // 0 = sin límite
        'attempts_left'   => $attempts_left,
        'time_max_s'      => $time_max_s,   // 0 = sin límite
        'time_left_s'     => $time_left,    // -1 si no aplica
        'blocked'         => $blocked,
        'blocked_reason'  => $blocked_reason,
        'passed'          => $passed,
        'state_key'       => $state_key,
    ];
}

add_action('rest_api_init', function(){

  // =========================================================
  // POST /wp-json/gincana/v1/guest/login
  // Crea un usuario 'guest' (rol gc_guest) con el nombre indicado y lo
  // loguea por cookie. Solo se permite si el escenario tiene
  // gc_permitir_guest = '1'. Devuelve el user_id creado.
  // =========================================================
  register_rest_route('gincana/v1','/guest/login',[
    'methods'  => 'POST',
    'permission_callback' => '__return_true', // no requiere estar logueado
    'callback' => function(WP_REST_Request $req){
      $nombre       = sanitize_text_field((string) $req->get_param('nombre'));
      $escenario_id = (int) $req->get_param('escenario_id');

      if ($nombre === '' || mb_strlen($nombre) < 2) {
        return new WP_REST_Response(['ok'=>false,'error'=>'nombre_invalido'], 400);
      }
      if ($escenario_id <= 0 || get_post_type($escenario_id) !== 'escenario') {
        return new WP_REST_Response(['ok'=>false,'error'=>'escenario_invalido'], 400);
      }
      if ( ! function_exists('gc_permite_guest') || ! gc_permite_guest($escenario_id) ) {
        return new WP_REST_Response(['ok'=>false,'error'=>'guest_no_permitido'], 403);
      }

      // Generar login único: gcg_<slug>_<rand>
      $slug = sanitize_title($nombre);
      if ($slug === '') $slug = 'jugador';
      $login = 'gcg_' . substr($slug, 0, 24) . '_' . substr(wp_generate_password(8, false), 0, 6);
      $tries = 0;
      while (username_exists($login) && $tries < 5) {
        $login = 'gcg_' . substr($slug, 0, 24) . '_' . substr(wp_generate_password(8, false), 0, 6);
        $tries++;
      }

      // Email autogenerado único
      $domain = parse_url(home_url('/'), PHP_URL_HOST) ?: 'gincana.local';
      $email  = $login . '@guest.' . $domain;

      $user_id = wp_insert_user([
        'user_login'    => $login,
        'user_email'    => $email,
        'user_pass'     => wp_generate_password(20, true, true),
        'display_name'  => $nombre,
        'first_name'    => $nombre,
        'nickname'      => $nombre,
        'role'          => 'gc_guest',
      ]);
      if ( is_wp_error($user_id) ) {
        return new WP_REST_Response(['ok'=>false,'error'=>'wp_insert_failed','detail'=>$user_id->get_error_message()], 500);
      }

      // Marcar como guest y guardar escenario de origen para limpieza posterior
      update_user_meta($user_id, 'gc_guest', '1');
      update_user_meta($user_id, 'gc_guest_origen_escenario', $escenario_id);
      update_user_meta($user_id, 'gc_guest_creado', time());

      // Loguear con cookie persistente
      wp_clear_auth_cookie();
      wp_set_current_user($user_id);
      wp_set_auth_cookie($user_id, true);

      return new WP_REST_Response([
        'ok'           => true,
        'user_id'      => (int) $user_id,
        'display_name' => $nombre,
      ], 200);
    }
  ]);

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

      // Si el escenario tiene la gamificación desactivada, no calcular ni añadir
      // puntos. Solo se registra la estación como passed (sin entradas en el
      // log de puntos y sin valor que devolver al frontend).
      $gamificacion = function_exists('gc_show_points') ? gc_show_points($escenario_id) : true;

      $points_to_add = ($gamificacion && function_exists('gincana_points_calculate'))
        ? gincana_points_calculate($user_id, $escenario_id, $estacion_id, $time_ms, ! $had_fail)
        : 0;

      if ($gamificacion) {
        if (!function_exists('gincana_points_add')) {
          return new WP_REST_Response(['ok'=>false,'error'=>'points_add_missing'], 500);
        }
        gincana_points_add($user_id, $escenario_id, $points_to_add, 'passed', $estacion_id, [
          'time_ms'   => $time_ms,
          'first_try' => ! $had_fail
        ]);
      }

      // Limpiar user_meta de estado de quiz/ahorcado/sopa de esta prueba+estación
      if ($prueba_id) {
        delete_user_meta($user_id, 'gc_quiz_state_' . $prueba_id . '_' . $estacion_id . '_started');
        delete_user_meta($user_id, 'gc_ahorcado_revealed_' . $prueba_id . '_' . $estacion_id);
        delete_user_meta($user_id, 'gc_ahorcado_miss_' . $prueba_id . '_' . $estacion_id);
        if (function_exists('gc_sopa_limpiar')) {
          gc_sopa_limpiar($user_id, $prueba_id, $estacion_id);
        }
      }

      return new WP_REST_Response([
        'ok'             => true,
        'already_passed' => false,
        'points_awarded' => (int) $points_to_add,
        'first_try'      => ! $had_fail
      ], 200);
    }
  ]);

  // =========================================================
  // POST /wp-json/gincana/v1/quiz/ahorcado/letra
  // Procesa una letra pulsada en el tipo 'ahorcado'.
  // Devuelve si está en la palabra, las letras reveladas/erróneas
  // y el estado del intento (intentos restantes, blocked, etc.).
  // =========================================================
  register_rest_route('gincana/v1','/quiz/ahorcado/letra',[
    'methods'  => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback' => function(WP_REST_Request $req){
      $user_id     = get_current_user_id();
      $prueba_id   = (int) $req->get_param('prueba_id');
      $estacion_id = (int) $req->get_param('estacion_id');
      $q_index     = (int) $req->get_param('q_index');
      $letra       = strtoupper((string) $req->get_param('letra'));
      $letra       = remove_accents($letra);

      if (!$user_id || !$prueba_id || !$estacion_id || $letra === '') {
        return new WP_REST_Response(['ok'=>false,'error'=>'missing_params'], 400);
      }

      // Estado: ¿ya bloqueado?
      $state_pre = gc_quiz_user_state($user_id, $prueba_id, $estacion_id);
      if ($state_pre['blocked']) {
        return new WP_REST_Response(['ok'=>false,'blocked'=>true,'blocked_reason'=>$state_pre['blocked_reason'],'state'=>$state_pre], 200);
      }

      // Cargar la palabra
      $pregs = get_post_meta($prueba_id, 'gc_preguntas', true);
      if (!is_array($pregs) || !isset($pregs[$q_index])) {
        return new WP_REST_Response(['ok'=>false,'error'=>'invalid_q_index'], 400);
      }
      $pregunta = $pregs[$q_index];
      $palabra  = mb_strtoupper((string) ($pregunta['respuesta_texto_correcta'] ?? ''));
      $palabra_norm = remove_accents($palabra);
      if ($palabra === '') return new WP_REST_Response(['ok'=>false,'error'=>'no_word'], 400);

      $reveal_key = 'gc_ahorcado_revealed_' . $prueba_id . '_' . $estacion_id;
      $miss_key   = 'gc_ahorcado_miss_'     . $prueba_id . '_' . $estacion_id;
      $revealed = (array) get_user_meta($user_id, $reveal_key, true);
      $missed   = (array) get_user_meta($user_id, $miss_key, true);

      // En ahorcado_light, las letras del patrón también cuentan como reveladas
      // (no las guardamos en user_meta — se recalculan siempre desde el patrón).
      $tipo_preg     = isset($pregunta['tipo']) ? (string) $pregunta['tipo'] : 'ahorcado';
      $pista_pattern = ($tipo_preg === 'ahorcado_light' && isset($pregunta['pista_pattern']))
                        ? (string) $pregunta['pista_pattern'] : '';
      $pattern_letters = [];
      if ($pista_pattern !== '') {
        $plen = mb_strlen($pista_pattern);
        for ($pi = 0; $pi < $plen; $pi++) {
          $pch = mb_substr($pista_pattern, $pi, 1);
          if ($pch === '_') continue;
          if (preg_match('/\p{L}/u', $pch)) {
            $pattern_letters[] = mb_strtoupper(remove_accents($pch));
          }
        }
        $pattern_letters = array_values(array_unique($pattern_letters));
      }
      // Para chequear duplicados y completitud unimos letras del usuario + del patrón.
      $revealed_effective = array_values(array_unique(array_merge((array) $revealed, $pattern_letters)));

      // ¿La letra ya se había usado?
      if (in_array($letra, $revealed_effective, true) || in_array($letra, $missed, true)) {
        return new WP_REST_Response(['ok'=>false,'error'=>'letter_already_used','state'=>$state_pre], 200);
      }

      // ¿Está la letra en la palabra (comparación sin tildes)?
      $en_palabra = mb_strpos($palabra_norm, $letra) !== false;

      $attempts_table = $GLOBALS['wpdb']->prefix . 'gincana_attempts';
      $escenario_id   = (int) get_post_meta($estacion_id, 'gc_escenario_ref', true);

      if ($en_palabra) {
        $revealed[] = $letra;
        update_user_meta($user_id, $reveal_key, array_values(array_unique($revealed)));
      } else {
        $missed[] = $letra;
        update_user_meta($user_id, $miss_key, array_values(array_unique($missed)));
        // Registrar fail en attempts (cuenta para max_attempts)
        $GLOBALS['wpdb']->insert($attempts_table, [
          'user_id'      => $user_id,
          'prueba_id'    => $prueba_id,
          'escenario_id' => $escenario_id,
          'estacion_id'  => $estacion_id,
          'result'       => 'fail',
          'time_ms'      => 0,
          'payload_json' => wp_json_encode(['letra'=>$letra,'modo'=>'ahorcado']),
          'ip_hash'      => null,
          'ua_hash'      => null,
        ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s']);
      }

      // ¿Palabra completa descubierta? (considera también las letras del patrón
      // en ahorcado_light, no solo las que el usuario ha tecleado).
      $all_letters = [];
      $len = mb_strlen($palabra_norm);
      for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($palabra_norm, $i, 1);
        if (preg_match('/\p{L}/u', $ch)) $all_letters[$ch] = true;
      }
      $revealed_after = array_values(array_unique(array_merge((array) $revealed, $pattern_letters)));
      $palabra_completa = true;
      foreach (array_keys($all_letters) as $L) {
        if (!in_array($L, $revealed_after, true)) { $palabra_completa = false; break; }
      }

      $state_post = gc_quiz_user_state($user_id, $prueba_id, $estacion_id);

      return new WP_REST_Response([
        'ok'              => true,
        'en_palabra'      => $en_palabra,
        'letra'           => $letra,
        'revealed'        => array_values(array_unique($revealed)),
        'missed'          => array_values(array_unique($missed)),
        'palabra_completa'=> $palabra_completa,
        'state'           => $state_post,
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
      $q_mode    = sanitize_text_field((string) $req->get_param('q_mode')); // hint del front

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
        $s = trim((string) $s);
        // Importante: remove_accents PRIMERO (mientras los acentos siguen
        // visibles) y luego mb_strtolower (UTF-8 aware). strtolower() de
        // PHP es single-byte y NO baja a minúscula caracteres como É/Ñ,
        // por lo que la comparación "CAFÉ" vs "café" fallaba.
        if (function_exists('remove_accents')) {
          $s = remove_accents($s);
        }
        if (function_exists('mb_strtolower')) {
          $s = mb_strtolower($s, 'UTF-8');
        } else {
          $s = strtolower($s);
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

      // === SHORT-CIRCUIT: prueba de tipo "Lista libre" ===
      // Skip si el desplegable principal de la prueba es lista_libre, o si el
      // frontend manda explícitamente q_mode='lista_libre' (hint del data-mode
      // del formulario). Esto cubre cualquier desfase en el meta.
      $skip_validation = ($tipo_global === 'lista_libre' || $q_mode === 'lista_libre');

      foreach ($pregs_to_check as $i => $p) {
        if ($skip_validation) { continue; }

        $tipo = !empty($p['tipo']) ? $p['tipo'] : $tipo_global;
        $ans  = array_key_exists($i, $answers_to_check) ? $answers_to_check[$i] : null;

        // === Lista libre (per-pregunta) ===
        // Si el tipo guardado es 'lista_libre' o, como salvaguarda, la
        // respuesta tiene forma de array PLANO de strings (lista_libre del
        // front), pasamos sin validar. Excluimos arrays anidados (que sí los
        // usa sopa_letras: [[r,c],…]).
        $looks_like_lista = false;
        if (is_string($ans) && strlen($ans) > 0 && $ans[0] === '[') {
          $maybe = json_decode($ans, true);
          if (is_array($maybe)) {
            // Plano = ningún elemento es a su vez array.
            $flat = true;
            foreach ($maybe as $el) { if (is_array($el)) { $flat = false; break; } }
            if ($flat) $looks_like_lista = true;
          }
        }
        if ($tipo === 'lista_libre' || $looks_like_lista) {
          // Las respuestas se guardan en el log de intentos vía payload general.
          continue;
        }

        // Tipos de respuesta libre (string normalizado)
        if ( in_array($tipo, ['texto', 'cifrado_cesar', 'anagrama', 'ahorcado', 'ahorcado_light', 'jeroglifico'], true) ) {
          $correcta = $norm($p['respuesta_texto_correcta'] ?? '');
          $user     = $norm($ans);
          if ($correcta === '' || $user === '' || $user !== $correcta) { $all_ok = false; break; }
        } elseif ($tipo === 'sopa_letras') {
          // Validar selección [[r,c],...] contra word_path persistido
          $seleccion = is_string($ans) ? json_decode($ans, true) : (is_array($ans) ? $ans : null);
          if (!is_array($seleccion) || empty($seleccion)) { $all_ok = false; break; }
          $uid_sopa = (int) get_current_user_id();
          $eid_sopa = (int) get_post_meta($prueba_id, 'gc_estacion_ref', true);
          if (function_exists('gc_sopa_get_or_create')) {
            $cols = isset($p['cols']) ? (int) $p['cols'] : (isset($p['tamano_grid']) ? (int) $p['tamano_grid'] : 10);
            $rows = isset($p['rows']) ? (int) $p['rows'] : (isset($p['tamano_grid']) ? (int) $p['tamano_grid'] : 7);
            // Índice real de la pregunta: si vino en el request (modo pool o normal con una sola pregunta visible),
            // ese es el índice del grid; si no, el de la iteración (foreach preserva claves del array original).
            $qi_for_sopa = ($q_index !== null && is_numeric($q_index)) ? (int) $q_index : (int) $i;
            $sopa_state = gc_sopa_get_or_create($uid_sopa, $prueba_id, $eid_sopa, $p['respuesta_texto_correcta'] ?? '', $cols, $rows, $qi_for_sopa);
            if (!$sopa_state || empty($sopa_state['word_path'])) { $all_ok = false; break; }
            if (!gc_sopa_es_correcta($seleccion, $sopa_state['word_path'])) { $all_ok = false; break; }
          } else {
            $all_ok = false; break;
          }
        } else {
          // multiple, multiple_imagen, vf → comprobar índice de opciones.
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