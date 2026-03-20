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

  ob_start();
  ?>
  <div class="gc-escenario-content" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">

    <h2 style="margin:0 0 12px;font-size:22px;font-weight:700;line-height:1.3;"><?php echo esc_html($title); ?></h2>

    <?php if ($descripcion): ?>
      <div style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;">
        <?php echo wp_kses_post($descripcion); ?>
      </div>
    <?php endif; ?>

    <?php if ($audio): ?>
      <div style="margin:0 0 16px;">
        <audio controls style="width:100%;"><source src="<?php echo esc_url($audio); ?>">Tu navegador no soporta audio HTML5.</audio>
      </div>
    <?php endif; ?>

    <?php if ($img1 || $img2): ?>
      <div style="display:flex;flex-direction:column;gap:12px;margin:0 0 24px;">
        <?php if ($img1): ?><img src="<?php echo esc_url($img1); ?>" alt="" style="width:100%;height:auto;border-radius:10px;"><?php endif; ?>
        <?php if ($img2): ?><img src="<?php echo esc_url($img2); ?>" alt="" style="width:100%;height:auto;border-radius:10px;"><?php endif; ?>
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

  $tipo_escenario = get_post_meta($escenario_id, 'gc_tipo_escenario', true) ?: 'adulto';

  // ── Obtener estaciones ordenadas ───────────────────────
  $q = new WP_Query([
    'post_type'      => 'estacion',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value_num',
    'order'          => 'ASC',
    'meta_query'     => [['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'=']],
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
  }

  // Calcular siguiente desbloqueada
  $next_unlocked = 0;
  foreach ($est_ids as $i => $eid) {
    if (!empty($progress[$eid]) && $progress[$eid] === 'passed') continue;
    $prev_ok = ($i === 0) || (!empty($progress[$est_ids[$i-1]]) && $progress[$est_ids[$i-1]] === 'passed');
    if ($prev_ok) { $next_unlocked = $eid; break; }
  }

  // Contar completadas
  $completed = 0;
  foreach ($est_ids as $eid) {
    if (!empty($progress[$eid]) && $progress[$eid] === 'passed') $completed++;
  }
  $total = count($est_ids);
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

    <?php if ($user_id): ?>
    <div class="gc-progress-wrap">
      <div class="gc-progress-bar">
        <div class="gc-progress-fill" style="width:<?php echo (int)$pct; ?>%;"></div>
      </div>
      <div class="gc-progress-label">
        <?php echo (int)$completed; ?>/<?php echo (int)$total; ?> estaciones completadas
      </div>
    </div>
    <?php endif; ?>

    <div class="gc-cards">
      <?php foreach ($est_ids as $i => $eid):
        $order  = (int) get_post_meta($eid, 'gc_orden', true) ?: ($i + 1);
        $title  = get_the_title($eid) ?: ('Estacion ' . $order);
        $url    = get_permalink($eid);

        $is_passed  = !empty($progress[$eid]) && $progress[$eid] === 'passed';
        $is_current = ($eid === $next_unlocked);
        $is_locked  = !$is_passed && !$is_current;

        // Estado visual
        if ($is_passed) {
          $icon_bg     = '#16a34a';
          $icon_fg     = '#ffffff';
          $icon_text   = '&#10003;'; // checkmark
          $status_text = 'Completada';
          $status_cls  = 'passed';
          $card_cls    = '';
        } elseif ($is_current) {
          $icon_bg     = '#2563eb';
          $icon_fg     = '#ffffff';
          $icon_text   = (string)$order;
          $status_text = ($tipo_escenario === 'infantil') ? 'Siguiente puerta' : 'Siguiente estacion';
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

        $tag = ($is_passed || $is_current) ? 'a' : 'div';
        $href = ($tag === 'a') ? ' href="' . esc_url($url) . '"' : '';
      ?>

        <<?php echo $tag; ?> class="gc-card <?php echo esc_attr($card_cls); ?>"<?php echo $href; ?>>
          <div class="gc-card-icon" style="background:<?php echo $icon_bg; ?>;">
            <span style="color:<?php echo $icon_fg; ?>;line-height:1;"><?php echo $icon_text; ?></span>
          </div>
          <div class="gc-card-body">
            <div class="gc-card-title"><?php echo esc_html($title); ?></div>
            <div class="gc-card-status <?php echo esc_attr($status_cls); ?>"><?php echo esc_html($status_text); ?></div>
          </div>
          <?php if ($tag === 'a'): ?>
            <svg class="gc-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          <?php endif; ?>
        </<?php echo $tag; ?>>

      <?php endforeach; ?>
    </div>
  </div>

  <?php
  return ob_get_clean();
});
