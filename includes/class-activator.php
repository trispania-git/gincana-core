<?php
if ( ! defined('ABSPATH') ) exit;

class Gincana_Core_Activator {

  public static function activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    // 1) Progreso
    $sql1 = "CREATE TABLE {$wpdb->prefix}gincana_user_progress (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      escenario_id BIGINT UNSIGNED NOT NULL,
      estacion_id BIGINT UNSIGNED NOT NULL,
      status ENUM('locked','in_progress','passed','bypass') NOT NULL DEFAULT 'locked',
      points_earned INT NOT NULL DEFAULT 0,
      attempts INT NOT NULL DEFAULT 0,
      best_time_ms BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY ux_user_esc_est (user_id, escenario_id, estacion_id),
      KEY k_esc_est (escenario_id, estacion_id),
      KEY k_status (status)
    ) $charset;";

    // 2) Intentos
    $sql2 = "CREATE TABLE {$wpdb->prefix}gincana_attempts (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      prueba_id BIGINT UNSIGNED NOT NULL,
      escenario_id BIGINT UNSIGNED NOT NULL,
      estacion_id BIGINT UNSIGNED NOT NULL,
      result ENUM('success','fail') NOT NULL,
      time_ms BIGINT UNSIGNED NOT NULL,
      payload_json LONGTEXT NULL,
      ip_hash VARCHAR(128) NULL,
      ua_hash VARCHAR(128) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY k_user_prueba (user_id, prueba_id),
      KEY k_esc_est (escenario_id, estacion_id),
      KEY k_result (result)
    ) $charset;";

    // 3) Puntos
    $sql3 = "CREATE TABLE {$wpdb->prefix}gincana_points_log (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id BIGINT UNSIGNED NOT NULL,
      escenario_id BIGINT UNSIGNED NOT NULL,
      estacion_id BIGINT UNSIGNED NULL,
      points INT NOT NULL,
      reason VARCHAR(120) NOT NULL,
      meta_json LONGTEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY k_user_esc (user_id, escenario_id),
      KEY k_esc (escenario_id)
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);

    update_option('gincana_db_version','1.0.0');
  }

  public static function deactivate() {
    // No borramos tablas al desactivar. Para borrar, usa uninstall.php
  }
}