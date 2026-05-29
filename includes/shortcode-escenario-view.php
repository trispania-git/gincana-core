<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcode: [gincana_escenario_contenido]
 *
 * Muestra el contenido del escenario: titulo, descripcion, audio, imagenes.
 * Para usar en la plantilla Theme Builder del CPT "escenario".
 */
add_shortcode('gincana_escenario_contenido', function($atts){

  // Placeholder para Divi Builder
  if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
    return '<div style="padding:16px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;text-align:center;">
      <strong>Gincana — Contenido de Escenario</strong><br><small>(Vista previa del builder)</small>
    </div>';
  }

  $a = shortcode_atts(['escenario' => ''], $atts);

  // Resolver escenario
  $escenario_id = (int)$a['escenario'];
  if (!$escenario_id) {
    $ctx = get_the_ID();
    if ($ctx && get_post_type($ctx) === 'escenario') {
      $escenario_id = (int)$ctx;
    }
  }
  if (!$escenario_id) {
    return '<p>No se pudo determinar el escenario.</p>';
  }

  $title       = get_the_title($escenario_id);
  $descripcion = get_post_meta($escenario_id, 'gc_descripcion', true);
  $audio       = get_post_meta($escenario_id, 'gc_audio', true);
  $img1        = get_post_meta($escenario_id, 'gc_img_1', true);
  $img2        = get_post_meta($escenario_id, 'gc_img_2', true);
  $img3        = get_post_meta($escenario_id, 'gc_img_3', true);

  $bg_inline = function_exists('gc_bg_featured_inline') ? gc_bg_featured_inline($escenario_id) : '';

  ob_start();
  ?>
  <?php if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id); ?>
  <div class="gc-escenario-content gc-tema-esc-<?php echo (int) $escenario_id; ?>" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">

    <h2 style="margin:0 0 12px;font-size:22px;font-weight:700;line-height:1.3;"><?php echo esc_html($title); ?></h2>

    <?php if ($descripcion): ?>
      <div style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;padding:16px;border-radius:12px;<?php echo $bg_inline; ?>">
        <?php echo wp_kses_post(wpautop($descripcion)); ?>
      </div>
    <?php endif; ?>

    <?php if ($audio): ?>
      <div style="margin:0 0 16px;">
        <?php echo gc_render_action_icons($audio, ''); ?>
      </div>
    <?php endif; ?>

    <?php if ($img1 || $img2 || $img3): ?>
      <div style="display:flex;flex-direction:column;gap:12px;margin:0 0 24px;">
        <?php if ($img1): ?><img src="<?php echo esc_url($img1); ?>" alt="" style="width:100%;height:auto;border-radius:10px;"><?php endif; ?>
        <?php if ($img2): ?><img src="<?php echo esc_url($img2); ?>" alt="" style="width:100%;height:auto;border-radius:10px;"><?php endif; ?>
        <?php if ($img3): ?><img src="<?php echo esc_url($img3); ?>" alt="" style="width:100%;height:auto;border-radius:10px;"><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
});


/**
 * Shortcode: [gincana_estaciones_lista]
 *
 * Muestra las estaciones de un escenario como tarjetas visuales (mobile-first).
 * Se coloca en la plantilla Theme Builder del CPT "escenario".
 *
 * Atributos:
 *   escenario="ID"  — opcional, se auto-detecta si estamos en un CPT escenario/estación
 *   columns="1"     — columnas en móvil (1 o 2), en desktop siempre 2
 */
