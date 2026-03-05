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
  ?>
  <div class="wrap">
    <h1>Ajustes (próximamente)</h1>
    <p>Aquí añadiremos opciones como: tramos de tiempo, bonus primer intento, límite ranking, modo URLs, etc.</p>
  </div>
  <?php
}

/**
 * La vista "Usuarios & Hitos" la pintamos en admin-users.php → gincana_core_users_cb()
 */
