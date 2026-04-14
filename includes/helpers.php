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
    return in_array($val, ['enlace', 'validacion_boton', 'validacion_quiz', 'validacion_gps'], true) ? $val : 'enlace';
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
    if ($tipo_qr === 'enlace') {
      return get_permalink((int)$station_id);
    }
    // validacion_boton, validacion_quiz o validacion_gps: URL con token
    return function_exists('gc_get_station_entry_url') ? gc_get_station_entry_url((int)$station_id) : get_permalink((int)$station_id);
  }
}

/**
 * ¿Requiere prueba este escenario? Default true para escenarios legacy sin valor guardado.
 */
if ( ! function_exists('gc_requiere_prueba') ) {
  function gc_requiere_prueba($escenario_id) {
    $val = get_post_meta((int)$escenario_id, 'gc_requiere_prueba', true);
    return ($val === '' || $val === '1'); // vacío (legacy) o '1' = sí
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
    wp_reset_postdata();

    $title   = get_the_title($id);
    $portada = get_post_meta($id, 'gc_portada', true);

    $sp = 'style="margin-bottom:12px;"'; // spacing para los <li>

    $html  = "<h3>¿Cómo funciona?</h3>\n";
    $html .= "<p>Bienvenido a <strong>{$title}</strong>. ";
    $html .= "El recorrido consta de <strong>{$num_est} " . rtrim($plural, 's') . "</strong> que deberás completar en orden.</p>\n";

    $html .= "<ol>\n";

    // Paso 1: como acceder
    $html .= "<li {$sp}><strong>Accede a cada {$label}</strong> — ";
    switch ($qr) {
      case 'enlace':
        $html .= "Escanea el código QR que encontrarás en cada punto del recorrido.</li>\n";
        break;
      case 'validacion_boton':
        $html .= "Escanea el código QR y confirma tu llegada pulsando el botón de validación.</li>\n";
        break;
      case 'validacion_quiz':
        $html .= "Escanea el código QR y responde correctamente a la pregunta para validar el {$label}.</li>\n";
        break;
      case 'validacion_gps':
        $html .= "Acércate al punto indicado; tu ubicación GPS se verificará automáticamente.</li>\n";
        break;
    }

    // Paso 2: prueba/quiz
    if ($prueba) {
      $html .= "<li {$sp}><strong>Responde al desafío</strong> — En cada {$label} tendrás que resolver una pregunta o prueba.</li>\n";
    }

    // Paso 3: puntos
    if ($puntos) {
      $html .= "<li {$sp}><strong>Consigue puntos</strong> — Cuanto más rápido respondas, más puntos obtendrás. Acertar a la primera también suma puntos extra.</li>\n";
    }

    // Paso 4: accion final
    if ($accion === 'subir_foto') {
      $html .= "<li {$sp}><strong>Sube tu foto</strong> — Al completar {$plural}, podrás subir una foto como recuerdo de tu aventura.</li>\n";
    }

    // Paso 5: diploma
    if ($diploma) {
      $html .= "<li {$sp}><strong>Descarga tu diploma</strong> — Al finalizar recibirás un diploma personalizado que podrás descargar.</li>\n";
    }

    $html .= "</ol>\n";
    $html .= "<p><strong>¡Buena suerte y disfruta del recorrido!</strong></p>\n";

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

    $html  = "<h3>Sistema de puntuaciones</h3>\n";
    $html .= "<p>En cada {$label} puedes obtener hasta <strong>100 puntos</strong>. La puntuacion depende de dos factores:</p>\n";

    $html .= "<h4>Puntos por velocidad</h4>\n";
    $html .= "<table style=\"width:100%;border-collapse:collapse;margin-bottom:16px;\">\n";
    $html .= "<thead><tr><th style=\"text-align:left;padding:8px;border-bottom:2px solid #e2e8f0;\">Tiempo de respuesta</th>";
    $html .= "<th style=\"text-align:right;padding:8px;border-bottom:2px solid #e2e8f0;\">Puntos</th></tr></thead>\n<tbody>\n";

    $rules = [
      ['Menos de 5 segundos', 90],
      ['5 — 10 segundos', 75],
      ['10 — 15 segundos', 60],
      ['15 — 20 segundos', 45],
      ['20 — 25 segundos', 30],
      ['25 — 30 segundos', 15],
      ['Mas de 30 segundos', 0],
    ];
    foreach ($rules as $r) {
      $html .= "<tr><td style=\"padding:6px 8px;border-bottom:1px solid #f1f5f9;\">{$r[0]}</td>";
      $html .= "<td style=\"text-align:right;padding:6px 8px;border-bottom:1px solid #f1f5f9;font-weight:600;\">{$r[1]}</td></tr>\n";
    }
    $html .= "</tbody></table>\n";

    $html .= "<h4>Bonus por primer intento</h4>\n";
    $html .= "<p>Si aciertas la pregunta <strong>a la primera</strong>, obtienes <strong>+10 puntos</strong> adicionales.</p>\n";

    $html .= "<h4>Puntuacion maxima</h4>\n";
    $html .= "<p>La puntuacion maxima por {$label} es <strong>100 puntos</strong> (90 por velocidad + 10 por primer intento).</p>";

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
