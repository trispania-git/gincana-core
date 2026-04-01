<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin menu "Gincana Core"
 * - Top-level + submenús
 */

add_action('admin_menu', function(){

  // Top-level
  add_menu_page(
    'Gincana Core',                    // Page title
    'Gincana Core',                    // Menu title
    'manage_options',                  // Capability
    'gincana-core',                    // Menu slug
    'gincana_core_dashboard_cb',       // Callback
    'dashicons-admin-site-alt3',       // Icon
    58                                 // Position
  );

  // Submenú: Panel (alias del top-level)
  add_submenu_page(
    'gincana-core',
    'Panel',
    'Panel',
    'manage_options',
    'gincana-core',
    'gincana_core_dashboard_cb'
  );

  // Submenú: Usuarios & Hitos
  add_submenu_page(
    'gincana-core',
    'Usuarios & Hitos',
    'Usuarios & Hitos',
    'manage_options',
    'gincana-users',
    'gincana_core_users_cb'
  );

  // (Eliminados los accesos directos a CPT para evitar duplicados)

  // Submenú: Ajustes (placeholder)
  add_submenu_page(
    'gincana-core',
    'Ajustes',
    'Ajustes',
    'manage_options',
    'gincana-settings',
    'gincana_core_settings_cb'
  );
});

/** ===== Callbacks ===== */

function gincana_core_dashboard_cb(){
  ?>
  <div class="wrap">
    <h1 style="margin-bottom:12px;">Gincana Core</h1>
    <p>Centro de control. Usa el menú de la izquierda para acceder a <strong>Usuarios & Hitos</strong> o a los tipos <em>Escenarios / Estaciones / Pruebas</em>.</p>
    <hr/>
    <h2>Atajos rápidos</h2>
    <p>
      <a class="button button-primary" href="<?php echo admin_url('admin.php?page=gincana-users'); ?>">Usuarios & Hitos</a>
      <a class="button" href="<?php echo admin_url('admin.php?page=gincana-settings'); ?>">Ajustes</a>
    </p>
  </div>
  <?php
}

function gincana_core_settings_cb(){
  // Guardar ajustes
  if (isset($_POST['gc_settings_nonce']) && wp_verify_nonce($_POST['gc_settings_nonce'], 'gc_save_settings')) {
    update_option('gc_mobile_only', isset($_POST['gc_mobile_only']) ? '1' : '0');
    update_option('gc_mobile_only_message', wp_kses_post($_POST['gc_mobile_only_message'] ?? ''));
    $slug = sanitize_title($_POST['gc_mobile_bypass_slug'] ?? 'accesogymk');
    update_option('gc_mobile_bypass_slug', $slug ?: 'accesogymk');
    echo '<div class="notice notice-success"><p>Ajustes guardados.</p></div>';
  }

  $mobile_only  = get_option('gc_mobile_only', '0');
  $mobile_msg   = get_option('gc_mobile_only_message', '');
  $bypass_slug  = get_option('gc_mobile_bypass_slug', 'accesogymk');
  ?>
  <div class="wrap">
    <h1>Ajustes de Gincana Core</h1>

    <form method="post">
      <?php wp_nonce_field('gc_save_settings', 'gc_settings_nonce'); ?>

      <table class="form-table">
        <tr>
          <th>Acceso solo desde móvil</th>
          <td>
            <label style="display:inline-flex;gap:8px;align-items:center;">
              <input type="checkbox" name="gc_mobile_only" value="1" <?php checked($mobile_only, '1'); ?> />
              <span>Mostrar aviso a usuarios de escritorio en el frontend</span>
            </label>
            <p class="description">Si se activa, los visitantes desde ordenador verán un mensaje indicando que la web es solo para móvil. El panel de administración (backend) no se ve afectado.</p>
          </td>
        </tr>
        <tr>
          <th><label for="gc_mobile_bypass_slug">URL de acceso escritorio</label></th>
          <td>
            <div style="display:flex;align-items:center;gap:4px;">
              <code style="font-size:13px;padding:6px 8px;background:#f1f5f9;border-radius:6px;"><?php echo esc_html(home_url('/')); ?></code>
              <input type="text" name="gc_mobile_bypass_slug" id="gc_mobile_bypass_slug"
                     value="<?php echo esc_attr($bypass_slug); ?>"
                     placeholder="accesogymk" style="width:200px;" />
            </div>
            <p class="description">
              URL secreta para acceder desde escritorio. Al visitarla se setea una cookie de 30 días y redirige al admin.<br>
              <strong>Enlace actual:</strong> <a href="<?php echo esc_url(home_url('/' . $bypass_slug)); ?>" target="_blank"><?php echo esc_html(home_url('/' . $bypass_slug)); ?></a>
            </p>
          </td>
        </tr>
        <tr>
          <th><label for="gc_mobile_only_message">Mensaje personalizado</label></th>
          <td>
            <textarea name="gc_mobile_only_message" id="gc_mobile_only_message" rows="4" style="width:100%;max-width:600px;"><?php echo esc_textarea($mobile_msg); ?></textarea>
            <p class="description">Mensaje que verán los usuarios de escritorio. Si se deja vacío se usa el texto por defecto.</p>
          </td>
        </tr>
      </table>

      <?php submit_button('Guardar ajustes'); ?>
    </form>
  </div>
  <?php
}

/**
 * La vista "Usuarios & Hitos" la pintamos en admin-users.php → gincana_core_users_cb()
 */
