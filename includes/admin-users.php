<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Vista administrativa: Usuarios & Hitos
 * - Filtro por Escenario
 * - Tabla de usuarios con: total puntos, estaciones completadas, mejor tiempo medio
 * - Detalle por usuario (click) con log de puntos y progreso por estación
 */

function gincana_core_users_cb(){
  if ( ! current_user_can('manage_options') ) {
    wp_die('No tienes permisos suficientes.');
  }

  global $wpdb;
  $tbl_points = $wpdb->prefix . 'gincana_points_log';
  $tbl_prog   = $wpdb->prefix . 'gincana_user_progress';

  // ====== Procesar acciones de borrado ======
  if (isset($_POST['gc_reset_action']) && check_admin_referer('gc_reset_data')) {
    $action      = sanitize_text_field($_POST['gc_reset_action']);
    $reset_esc   = (int) ($_POST['gc_reset_escenario'] ?? 0);
    $reset_user  = (int) ($_POST['gc_reset_user'] ?? 0);

    $deleted_pts  = 0;
    $deleted_prog = 0;

    if ($action === 'reset_user_escenario' && $reset_user && $reset_esc) {
      // Vaciar datos de UN usuario en UN escenario
      $deleted_pts  = (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM $tbl_points WHERE user_id=%d AND escenario_id=%d", $reset_user, $reset_esc
      ));
      $deleted_prog = (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM $tbl_prog WHERE user_id=%d AND escenario_id=%d", $reset_user, $reset_esc
      ));
    } elseif ($action === 'reset_user_all' && $reset_user) {
      // Vaciar TODOS los datos de un usuario
      $deleted_pts  = (int) $wpdb->query($wpdb->prepare("DELETE FROM $tbl_points WHERE user_id=%d", $reset_user));
      $deleted_prog = (int) $wpdb->query($wpdb->prepare("DELETE FROM $tbl_prog WHERE user_id=%d", $reset_user));
    } elseif ($action === 'reset_escenario' && $reset_esc) {
      // Vaciar TODOS los datos de un escenario (todos los usuarios)
      $deleted_pts  = (int) $wpdb->query($wpdb->prepare("DELETE FROM $tbl_points WHERE escenario_id=%d", $reset_esc));
      $deleted_prog = (int) $wpdb->query($wpdb->prepare("DELETE FROM $tbl_prog WHERE escenario_id=%d", $reset_esc));
    }

    if ($deleted_pts || $deleted_prog) {
      echo '<div class="notice notice-success is-dismissible"><p>Datos eliminados: <strong>' . $deleted_pts . '</strong> registros de puntos y <strong>' . $deleted_prog . '</strong> registros de progreso.</p></div>';
    } else {
      echo '<div class="notice notice-warning is-dismissible"><p>No se encontraron datos que eliminar.</p></div>';
    }
  }

  // ====== Filtros ======
  $escenario_id = isset($_GET['esc']) ? (int) $_GET['esc'] : 0;
  $focus_user   = isset($_GET['user']) ? (int) $_GET['user'] : 0;

  // Cargar escenarios (selector)
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

  // ====== Render encabezado ======
  ?>
  <div class="wrap">
    <h1>Usuarios & Hitos</h1>

    <form method="get" style="margin:12px 0;">
      <input type="hidden" name="page" value="gincana-users"/>
      <label for="esc"><strong>Escenario:</strong></label>
      <select name="esc" id="esc">
        <option value="">— Todos —</option>
        <?php foreach ($escenarios as $e): ?>
          <option value="<?php echo (int)$e; ?>" <?php selected($escenario_id, (int)$e); ?>>
            <?php echo esc_html( get_the_title($e) ?: ('Escenario #'.$e) ); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($focus_user): ?>
        <input type="hidden" name="user" value="<?php echo (int)$focus_user; ?>"/>
      <?php endif; ?>
      <button class="button">Filtrar</button>
      <?php if ($escenario_id || $focus_user): ?>
        <a class="button" href="<?php echo admin_url('admin.php?page=gincana-users'); ?>">Limpiar</a>
      <?php endif; ?>
    </form>

    <?php if ($escenario_id && !$focus_user): ?>
    <form method="post" style="margin:0 0 16px;" onsubmit="return confirm('¿Seguro? Esto eliminara TODOS los puntos y progreso de TODOS los usuarios en este escenario. Esta accion no se puede deshacer.');">
      <?php wp_nonce_field('gc_reset_data'); ?>
      <input type="hidden" name="gc_reset_action" value="reset_escenario" />
      <input type="hidden" name="gc_reset_escenario" value="<?php echo (int)$escenario_id; ?>" />
      <button type="submit" class="button" style="color:#dc2626;border-color:#dc2626;">
        Vaciar datos del escenario &laquo;<?php echo esc_html(get_the_title($escenario_id)); ?>&raquo;
      </button>
    </form>
    <?php endif; ?>
  <?php

  // ====== Vista detalle de un usuario ======
  if ($focus_user) {
    $user = get_user_by('id', $focus_user);
    if (!$user) {
      echo '<div class="notice notice-error"><p>Usuario no encontrado.</p></div></div>';
      return;
    }

    $user_name = $user->display_name ?: $user->user_login;

    // Total puntos (escenario opcional)
    if ($escenario_id) {
      $total_points = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(points),0) FROM $tbl_points WHERE user_id=%d AND escenario_id=%d",
        $focus_user, $escenario_id
      ));
    } else {
      $total_points = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(points),0) FROM $tbl_points WHERE user_id=%d",
        $focus_user
      ));
    }

    echo '<h2>Detalle de usuario: '.esc_html($user_name).' (ID '.$focus_user.')</h2>';
    echo '<p><strong>Total puntos:</strong> '.$total_points.'</p>';

    // Progreso por estación (solo las del escenario si está filtrado)
    $where_esc = $escenario_id ? $wpdb->prepare("AND escenario_id=%d", $escenario_id) : '';
    $rows_prog = $wpdb->get_results($wpdb->prepare("
      SELECT estacion_id, escenario_id, status, best_time_ms, attempts
      FROM $tbl_prog
      WHERE user_id=%d $where_esc
      ORDER BY escenario_id ASC, estacion_id ASC
    ", $focus_user));

    echo '<h3>Progreso por estación</h3>';
    if ($rows_prog) {
      echo '<table class="widefat striped"><thead><tr>
              <th>Escenario</th><th>Estación</th><th>Estado</th><th>Mejor tiempo</th><th>Intentos</th>
            </tr></thead><tbody>';
      foreach ($rows_prog as $r) {
        $esc_t = get_the_title((int)$r->escenario_id) ?: ('#'.$r->escenario_id);
        $est_t = get_the_title((int)$r->estacion_id) ?: ('#'.$r->estacion_id);
        $best  = is_null($r->best_time_ms) ? '—' : ( floor((int)$r->best_time_ms / 1000).' s' );
        echo '<tr>
          <td>'.esc_html($esc_t).'</td>
          <td><a href="'.esc_url( get_edit_post_link((int)$r->estacion_id) ).'">'.esc_html($est_t).'</a></td>
          <td>'.esc_html($r->status).'</td>
          <td style="text-align:right;">'.$best.'</td>
          <td style="text-align:right;">'.(int)$r->attempts.'</td>
        </tr>';
      }
      echo '</tbody></table>';
    } else {
      echo '<p>No hay progreso registrado para este usuario.</p>';
    }

    // Log de puntos
    $rows_pts = $wpdb->get_results($wpdb->prepare("
      SELECT id, escenario_id, estacion_id, points, reason, created_at
      FROM $tbl_points
      WHERE user_id=%d
      ".($escenario_id ? "AND escenario_id=".(int)$escenario_id : "")."
      ORDER BY created_at DESC
      LIMIT 200
    ", $focus_user));

    echo '<h3 style="margin-top:20px;">Últimos puntos</h3>';
    if ($rows_pts) {
      echo '<table class="widefat striped"><thead><tr>
              <th>Fecha</th><th>Escenario</th><th>Estación</th><th style="text-align:right;">Puntos</th><th>Motivo</th>
            </tr></thead><tbody>';
      foreach ($rows_pts as $r) {
        $esc_t = $r->escenario_id ? ( get_the_title((int)$r->escenario_id) ?: ('#'.$r->escenario_id) ) : '—';
        $est_t = $r->estacion_id ? ( get_the_title((int)$r->estacion_id) ?: ('#'.$r->estacion_id) ) : '—';
        $date  = $r->created_at ?: '';
        echo '<tr>
          <td>'.esc_html($date).'</td>
          <td>'.esc_html($esc_t).'</td>
          <td>'.esc_html($est_t).'</td>
          <td style="text-align:right;">'.(int)$r->points.'</td>
          <td>'.esc_html($r->reason ?: '').'</td>
        </tr>';
      }
      echo '</tbody></table>';
    } else {
      echo '<p>No hay movimientos de puntos para este usuario.</p>';
    }

    // Botones de accion
    echo '<div style="margin-top:20px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;background:#fefce8;">';
    echo '<h3 style="margin:0 0 10px;color:#92400e;">Acciones</h3>';

    if ($escenario_id) {
      echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'¿Seguro? Se eliminaran los puntos y progreso de este usuario en el escenario seleccionado.\');">';
      wp_nonce_field('gc_reset_data');
      echo '<input type="hidden" name="gc_reset_action" value="reset_user_escenario" />';
      echo '<input type="hidden" name="gc_reset_escenario" value="'.(int)$escenario_id.'" />';
      echo '<input type="hidden" name="gc_reset_user" value="'.(int)$focus_user.'" />';
      echo '<button type="submit" class="button" style="color:#dc2626;border-color:#dc2626;">Vaciar datos de este usuario en &laquo;'.esc_html(get_the_title($escenario_id)).'&raquo;</button>';
      echo '</form> ';
    }

    echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'¿Seguro? Se eliminaran TODOS los puntos y progreso de este usuario en TODOS los escenarios. Esta accion no se puede deshacer.\');">';
    wp_nonce_field('gc_reset_data');
    echo '<input type="hidden" name="gc_reset_action" value="reset_user_all" />';
    echo '<input type="hidden" name="gc_reset_user" value="'.(int)$focus_user.'" />';
    echo '<button type="submit" class="button" style="color:#dc2626;border-color:#dc2626;">Vaciar TODOS los datos de este usuario</button>';
    echo '</form>';
    echo '</div>';

    echo '<p style="margin-top:16px;"><a class="button" href="'.admin_url('admin.php?page=gincana-users&esc='.$escenario_id).'">Volver</a></p>';
    echo '</div>'; // wrap
    return;
  }

  // ====== Vista “lista” de usuarios (ranking por escenario o global) ======
  $where_esc = $escenario_id ? $wpdb->prepare("WHERE escenario_id=%d", $escenario_id) : '';

  $rows = $wpdb->get_results("
    SELECT user_id, SUM(points) AS total_points
    FROM $tbl_points
    $where_esc
    GROUP BY user_id
    HAVING total_points > 0
    ORDER BY total_points DESC
    LIMIT 500
  ");

  if (!$rows) {
    echo '<p>No hay puntos registrados todavía.</p></div>';
    return;
  }

  // Precalcular estaciones completadas por usuario (y promedio de mejor tiempo)
  $user_ids = array_map('intval', wp_list_pluck($rows, 'user_id'));
  $in_users = implode(',', $user_ids);
  $where_esc2 = $escenario_id ? $wpdb->prepare("AND escenario_id=%d", $escenario_id) : '';

  $prog_rows = $wpdb->get_results("
    SELECT user_id, COUNT(*) AS passed_count,
           AVG(NULLIF(best_time_ms,0)) AS avg_best_ms
    FROM $tbl_prog
    WHERE status='passed' ".($where_esc2 ? $where_esc2 : '')."
      AND user_id IN ($in_users)
    GROUP BY user_id
  ");

  $map_prog = [];
  foreach ($prog_rows as $p) {
    $map_prog[(int)$p->user_id] = [
      'passed' => (int)$p->passed_count,
      'avg_ms' => is_null($p->avg_best_ms) ? null : (int)$p->avg_best_ms
    ];
  }

  echo '<h2>Ranking '.($escenario_id ? 'del escenario: '.esc_html(get_the_title($escenario_id)) : 'global').'</h2>';
  echo '<table class="widefat striped"><thead><tr>
          <th style="width:60px;">#</th>
          <th>Usuario</th>
          <th style="text-align:right;">Puntos</th>
          <th style="text-align:right;">Estaciones completadas</th>
          <th style="text-align:right;">Mejor tiempo medio</th>
          <th style="text-align:right;">Acciones</th>
        </tr></thead><tbody>';

  $pos = 1;
  foreach ($rows as $r) {
    $uid  = (int)$r->user_id;
    $user = get_user_by('id', $uid);
    $name = $user ? ($user->display_name ?: $user->user_login) : ('Usuario #'.$uid);
    $pp   = (int)$r->total_points;

    $passed = isset($map_prog[$uid]['passed']) ? (int)$map_prog[$uid]['passed'] : 0;
    $avgms  = isset($map_prog[$uid]['avg_ms']) ? (int)$map_prog[$uid]['avg_ms'] : null;

    echo '<tr>
      <td>'.$pos.'</td>
      <td>'.esc_html($name).' <span style="color:#999">(#'.$uid.')</span></td>
      <td style="text-align:right;">'.$pp.'</td>
      <td style="text-align:right;">'.$passed.'</td>
      <td style="text-align:right;">'.( is_null($avgms) ? '—' : (floor($avgms/1000).' s') ).'</td>
      <td style="text-align:right;white-space:nowrap;">
        <a class="button" href="'.admin_url('admin.php?page=gincana-users&esc='.$escenario_id.'&user='.$uid).'">Ver detalle</a>
      </td>
    </tr>';
    $pos++;
  }

  echo '</tbody></table>';
  echo '</div>'; // wrap
}