add_shortcode('gincana_estaciones_lista', function($atts){

  // Placeholder para Divi Builder
  if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
    return '<div style="padding:16px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;text-align:center;">
      <strong>Gincana — Lista de Estaciones</strong><br><small>(Vista previa del builder)</small>
    </div>';
  }

  $a = shortcode_atts([
    'escenario' => '',
    'columns'   => '1',
  ], $atts);

  // ── Resolver escenario ────────────────────────────────
  $escenario_id = (int)$a['escenario'];
  if (!$escenario_id) {
    $ctx = get_the_ID();
    if ($ctx) {
      if (get_post_type($ctx) === 'escenario') {
        $escenario_id = (int)$ctx;
      } elseif (get_post_type($ctx) === 'estacion') {
        $escenario_id = (int) get_post_meta($ctx, 'gc_escenario_ref', true);
      }
    }
  }
  if (!$escenario_id) {
    return '<p>No se pudo determinar el escenario.</p>';
  }

  $tipo_escenario       = get_post_meta($escenario_id, 'gc_tipo_escenario', true) ?: 'adulto';
  $label_estacion       = gc_get_label_estacion($escenario_id);
  $label_estacion_plural = gc_get_label_estacion_plural($escenario_id);
  $cta_texto            = gc_get_cta_texto($escenario_id);
  $show_points          = gc_show_points($escenario_id);

  // ── Obtener estaciones ordenadas ───────────────────────
  $q = new WP_Query([
    'post_type'      => 'estacion',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value_num',
    'order'          => 'ASC',
    'meta_query'     => [
      'relation' => 'AND',
      ['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'='],
      ['key'=>'gc_orden','compare'=>'EXISTS'],
    ],
    'meta_key'       => 'gc_orden',
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ]);
  if (!$q->have_posts()) {
    return '<p>Este escenario no tiene estaciones configuradas.</p>';
  }
  $est_ids = array_map('intval', $q->posts);
  wp_reset_postdata();


  // ── Progreso del usuario ───────────────────────────────
  $user_id  = get_current_user_id();
  $progress = [];
  $points_per_station = [];
  if ($user_id) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'gincana_user_progress';
    $in  = implode(',', $est_ids);
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT estacion_id, status FROM $tbl WHERE user_id=%d AND escenario_id=%d AND estacion_id IN ($in)",
      $user_id, $escenario_id
    ));
    foreach ($rows as $r) {
      $progress[(int)$r->estacion_id] = $r->status;
    }

    // Puntos por estación
    $pts_tbl = $wpdb->prefix . 'gincana_points_log';
    $pts_rows = $wpdb->get_results($wpdb->prepare(
      "SELECT estacion_id, SUM(points) as pts FROM {$pts_tbl} WHERE user_id=%d AND escenario_id=%d AND estacion_id IN ($in) GROUP BY estacion_id",
      $user_id, $escenario_id
    ));
    foreach ($pts_rows as $pr) {
      $points_per_station[(int)$pr->estacion_id] = (int)$pr->pts;
    }
  }

  // Marcar deshabilitadas
  $disabled_ids = [];
  foreach ($est_ids as $eid) {
    if (get_post_meta($eid, 'gc_deshabilitada', true) === '1') {
      $disabled_ids[$eid] = true;
    }
  }

  // Calcular siguiente desbloqueada (ignorando deshabilitadas)
  $next_unlocked = 0;
  // Construir secuencia "activa" (sin deshabilitadas) para calcular dependencias
  $active_ids = array_values(array_filter($est_ids, function($id) use ($disabled_ids) {
    return empty($disabled_ids[$id]);
  }));
  $es_orden_libre = function_exists('gc_orden_aleatorio') && gc_orden_aleatorio($escenario_id);
  if ($es_orden_libre) {
    // Orden libre: la primera no completada es la "actual" para destacarla,
    // pero todas las no completadas estarán disponibles (ver render más abajo).
    foreach ($active_ids as $eid) {
      if (empty($progress[$eid]) || $progress[$eid] !== 'passed') { $next_unlocked = $eid; break; }
    }
  } else {
    foreach ($active_ids as $i => $eid) {
      if (!empty($progress[$eid]) && $progress[$eid] === 'passed') continue;
      $prev_ok = ($i === 0) || (!empty($progress[$active_ids[$i-1]]) && $progress[$active_ids[$i-1]] === 'passed');
      if ($prev_ok) { $next_unlocked = $eid; break; }
    }
  }

  // Contar completadas y total (excluyendo deshabilitadas)
  $completed = 0;
  foreach ($active_ids as $eid) {
    if (!empty($progress[$eid]) && $progress[$eid] === 'passed') $completed++;
  }
  $total = count($active_ids);
  $pct   = $total > 0 ? round(($completed / $total) * 100) : 0;

  // ── ID único para scope CSS ────────────────────────────
  $uid = 'gc-el-' . uniqid();

  ob_start(); ?>

  <style>
    #<?php echo $uid; ?> {
      --gc-accent: #2563eb;
      --gc-success: #16a34a;
      --gc-warn: #f59e0b;
      --gc-muted: #94a3b8;
      --gc-bg: #f8fafc;
      --gc-card-bg: #ffffff;
      --gc-radius: 14px;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Barra de progreso */
    #<?php echo $uid; ?> .gc-progress-wrap {
      margin-bottom: 20px;
    }
    #<?php echo $uid; ?> .gc-progress-bar {
      height: 8px;
      border-radius: 99px;
      background: #e2e8f0;
      overflow: hidden;
    }
    #<?php echo $uid; ?> .gc-progress-fill {
      height: 100%;
      border-radius: 99px;
      background: var(--gc-success);
      transition: width 0.6s ease;
    }
    #<?php echo $uid; ?> .gc-progress-label {
      margin-top: 6px;
      font-size: 13px;
      color: var(--gc-muted);
    }

    /* Grid de tarjetas */
    #<?php echo $uid; ?> .gc-cards {
      display: flex;
      flex-direction: column;
      gap: 12px;
      padding-bottom: 80px; /* espacio para barra sticky inferior */
    }

    /* Tarjeta */
    #<?php echo $uid; ?> .gc-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      background: var(--gc-card-bg);
      border: 1px solid #e2e8f0;
      border-radius: var(--gc-radius);
      text-decoration: none;
      color: inherit;
      transition: box-shadow 0.2s, border-color 0.2s;
    }
    #<?php echo $uid; ?> a.gc-card:hover,
    #<?php echo $uid; ?> a.gc-card:focus {
      border-color: var(--gc-accent);
      box-shadow: 0 2px 8px rgba(37,99,235,0.12);
    }
    #<?php echo $uid; ?> .gc-card.is-locked {
      opacity: 0.55;
      cursor: default;
    }
    #<?php echo $uid; ?> .gc-card.is-current {
      border-color: var(--gc-accent);
      box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
    }

    /* Icono circular */
    #<?php echo $uid; ?> .gc-card-icon {
      flex-shrink: 0;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 15px;
      color: #fff;
    }
    #<?php echo $uid; ?> .gc-card-icon.passed  { background: var(--gc-success); color: #fff !important; }
    #<?php echo $uid; ?> .gc-card-icon.current { background: var(--gc-accent); color: #fff !important; }
    #<?php echo $uid; ?> .gc-card-icon.locked  { background: #cbd5e1; color: #64748b !important; }

    /* Contenido */
    #<?php echo $uid; ?> .gc-card-body {
      flex: 1;
      min-width: 0;
    }
    #<?php echo $uid; ?> .gc-card-title {
      font-size: 15px;
      font-weight: 600;
      line-height: 1.3;
      margin: 0;
    }
    #<?php echo $uid; ?> .gc-card-status {
      font-size: 12px;
      margin-top: 3px;
    }
    #<?php echo $uid; ?> .gc-card-status.passed  { color: var(--gc-success); }
    #<?php echo $uid; ?> .gc-card-status.current { color: var(--gc-accent); }
    #<?php echo $uid; ?> .gc-card-status.locked  { color: var(--gc-muted); }

    /* Flecha */
    #<?php echo $uid; ?> .gc-card-arrow {
      flex-shrink: 0;
      width: 24px;
      height: 24px;
      color: var(--gc-muted);
    }
    #<?php echo $uid; ?> a.gc-card:hover .gc-card-arrow {
      color: var(--gc-accent);
    }

    /* Responsive: 2 columnas en pantallas anchas */
    @media (min-width: 600px) {
      #<?php echo $uid; ?> .gc-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
      }
    }
  </style>

  <div id="<?php echo esc_attr($uid); ?>" style="width:95%;max-width:760px;margin:0 auto;">

    <?php
      $instr_url   = function_exists('gc_escenario_subpage_url') ? gc_escenario_subpage_url($escenario_id, 'instrucciones') : '';
      $tiene_instr = get_post_meta($escenario_id, 'gc_instrucciones', true);
      if ($instr_url && $tiene_instr):
    ?>
    <div style="text-align:center;margin-bottom:16px;">
      <a href="<?php echo esc_url($instr_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:2px solid #e2e8f0;border-radius:10px;color:#64748b;text-decoration:none;font-weight:600;font-size:15px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Instrucciones
      </a>
    </div>
    <?php endif; ?>

    <!-- CTA motivacional -->
    <div style="text-align:center;padding:16px 12px 20px;border:2px solid #2563eb;border-radius:14px;margin-bottom:20px;">
      <p style="margin:0;font-size:17px;font-weight:600;line-height:1.5;color:#1e293b;">
        <?php echo esc_html($cta_texto); ?>
      </p>
      <div style="margin-top:8px;color:#2563eb;font-size:28px;line-height:1;animation:gc-bounce 2s infinite;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
      </div>
    </div>
    <style>
      @keyframes gc-bounce {
        0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(6px); }
        60% { transform: translateY(3px); }
      }
    </style>

    <?php if ($user_id):
      $total_points = 0;
      if ($show_points) {
        global $wpdb;
        $points_table = $wpdb->prefix . 'gincana_points_log';
        $total_points = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT COALESCE(SUM(points),0) FROM {$points_table} WHERE user_id=%d AND escenario_id=%d",
          $user_id, $escenario_id
        ));
      }
    ?>
    <!-- Barra de progreso + Puntos -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
      <div style="flex:1;min-width:0;">
        <div class="gc-progress-bar">
          <div class="gc-progress-fill" style="width:<?php echo (int)$pct; ?>%;"></div>
        </div>
        <div class="gc-progress-label">
          <?php echo (int)$completed; ?>/<?php echo (int)$total; ?> <?php echo esc_html($label_estacion_plural); ?>
        </div>
      </div>
      <?php if ($show_points): ?>
      <div style="flex-shrink:0;text-align:center;padding:8px 14px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:12px;color:#fff;min-width:70px;">
        <div style="font-size:20px;font-weight:800;line-height:1.1;"><?php echo (int)$total_points; ?></div>
        <div style="font-size:10px;font-weight:500;opacity:0.85;text-transform:uppercase;letter-spacing:0.5px;">puntos</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
      $ranking_url = function_exists('gc_escenario_subpage_url') ? gc_escenario_subpage_url($escenario_id, 'ranking') : '';
      $punt_url    = function_exists('gc_escenario_subpage_url') ? gc_escenario_subpage_url($escenario_id, 'puntuaciones') : '';
      $tiene_punt  = get_post_meta($escenario_id, 'gc_puntuaciones', true);
      if ($ranking_url && $show_points):
    ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-bottom:16px;">
      <a href="<?php echo esc_url($ranking_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:2px solid #2563eb;border-radius:10px;color:#2563eb;text-decoration:none;font-weight:600;font-size:15px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15l-2 5l9-11h-5l2-5l-9 11h5z"/></svg>
        Ver ranking
      </a>
      <?php if ($tiene_punt): ?>
      <a href="<?php echo esc_url($punt_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:2px solid #e2e8f0;border-radius:10px;color:#64748b;text-decoration:none;font-weight:600;font-size:15px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        Puntuaciones
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="gc-cards">
      <?php foreach ($est_ids as $i => $eid):
        $order  = (int) get_post_meta($eid, 'gc_orden', true) ?: ($i + 1);
        $title  = get_the_title($eid) ?: ('Estacion ' . $order);
        $url    = get_permalink($eid);

        $is_disabled = !empty($disabled_ids[$eid]);
        $is_passed   = !$is_disabled && !empty($progress[$eid]) && $progress[$eid] === 'passed';
        $is_current  = !$is_disabled && ($eid === $next_unlocked);
        // En modo orden libre, todas las no completadas están disponibles (no hay bloqueadas)
        $is_available = !$is_disabled && !$is_passed && $es_orden_libre;
        $is_locked    = !$is_disabled && !$is_passed && !$is_current && !$is_available;

        $extra_style = '';
        // Estado visual
        if ($is_disabled) {
          $icon_bg     = '#fecaca';
          $icon_fg     = '#991b1b';
          $icon_text   = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>';
          $status_text = 'No disponible';
          $status_cls  = 'disabled';
          $card_cls    = 'is-disabled';
          $extra_style = 'background:repeating-linear-gradient(45deg,#fff5f5 0 10px,#fff 10px 20px);border:1.5px dashed #fca5a5;opacity:0.9;';
        } elseif ($is_passed) {
          $icon_bg     = '#16a34a';
          $icon_fg     = '#ffffff';
          $icon_text   = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
          $status_text = 'Completada';
          $status_cls  = 'passed';
          $card_cls    = '';
        } elseif ($is_current) {
          $icon_bg     = '#2563eb';
          $icon_fg     = '#ffffff';
          $icon_text   = (string)$order;
          $status_text = $es_orden_libre ? 'Disponible' : ('Siguiente ' . $label_estacion);
          $status_cls  = 'current';
          $card_cls    = 'is-current';
        } elseif ($is_available) {
          // Orden libre: estación disponible (no es la "actual" destacada pero accesible)
          $icon_bg     = '#f59e0b';
          $icon_fg     = '#ffffff';
          $icon_text   = (string)$order;
          $status_text = 'Disponible';
          $status_cls  = 'current';
          $card_cls    = 'is-current';
        } else {
          $icon_bg     = '#cbd5e1';
          $icon_fg     = '#64748b';
          $icon_text   = (string)$order;
          $status_text = 'Bloqueada';
          $status_cls  = 'locked';
          $card_cls    = 'is-locked';
        }

        $tag = (!$is_disabled && ($is_passed || $is_current || $is_available)) ? 'a' : 'div';
        $href = ($tag === 'a') ? ' href="' . esc_url($url) . '"' : '';
      ?>

        <<?php echo $tag; ?> class="gc-card <?php echo esc_attr($card_cls); ?>"<?php echo $href; ?> style="<?php echo esc_attr($extra_style); ?>">
          <div class="gc-card-icon" style="background:<?php echo $icon_bg; ?>;">
            <?php if ($is_passed || $is_disabled): ?>
              <?php echo $icon_text; ?>
            <?php else: ?>
              <span style="color:<?php echo $icon_fg; ?>;line-height:1;"><?php echo $icon_text; ?></span>
            <?php endif; ?>
          </div>
          <div class="gc-card-body">
            <div class="gc-card-title" style="<?php echo $is_disabled ? 'text-decoration:line-through;color:#991b1b;' : ''; ?>"><?php echo esc_html($title); ?></div>
            <div class="gc-card-status <?php echo esc_attr($status_cls); ?>" style="<?php echo $is_disabled ? 'color:#dc2626;font-weight:700;' : ''; ?>"><?php
              echo esc_html($status_text);
              if ($is_passed && $show_points) {
                $eid_pts = isset($points_per_station[$eid]) ? (int)$points_per_station[$eid] : 0;
                echo ' · <strong>' . $eid_pts . ' pts</strong>';
              }
            ?></div>
          </div>
          <?php if ($tag === 'a'): ?>
            <svg class="gc-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          <?php endif; ?>
        </<?php echo $tag; ?>>

      <?php endforeach; ?>

      <?php
        $ranking_url_bottom = function_exists('gc_escenario_subpage_url') ? gc_escenario_subpage_url($escenario_id, 'ranking') : '';
        if ($ranking_url_bottom && $show_points):
      ?>
      <a href="<?php echo esc_url($ranking_url_bottom); ?>" class="gc-ranking-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;background:linear-gradient(135deg,#fbbf24,#f59e0b);border:2px solid #d97706;border-radius:var(--gc-radius);text-decoration:none;color:#78350f;transition:transform 0.2s,box-shadow 0.2s;">
        <div style="flex-shrink:0;width:44px;height:44px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.12);">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M4 22h16"/><path d="M10 22V8a4 4 0 0 0-4-4H6a4 4 0 0 0 4 4h4a4 4 0 0 0 4-4h0a4 4 0 0 0-4 4v14"/><path d="M8 22h8"/><path d="M12 17v5"/></svg>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:16px;font-weight:700;line-height:1.3;">Consulta el ranking</div>
          <div style="font-size:13px;opacity:0.85;margin-top:2px;">Mira tu posición y compara con otros jugadores</div>
        </div>
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#78350f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
      </a>
      <style>
        #<?php echo $uid; ?> .gc-ranking-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 16px rgba(245,158,11,0.35);
        }
      </style>
      <?php endif; ?>

      <?php
      // === Panel de acción final (foto, etc.) ===
      if ($user_id && $completed === $total && $total > 0):
        $accion_final = gc_get_accion_final($escenario_id);

        if ($accion_final === 'subir_foto'):
          $foto_texto = gc_get_foto_texto($escenario_id);
          $existing_photo_id = gc_user_has_final_photo($user_id, $escenario_id);
          $nonce_foto = wp_create_nonce('wp_rest');
      ?>
      <div id="gc-foto-final" style="margin-top:8px;padding:24px 20px;border:2px solid #16a34a;border-radius:var(--gc-radius);background:linear-gradient(135deg,#f0fdf4,#dcfce7);text-align:center;">
        <?php if ($existing_photo_id): ?>
          <!-- Ya subió la foto -->
          <div style="font-size:40px;margin-bottom:12px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#14532d;">¡Aventura completada!</h3>
          <p style="margin:0 0 16px;font-size:15px;color:#166534;">Tu foto ha sido registrada. ¡Enhorabuena!</p>
          <img src="<?php echo esc_url(wp_get_attachment_image_url($existing_photo_id, 'medium')); ?>" alt="Tu foto final"
               style="max-width:280px;width:100%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);" />
        <?php else: ?>
          <!-- Pedir foto -->
          <div style="font-size:40px;margin-bottom:12px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </div>
          <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#14532d;">¡Has completado todas las estaciones!</h3>
          <p style="margin:0 0 18px;font-size:15px;color:#166534;line-height:1.5;"><?php echo esc_html($foto_texto); ?></p>

          <div id="gc-foto-upload-area">
            <label for="gc-foto-input" style="display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border:0;border-radius:12px;background:#16a34a;color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:background 0.2s;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              Hacer foto
            </label>
            <input type="file" id="gc-foto-input" accept="image/*" capture="environment" style="display:none;" />
          </div>

          <!-- Preview + confirmar -->
          <div id="gc-foto-preview" style="display:none;margin-top:16px;">
            <img id="gc-foto-preview-img" src="" alt="Vista previa"
                 style="max-width:280px;width:100%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:14px;" />
            <div style="display:flex;gap:10px;justify-content:center;">
              <button type="button" id="gc-foto-confirm"
                      style="padding:12px 24px;border:0;border-radius:10px;background:#16a34a;color:#fff;font-size:15px;font-weight:600;cursor:pointer;">
                Enviar foto
              </button>
              <button type="button" id="gc-foto-retry"
                      style="padding:12px 24px;border:1px solid #d1d5db;border-radius:10px;background:#fff;color:#374151;font-size:15px;font-weight:600;cursor:pointer;">
                Repetir
              </button>
            </div>
          </div>

          <!-- Mensaje de estado -->
          <div id="gc-foto-msg" style="margin-top:14px;"></div>

          <!-- Spinner -->
          <div id="gc-foto-loading" style="display:none;margin-top:16px;">
            <div style="display:inline-block;width:32px;height:32px;border:3px solid #d1fae5;border-top-color:#16a34a;border-radius:50%;animation:gc-spin 0.7s linear infinite;"></div>
            <p style="margin:8px 0 0;font-size:14px;color:#166534;">Subiendo foto...</p>
          </div>
          <style>@keyframes gc-spin { to { transform: rotate(360deg); } }</style>

          <script>
          (function(){
            var input = document.getElementById('gc-foto-input');
            var preview = document.getElementById('gc-foto-preview');
            var previewImg = document.getElementById('gc-foto-preview-img');
            var uploadArea = document.getElementById('gc-foto-upload-area');
            var confirmBtn = document.getElementById('gc-foto-confirm');
            var retryBtn = document.getElementById('gc-foto-retry');
            var msg = document.getElementById('gc-foto-msg');
            var loading = document.getElementById('gc-foto-loading');
            var selectedFile = null;

            if (!input) return;

            input.addEventListener('change', function(){
              if (!this.files || !this.files[0]) return;
              selectedFile = this.files[0];
              var reader = new FileReader();
              reader.onload = function(e){
                previewImg.src = e.target.result;
                uploadArea.style.display = 'none';
                preview.style.display = 'block';
                msg.innerHTML = '';
              };
              reader.readAsDataURL(selectedFile);
            });

            retryBtn.addEventListener('click', function(){
              selectedFile = null;
              preview.style.display = 'none';
              uploadArea.style.display = 'block';
              input.value = '';
            });

            confirmBtn.addEventListener('click', async function(){
              if (!selectedFile) return;

              confirmBtn.disabled = true;
              retryBtn.style.display = 'none';
              loading.style.display = 'block';
              msg.innerHTML = '';

              var formData = new FormData();
              formData.append('photo', selectedFile);
              formData.append('escenario_id', '<?php echo (int)$escenario_id; ?>');

              try {
                var nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js($nonce_foto); ?>';
                var res = await fetch('/wp-json/gincana/v1/photo/upload', {
                  method: 'POST',
                  headers: { 'X-WP-Nonce': nonce },
                  credentials: 'same-origin',
                  body: formData
                });

                var data = await res.json();
                loading.style.display = 'none';

                if (data && data.ok) {
                  preview.style.display = 'none';
                  document.getElementById('gc-foto-final').innerHTML =
                    '<div style="font-size:40px;margin-bottom:12px;"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>' +
                    '<h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#14532d;">¡Aventura completada!</h3>' +
                    '<p style="margin:0 0 16px;font-size:15px;color:#166534;">Tu foto ha sido registrada. ¡Enhorabuena!</p>' +
                    '<img src="' + (data.thumbnail_url || previewImg.src) + '" style="max-width:280px;width:100%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);" />';
                } else {
                  msg.innerHTML = '<div style="padding:12px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">Error: ' + (data.error || 'No se pudo subir la foto') + '</div>';
                  confirmBtn.disabled = false;
                  retryBtn.style.display = '';
                }
              } catch(err) {
                loading.style.display = 'none';
                msg.innerHTML = '<div style="padding:12px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">Error de conexión: ' + err.message + '</div>';
                confirmBtn.disabled = false;
                retryBtn.style.display = '';
              }
            });
          })();
          </script>
        <?php endif; ?>
      </div>

      <?php elseif ($accion_final === 'ninguna' && $completed === $total && $total > 0):
        $enhorabuena_msg = get_post_meta($escenario_id, 'gc_enhorabuena_msg', true);
        $diploma_activo  = get_post_meta($escenario_id, 'gc_diploma_activo', true) === '1';
        $diploma_msg     = get_post_meta($escenario_id, 'gc_diploma_msg', true);
        $diploma_fondo   = get_post_meta($escenario_id, 'gc_diploma_fondo', true);
        $diploma_mostrar_puntos = get_post_meta($escenario_id, 'gc_mostrar_puntos', true) === '1';
        $diploma_portada = get_post_meta($escenario_id, 'gc_portada', true);
        $diploma_pie_activo = get_post_meta($escenario_id, 'gc_diploma_pie_activo', true);
        if ($diploma_pie_activo === '') $diploma_pie_activo = '1';
        $diploma_pie_texto  = get_post_meta($escenario_id, 'gc_diploma_pie_texto', true);
        if ($diploma_pie_texto === '') $diploma_pie_texto = 'Generado por Gincana';
        $current_user    = wp_get_current_user();
        $user_display    = $current_user->display_name ?: $current_user->user_login;
        $esc_title       = get_the_title($escenario_id);

        // Calcular ranking del usuario
        global $wpdb;
        $pts_tbl = $wpdb->prefix . 'gincana_points_log';
        $user_total_pts = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT COALESCE(SUM(points),0) FROM {$pts_tbl} WHERE user_id=%d AND escenario_id=%d",
          $user_id, $escenario_id
        ));
        $ranking_pos = 1 + (int) $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM (SELECT user_id FROM {$pts_tbl} WHERE escenario_id=%d AND user_id != %d GROUP BY user_id HAVING SUM(points) > %d) AS better",
          $escenario_id, $user_id, $user_total_pts
        ));
      ?>
        <!-- Completado sin acción final -->
        <div style="margin-top:8px;padding:20px;border:2px solid #16a34a;border-radius:var(--gc-radius);background:linear-gradient(135deg,#f0fdf4,#dcfce7);text-align:center;">
          <div style="font-size:40px;margin-bottom:8px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3 style="margin:0 0 4px;font-size:20px;font-weight:700;color:#14532d;">¡Enhorabuena!</h3>
          <p style="margin:0 0 8px;font-size:15px;color:#166534;">
            <?php echo $enhorabuena_msg ? esc_html($enhorabuena_msg) : 'Has completado todas ' . esc_html($label_estacion_plural) . '.'; ?>
          </p>

          <?php if ($diploma_activo): ?>
            <div style="margin-top:16px;">
              <button type="button" id="gc-diploma-download-btn" style="display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border:0;border-radius:12px;background:#16a34a;color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:background 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descargar diploma
              </button>
            </div>
            <canvas id="gc-diploma-canvas" style="display:none;"></canvas>
            <script>
            (function(){
              var btn = document.getElementById('gc-diploma-download-btn');
              if (!btn) return;

              var userName  = <?php echo wp_json_encode($user_display); ?>;
              var escName   = <?php echo wp_json_encode($esc_title); ?>;
              var ranking   = <?php echo (int) $ranking_pos; ?>;
              var totalPts  = <?php echo (int) $user_total_pts; ?>;
              var diplomaMsg = <?php echo wp_json_encode($diploma_msg ?: ''); ?>;
              var fondoUrl  = <?php echo wp_json_encode($diploma_fondo ?: ''); ?>;
              var portadaUrl = <?php echo wp_json_encode($diploma_portada ?: ''); ?>;
              var mostrarPuntos = <?php echo $diploma_mostrar_puntos ? 'true' : 'false'; ?>;
              var pieActivo = <?php echo $diploma_pie_activo === '1' ? 'true' : 'false'; ?>;
              var pieTexto  = <?php echo wp_json_encode($diploma_pie_texto); ?>;
              var fecha     = new Date().toLocaleDateString('es-ES', {day:'numeric',month:'long',year:'numeric'});
              var font = function(w, s) { return w + ' ' + s + 'px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'; };

              // Dividir texto en líneas que quepan en maxW
              function wrapText(ctx, text, maxW) {
                var words = text.split(' '), lines = [], line = '';
                for (var i = 0; i < words.length; i++) {
                  var test = line + (line ? ' ' : '') + words[i];
                  if (ctx.measureText(test).width > maxW && line) {
                    lines.push(line); line = words[i];
                  } else { line = test; }
                }
                if (line) lines.push(line);
                return lines;
              }

              btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.textContent = 'Generando...';

                var canvas = document.getElementById('gc-diploma-canvas');
                var ctx = canvas.getContext('2d');
                var W = 800, H = 1200;
                canvas.width = W;
                canvas.height = H;

                function drawDiploma() {
                  // Fondo por defecto (si no hay imagen o antes de dibujar encima)
                  if (!fondoUrl) {
                    var grad = ctx.createLinearGradient(0, 0, W, H);
                    grad.addColorStop(0, '#f0fdf4');
                    grad.addColorStop(0.5, '#dcfce7');
                    grad.addColorStop(1, '#bbf7d0');
                    ctx.fillStyle = grad;
                    ctx.fillRect(0, 0, W, H);
                  }

                  // Marco decorativo
                  ctx.strokeStyle = '#16a34a';
                  ctx.lineWidth = 6;
                  ctx.strokeRect(24, 24, W - 48, H - 48);
                  ctx.strokeStyle = '#86efac';
                  ctx.lineWidth = 2;
                  ctx.strokeRect(36, 36, W - 72, H - 72);

                  ctx.textAlign = 'center';

                  // Titulo
                  ctx.fillStyle = '#14532d';
                  ctx.font = font('bold', 66);
                  ctx.fillText('\u00A1Enhorabuena!', W/2, 150);

                  // Separador
                  ctx.strokeStyle = '#86efac';
                  ctx.lineWidth = 2;
                  ctx.beginPath(); ctx.moveTo(W/2 - 140, 180); ctx.lineTo(W/2 + 140, 180); ctx.stroke();

                  // Nombre del usuario
                  ctx.fillStyle = '#166534';
                  ctx.font = font('bold', 56);
                  var nameLines = wrapText(ctx, userName, W - 120);
                  var nameY = 260;
                  for (var n = 0; n < nameLines.length; n++) {
                    ctx.fillText(nameLines[n], W/2, nameY + n * 66);
                  }
                  var afterName = nameY + nameLines.length * 66 + 24;

                  // "Ha completado el escenario"
                  ctx.fillStyle = '#334155';
                  ctx.font = font('normal', 38);
                  ctx.fillText('Ha completado el escenario', W/2, afterName);

                  // Nombre escenario
                  ctx.fillStyle = '#1e40af';
                  ctx.font = font('bold', 50);
                  var escLines = wrapText(ctx, escName, W - 120);
                  var escY = afterName + 70;
                  for (var e = 0; e < escLines.length; e++) {
                    ctx.fillText(escLines[e], W/2, escY + e * 60);
                  }
                  var afterEsc = escY + escLines.length * 60 + 40;

                  // Ranking y puntos (solo si hay gamificacion)
                  if (mostrarPuntos && totalPts > 0) {
                    ctx.fillStyle = '#334155';
                    ctx.font = font('bold', 36);
                    ctx.fillText('Posicion #' + ranking + '  \u00B7  ' + totalPts + ' puntos', W/2, afterEsc);
                    afterEsc += 60;
                  }

                  // Fecha
                  ctx.fillStyle = '#64748b';
                  ctx.font = font('normal', 30);
                  ctx.fillText(fecha, W/2, afterEsc);
                  afterEsc += 70;

                  // Mensaje diploma (premio / instrucciones) — sin fondo
                  var contentEndY = afterEsc;
                  if (diplomaMsg) {
                    ctx.fillStyle = '#991b1b';
                    ctx.font = font('bold', 34);
                    var msgLines = wrapText(ctx, diplomaMsg, W - 120);
                    var msgY = afterEsc;
                    for (var j = 0; j < msgLines.length; j++) {
                      ctx.fillText(msgLines[j], W/2, msgY + j * 46);
                    }
                    contentEndY = msgY + msgLines.length * 46 + 10;
                  }

                  // Portada del escenario (si hay) debajo del mensaje
                  if (portadaUrl) {
                    var pImg = new Image();
                    pImg.crossOrigin = 'anonymous';
                    pImg.onload = function() {
                      var maxImgH = H - 80 - contentEndY;
                      var maxImgW = W - 200;
                      if (maxImgH > 60 && maxImgW > 60) {
                        var s = Math.min(maxImgW / pImg.width, maxImgH / pImg.height);
                        var iw = pImg.width * s;
                        var ih = pImg.height * s;
                        var ix = (W - iw) / 2;
                        var iy = contentEndY + 10;
                        ctx.save();
                        var r = 10;
                        ctx.beginPath();
                        ctx.moveTo(ix + r, iy);
                        ctx.arcTo(ix + iw, iy, ix + iw, iy + ih, r);
                        ctx.arcTo(ix + iw, iy + ih, ix, iy + ih, r);
                        ctx.arcTo(ix, iy + ih, ix, iy, r);
                        ctx.arcTo(ix, iy, ix + iw, iy, r);
                        ctx.closePath();
                        ctx.clip();
                        ctx.drawImage(pImg, ix, iy, iw, ih);
                        ctx.restore();
                      }
                      finishAndDownload();
                    };
                    pImg.onerror = finishAndDownload;
                    pImg.src = portadaUrl;
                  } else {
                    finishAndDownload();
                  }
                }

                function finishAndDownload() {
                  // Pie (opcional)
                  if (pieActivo && pieTexto) {
                    ctx.fillStyle = '#94a3b8';
                    ctx.font = font('normal', 22);
                    ctx.fillText(pieTexto, W/2, H - 45);
                  }

                  // Descargar
                  var link = document.createElement('a');
                  link.download = 'diploma-' + escName.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase() + '.png';
                  link.href = canvas.toDataURL('image/png');
                  link.click();

                  btn.disabled = false;
                  btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Descargar diploma';
                }

                if (fondoUrl) {
                  var img = new Image();
                  img.crossOrigin = 'anonymous';
                  img.onload = function() {
                    // Dibujar imagen de fondo cubriendo todo el canvas
                    var scale = Math.max(W / img.width, H / img.height);
                    var sw = img.width * scale, sh = img.height * scale;
                    ctx.drawImage(img, (W - sw) / 2, (H - sh) / 2, sw, sh);
                    // Overlay blanco semitransparente para aclarar el fondo
                    ctx.fillStyle = 'rgba(255, 255, 255, 0.55)';
                    ctx.fillRect(0, 0, W, H);
                    drawDiploma();
                  };
                  img.onerror = function() {
                    fondoUrl = '';
                    drawDiploma();
                  };
                  img.src = fondoUrl;
                } else {
                  drawDiploma();
                }
              });
            })();
            </script>
          <?php endif; ?>
        </div>
      <?php
        endif;
      endif;
      ?>

    </div>
    <?php
    // Los logos NO se renderizan aquí en la portada del escenario porque la
    // plantilla de Divi añade un footer propio con el itinerario sticky.
    // En su lugar, wp_footer los imprime al final del body, después del
    // footer de Divi, que es donde el usuario los espera.
    ?>
  </div>

  <?php
  return ob_get_clean();
});
