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

      <div class="gc-qr-grid">
        <?php foreach ($stations as $sid):
          $sid   = (int) $sid;
          $order = (int) get_post_meta($sid, 'gc_orden', true);
          $title = get_the_title($sid) ?: ($label . ' ' . $order);
          $qr_url = function_exists('gc_get_qr_url') ? gc_get_qr_url($sid, $selected_esc) : get_permalink($sid);
          $pista  = get_post_meta($sid, 'gc_pista_busqueda', true);
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
    .gc-qr-card {
      border: 1px solid #dcdcde;
      border-radius: 12px;
      padding: 16px;
      background: #fff;
      text-align: center;
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
      .gc-qr-card {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 2px solid #333;
      }
      .gc-qr-url { display: none; }
    }
  </style>
  <?php
}
