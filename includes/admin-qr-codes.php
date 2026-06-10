<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin – Códigos QR por escenario
 * Muestra todas las URLs QR de las estaciones de un escenario,
 * genera códigos QR visuales y permite imprimir.
 */

add_action('admin_menu', function () {
  add_submenu_page(
    'gincana-core',
    'Códigos QR',
    'Códigos QR',
    'manage_options',
    'gincana-qr-codes',
    'gincana_core_render_qr_codes_page'
  );
});

function gincana_core_render_qr_codes_page() {
  if ( ! current_user_can('manage_options') ) {
    wp_die('No tienes permisos para acceder a esta página.');
  }

  $escenarios = get_posts([
    'post_type'       => 'escenario',
    'post_status'     => 'publish',
    'numberposts'     => -1,
    'orderby'         => 'title',
    'order'           => 'ASC',
    'fields'          => 'ids',
    'no_found_rows'   => true,
    'suppress_filters'=> true,
  ]);

  $selected_esc = isset($_GET['esc']) ? (int) $_GET['esc'] : 0;
  $qr_size = isset($_GET['qr_size']) ? max(100, min(400, (int)$_GET['qr_size'])) : 200;

  ?>
  <div class="wrap" id="gc-qr-page">
    <h1>Códigos QR</h1>
    <p>Selecciona un escenario para ver las URLs QR de todas sus estaciones. Puedes imprimir la página para obtener los QR.</p>

    <form method="get" style="margin:12px 0 20px;">
      <input type="hidden" name="page" value="gincana-qr-codes" />
      <label for="esc"><strong>Escenario:</strong></label>
      <select name="esc" id="esc" style="min-width:300px;">
        <option value="">— Selecciona escenario —</option>
        <?php foreach ($escenarios as $esc_id):
          $t = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
        ?>
          <option value="<?php echo (int)$esc_id; ?>" <?php selected($selected_esc, $esc_id); ?>><?php echo esc_html($t); ?></option>
        <?php endforeach; ?>
      </select>

      <label for="qr_size" style="margin-left:16px;"><strong>Tamaño QR:</strong></label>
      <select name="qr_size" id="qr_size">
        <option value="150" <?php selected($qr_size, 150); ?>>Pequeño (150px)</option>
        <option value="200" <?php selected($qr_size, 200); ?>>Medio (200px)</option>
        <option value="300" <?php selected($qr_size, 300); ?>>Grande (300px)</option>
        <option value="400" <?php selected($qr_size, 400); ?>>Muy grande (400px)</option>
      </select>

      <?php submit_button('Ver QR', 'primary', '', false); ?>
    </form>

    <?php if ($selected_esc):
      $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($selected_esc) : 'estación';
      $esc_title = get_the_title($selected_esc);

      $stations = get_posts([
        'post_type'      => 'estacion',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => [
          'relation' => 'AND',
          ['key'=>'gc_escenario_ref','value'=>$selected_esc,'compare'=>'='],
          ['key'=>'gc_orden','compare'=>'EXISTS'],
        ],
        'meta_key'       => 'gc_orden',
        'fields'         => 'ids',
        'no_found_rows'  => true,
      ]);

      if (empty($stations)):
        echo '<div class="notice notice-warning"><p>Este escenario no tiene estaciones.</p></div>';
      elseif (function_exists('gc_es_solo_pregunta') && gc_es_solo_pregunta($selected_esc)):
        echo '<div class="notice notice-info" style="padding:14px 16px;"><p style="margin:0;"><strong>Este escenario no usa códigos QR.</strong> Su tipo de mecánica es <em>&quot;Sin QR · Solo pregunta&quot;</em>, así que los jugadores acceden a las estaciones desde la lista del escenario y las validan respondiendo a una pregunta.</p></div>';
      else:
        $tipo_qr = function_exists('gc_get_tipo_qr') ? gc_get_tipo_qr($selected_esc) : 'enlace';

        // Asegurar que todas tengan token QR (necesario para modo validación)
        if ($tipo_qr === 'validacion') {
          foreach ($stations as $sid) {
            $token = get_post_meta((int)$sid, 'gc_qr_token', true);
            if (empty($token) && function_exists('gc_generate_station_token')) {
              $token = gc_generate_station_token((int)$sid);
              update_post_meta((int)$sid, 'gc_qr_token', $token);
              update_post_meta((int)$sid, 'gc_qr_url', gc_get_station_entry_url((int)$sid));
            }
          }
        }

        $tipo_qr_label = ($tipo_qr === 'validacion') ? 'Validación (con token)' : 'Enlace directo';
    ?>

      <div class="gc-qr-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <div>
          <h2 style="margin:0;"><?php echo esc_html($esc_title); ?> — <?php echo count($stations); ?> <?php echo esc_html($label); ?>s</h2>
          <p style="margin:4px 0 0;font-size:13px;color:#64748b;">Tipo de QR: <strong><?php echo esc_html($tipo_qr_label); ?></strong></p>
        </div>
        <button type="button" class="button button-primary" onclick="window.print();">🖨️ Imprimir QR</button>
      </div>

      <?php
        // QR del escenario (punto de entrada al juego)
        $esc_url     = get_permalink($selected_esc);
        $esc_qr_size = (int) round($qr_size * 1.25); // un poco mayor que el de las estaciones
        $esc_qr_img  = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $esc_qr_size . 'x' . $esc_qr_size . '&data=' . urlencode($esc_url) . '&format=png&margin=8';
      ?>
      <div class="gc-qr-grid gc-qr-grid-escenario">
        <div class="gc-qr-card gc-qr-card-escenario">
          <div class="gc-qr-card-header">
            <span class="gc-qr-order gc-qr-order-escenario">▶</span>
            <span class="gc-qr-title"><?php echo esc_html($esc_title); ?></span>
            <span class="gc-qr-badge">Inicio · Escenario</span>
          </div>
          <div class="gc-qr-img">
            <img src="<?php echo esc_url($esc_qr_img); ?>" alt="QR <?php echo esc_attr($esc_title); ?>" width="<?php echo (int)$esc_qr_size; ?>" height="<?php echo (int)$esc_qr_size; ?>" />
          </div>
          <div class="gc-qr-pista" style="background:#eef2ff;border-color:#a5b4fc;color:#3730a3;">📍 Coloca este QR en la entrada o en el punto de salida del juego.</div>
          <div class="gc-qr-url"><?php echo esc_html($esc_url); ?></div>
        </div>
      </div>

      <h3 class="gc-qr-subhead"><?php echo esc_html(ucfirst($label)); ?>s · <?php echo count($stations); ?></h3>

      <div class="gc-qr-grid">
        <?php foreach ($stations as $sid):
          $sid   = (int) $sid;
          $order = (int) get_post_meta($sid, 'gc_orden', true);
          $title = get_the_title($sid) ?: ($label . ' ' . $order);
          $qr_url = function_exists('gc_get_qr_url') ? gc_get_qr_url($sid, $selected_esc) : get_permalink($sid);
          $pista  = get_post_meta($sid, 'gc_pista_busqueda', true);

          // ¿La estación tiene una prueba de "acción externa por QR"? Si es así,
          // generamos DOS QR (Acierto/Fallo) con el parámetro gc_result.
          $accion_prueba = get_posts([
            'post_type'      => 'prueba',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
              'relation' => 'AND',
              ['key' => 'gc_estacion_ref', 'value' => $sid, 'compare' => '='],
              ['key' => 'gc_tipo', 'value' => 'accion_qr', 'compare' => '='],
            ],
            'fields'         => 'ids',
            'no_found_rows'  => true,
          ]);
          if (!empty($accion_prueba)):
            $url_ok = add_query_arg('gc_result', 'ok', $qr_url);
            $url_ko = add_query_arg('gc_result', 'ko', $qr_url);
            $img_ok = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qr_size . 'x' . $qr_size . '&data=' . urlencode($url_ok) . '&format=png&margin=8';
            $img_ko = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qr_size . 'x' . $qr_size . '&data=' . urlencode($url_ko) . '&format=png&margin=8';
        ?>
          <div class="gc-qr-card" style="border-color:#16a34a;">
            <div class="gc-qr-card-header">
              <span class="gc-qr-order"><?php echo (int)$order; ?></span>
              <span class="gc-qr-title"><?php echo esc_html($title); ?> — ✅ Acierto</span>
            </div>
            <div class="gc-qr-img">
              <img src="<?php echo esc_url($img_ok); ?>" alt="QR Acierto <?php echo esc_attr($title); ?>" width="<?php echo (int)$qr_size; ?>" height="<?php echo (int)$qr_size; ?>" />
            </div>
            <div class="gc-qr-pista" style="background:#f0fdf4;border-color:#86efac;color:#166534;">✅ Escanéalo si el jugador ACIERTA.</div>
            <div class="gc-qr-url"><?php echo esc_html($url_ok); ?></div>
          </div>
          <div class="gc-qr-card" style="border-color:#f59e0b;">
            <div class="gc-qr-card-header">
              <span class="gc-qr-order"><?php echo (int)$order; ?></span>
              <span class="gc-qr-title"><?php echo esc_html($title); ?> — ❌ Fallo</span>
            </div>
            <div class="gc-qr-img">
              <img src="<?php echo esc_url($img_ko); ?>" alt="QR Fallo <?php echo esc_attr($title); ?>" width="<?php echo (int)$qr_size; ?>" height="<?php echo (int)$qr_size; ?>" />
            </div>
            <div class="gc-qr-pista" style="background:#fffbeb;border-color:#fcd34d;color:#92400e;">❌ Escanéalo si el jugador FALLA.</div>
            <div class="gc-qr-url"><?php echo esc_html($url_ko); ?></div>
          </div>
        <?php else:
          // QR Server API (gratuita, sin dependencias)
          $qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qr_size . 'x' . $qr_size . '&data=' . urlencode($qr_url) . '&format=png&margin=8';
        ?>
          <div class="gc-qr-card">
            <div class="gc-qr-card-header">
              <span class="gc-qr-order"><?php echo (int)$order; ?></span>
              <span class="gc-qr-title"><?php echo esc_html($title); ?></span>
            </div>
            <div class="gc-qr-img">
              <img src="<?php echo esc_url($qr_img); ?>" alt="QR <?php echo esc_attr($title); ?>" width="<?php echo (int)$qr_size; ?>" height="<?php echo (int)$qr_size; ?>" />
            </div>
            <?php if ($pista): ?>
              <div class="gc-qr-pista">💡 <?php echo esc_html($pista); ?></div>
            <?php endif; ?>
            <div class="gc-qr-url"><?php echo esc_html($qr_url); ?></div>
          </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>

    <?php
      endif; // empty stations
    endif; // selected_esc
    ?>
  </div>

  <style>
    .gc-qr-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }
    .gc-qr-grid-escenario {
      grid-template-columns: 1fr;
      max-width: 520px;
      margin: 0 auto 24px;
    }
    .gc-qr-subhead {
      margin: 24px 0 12px;
      padding-bottom: 8px;
      border-bottom: 2px solid #e2e8f0;
      font-size: 16px;
      color: #334155;
    }
    .gc-qr-card {
      border: 1px solid #dcdcde;
      border-radius: 12px;
      padding: 16px;
      background: #fff;
      text-align: center;
    }
    .gc-qr-card-escenario {
      border: 2px solid #6366f1;
      background: linear-gradient(180deg,#eef2ff 0%,#fff 60%);
      box-shadow: 0 2px 10px rgba(99,102,241,0.15);
    }
    .gc-qr-order-escenario {
      background: #6366f1 !important;
      font-size: 18px !important;
    }
    .gc-qr-badge {
      margin-left: auto;
      padding: 4px 10px;
      border-radius: 999px;
      background: #6366f1;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .gc-qr-card-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 12px;
    }
    .gc-qr-order {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #2563eb;
      color: #fff;
      font-weight: 700;
      font-size: 14px;
      flex-shrink: 0;
    }
    .gc-qr-title {
      font-weight: 600;
      font-size: 15px;
      text-align: left;
    }
    .gc-qr-img {
      margin: 8px 0;
    }
    .gc-qr-img img {
      border: 4px solid #fff;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
      border-radius: 8px;
    }
    .gc-qr-pista {
      margin: 8px 0 0;
      font-size: 12px;
      color: #92400e;
      background: #fffbeb;
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px dashed #f59e0b;
    }
    .gc-qr-url {
      margin-top: 8px;
      font-size: 10px;
      color: #94a3b8;
      word-break: break-all;
      line-height: 1.3;
    }

    /* Estilos de impresión */
    @media print {
      /* Ocultar todo menos los QR */
      #wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap,
      #wpfooter, .notice, .update-nag,
      .gc-qr-header button,
      #gc-qr-page > p,
      #gc-qr-page > form { display: none !important; }

      #wpcontent, #wpbody, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
      .wrap { max-width: 100% !important; }

      .gc-qr-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
      }
      .gc-qr-grid-escenario {
        grid-template-columns: 1fr !important;
        max-width: 100% !important;
      }
      .gc-qr-card-escenario {
        page-break-after: always;
        break-after: page;
        border: 3px solid #333 !important;
        background: #fff !important;
        box-shadow: none !important;
      }
      .gc-qr-card {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 2px solid #333;
      }
      .gc-qr-url { display: none; }
      .gc-qr-badge {
        background: #333 !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
  </style>
  <?php
}
