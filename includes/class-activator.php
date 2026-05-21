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

    // Rol 'gc_guest' para jugadores sin registro
    if (function_exists('add_role')) {
        add_role('gc_guest', __('Jugador invitado', 'gincana-core'), [
            'read' => true,
        ]);
    }

    // Flush rewrite rules para registrar las rutas virtuales
    flush_rewrite_rules();
  }

  public static function deactivate() {
    // No borramos tablas al desactivar. Para borrar, usa uninstall.php
    flush_rewrite_rules();
  }
}

// Crear rol 'gc_guest' al inicio si aún no existe (cubre plugins ya activos
// donde activate() no se vuelve a ejecutar).
add_action('init', function () {
    if (!get_role('gc_guest') && function_exists('add_role')) {
        add_role('gc_guest', __('Jugador invitado', 'gincana-core'), ['read' => true]);
    }
}, 5);

// === Limpieza de guests inactivos ===
// Una vez al día, borra los usuarios con rol gc_guest creados hace más de 90 días
// y sin actividad reciente (sin filas en gincana_user_progress, gincana_attempts
// ni gincana_points_log).
add_action('init', function () {
    if (!wp_next_scheduled('gc_cleanup_guests')) {
        wp_schedule_event(time() + 3600, 'daily', 'gc_cleanup_guests');
    }
});

add_action('gc_cleanup_guests', function () {
    global $wpdb;
    $ttl_days = 90;
    $threshold = time() - ($ttl_days * DAY_IN_SECONDS);

    // Buscar guests creados antes del umbral
    $guests = get_users([
        'role'    => 'gc_guest',
        'fields'  => ['ID'],
        'number'  => 500, // por iteración
        'meta_query' => [[
            'key'     => 'gc_guest_creado',
            'value'   => $threshold,
            'compare' => '<',
            'type'    => 'NUMERIC',
        ]],
    ]);

    if (!$guests) return;

    $tbl_prog     = $wpdb->prefix . 'gincana_user_progress';
    $tbl_attempts = $wpdb->prefix . 'gincana_attempts';
    $tbl_points   = $wpdb->prefix . 'gincana_points_log';

    require_once ABSPATH . 'wp-admin/includes/user.php';

    foreach ($guests as $g) {
        $uid = (int) $g->ID;
        // ¿Tiene actividad reciente? Cualquier fila en cualquier tabla → conservar.
        $has = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM $tbl_prog WHERE user_id=%d LIMIT 1", $uid
        ));
        if (!$has) $has = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM $tbl_attempts WHERE user_id=%d LIMIT 1", $uid
        ));
        if (!$has) $has = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM $tbl_points WHERE user_id=%d LIMIT 1", $uid
        ));
        if ($has) continue;
        // Sin actividad → eliminar
        wp_delete_user($uid);
    }
});