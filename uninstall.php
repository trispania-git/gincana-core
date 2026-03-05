<?php
// if uninstall not called from WordPress, exit
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;

global $wpdb;
// Descomenta para borrar todo en desinstalación:
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gincana_user_progress");
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gincana_attempts");
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gincana_points_log");
delete_option('gincana_db_version');