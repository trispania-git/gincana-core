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
 * === Puntuación configurable por prueba ===
 *
 * Cada prueba define:
 *  - gc_puntos_acierto_max     (def. 10) puntos por acertar a la 1ª.
 *  - gc_puntos_intento         (opcional) lista "10,5,1" de puntos por intento.
 *  - gc_puntos_tiempo_max      (def. 10) puntos máximos por rapidez.
 *  - gc_puntos_tiempo_rangos   (def. 6)  nº de rangos en que se reparte el tiempo.
 *
 * Total de la prueba = puntos por acierto (según el intento) + puntos por tiempo.
 */

/**
 * Reparte el tiempo máximo en N rangos con puntos decrecientes.
 * Devuelve bandas de la MÁS RÁPIDA a la MÁS LENTA:
 *   ['pts', 'elapsed_from', 'elapsed_to', 'rem_from', 'rem_to'] (segundos)
 * La banda más lenta dentro de tiempo siempre da >=1; agotar el tiempo = 0.
 */
if ( ! function_exists('gc_calc_rangos_tiempo') ) {
  function gc_calc_rangos_tiempo($tiempo_max_s, $rangos, $puntos_max) {
    $tiempo_max_s = max(1, (int) $tiempo_max_s);
    $rangos       = max(1, min(20, (int) $rangos));
    $puntos_max   = max(0, (int) $puntos_max);

    $bands = [];
    for ($k = 0; $k < $rangos; $k++) {
      if ($rangos === 1) {
        $pts = $puntos_max;
      } else {
        $pts = (int) round($puntos_max * ($rangos - 1 - $k) / ($rangos - 1));
        // La banda más lenta dentro de tiempo siempre suma al menos 1.
        if ($k === $rangos - 1 && $pts === 0 && $puntos_max > 0) $pts = 1;
      }
      $from_elapsed = (int) floor($k * $tiempo_max_s / $rangos);
      $to_elapsed   = ($k === $rangos - 1)
        ? ($tiempo_max_s - 1)
        : ((int) floor(($k + 1) * $tiempo_max_s / $rangos) - 1);
      $bands[] = [
        'pts'          => $pts,
        'elapsed_from' => $from_elapsed,
        'elapsed_to'   => $to_elapsed,
        'rem_from'     => $tiempo_max_s - $from_elapsed,
        'rem_to'       => $tiempo_max_s - $to_elapsed,
      ];
    }
    return $bands;
  }
}

/**
 * Puntos por tiempo según los ms empleados en responder.
 */
if ( ! function_exists('gc_puntos_tiempo_por_ms') ) {
  function gc_puntos_tiempo_por_ms($prueba_id, $time_ms) {
    $prueba_id    = (int) $prueba_id;
    $tiempo_max_s = (int) get_post_meta($prueba_id, 'gc_tiempo_max_s', true);
    if ($tiempo_max_s <= 0) return 0; // sin cronómetro → no hay puntos por tiempo

    $pmax = get_post_meta($prueba_id, 'gc_puntos_tiempo_max', true);
    $pmax = ($pmax === '' || $pmax === null) ? 10 : (int) $pmax;
    if ($pmax <= 0) return 0;

    $rangos = (int) get_post_meta($prueba_id, 'gc_puntos_tiempo_rangos', true);
    if ($rangos <= 0) $rangos = 6;

    $elapsed_s = (int) floor(max(0, (int) $time_ms) / 1000);
    if ($elapsed_s >= $tiempo_max_s) return 0; // se agotó el tiempo

    foreach (gc_calc_rangos_tiempo($tiempo_max_s, $rangos, $pmax) as $b) {
      if ($elapsed_s >= $b['elapsed_from'] && $elapsed_s <= $b['elapsed_to']) {
        return (int) $b['pts'];
      }
    }
    return 0;
  }
}

/**
 * Devuelve la lista de puntos por intento (array indexado desde 0 = 1er intento).
 * Si no hay lista explícita, la genera de forma decreciente desde el máximo.
 */
if ( ! function_exists('gc_puntos_intento_lista') ) {
  function gc_puntos_intento_lista($prueba_id) {
    $prueba_id = (int) $prueba_id;
    $max = get_post_meta($prueba_id, 'gc_puntos_acierto_max', true);
    $max = ($max === '' || $max === null) ? 10 : (int) $max;

    $raw = (string) get_post_meta($prueba_id, 'gc_puntos_intento', true);
    if (trim($raw) !== '') {
      $arr = array_map(function($v){ return max(0, (int) trim($v)); }, explode(',', $raw));
      $arr = array_values(array_filter($arr, function($v){ return $v !== null; }));
      if (!empty($arr)) return $arr;
    }

    // Auto: 1er intento = max; decreciente lineal a 0 según nº de intentos.
    $intentos = (int) get_post_meta($prueba_id, 'gc_intentos_max', true);
    if ($intentos <= 1) return [$max]; // sin límite o 1 intento → solo 1er valor
    $lista = [];
    for ($i = 1; $i <= $intentos; $i++) {
      $lista[] = max(0, (int) round($max * ($intentos - $i) / ($intentos - 1)));
    }
    // El último (si quedó 0) lo dejamos en 0: acierto in extremis vale 0 por acierto
    // pero aún puede sumar por tiempo. Para que el 1er valor sea el máximo:
    $lista[0] = $max;
    return $lista;
  }
}

/**
 * Puntos por acierto según en qué intento (1-based) se acertó.
 */
if ( ! function_exists('gc_puntos_acierto_por_intento') ) {
  function gc_puntos_acierto_por_intento($prueba_id, $attempt_no) {
    $attempt_no = max(1, (int) $attempt_no);
    $lista = gc_puntos_intento_lista($prueba_id);
    if (empty($lista)) return 0;
    if (isset($lista[$attempt_no - 1])) return max(0, (int) $lista[$attempt_no - 1]);
    return max(0, (int) end($lista)); // más allá del último configurado → último valor
  }
}

/**
 * Puntos totales de una prueba: por acierto (según intento) + por tiempo.
 */
