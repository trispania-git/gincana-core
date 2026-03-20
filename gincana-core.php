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
require_once GINCANA_CORE_PATH . 'includes/metabox-escenario.php';
require_once GINCANA_CORE_PATH . 'includes/shortcode-estacion-acceso.php';

require_once GINCANA_CORE_PATH . 'includes/admin-import-csv.php';

// Hooks de activación/desactivación
register_activation_hook(__FILE__, ['Gincana_Core_Activator','activate']);
register_deactivation_hook(__FILE__, ['Gincana_Core_Activator','deactivate']);

add_action('wp_enqueue_scripts', function(){
  wp_register_script('gincana-inline', false);
  wp_enqueue_script('gincana-inline');
  wp_add_inline_script('gincana-inline', 'window.gincanaNonce = "'. esc_js( wp_create_nonce('wp_rest') ) .'";', 'before');
});