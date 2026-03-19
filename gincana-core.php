<?php
/**
 * Plugin Name: Gincana Core
 * Description: Lógica de escenarios, estaciones, pruebas y gamificación ligera (puntos, intentos, ranking) para la gimcana digital.
 * Version: 0.1.4
 * Author: Welow Marketing
 * Text Domain: gincana-core
 */

if ( ! defined('ABSPATH') ) exit;

define('GINCANA_CORE_VERSION', '0.1.4');
define('GINCANA_CORE_PATH', plugin_dir_path(__FILE__));
define('GINCANA_CORE_URL', plugin_dir_url(__FILE__));

// Carga includes
require_once GINCANA_CORE_PATH . 'includes/helpers.php';
require_once GINCANA_CORE_PATH . 'includes/shortcodes.php';
require_once GINCANA_CORE_PATH . 'includes/permalinks.php';
require_once GINCANA_CORE_PATH . 'includes/class-activator.php';
require_once GINCANA_CORE_PATH . 'includes/rest-routes.php';
require_once GINCANA_CORE_PATH . 'includes/admin-list.php';
require_once GINCANA_CORE_PATH . 'includes/admin-list-pruebas.php';
require_once GINCANA_CORE_PATH . 'includes/admin-menu.php';
require_once GINCANA_CORE_PATH . 'includes/admin-cpt-menu.php';
require_once GINCANA_CORE_PATH . 'includes/admin-users.php';
require_once GINCANA_CORE_PATH . 'includes/metabox-estacion.php';

// NUEVO: Importador CSV
require_once GINCANA_CORE_PATH . 'includes/admin-import-csv.php';

// Hooks de activación/desactivación
register_activation_hook(__FILE__, ['Gincana_Core_Activator','activate']);
register_deactivation_hook(__FILE__, ['Gincana_Core_Activator','deactivate']);

add_action('wp_enqueue_scripts', function(){
  // No carga archivo, solo imprime una variable JS con el nonce
  wp_register_script('gincana-inline', false);
  wp_enqueue_script('gincana-inline');
  wp_add_inline_script('gincana-inline', 'window.gincanaNonce = "'. esc_js( wp_create_nonce('wp_rest') ) .'";', 'before');
});

// (Opcional) Encolar assets front si luego lo necesitas
add_action('wp_enqueue_scripts', function(){
  // wp_enqueue_style('gincana-core', GINCANA_CORE_URL.'assets/gincana.css', [], GINCANA_CORE_VERSION);
  // wp_enqueue_script('gincana-core', GINCANA_CORE_URL.'assets/gincana.js', ['jquery'], GINCANA_CORE_VERSION, true);
});

// === DEBUG: comprobar registro de shortcodes (quitar luego) ===
add_action('init', function () {
  // Shortcode mínimo de prueba
  add_shortcode('test_ok', function(){ return '<div style="padding:8px;border:1px solid #0c0">SHORTCODE OK</div>'; });

  // Aviso en admin con el estado de gincana_prueba
  add_action('admin_notices', function(){
    $exists = shortcode_exists('gincana_prueba') ? 'SÍ' : 'NO';
    echo '<div class="notice notice-info"><p>Gincana Core: shortcode <code>gincana_prueba</code> registrado: <strong>'.$exists.'</strong></p></div>';
  });
});