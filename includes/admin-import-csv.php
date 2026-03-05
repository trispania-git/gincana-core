<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin – Importador CSV (Escenario -> Estaciones -> Pruebas)
 *
 * Arquitectura del plugin:
 * - Estación -> Escenario: gc_escenario_ref (ID)
 * - Estación -> Prueba:    gc_prueba_ref (ID)
 * - Orden estación:        gc_orden (NUMERIC)
 *
 * Meta escenario para nº estaciones: gc_num_estaciones
 *
 * CSV esperado (cabeceras obligatorias):
 * station_slug,station_title,station_order,test_slug,test_title
 *
 * Cabeceras opcionales (si vienen, se guardan como meta en la prueba):
 * question,answer,points
 */

add_action('admin_menu', function () {
  $parent_slug = 'gincana-core';

  add_submenu_page(
    $parent_slug,
    'Importar CSV',
    'Importar CSV',
    'manage_options',
    'gincana-import-csv',
    'gincana_core_render_import_csv_page'
  );
});

function gincana_core_render_import_csv_page() {
  if ( ! current_user_can('manage_options') ) {
    wp_die('No tienes permisos para acceder a esta página.');
  }

  $result = null;
  $errors = [];

  if ( isset($_POST['gincana_csv_submit']) ) {
    check_admin_referer('gincana_import_csv_action', 'gincana_import_csv_nonce');

    $escenario_id = isset($_POST['gincana_escenario_id']) ? (int) $_POST['gincana_escenario_id'] : 0;
    $replace_mode = ! empty($_POST['gincana_replace_mode']) ? true : false;

    if ( $escenario_id <= 0 ) $errors[] = 'Selecciona un escenario.';
    if ( empty($_FILES['gincana_csv_file']) || empty($_FILES['gincana_csv_file']['tmp_name']) ) $errors[] = 'Sube un fichero CSV.';

    if ( empty($errors) ) {
      $file = $_FILES['gincana_csv_file'];
      $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
      if ( $ext !== 'csv' ) {
        $errors[] = 'El fichero debe ser .csv';
      } else {
        $result = gincana_core_handle_csv_import($escenario_id, $file['tmp_name'], $replace_mode);
        if ( ! empty($result['errors']) ) $errors = array_merge($errors, $result['errors']);
      }
    }
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

  $selected_esc = isset($_POST['gincana_escenario_id']) ? (int) $_POST['gincana_escenario_id'] : 0;
  $selected_replace = ! empty($_POST['gincana_replace_mode']) ? true : false;

  echo '<div class="wrap">';
  echo '<h1>Importar CSV</h1>';
  echo '<p>Importa estaciones y pruebas en bloque dentro de un escenario. El importador <strong>crea o actualiza</strong> por <code>slug</code> y enlaza <code>gc_prueba_ref</code> en cada estación.</p>';

  if ( ! empty($errors) ) {
    echo '<div class="notice notice-error"><p><strong>Errores:</strong></p><ul style="margin-left:18px;list-style:disc;">';
    foreach ($errors as $e) echo '<li>'.esc_html($e).'</li>';
    echo '</ul></div>';
  }

  if ( is_array($result) && empty($errors) ) {
    echo '<div class="notice notice-success"><p><strong>Importación completada</strong></p></div>';

    echo '<h2>Resumen</h2>';
    echo '<ul style="margin-left:18px;list-style:disc;">';
    echo '<li>Modo: <strong>'.($result['replace_mode'] ? 'Reemplazar (borrado previo)' : 'Actualizar/Crear (sin borrado)').'</strong></li>';
    echo '<li>Filas procesadas: <strong>'.(int)$result['rows'].'</strong></li>';
    echo '<li>Estaciones borradas: <strong>'.(int)$result['stations_deleted'].'</strong></li>';
    echo '<li>Pruebas borradas: <strong>'.(int)$result['tests_deleted'].'</strong></li>';
    echo '<li>Estaciones creadas: <strong>'.(int)$result['stations_created'].'</strong></li>';
    echo '<li>Estaciones actualizadas: <strong>'.(int)$result['stations_updated'].'</strong></li>';
    echo '<li>Pruebas creadas: <strong>'.(int)$result['tests_created'].'</strong></li>';
    echo '<li>Pruebas actualizadas: <strong>'.(int)$result['tests_updated'].'</strong></li>';
    echo '<li>Escenario actualizado: <strong>gc_num_estaciones = '.(int)$result['num_stations'].'</strong></li>';
    echo '</ul>';

    if ( ! empty($result['log']) ) {
      echo '<h2>Log</h2>';
      echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:320px;overflow:auto;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;font-size:12px;line-height:1.35;">';
      foreach ($result['log'] as $line) echo esc_html($line) . "<br>";
      echo '</div>';
    }
  }

  echo '<hr style="margin:20px 0;">';
  echo '<h2>Subir CSV</h2>';
  echo '<form method="post" enctype="multipart/form-data">';
  wp_nonce_field('gincana_import_csv_action', 'gincana_import_csv_nonce');

  echo '<table class="form-table" role="presentation"><tbody>';

  echo '<tr>';
  echo '<th scope="row"><label for="gincana_escenario_id">Escenario</label></th>';
  echo '<td>';
  echo '<select name="gincana_escenario_id" id="gincana_escenario_id" required style="min-width:320px;">';
  echo '<option value="">— Selecciona escenario —</option>';
  foreach ($escenarios as $esc_id) {
    $t = get_the_title($esc_id) ?: ('Escenario #'.$esc_id);
    printf('<option value="%d"%s>%s</option>',
      (int)$esc_id,
      selected($selected_esc, $esc_id, false),
      esc_html($t)
    );
  }
  echo '</select>';
  echo '<p class="description">El importador actualizará automáticamente <code>gc_num_estaciones</code> al número de estaciones únicas del CSV.</p>';
  echo '</td>';
  echo '</tr>';

  echo '<tr>';
  echo '<th scope="row">Modo de importación</th>';
  echo '<td>';
  echo '<label style="display:inline-flex;gap:8px;align-items:center;">';
  echo '<input type="checkbox" name="gincana_replace_mode" value="1" '.checked($selected_replace, true, false).' />';
  echo '<span><strong>Reemplazar escenario</strong>: borrar estaciones del escenario y sus pruebas enlazadas antes de importar.</span>';
  echo '</label>';
  echo '<p class="description" style="max-width:820px;">Recomendado si quieres que el escenario quede exactamente como el CSV. Si no lo marcas, el importador solo crea/actualiza y puede quedar contenido antiguo.</p>';
  echo '</td>';
  echo '</tr>';

  echo '<tr>';
  echo '<th scope="row"><label for="gincana_csv_file">Fichero CSV</label></th>';
  echo '<td>';
  echo '<input type="file" name="gincana_csv_file" id="gincana_csv_file" accept=".csv,text/csv" required />';
  echo '<p class="description">Obligatorias: <code>station_slug, station_title, station_order, test_slug, test_title</code>. Opcionales: <code>question, answer, points</code>.</p>';
  echo '</td>';
  echo '</tr>';

  echo '</tbody></table>';

  submit_button('Importar', 'primary', 'gincana_csv_submit');

  echo '</form>';

  echo '<hr style="margin:20px 0;">';
  echo '<h2>Plantilla CSV</h2>';
  echo '<p>Copia esto en Excel y guarda como CSV (delimitado por comas):</p>';
  echo '<pre style="background:#f6f7f7;border:1px solid #ccd0d4;padding:12px;white-space:pre-wrap;">';
  echo esc_html("station_slug,station_title,station_order,test_slug,test_title,question,answer,points\nentrada,Entrada principal,1,p1,Primera prueba,¿Capital de Francia?,Paris,10\ncastillo,Castillo,2,p2,Segunda prueba,2+2?,4,5");
  echo '</pre>';

  echo '</div>';
}

function gincana_core_handle_csv_import($escenario_id, $tmp_path, $replace_mode = false) {
  $log = [];
  $errors = [];

  $stations_deleted = 0;
  $tests_deleted = 0;

  if ( ! file_exists($tmp_path) ) {
    return ['errors' => ['No se ha podido leer el fichero subido.']];
  }

  // 0) Si replace_mode: borrar estaciones del escenario (y sus pruebas enlazadas)
  if ( $replace_mode ) {
    $deleted = gincana_core_delete_scenario_stations_and_tests($escenario_id);
    $stations_deleted = (int) ($deleted['stations_deleted'] ?? 0);
    $tests_deleted    = (int) ($deleted['tests_deleted'] ?? 0);
    $log[] = "Modo reemplazar: borradas {$stations_deleted} estaciones y {$tests_deleted} pruebas enlazadas.";
  }

  $fh = fopen($tmp_path, 'r');
  if ( ! $fh ) {
    return ['errors' => ['No se ha podido abrir el CSV.']];
  }

  $header = fgetcsv($fh, 0, ',');
  if ( ! is_array($header) || empty($header) ) {
    fclose($fh);
    return ['errors' => ['El CSV está vacío o no tiene cabecera.']];
  }

  $header = array_map(function($h){
    $h = trim((string)$h);
    $h = strtolower($h);
    return $h;
  }, $header);

  $required = ['station_slug','station_title','station_order','test_slug','test_title'];
  foreach ($required as $req) {
    if ( ! in_array($req, $header, true) ) $errors[] = 'Falta la columna obligatoria: '.$req;
  }
  if ( ! empty($errors) ) {
    fclose($fh);
    return ['errors' => $errors];
  }

  $idx = array_flip($header);

  $rows = 0;
  $stations_created = 0;
  $stations_updated = 0;
  $tests_created = 0;
  $tests_updated = 0;

  $station_slugs_seen = [];

  while ( ($row = fgetcsv($fh, 0, ',')) !== false ) {
    if ( ! is_array($row) || count(array_filter($row, fn($v)=>trim((string)$v)!=='')) === 0 ) continue;

    $rows++;

    $station_slug  = gincana_core_csv_cell($row, $idx['station_slug']  ?? null);
    $station_title = gincana_core_csv_cell($row, $idx['station_title'] ?? null);
    $station_order = gincana_core_csv_cell($row, $idx['station_order'] ?? null);
    $test_slug     = gincana_core_csv_cell($row, $idx['test_slug']     ?? null);
    $test_title    = gincana_core_csv_cell($row, $idx['test_title']    ?? null);

    if ( $station_slug === '' || $station_title === '' || $station_order === '' || $test_slug === '' || $test_title === '' ) {
      $errors[] = "Fila {$rows}: faltan datos obligatorios (slug/título/orden).";
      continue;
    }

    $station_order_int = (int) $station_order;
    if ( $station_order_int <= 0 ) $station_order_int = 1;

    $station_slugs_seen[$station_slug] = true;

    // 1) Estación (por escenario + slug)
    $station_id = gincana_core_find_station_by_slug_in_scenario($station_slug, $escenario_id);

    if ( $station_id ) {
      wp_update_post([
        'ID'         => $station_id,
        'post_title' => $station_title,
        'post_name'  => sanitize_title($station_slug),
      ]);
      $stations_updated++;
      $log[] = "Estación actualizada: {$station_title} (ID {$station_id})";
    } else {
      $station_id = wp_insert_post([
        'post_type'   => 'estacion',
        'post_status' => 'publish',
        'post_title'  => $station_title,
        'post_name'   => sanitize_title($station_slug),
      ], true);

      if ( is_wp_error($station_id) || ! $station_id ) {
        $errors[] = "Fila {$rows}: no se pudo crear la estación '{$station_title}'.";
        continue;
      }
      $stations_created++;
      $log[] = "Estación creada: {$station_title} (ID {$station_id})";
    }

    update_post_meta($station_id, 'gc_escenario_ref', (int)$escenario_id);
    update_post_meta($station_id, 'gc_orden', (int)$station_order_int);

    // 2) Prueba (por slug, global)
    $test_id = gincana_core_find_test_by_slug($test_slug);

    if ( $test_id ) {
      wp_update_post([
        'ID'         => $test_id,
        'post_title' => $test_title,
        'post_name'  => sanitize_title($test_slug),
      ]);
      $tests_updated++;
      $log[] = "  Prueba actualizada: {$test_title} (ID {$test_id})";
    } else {
      $test_id = wp_insert_post([
        'post_type'   => 'prueba',
        'post_status' => 'publish',
        'post_title'  => $test_title,
        'post_name'   => sanitize_title($test_slug),
      ], true);

      if ( is_wp_error($test_id) || ! $test_id ) {
        $errors[] = "Fila {$rows}: no se pudo crear la prueba '{$test_title}'.";
        continue;
      }
      $tests_created++;
      $log[] = "  Prueba creada: {$test_title} (ID {$test_id})";
    }

    // Opcionales a meta de prueba
    if ( isset($idx['question']) ) {
      $q = gincana_core_csv_cell($row, $idx['question']);
      if ($q !== '') update_post_meta($test_id, 'gc_question', $q);
    }
    if ( isset($idx['answer']) ) {
      $a = gincana_core_csv_cell($row, $idx['answer']);
      if ($a !== '') update_post_meta($test_id, 'gc_answer', $a);
    }
    if ( isset($idx['points']) ) {
      $p = gincana_core_csv_cell($row, $idx['points']);
      if ($p !== '') update_post_meta($test_id, 'gc_points', (int)$p);
    }

    // 3) Enlazar estación -> prueba
    update_post_meta($station_id, 'gc_prueba_ref', (int)$test_id);
  }

  fclose($fh);

  // 4) Actualizar gc_num_estaciones
  $num_stations = count($station_slugs_seen);
  update_post_meta($escenario_id, 'gc_num_estaciones', (int)$num_stations);
  $log[] = "Escenario {$escenario_id}: gc_num_estaciones actualizado a {$num_stations}";

  return [
    'replace_mode'     => (bool)$replace_mode,
    'rows'             => (int)$rows,
    'stations_deleted' => (int)$stations_deleted,
    'tests_deleted'    => (int)$tests_deleted,
    'stations_created' => (int)$stations_created,
    'stations_updated' => (int)$stations_updated,
    'tests_created'    => (int)$tests_created,
    'tests_updated'    => (int)$tests_updated,
    'num_stations'     => (int)$num_stations,
    'errors'           => $errors,
    'log'              => $log,
  ];
}

function gincana_core_delete_scenario_stations_and_tests($escenario_id) {
  $stations = get_posts([
    'post_type'       => 'estacion',
    'post_status'     => 'any',
    'numberposts'     => -1,
    'fields'          => 'ids',
    'meta_query'      => [[
      'key'     => 'gc_escenario_ref',
      'value'   => (int)$escenario_id,
      'compare' => '=',
    ]],
    'no_found_rows'   => true,
    'suppress_filters'=> true,
  ]);

  $stations_deleted = 0;
  $tests_deleted = 0;

  foreach ($stations as $station_id) {
    $station_id = (int)$station_id;

    $test_id = (int) get_post_meta($station_id, 'gc_prueba_ref', true);
    if ($test_id > 0) {
      wp_delete_post($test_id, true);
      $tests_deleted++;
    }

    wp_delete_post($station_id, true);
    $stations_deleted++;
  }

  return [
    'stations_deleted' => (int)$stations_deleted,
    'tests_deleted'    => (int)$tests_deleted,
  ];
}

function gincana_core_csv_cell($row, $index) {
  if ($index === null) return '';
  return isset($row[$index]) ? trim((string)$row[$index]) : '';
}

function gincana_core_find_station_by_slug_in_scenario($slug, $escenario_id) {
  $slug = sanitize_title($slug);

  $q = new WP_Query([
    'post_type'      => 'estacion',
    'post_status'    => 'any',
    'posts_per_page' => 1,
    'name'           => $slug,
    'meta_query'     => [[
      'key'     => 'gc_escenario_ref',
      'value'   => (int)$escenario_id,
      'compare' => '=',
    ]],
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ]);

  return $q->have_posts() ? (int)$q->posts[0] : 0;
}

function gincana_core_find_test_by_slug($slug) {
  $slug = sanitize_title($slug);

  $q = new WP_Query([
    'post_type'      => 'prueba',
    'post_status'    => 'any',
    'posts_per_page' => 1,
    'name'           => $slug,
    'fields'         => 'ids',
    'no_found_rows'  => true,
  ]);

  return $q->have_posts() ? (int)$q->posts[0] : 0;
}