if ( ! function_exists('gincana_points_calculate') ) {
  function gincana_points_calculate($user_id, $escenario_id, $estacion_id, $time_ms, $attempt_no = 1, $prueba_id = 0) {
    $prueba_id = (int) $prueba_id;
    if (!$prueba_id) $prueba_id = (int) get_post_meta($estacion_id, 'gc_prueba_ref', true);

    $bonus_try  = $prueba_id ? gc_puntos_acierto_por_intento($prueba_id, $attempt_no) : ((int)$attempt_no === 1 ? 10 : 0);
    $points_time = $prueba_id ? gc_puntos_tiempo_por_ms($prueba_id, $time_ms) : 0;
    $total = max(0, $bonus_try + $points_time);

    return (int) apply_filters('gincana_points_total', $total, [
      'user_id'      => $user_id,
      'escenario_id' => $escenario_id,
      'estacion_id'  => $estacion_id,
      'prueba_id'    => $prueba_id,
      'time_ms'      => max(0, (int) $time_ms),
      'attempt_no'   => (int) $attempt_no,
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

/**
 * Marca una estación como superada y otorga puntos fijos. Reutilizable por
 * mecánicas que validan sin quiz (p. ej. "acción externa por QR").
 * Idempotente: si ya estaba superada, no vuelve a sumar.
 *
 * @return bool true si se acaba de completar; false si ya estaba superada.
 */
if ( ! function_exists('gc_complete_station') ) {
  function gc_complete_station($user_id, $escenario_id, $estacion_id, $points, $gamif = true, $meta = []) {
    global $wpdb;
    $user_id      = (int) $user_id;
    $escenario_id = (int) $escenario_id;
    $estacion_id  = (int) $estacion_id;
    $points       = max(0, (int) $points);
    if (!$user_id || !$escenario_id || !$estacion_id) return false;

    $pt = $wpdb->prefix . 'gincana_user_progress';
    $status = $wpdb->get_var( $wpdb->prepare(
      "SELECT status FROM $pt WHERE user_id=%d AND escenario_id=%d AND estacion_id=%d",
      $user_id, $escenario_id, $estacion_id
    ));
    if ($status === 'passed') return false; // ya superada → no duplicar

    $wpdb->query( $wpdb->prepare("
      INSERT INTO $pt (user_id, escenario_id, estacion_id, status, attempts, best_time_ms)
      VALUES (%d,%d,%d,'passed',1,0)
      ON DUPLICATE KEY UPDATE status='passed'
    ", $user_id, $escenario_id, $estacion_id ) );

    if ($gamif && function_exists('gincana_points_add')) {
      gincana_points_add($user_id, $escenario_id, $points, 'passed', $estacion_id, $meta);
    }
    return true;
  }
}

/**
 * Si la estación tiene una prueba de tipo "acción externa por QR", devuelve
 * el ID de esa prueba; si no, 0.
 */
if ( ! function_exists('gc_station_accion_prueba_id') ) {
  function gc_station_accion_prueba_id($station_id) {
    $station_id = (int) $station_id;
    if (!$station_id) return 0;
    // Compat: ref legacy en la estación
    $pid = (int) get_post_meta($station_id, 'gc_prueba_ref', true);
    if ($pid && get_post_meta($pid, 'gc_tipo', true) === 'accion_qr') return $pid;
    // Fuente de verdad: prueba con gc_estacion_ref = estación y tipo accion_qr
    $q = get_posts([
      'post_type'      => 'prueba',
      'post_status'    => 'publish',
      'posts_per_page' => 1,
      'meta_query'     => [
        'relation' => 'AND',
        ['key' => 'gc_estacion_ref', 'value' => $station_id, 'compare' => '='],
        ['key' => 'gc_tipo', 'value' => 'accion_qr', 'compare' => '='],
      ],
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    return !empty($q) ? (int) $q[0] : 0;
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

/**
 * ¿Debe mostrarse la puntuación en este escenario?
 */
if ( ! function_exists('gc_show_points') ) {
  function gc_show_points($escenario_id) {
    $val = get_post_meta((int)$escenario_id, 'gc_mostrar_puntos', true);
    return ($val === '' || $val === '1'); // default: sí
  }
}

/**
 * Devuelve el tipo de QR de un escenario: 'enlace', 'validacion_boton' o 'validacion_quiz'.
 * Compatibilidad: el antiguo 'validacion' se trata como 'validacion_boton'.
 */
if ( ! function_exists('gc_get_tipo_qr') ) {
  function gc_get_tipo_qr($escenario_id) {
    $val = get_post_meta((int)$escenario_id, 'gc_tipo_qr', true);
    if ($val === 'validacion') $val = 'validacion_boton'; // migración automática
    return in_array($val, ['enlace', 'validacion_boton', 'validacion_boton_quiz', 'validacion_quiz', 'validacion_gps', 'solo_pregunta'], true) ? $val : 'enlace';
  }
}

/**
 * Devuelve la URL QR de una estación según el tipo de QR del escenario.
 * - 'enlace': permalink de la estación
 * - 'validacion_boton' / 'validacion_quiz': URL con token para validar
 */
if ( ! function_exists('gc_get_qr_url') ) {
  function gc_get_qr_url($station_id, $escenario_id = 0) {
    if (!$escenario_id) {
      $escenario_id = (int) get_post_meta((int)$station_id, 'gc_escenario_ref', true);
    }
    $tipo_qr = gc_get_tipo_qr($escenario_id);
    if ($tipo_qr === 'enlace' || $tipo_qr === 'solo_pregunta') {
      // Sin QR físico: permalink directo (solo_pregunta no genera QR real)
      return get_permalink((int)$station_id);
    }
    // validacion_boton, validacion_quiz o validacion_gps: URL con token
    return function_exists('gc_get_station_entry_url') ? gc_get_station_entry_url((int)$station_id) : get_permalink((int)$station_id);
  }
}

/**
 * ¿El escenario es de tipo "solo pregunta" (sin QR físico)?
 */
if ( ! function_exists('gc_es_solo_pregunta') ) {
  function gc_es_solo_pregunta($escenario_id) {
    return gc_get_tipo_qr($escenario_id) === 'solo_pregunta';
  }
}

/**
 * ¿El escenario permite jugar sin registro (modo invitado)?
 */
if ( ! function_exists('gc_permite_guest') ) {
  function gc_permite_guest($escenario_id) {
    return get_post_meta((int)$escenario_id, 'gc_permitir_guest', true) === '1';
  }
}

/**
 * ¿El escenario tiene activada la moraleja por estación?
 * Si es true, el campo 'Moraleja' aparece en cada estación y se muestra
 * al jugador tras superar la prueba.
 */
if ( ! function_exists('gc_moraleja_activa') ) {
  function gc_moraleja_activa($escenario_id) {
    return get_post_meta((int)$escenario_id, 'gc_moraleja_activa', true) === '1';
  }
}

/**
 * ¿El escenario se juega en ORDEN LIBRE? El jugador elige en qué orden
 * hacer las estaciones, todas están visibles desde el inicio.
 * El "orden" configurado para cada estación se sigue mostrando como número.
 */
if ( ! function_exists('gc_orden_libre') ) {
  function gc_orden_libre($escenario_id) {
    if (get_post_meta((int)$escenario_id, 'gc_orden_libre', true) === '1') return true;
    // Compatibilidad con la opción anterior (v1.0.37)
    return get_post_meta((int)$escenario_id, 'gc_orden_aleatorio', true) === '1';
  }
}

/**
 * ¿El escenario se juega en ORDEN ALEATORIO SECRETO? Cada jugador recibe
 * su propio orden (sortado al iniciar). Va secuencial pero solo se ve el
 * nombre de la estación actual (las siguientes salen como '¿?').
 * Mutuamente excluyente con 'orden libre' (si ambas están, prevalece esta).
 */
if ( ! function_exists('gc_orden_secreto') ) {
  function gc_orden_secreto($escenario_id) {
    return get_post_meta((int)$escenario_id, 'gc_orden_secreto', true) === '1';
  }
}

/**
 * ¿El escenario obliga a seguir el orden de las estaciones? Si es true, un QR
 * de una estación que no toca se bloquea con aviso. No aplica en orden libre.
 */
if ( ! function_exists('gc_orden_estricto') ) {
  function gc_orden_estricto($escenario_id) {
    if (function_exists('gc_orden_libre') && gc_orden_libre($escenario_id)) return false;
    return get_post_meta((int)$escenario_id, 'gc_orden_estricto', true) === '1';
  }
}

/**
 * Compatibilidad: alias al helper antiguo (v1.0.37). Se mantiene para que
 * el código que ya leía 'orden aleatorio' siga compilando, pero conceptualmente
 * equivale ahora a 'orden libre'.
 */
if ( ! function_exists('gc_orden_aleatorio') ) {
  function gc_orden_aleatorio($escenario_id) {
    return gc_orden_libre($escenario_id);
  }
}

/**
 * ¿El escenario obliga a empezar por la portada?
 * Si es true, un QR escaneado por un usuario que aún no se ha registrado
 * NO ofrece el alta inline en la estación: muestra un aviso y un botón
 * que lleva a la portada del escenario para que se registre allí.
 */
if ( ! function_exists('gc_forzar_portada') ) {
  function gc_forzar_portada($escenario_id) {
    return get_post_meta((int)$escenario_id, 'gc_forzar_portada', true) === '1';
  }
}

/**
 * Devuelve el orden personal de las estaciones para un usuario+escenario.
 *
 * Persistencia:
 *  - Si el usuario está logueado: user_meta gc_random_order_<esc_id>.
 *  - Si NO está logueado (guest sin login): cookie gc_rndord_<esc_id>
 *    de 1 año.
 *
 * Si no existía, se genera con shuffle y se guarda. Si la lista de
 * estaciones cambia (se añaden/quitan), se actualiza preservando el orden
 * de las que ya estaban.
 */
if ( ! function_exists('gc_get_user_random_order') ) {
  function gc_get_user_random_order($user_id, $escenario_id, $est_ids) {
    $user_id      = (int) $user_id;
    $escenario_id = (int) $escenario_id;
    $est_ids      = array_values(array_map('intval', (array) $est_ids));
    if (empty($est_ids)) return $est_ids;

    $meta_key   = 'gc_random_order_' . $escenario_id;
    $cookie_key = 'gc_rndord_' . $escenario_id;

    // Reset por URL: ?gc_reset_random_order=1 borra y regenera
    if (isset($_GET['gc_reset_random_order']) && $_GET['gc_reset_random_order'] === '1') {
      if ($user_id > 0) {
        delete_user_meta($user_id, $meta_key);
      }
      if (isset($_COOKIE[$cookie_key])) {
        setcookie($cookie_key, '', time() - 3600, defined('COOKIEPATH') ? COOKIEPATH : '/', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '', is_ssl(), false);
        unset($_COOKIE[$cookie_key]);
      }
    }

    // Cargar orden previo (meta si logueado, cookie si no)
    $saved = null;
    if ($user_id > 0) {
      $stored = get_user_meta($user_id, $meta_key, true);
      if (is_array($stored)) $saved = $stored;
    } elseif (isset($_COOKIE[$cookie_key])) {
      $decoded = json_decode(stripslashes($_COOKIE[$cookie_key]), true);
      if (is_array($decoded)) $saved = $decoded;
    }

    if (is_array($saved) && !empty($saved)) {
      $saved  = array_values(array_map('intval', $saved));
      $faltan = array_values(array_diff($est_ids, $saved));
      $sobran = array_values(array_diff($saved, $est_ids));
      if (!empty($faltan) || !empty($sobran)) {
        $saved = array_values(array_diff($saved, $sobran));
        if (!empty($faltan)) shuffle($faltan);
        $saved = array_merge($saved, $faltan);
        gc_persist_user_random_order($user_id, $meta_key, $cookie_key, $saved);
      }
      return $saved;
    }

    // Generar nuevo orden aleatorio y persistir
    $order = $est_ids;
    if (count($order) > 1) shuffle($order);
    gc_persist_user_random_order($user_id, $meta_key, $cookie_key, $order);
    return $order;
  }
}

if ( ! function_exists('gc_persist_user_random_order') ) {
  function gc_persist_user_random_order($user_id, $meta_key, $cookie_key, $order) {
    if ($user_id > 0) {
      update_user_meta($user_id, $meta_key, $order);
      return;
    }
    $json = wp_json_encode($order);
    if ( ! headers_sent() ) {
      setcookie(
        $cookie_key, $json,
        time() + YEAR_IN_SECONDS,
        defined('COOKIEPATH') ? COOKIEPATH : '/',
        defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
        is_ssl(), false
      );
    }
    // Disponible en la misma request
    $_COOKIE[$cookie_key] = $json;
  }
}

/**
 * ¿El usuario actual es un jugador invitado (gc_guest)?
 */
if ( ! function_exists('gc_user_es_guest') ) {
  function gc_user_es_guest($user_id = 0) {
    $u = $user_id ? get_user_by('id', (int)$user_id) : wp_get_current_user();
    if (!$u || !$u->ID) return false;
    return in_array('gc_guest', (array) $u->roles, true);
  }
}

/**
 * Renderiza el formulario inline de acceso del jugador.
 * Si el escenario permite jugar como invitado, muestra:
 *   - input "¿Cómo te llamas?"
 *   - botón "Empezar" → llama a /guest/login y recarga la página
 *   - separador "o" + botones tradicionales "Iniciar sesión / Registrarse"
 * Si NO permite invitado, solo muestra los botones tradicionales (igual que antes).
 */
if ( ! function_exists('gc_render_login_o_guest') ) {
  function gc_render_login_o_guest($escenario_id, $titulo_pre = '¿Quieres participar en la gimkana?', $subtitulo = 'Escribe tu nombre y empieza a jugar.') {
    $permite_guest = function_exists('gc_permite_guest') && gc_permite_guest($escenario_id);
    $current_url = (is_ssl() ? 'https' : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
    $login_url    = wp_login_url($current_url);
    $register_url = wp_registration_url();
    $nonce        = wp_create_nonce('wp_rest');
    ob_start();
    ?>
    <div class="gc-login-or-guest" style="padding:24px 20px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;text-align:center;margin-top:8px;">
        <p style="margin:0 0 6px;font-size:17px;font-weight:700;color:#1e293b;"><?php echo esc_html($titulo_pre); ?></p>
        <?php if ($permite_guest): ?>
            <p style="margin:0 0 16px;font-size:14px;color:#64748b;"><?php echo esc_html($subtitulo); ?></p>
            <form class="gc-guest-form" style="display:flex;flex-direction:column;gap:10px;max-width:340px;margin:0 auto;" data-escenario-id="<?php echo (int) $escenario_id; ?>">
                <input type="text" name="nombre" placeholder="Tu nombre" autocomplete="given-name" required minlength="2" maxlength="40"
                       style="padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:16px;text-align:center;" />
                <button type="submit" style="padding:14px 24px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-size:16px;font-weight:700;cursor:pointer;">
                    ¡Empezar! 🚀
                </button>
                <div class="gc-guest-msg" style="font-size:13px;color:#dc2626;min-height:18px;"></div>
            </form>
            <div style="display:flex;align-items:center;gap:10px;margin:18px 0 12px;max-width:340px;margin-left:auto;margin-right:auto;color:#94a3b8;font-size:12px;text-transform:uppercase;letter-spacing:1px;">
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
                <span>o</span>
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
            </div>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;font-size:13px;">
                <a href="<?php echo esc_url($login_url); ?>" style="color:#2563eb;text-decoration:none;font-weight:600;">Iniciar sesión</a>
                <span style="color:#cbd5e1;">·</span>
                <a href="<?php echo esc_url($register_url); ?>" style="color:#2563eb;text-decoration:none;font-weight:600;">Registrarse</a>
            </div>
            <script>
            (function(){
                var forms = document.querySelectorAll('.gc-guest-form');
                forms.forEach(function(form){
                    if (form.dataset.gcBound) return;
                    form.dataset.gcBound = '1';
                    var nonce = <?php echo wp_json_encode($nonce); ?>;
                    form.addEventListener('submit', function(e){
                        e.preventDefault();
                        var msg = form.querySelector('.gc-guest-msg');
                        var btn = form.querySelector('button[type="submit"]');
                        var nombre = (form.querySelector('input[name="nombre"]').value || '').trim();
                        if (nombre.length < 2) { msg.textContent = 'Escribe un nombre de al menos 2 letras.'; return; }
                        btn.disabled = true; btn.style.opacity = '0.7'; btn.textContent = 'Empezando…';
                        msg.textContent = '';
                        fetch('/wp-json/gincana/v1/guest/login', {
                            method:'POST',
                            headers:{'Content-Type':'application/json','X-WP-Nonce': nonce},
                            credentials:'same-origin',
                            body: JSON.stringify({ nombre: nombre, escenario_id: parseInt(form.dataset.escenarioId || '0', 10) })
                        }).then(function(r){ return r.json(); }).then(function(data){
                            if (data && data.ok) {
                                // Recargar para que el resto del flujo se ejecute ya logueado
                                location.reload();
                            } else {
                                msg.textContent = (data && data.error) ? ('No se pudo entrar: ' + data.error) : 'No se pudo entrar. Inténtalo de nuevo.';
                                btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '¡Empezar! 🚀';
                            }
                        }).catch(function(err){
                            msg.textContent = 'Error: ' + err.message;
                            btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '¡Empezar! 🚀';
                        });
                    });
                });
            })();
            </script>
        <?php else: ?>
            <p style="margin:0 0 16px;font-size:14px;color:#64748b;">Inicia sesión o regístrate para jugar y acumular puntos.</p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="<?php echo esc_url($login_url); ?>" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Iniciar sesión</a>
                <a href="<?php echo esc_url($register_url); ?>" style="display:inline-block;padding:12px 24px;border:2px solid #2563eb;border-radius:10px;background:#fff;color:#2563eb;text-decoration:none;font-weight:600;">Registrarse</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
  }
}

/**
 * Sincroniza la relación prueba ↔ estación en ambos lados:
 * - prueba->gc_estacion_ref = estacion_id
 * - estacion->gc_prueba_ref  = prueba_id
 *
 * Además, si había una prueba previa ligada a esa estación o esta prueba
 * estaba ligada a otra estación, limpia esos punteros huérfanos.
 *
 * Pasar $estacion_id=0 para desvincular la prueba (la estación quedará
 * sin prueba; la prueba podrá usarse como pool).
 */
if ( ! function_exists('gc_sync_estacion_prueba') ) {
  function gc_sync_estacion_prueba($prueba_id, $estacion_id) {
    $prueba_id   = (int) $prueba_id;
    $estacion_id = (int) $estacion_id;
    if ($prueba_id <= 0) return;

    $prev_est = (int) get_post_meta($prueba_id, 'gc_estacion_ref', true);

    if ($estacion_id <= 0) {
      // Desvincular: limpia prueba y el puntero de la estación previa si apunta a esta prueba
      update_post_meta($prueba_id, 'gc_estacion_ref', 0);
      if ($prev_est > 0) {
        $prev_ref = (int) get_post_meta($prev_est, 'gc_prueba_ref', true);
        if ($prev_ref === $prueba_id) {
          delete_post_meta($prev_est, 'gc_prueba_ref');
        }
      }
      return;
    }

    // Si la estación previa era otra, quitar el puntero antiguo
    if ($prev_est > 0 && $prev_est !== $estacion_id) {
      $prev_ref = (int) get_post_meta($prev_est, 'gc_prueba_ref', true);
      if ($prev_ref === $prueba_id) {
        delete_post_meta($prev_est, 'gc_prueba_ref');
      }
    }

    // Si la nueva estación ya apuntaba a OTRA prueba, limpiar esa prueba
    $old_test_on_station = (int) get_post_meta($estacion_id, 'gc_prueba_ref', true);
    if ($old_test_on_station > 0 && $old_test_on_station !== $prueba_id) {
      $old_est_on_test = (int) get_post_meta($old_test_on_station, 'gc_estacion_ref', true);
      if ($old_est_on_test === $estacion_id) {
        update_post_meta($old_test_on_station, 'gc_estacion_ref', 0);
      }
    }

    update_post_meta($prueba_id, 'gc_estacion_ref', $estacion_id);
    update_post_meta($estacion_id, 'gc_prueba_ref', $prueba_id);
  }
}

/**
 * ¿Requiere prueba este escenario? Default true para escenarios legacy sin valor guardado.
 */
if ( ! function_exists('gc_requiere_prueba') ) {
  function gc_requiere_prueba($escenario_id) {
    // 'solo_pregunta' siempre requiere prueba (la pregunta ES la validación)
    if (function_exists('gc_get_tipo_qr') && gc_get_tipo_qr($escenario_id) === 'solo_pregunta') return true;
    $val = get_post_meta((int)$escenario_id, 'gc_requiere_prueba', true);
    return ($val === '' || $val === '1'); // vacío (legacy) o '1' = sí
  }
}

/**
 * ¿Puede este usuario acceder a esta estación según el orden vigente?
 *
 * Reglas:
 *  - Si el escenario NO tiene activo "Obligar a empezar por la portada"
 *    (gc_forzar_portada), no se aplica ningún control de orden y se
 *    permite todo. La opción del escenario es el interruptor maestro.
 *  - Modo "orden libre": siempre se puede (todas están abiertas).
 *  - Si la estación ya está pasada: se permite (review).
 *  - En cualquier otro modo (orden secreto del usuario o secuencial fijo):
 *    sólo se permite si la estación coincide con la "siguiente desbloqueada"
 *    para ese usuario.
 *
 * Devuelve true si está permitido, false en caso contrario.
 */
/**
 * Devuelve las estaciones ACTIVAS del escenario ordenadas:
 * por gc_orden (ordenado en PHP para evitar ambigüedades de meta_query) o,
 * en modo secreto, por el orden personal aleatorio del usuario.
 * Excluye deshabilitadas y las que no tengan gc_orden.
 */
if ( ! function_exists('gc_user_ordered_active_stations') ) {
  function gc_user_ordered_active_stations($user_id, $escenario_id) {
    $escenario_id = (int) $escenario_id;
    $q = new WP_Query([
      'post_type'      => 'estacion',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => [
        'relation' => 'AND',
        ['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'='],
        ['key'=>'gc_orden','compare'=>'EXISTS'],
      ],
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    $ids = $q->have_posts() ? array_map('intval', $q->posts) : [];
    wp_reset_postdata();
    if (empty($ids)) return [];

    // Ordenar por gc_orden en PHP (robusto, sin depender del orderby de WP).
    usort($ids, function($a, $b){
      $oa = (int) get_post_meta($a, 'gc_orden', true);
      $ob = (int) get_post_meta($b, 'gc_orden', true);
      if ($oa === $ob) return $a - $b;
      return $oa - $ob;
    });

    // Excluir deshabilitadas
    $active = array_values(array_filter($ids, function($e){
      return get_post_meta($e, 'gc_deshabilitada', true) !== '1';
    }));

    // Orden secreto: orden personal aleatorio
    if (function_exists('gc_orden_secreto') && gc_orden_secreto($escenario_id)
        && function_exists('gc_get_user_random_order')) {
      $active = gc_get_user_random_order($user_id, $escenario_id, $active);
    }
    return array_map('intval', (array) $active);
  }
}

/**
 * Estación "actual" del usuario: la primera no superada según el orden.
 * 0 si ya las superó todas (o no hay).
 */
if ( ! function_exists('gc_user_current_station_id') ) {
  function gc_user_current_station_id($user_id, $escenario_id) {
    foreach (gc_user_ordered_active_stations($user_id, $escenario_id) as $eid) {
      if (!(function_exists('gincana_user_passed') && gincana_user_passed($user_id, $eid))) {
        return (int) $eid;
      }
    }
    return 0;
  }
}

if ( ! function_exists('gc_user_can_access_station') ) {
  function gc_user_can_access_station($user_id, $escenario_id, $station_id) {
    $user_id      = (int) $user_id;
    $escenario_id = (int) $escenario_id;
    $station_id   = (int) $station_id;
    if (!$escenario_id || !$station_id) return true;

    // El gating de orden se aplica si el escenario obliga a empezar por la
    // portada O si tiene activado "Obligar a seguir el orden". Si ninguno está
    // activo, el acceso por QR es libre (comportamiento clásico).
    $forzar   = function_exists('gc_forzar_portada') && gc_forzar_portada($escenario_id);
    $estricto = function_exists('gc_orden_estricto') && gc_orden_estricto($escenario_id);
    if (!$forzar && !$estricto) {
      return true;
    }

    // Orden libre: cualquier estación es accesible.
    if (function_exists('gc_orden_libre') && gc_orden_libre($escenario_id)) {
      return true;
    }

    // Si ya está pasada, permitir review.
    if ($user_id && function_exists('gincana_user_passed') && gincana_user_passed($user_id, $station_id)) {
      return true;
    }

    $active = gc_user_ordered_active_stations($user_id, $escenario_id);
    if (empty($active)) return true;

    // Si la estación solicitada no está en la secuencia (sin gc_orden, etc.),
    // no la bloqueamos.
    $pos = array_search($station_id, $active, true);
    if ($pos === false) return true;

    // Se permite si TODAS las estaciones anteriores en el orden están superadas.
    for ($i = 0; $i < $pos; $i++) {
      if (!(function_exists('gincana_user_passed') && gincana_user_passed($user_id, $active[$i]))) {
        return false;
      }
    }
    return true;
  }
}

/**
 * Card de aviso cuando el usuario abre el QR (o el enlace) de una estación
 * que no le corresponde según el orden vigente. No revela qué estación le
 * toca: solo le invita a volver a la portada.
 */
if ( ! function_exists('gc_render_estacion_fuera_de_orden_card') ) {
  function gc_render_estacion_fuera_de_orden_card($escenario_id, $station_title = '') {
    $escenario_url = get_permalink((int) $escenario_id) ?: home_url('/');
    $esc_title     = get_the_title((int) $escenario_id);
    // Plural con artículo configurado (p. ej. "los partidos"), neutro en género.
    $plural = function_exists('gc_get_label_estacion_plural') ? gc_get_label_estacion_plural($escenario_id) : 'las estaciones';
    ob_start();
    ?>
    <div style="margin:18px 0;padding:22px 22px 20px;border-radius:14px;background:#fffbeb;border:1px solid #fcd34d;text-align:center;">
      <div style="display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;padding:6px 14px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
        <span aria-hidden="true">⚠</span>
        <span>Aviso</span>
      </div>
      <h3 style="margin:0 0 6px;color:#78350f;font-size:18px;line-height:1.4;font-weight:700;">
        Todavía no te toca aquí
      </h3>
      <?php
        $cur_id    = function_exists('gc_user_current_station_id') ? gc_user_current_station_id(get_current_user_id(), $escenario_id) : 0;
        $cur_title = $cur_id ? get_the_title($cur_id) : '';
      ?>
      <p style="margin:0 0 16px;color:#92400e;font-size:14px;line-height:1.5;">
        Sigue el orden de <?php echo esc_html($plural); ?>.
        <?php if ($cur_title !== ''): ?>Ahora te toca: <strong><?php echo esc_html($cur_title); ?></strong>.<?php else: ?>Vuelve a la portada para ver qué te toca ahora.<?php endif; ?>
      </p>
      <a href="<?php echo esc_url($escenario_url); ?>"
         style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#d97706;color:#fff;text-decoration:none;font-weight:700;font-size:15px;">
        Ir a la portada<?php echo $esc_title ? ' de ' . esc_html($esc_title) : ''; ?>
      </a>
    </div>
    <?php
    return ob_get_clean();
  }
}

/**
 * Card que se muestra cuando un escenario tiene 'forzar_portada' activo y
 * un usuario llega a una estación sin haberse registrado todavía. En lugar
 * del alta inline, se le pide ir a la portada del escenario.
 */
if ( ! function_exists('gc_render_forzar_portada_card') ) {
  function gc_render_forzar_portada_card($escenario_id, $station_title = '', $label = 'estación') {
    $escenario_url = get_permalink((int) $escenario_id) ?: home_url('/');
    $esc_title     = get_the_title((int) $escenario_id);
    ob_start();
    ?>
    <div style="margin:18px 0;padding:22px 22px 20px;border-radius:14px;background:#fffbeb;border:1px solid #fcd34d;text-align:center;">
      <div style="display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;padding:6px 14px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">
        <span aria-hidden="true">⚠</span>
        <span>Aviso</span>
      </div>
      <h3 style="margin:0 0 14px;color:#78350f;font-size:18px;line-height:1.4;font-weight:700;">
        Debes comenzar desde el principio
      </h3>
      <a href="<?php echo esc_url($escenario_url); ?>"
         style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#d97706;color:#fff;text-decoration:none;font-weight:700;font-size:15px;">
        Ir a la portada<?php echo $esc_title ? ' de ' . esc_html($esc_title) : ''; ?>
      </a>
    </div>
    <?php
    return ob_get_clean();
  }
}

/**
 * Devuelve el HTML de imagen de "estación encontrada" para un escenario.
 * Si el escenario tiene gc_img_encontrada, usa esa. Si no, un icono por defecto.
 */
if ( ! function_exists('gc_get_img_encontrada') ) {
  function gc_get_img_encontrada($escenario_id, $size = 80) {
    $url = get_post_meta((int)$escenario_id, 'gc_img_encontrada', true);
    if ($url) {
      return '<img src="' . esc_url($url) . '" alt="" style="width:' . (int)$size . 'px;height:auto;" />';
    }
    return '<span style="font-size:' . (int)$size . 'px;line-height:1;">📍</span>';
  }
}

/**
 * Devuelve el label personalizado para "estacion" de un escenario.
 */
if ( ! function_exists('gc_get_label_estacion') ) {
  function gc_get_label_estacion($escenario_id) {
    $label = get_post_meta((int)$escenario_id, 'gc_label_estacion', true);
    return $label ? $label : 'estacion';
  }
}

/**
 * Devuelve el label plural con artículo (ej: "las estaciones", "los pasos").
 */
if ( ! function_exists('gc_get_label_estacion_plural') ) {
  function gc_get_label_estacion_plural($escenario_id) {
    $plural = get_post_meta((int)$escenario_id, 'gc_label_estacion_plural', true);
    return $plural ? $plural : 'las estaciones';
  }
}

/**
 * Devuelve el encabezado "al llegar a una parada" del escenario.
 * Personalizable con gc_msg_encontrada (admite {parada} / {Parada}).
 * Si no se configura, usa el clásico "¡{Parada} encontrada!".
 */
if ( ! function_exists('gc_get_msg_encontrada') ) {
  function gc_get_msg_encontrada($escenario_id) {
    $label    = gc_get_label_estacion($escenario_id);
    $label_uc = function_exists('mb_strtoupper')
      ? (mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1))
      : (ucfirst($label));
    $custom = (string) get_post_meta((int)$escenario_id, 'gc_msg_encontrada', true);
    if (trim($custom) !== '') {
      return str_replace(['{parada}', '{Parada}'], [$label, $label_uc], $custom);
    }
    return '¡' . $label_uc . ' encontrada!';
  }
}

/**
 * Devuelve el texto CTA de un escenario.
 */
if ( ! function_exists('gc_get_cta_texto') ) {
  function gc_get_cta_texto($escenario_id) {
    $cta = get_post_meta((int)$escenario_id, 'gc_cta_texto', true);
    if ($cta) return $cta;
    $plural = gc_get_label_estacion_plural($escenario_id);
    return '¿Te animas? ¡Comienza la aventura y completa ' . $plural . '!';
  }
}

/**
 * Genera el texto por defecto de INSTRUCCIONES basado en la configuración del escenario.
 */
if ( ! function_exists('gc_default_instrucciones') ) {
  function gc_default_instrucciones($escenario_id) {
    $id   = (int) $escenario_id;
    $tipo = get_post_meta($id, 'gc_tipo_escenario', true) ?: 'adulto';
    $qr   = get_post_meta($id, 'gc_tipo_qr', true) ?: 'enlace';
    $prueba = get_post_meta($id, 'gc_requiere_prueba', true);
    $puntos = get_post_meta($id, 'gc_mostrar_puntos', true);
    $accion = get_post_meta($id, 'gc_accion_final', true) ?: 'ninguna';
    $diploma = get_post_meta($id, 'gc_diploma_activo', true);
    $label  = gc_get_label_estacion($id);
    $plural = gc_get_label_estacion_plural($id);

    // Contar estaciones (sin no_found_rows para que cuente bien)
    $q = new WP_Query([
      'post_type'      => 'estacion',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_query'     => [['key' => 'gc_escenario_ref', 'value' => $id]],
    ]);
    $num_est = (int) $q->post_count;
    $est_ids = array_map('intval', (array) $q->posts);
    wp_reset_postdata();

    $title   = get_the_title($id);
    $portada = get_post_meta($id, 'gc_portada', true);

    // Características reales del escenario para adaptar las instrucciones.
    $es_libre   = function_exists('gc_orden_libre')   && gc_orden_libre($id);
    $es_secreto = function_exists('gc_orden_secreto') && gc_orden_secreto($id);
    if ($es_secreto) $es_libre = false; // mutuamente excluyentes

    // ¿Alguna estación tiene ubicación/mapa? (dirección, enlace de maps o GPS)
    $tiene_ubicacion = ($qr === 'validacion_gps');
    if (!$tiene_ubicacion) {
      foreach ($est_ids as $eid) {
        if ( get_post_meta($eid, 'gc_maps_url', true)
          || get_post_meta($eid, 'gc_direccion', true)
          || ( get_post_meta($eid, 'gc_latitud', true) && get_post_meta($eid, 'gc_longitud', true) ) ) {
          $tiene_ubicacion = true;
          break;
        }
      }
    }
    $sin_qr = ($qr === 'solo_pregunta');

    // URL de puntuaciones para enlazar
    $punt_url = function_exists('gc_escenario_subpage_url') ? gc_escenario_subpage_url($id, 'puntuaciones') : '';

    $blue   = '#2563eb';
    $blueDk = '#1e40af';
    $blueLt = '#eff6ff';

    // Mensaje de validación según el tipo de QR
    switch ($qr) {
      case 'enlace':
        $valida_txt = "Escanea el código QR que encontrarás en cada punto del recorrido.";
        break;
      case 'validacion_boton':
        $valida_txt = "Escanea el código QR y confirma tu llegada pulsando el botón de validación.";
        break;
      case 'validacion_boton_quiz':
        $valida_txt = "Escanea el código QR para confirmar tu llegada y, a continuación, responde correctamente a la pregunta para validar el {$label}.";
        break;
      case 'validacion_quiz':
        $valida_txt = "Escanea el código QR y responde correctamente a la pregunta para validar el {$label}.";
        break;
      case 'validacion_gps':
        $valida_txt = "Acércate al punto indicado; tu ubicación GPS se verificará automáticamente.";
        break;
      case 'solo_pregunta':
        $valida_txt = "Entra en cada {$label} desde la lista del escenario y responde correctamente a la pregunta para validarla. No necesitas código QR.";
        break;
      default:
        $valida_txt = "Sigue las indicaciones de cada {$label} para validarla.";
    }

    // Frase de orden, según mecánica
    if ($es_libre) {
      $orden_frase = "que puedes completar en <strong>el orden que prefieras</strong>";
    } elseif ($es_secreto) {
      $orden_frase = "que irás descubriendo <strong>una a una</strong>";
    } else {
      $orden_frase = "que deberás completar <strong>en orden</strong>";
    }

    // Cabecera
    $html  = "<h3 style=\"margin-bottom:6px;\">¿Cómo funciona?</h3>\n";
    $html .= "<p style=\"margin:0 0 22px;font-size:16px;line-height:1.6;\">Bienvenido a <strong>{$title}</strong>. ";
    $html .= "El recorrido consta de <strong>{$num_est} {$plural}</strong> {$orden_frase}.</p>\n";

    // === Card 1: Cómo jugar / seguir la ruta ===
    $card1_titulo = $tiene_ubicacion ? '🧭 Cómo seguir la ruta de la gymkana' : '🧭 Cómo jugar';
    $html .= "<div style=\"margin:0 0 18px;padding:18px 20px;border-radius:14px;background:{$blueLt};border-left:4px solid {$blue};\">\n";
    $html .= "  <h4 style=\"margin:0 0 12px;color:{$blueDk};font-size:18px;\">{$card1_titulo}</h4>\n";
    $html .= "  <ul style=\"margin:0;padding:0;list-style:none;\">\n";

    // Ubicación / mapa (solo si las estaciones tienen dirección o GPS)
    if ($tiene_ubicacion) {
      $html .= "    <li style=\"margin-bottom:10px;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">📍</span> En cada {$label} encontrarás una <strong>dirección</strong> acompañada de un icono de ubicación.</li>\n";
      $html .= "    <li style=\"margin-bottom:10px;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">👉</span> Pulsa sobre el icono de ubicación para abrir el mapa en tu móvil y llegar al punto.</li>\n";
    } elseif ($sin_qr) {
      $html .= "    <li style=\"margin-bottom:10px;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">📋</span> Entra en cada {$label} desde la <strong>lista del escenario</strong>. No necesitas código QR ni desplazarte.</li>\n";
    } else {
      $html .= "    <li style=\"margin-bottom:10px;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">📷</span> Busca el <strong>código QR</strong> de cada {$label} y escanéalo con tu móvil para acceder a la prueba.</li>\n";
    }

    // Orden de juego
    if ($es_libre) {
      $html .= "    <li style=\"margin-bottom:0;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">🔀</span> Puedes hacer las pruebas en el <strong>orden que quieras</strong>: todas están disponibles desde el inicio.</li>\n";
    } elseif ($es_secreto) {
      $html .= "    <li style=\"margin-bottom:0;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">🎲</span> El orden es <strong>personalizado</strong>: al superar una prueba se te revelará la siguiente.</li>\n";
    } else {
      $html .= "    <li style=\"margin-bottom:10px;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">🔢</span> Sigue las pruebas <strong>en orden</strong> (1, 2, 3, 4…). Cada una te llevará a la siguiente.</li>\n";
      $html .= "    <li style=\"margin-bottom:0;line-height:1.6;\"><span style=\"display:inline-block;width:24px;\">🚫</span> <strong>No saltes ninguna</strong>: cada parada forma parte del recorrido hasta el final.</li>\n";
    }

    $html .= "  </ul>\n";

    // Consejo de GPS solo cuando hay ubicación
    if ($tiene_ubicacion) {
      $html .= "  <div style=\"margin-top:14px;padding:10px 14px;border-radius:10px;background:#fffbeb;border:1px solid #fcd34d;font-size:14px;color:#78350f;\">\n";
      $html .= "    <strong>💡 Consejo:</strong> activa la ubicación de tu móvil para que el mapa te guíe correctamente.\n";
      $html .= "  </div>\n";
    }
    $html .= "</div>\n";

    // === Card 2: Responde al desafío ===
    if ($prueba) {
      $html .= "<div style=\"margin:0 0 18px;padding:18px 20px;border-radius:14px;background:#f5f3ff;border-left:4px solid #7c3aed;\">\n";
      $html .= "  <h4 style=\"margin:0 0 12px;color:#5b21b6;font-size:18px;\">🎯 Responde al desafío</h4>\n";
      $html .= "  <p style=\"margin:0 0 8px;line-height:1.6;\"><strong>📍 Cómo se valida cada {$label}:</strong> {$valida_txt}</p>\n";
      $html .= "  <p style=\"margin:0;line-height:1.6;color:#475569;\">En cada {$label} tendrás que resolver una pregunta o prueba.</p>\n";
      $html .= "</div>\n";
    } else {
      $html .= "<div style=\"margin:0 0 18px;padding:18px 20px;border-radius:14px;background:#f5f3ff;border-left:4px solid #7c3aed;\">\n";
      $html .= "  <h4 style=\"margin:0 0 12px;color:#5b21b6;font-size:18px;\">📍 Cómo se valida cada {$label}</h4>\n";
      $html .= "  <p style=\"margin:0;line-height:1.6;\">{$valida_txt}</p>\n";
      $html .= "</div>\n";
    }

    // === Card 3: Consigue puntos ===
    if ($puntos) {
      $html .= "<div style=\"margin:0 0 18px;padding:18px 20px;border-radius:14px;background:#ecfdf5;border-left:4px solid #16a34a;\">\n";
      $html .= "  <h4 style=\"margin:0 0 12px;color:#166534;font-size:18px;\">🏆 Consigue puntos</h4>\n";
      $html .= "  <p style=\"margin:0;line-height:1.6;\">Cuanto más rápido respondas, <strong>más puntos obtendrás</strong>. Acertar a la primera también suma puntos extra.";
      if ($punt_url) {
        $html .= " <a href=\"{$punt_url}\" style=\"color:#166534;font-weight:600;text-decoration:underline;\">Ver sistema de puntuaciones</a>.";
      }
      $html .= "</p>\n";
      $html .= "</div>\n";
    }

    // === Card 4: Acción final (foto) ===
    if ($accion === 'subir_foto') {
      $html .= "<div style=\"margin:0 0 18px;padding:18px 20px;border-radius:14px;background:#fff7ed;border-left:4px solid #ea580c;\">\n";
      $html .= "  <h4 style=\"margin:0 0 12px;color:#9a3412;font-size:18px;\">📸 Sube tu foto</h4>\n";
      $html .= "  <p style=\"margin:0;line-height:1.6;\">Al completar {$plural}, podrás subir una foto como recuerdo de tu aventura.</p>\n";
      $html .= "</div>\n";
    }

    // === Card 5: Diploma ===
    if ($diploma) {
      $html .= "<div style=\"margin:0 0 18px;padding:18px 20px;border-radius:14px;background:#fef3c7;border-left:4px solid #ca8a04;\">\n";
      $html .= "  <h4 style=\"margin:0 0 12px;color:#854d0e;font-size:18px;\">🎓 Descarga tu diploma</h4>\n";
      $html .= "  <p style=\"margin:0;line-height:1.6;\">Al finalizar recibirás un diploma personalizado que podrás descargar y compartir.</p>\n";
      $html .= "</div>\n";
    }

    // Cierre motivador
    $html .= "<p style=\"margin:24px 0 0;text-align:center;font-size:18px;font-weight:700;color:{$blueDk};\">¡Buena suerte y disfruta del recorrido! 🚀</p>\n";

    // Marca de versión (comentario HTML, no se ve en la página) para poder
    // verificar qué versión generó el texto desde el modo "Texto/HTML".
    $vmark = defined('GINCANA_CORE_VERSION') ? GINCANA_CORE_VERSION : '?';
    $ub    = $tiene_ubicacion ? '1' : '0';
    $om    = $es_libre ? 'libre' : ($es_secreto ? 'secreto' : 'fijo');
    $html .= "<!-- gc_instr v{$vmark} ubic={$ub} orden={$om} -->\n";

    // Portada del escenario al final
    if ($portada) {
      $html .= "<div style=\"text-align:center;margin-top:24px;\">";
      $html .= "<img src=\"" . esc_url($portada) . "\" alt=\"" . esc_attr($title) . "\" style=\"max-width:100%;height:auto;border-radius:12px;\" />";
      $html .= "</div>\n";
    }

    return $html;
  }
}

/**
 * Genera el texto por defecto de PUNTUACIONES basado en la configuración del escenario.
 */
if ( ! function_exists('gc_default_puntuaciones') ) {
  function gc_default_puntuaciones($escenario_id) {
    $id     = (int) $escenario_id;
    $puntos = get_post_meta($id, 'gc_mostrar_puntos', true);
    $label  = gc_get_label_estacion($id);

    if ( ! $puntos ) {
      return '<p>Este escenario no tiene sistema de puntuaciones activado.</p>';
    }

    $blue = '#2563eb';

    $html  = "<h3>Sistema de puntuaciones</h3>\n";
    $html .= "<p style=\"margin-bottom:16px;\">En cada {$label} puedes obtener hasta <strong>100 puntos</strong>. La puntuación depende de dos factores:</p>\n";

    $html .= "<h4 style=\"color:{$blue};margin-bottom:10px;\">Puntos por velocidad</h4>\n";
    $html .= "<table style=\"width:100%;border-collapse:collapse;margin-bottom:24px;\">\n";
    $html .= "<thead><tr><th style=\"text-align:left;padding:10px 8px;border-bottom:2px solid {$blue};color:{$blue};\">Tiempo de respuesta</th>";
    $html .= "<th style=\"text-align:right;padding:10px 8px;border-bottom:2px solid {$blue};color:{$blue};\">Puntos</th></tr></thead>\n<tbody>\n";

    $rules = [
      ['Menos de 5 segundos', 90],
      ['5 — 10 segundos', 75],
      ['10 — 15 segundos', 60],
      ['15 — 20 segundos', 45],
      ['20 — 25 segundos', 30],
      ['25 — 30 segundos', 15],
      ['Más de 30 segundos', 0],
    ];
    foreach ($rules as $r) {
      $html .= "<tr><td style=\"padding:8px;border-bottom:1px solid #e2e8f0;\">{$r[0]}</td>";
      $html .= "<td style=\"text-align:right;padding:8px;border-bottom:1px solid #e2e8f0;font-weight:700;\">{$r[1]}</td></tr>\n";
    }
    $html .= "</tbody></table>\n";

    $html .= "<h4 style=\"color:{$blue};margin-bottom:10px;\">Bonus por primer intento</h4>\n";
    $html .= "<p style=\"margin-bottom:20px;\">Si aciertas la pregunta <strong>a la primera</strong>, obtienes <strong>+10 puntos</strong> adicionales.</p>\n";

    $html .= "<h4 style=\"color:{$blue};margin-bottom:10px;\">Puntuación máxima</h4>\n";
    $html .= "<p style=\"margin-bottom:16px;\">La puntuación máxima por {$label} es <strong>100 puntos</strong> (90 por velocidad + 10 por primer intento).</p>";

    return $html;
  }
}

/**
 * Devuelve el origen de las preguntas: 'por_estacion' o 'pool'.
 */
if ( ! function_exists('gc_get_origen_preguntas') ) {
  function gc_get_origen_preguntas($escenario_id) {
    $val = get_post_meta((int)$escenario_id, 'gc_origen_preguntas', true);
    return in_array($val, ['por_estacion', 'pool'], true) ? $val : 'por_estacion';
  }
}

/**
 * Obtiene una pregunta aleatoria del pool para un usuario en un escenario/estación.
 * Devuelve array con 'index', 'pregunta', 'prueba_id' o null si no hay preguntas disponibles.
 * Guarda la asignación en user meta para no repetir.
 */
if ( ! function_exists('gc_get_pool_question') ) {
  function gc_get_pool_question($user_id, $escenario_id, $station_id) {
    $pool_prueba_id = (int) get_post_meta($escenario_id, 'gc_pool_prueba_ref', true);
    if (!$pool_prueba_id) return null;

    $preguntas = get_post_meta($pool_prueba_id, 'gc_preguntas', true);
    if (!is_array($preguntas) || empty($preguntas)) return null;

    // Obtener asignaciones previas de este usuario en este escenario
    $meta_key = 'gc_pool_assigned_' . (int)$escenario_id;
    $assigned = get_user_meta($user_id, $meta_key, true);
    if (!is_array($assigned)) $assigned = [];

    // ¿Ya tiene asignada una pregunta para esta estación?
    $station_key = (string)(int)$station_id;
    if (isset($assigned[$station_key])) {
      $idx = (int) $assigned[$station_key];
      if (isset($preguntas[$idx])) {
        return [
          'index'     => $idx,
          'pregunta'  => $preguntas[$idx],
          'prueba_id' => $pool_prueba_id,
        ];
      }
    }

    // Índices ya usados por este usuario
    $used_indices = array_map('intval', array_values($assigned));

    // Índices disponibles
    $available = [];
    foreach ($preguntas as $i => $p) {
      if (!in_array((int)$i, $used_indices, true)) {
        $available[] = (int)$i;
      }
    }

    if (empty($available)) {
      // Pool agotado: reciclar (permitir repetidas)
      $available = array_keys($preguntas);
    }

    // Evitar repetir la pregunta que se acaba de liberar al repetir la prueba.
    $avoid_key = 'gc_pool_avoid_' . (int) $escenario_id . '_' . (int) $station_id;
    $avoid_raw = get_user_meta($user_id, $avoid_key, true);
    if ($avoid_raw !== '' && count($available) > 1) {
      $avoid = (int) $avoid_raw;
      $filtered = array_values(array_filter($available, function($i) use ($avoid){ return (int)$i !== $avoid; }));
      if (!empty($filtered)) $available = $filtered;
    }
    delete_user_meta($user_id, $avoid_key); // solo se evita una vez

    // Elegir al azar con shuffle para mejor distribución
    shuffle($available);
    $rand_idx = $available[0];

    // Guardar asignación
    $assigned[$station_key] = $rand_idx;
    update_user_meta($user_id, $meta_key, $assigned);

    return [
      'index'     => $rand_idx,
      'pregunta'  => $preguntas[$rand_idx],
      'prueba_id' => $pool_prueba_id,
    ];
  }
}

/**
 * Devuelve la acción final del escenario: 'ninguna', 'subir_foto', etc.
 */
if ( ! function_exists('gc_get_accion_final') ) {
  function gc_get_accion_final($escenario_id) {
    $val = get_post_meta((int)$escenario_id, 'gc_accion_final', true);
    return $val ?: 'ninguna';
  }
}

/**
 * Devuelve el texto motivacional para la foto final.
 */
if ( ! function_exists('gc_get_foto_texto') ) {
  function gc_get_foto_texto($escenario_id) {
    $txt = get_post_meta((int)$escenario_id, 'gc_foto_texto', true);
    return $txt ?: '¡Hazte una foto para completar la aventura!';
  }
}

/**
 * Comprueba si el usuario ya subió su foto final para un escenario.
 */
if ( ! function_exists('gc_user_has_final_photo') ) {
  function gc_user_has_final_photo($user_id, $escenario_id) {
    $photos = get_posts([
      'post_type'      => 'attachment',
      'post_status'    => 'inherit',
      'posts_per_page' => 1,
      'author'         => (int)$user_id,
      'meta_query'     => [['key' => '_gc_foto_final_escenario', 'value' => (int)$escenario_id]],
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    return !empty($photos) ? (int)$photos[0] : 0;
  }
}

/**
 * Renderiza la barra de iconos de una estacion (audio + ubicacion).
 * Devuelve el CSS + clase para un fondo con la imagen destacada del escenario muy clarita.
 * Usa un pseudo-elemento ::before con opacity baja para no molestar la lectura.
 */
if ( ! function_exists('gc_bg_featured_inline') ) {
  /**
   * Devuelve el fragmento CSS inline para usar en un atributo style="".
   * Ejemplo: style="padding:16px;<?php echo gc_bg_featured_inline($id); ?>"
   */
  function gc_bg_featured_inline($escenario_id) {
    $thumb_url = get_post_meta((int)$escenario_id, 'gc_fondo_textos', true);
    if (!$thumb_url) return '';
    $url_esc = esc_url($thumb_url);
    return 'background: linear-gradient(rgba(255,255,255,0.92),rgba(255,255,255,0.92)), url(' . $url_esc . ') center/cover no-repeat;';
  }
}

/**
 * Devuelve HTML. Usa SVGs inline para evitar dependencias.
 */
if ( ! function_exists('gc_render_action_icons') ) {
  function gc_render_action_icons($audio_url = '', $maps_url = '', $direccion = '') {
    if (!$audio_url && !$maps_url && !$direccion) return '';

    $uid = 'gc-icons-' . uniqid();

    $html = '<div id="' . esc_attr($uid) . '" class="gc-action-icons" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin:0 0 16px;">';

    // Icono de audio
    if ($audio_url) {
      $html .= '<button type="button" class="gc-audio-toggle" title="Escuchar audio" style="display:flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-size:13px;color:#334155;transition:background 0.2s,border-color 0.2s;">';
      $html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>';
      $html .= '<span>Audio</span>';
      $html .= '</button>';
      $html .= '<audio id="' . esc_attr($uid) . '-player" preload="none" style="display:none;"><source src="' . esc_url($audio_url) . '"></audio>';
    }

    // Ubicacion: direccion + enlace maps
    if ($maps_url || $direccion) {
      $location_tag = $maps_url ? 'a' : 'span';
      $location_attrs = $maps_url
        ? ' href="' . esc_url($maps_url) . '" target="_blank" rel="noopener" title="Ver en Google Maps"'
        : '';
      $html .= '<' . $location_tag . $location_attrs . ' class="gc-icon-btn" style="display:flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;text-decoration:none;font-size:13px;color:#334155;transition:background 0.2s,border-color 0.2s;">';
      $html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
      $html .= '<span>' . esc_html($direccion ?: 'Ubicacion') . '</span>';
      $html .= '</' . $location_tag . '>';
    }

    $html .= '</div>';

    // JS para toggle audio
    if ($audio_url) {
      $html .= '<script>(function(){';
      $html .= 'var uid="' . esc_js($uid) . '";';
      $html .= 'var wrap=document.getElementById(uid);if(!wrap)return;';
      $html .= 'var btn=wrap.querySelector(".gc-audio-toggle");';
      $html .= 'var audio=document.getElementById(uid+"-player");';
      $html .= 'if(!btn||!audio)return;';
      $html .= 'var playing=false;';
      $html .= 'btn.addEventListener("click",function(){';
      $html .= '  if(playing){audio.pause();btn.querySelector("span").textContent="Audio";btn.style.background="#fff";btn.style.borderColor="#e2e8f0";playing=false;}';
      $html .= '  else{audio.play();btn.querySelector("span").textContent="Pausar";btn.style.background="#eff6ff";btn.style.borderColor="#2563eb";playing=true;}';
      $html .= '});';
      $html .= 'audio.addEventListener("ended",function(){btn.querySelector("span").textContent="Audio";btn.style.background="#fff";btn.style.borderColor="#e2e8f0";playing=false;});';
      $html .= '})();</script>';
    }

    return $html;
  }
}
