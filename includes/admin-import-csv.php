<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Admin – Importador CSV (Escenario -> Estaciones -> Pruebas)
 *
 * Arquitectura del plugin:
 * - Estación -> Escenario: gc_escenario_ref (ID)
 * - Estación -> Prueba:    gc_prueba_ref (ID)
 * - Orden estación:        gc_orden (NUMERIC)
 * - Nº estaciones escenario: gc_num_estaciones
 *
 * Meta reales de la prueba:
 * - gc_tipo
 * - gc_tiempo_max_s
 * - gc_intentos_max
 * - gc_estacion_ref
 * - gc_preguntas
 *
 * CSV esperado (obligatorias):
 * station_slug,station_title,station_order,test_slug,test_title
 *
 * CSV opcional recomendado:
 * test_type,time_limit_s,max_attempts,block_hint,question_type,question_text,
 * option_1,option_2,option_3,option_4,correct_option,correct_text
 *
 * Tipos válidos:
 * - test_type / question_type: multiple | vf | texto
 *
 * Reglas:
 * - multiple / vf: usa option_1..option_4 + correct_option
 * - texto: usa correct_text
 */

add_action('admin_menu', function () {
  add_submenu_page(
    'gincana-core',
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
  echo '<p>Importa estaciones y pruebas en bloque dentro de un escenario. El importador crea o actualiza por <code>slug</code>, enlaza <code>gc_prueba_ref</code> y actualiza <code>gc_num_estaciones</code>.</p>';

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
    printf(
      '<option value="%d"%s>%s</option>',
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
  echo '<p class="description">Obligatorias: <code>station_slug, station_title, station_order, test_slug, test_title</code>.</p>';
  echo '</td>';
  echo '</tr>';

  echo '</tbody></table>';

  submit_button('Importar', 'primary', 'gincana_csv_submit');

  echo '</form>';

  echo '<hr style="margin:20px 0;">';
  echo '<h2>Plantilla CSV recomendada</h2>';
  echo '<pre style="background:#f6f7f7;border:1px solid #ccd0d4;padding:12px;white-space:pre-wrap;">';
  echo esc_html(
"station_slug,station_title,station_order,test_slug,test_title,test_type,time_limit_s,max_attempts,block_hint,question_type,question_text,option_1,option_2,option_3,option_4,correct_option,correct_text
entrada,Entrada principal,1,p1,Primera prueba,multiple,30,2,Debes completar antes la estación anterior,multiple,¿Capital de Francia?,Madrid,Paris,Roma,Berlín,2,
castillo,Castillo,2,p2,Segunda prueba,texto,30,2,,texto,¿2+2?,,,,,,4"
  );
  echo '</pre>';

  echo '<p><strong>correct_option</strong> usa valores 1, 2, 3 o 4. En preguntas de texto, usa <strong>correct_text</strong>.</p>';

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
    return strtolower(trim((string)$h));
  }, $header);

  $required = ['station_slug','station_title','station_order','test_slug','test_title'];
  foreach ($required as $req) {
    if ( ! in_array($req, $header, true) ) {
      $errors[] = 'Falta la columna obligatoria: '.$req;
    }
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
      $errors[] = "Fila {$rows}: faltan datos obligatorios.";
      continue;
    }

    $station_order_int = max(1, (int) $station_order);
    $station_slugs_seen[$station_slug] = true;

    // ===== 1) ESTACIÓN =====
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

    if ( isset($idx['block_hint']) ) {
      $block_hint = gincana_core_csv_cell($row, $idx['block_hint']);
      if ($block_hint !== '') {
        update_post_meta($station_id, 'gc_pista_bloqueo', $block_hint);
      }
    }

    // ===== 2) PRUEBA =====
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

    // Meta básica real de la prueba
    $test_type    = gincana_core_csv_cell($row, $idx['test_type'] ?? null);
    $question_type= gincana_core_csv_cell($row, $idx['question_type'] ?? null);
    $time_limit_s = gincana_core_csv_cell($row, $idx['time_limit_s'] ?? null);
    $max_attempts = gincana_core_csv_cell($row, $idx['max_attempts'] ?? null);

    $final_type = $question_type !== '' ? $question_type : $test_type;
    if ( $final_type === '' ) $final_type = 'multiple';

    if ( ! in_array($final_type, ['multiple','vf','texto'], true) ) {
      $final_type = 'multiple';
    }

    update_post_meta($test_id, 'gc_tipo', $final_type);
    update_post_meta($test_id, 'gc_tiempo_max_s', $time_limit_s !== '' ? max(1, (int)$time_limit_s) : 30);
    update_post_meta($test_id, 'gc_intentos_max', $max_attempts !== '' ? max(1, (int)$max_attempts) : 2);

    // Referencia inversa útil para REST/auditoría
    update_post_meta($test_id, 'gc_estacion_ref', (int)$station_id);

    // ===== 3) ESTRUCTURA gc_preguntas =====
    $question_text = gincana_core_csv_cell($row, $idx['question_text'] ?? null);
    $correct_text  = gincana_core_csv_cell($row, $idx['correct_text'] ?? null);
    $correct_option= gincana_core_csv_cell($row, $idx['correct_option'] ?? null);

    $pregunta = [
      'tipo'      => $final_type,
      'enunciado' => $question_text !== '' ? $question_text : $test_title,
    ];

    if ( $final_type === 'texto' ) {
      $pregunta['respuesta_texto_correcta'] = $correct_text;
      $pregunta['opciones'] = [];
    } else {
      $options = [];
      for ($i = 1; $i <= 4; $i++) {
        $opt_val = gincana_core_csv_cell($row, $idx['option_'.$i] ?? null);
        if ($opt_val === '') continue;

        $options[] = [
          'texto'       => $opt_val,
          'es_correcta' => ((string)$correct_option === (string)$i) ? 1 : 0,
        ];
      }

      if ( empty($options) ) {
        $errors[] = "Fila {$rows}: la prueba '{$test_title}' necesita opciones para tipo '{$final_type}'.";
        continue;
      }

      $pregunta['opciones'] = $options;
      $pregunta['respuesta_texto_correcta'] = '';
    }

    update_post_meta($test_id, 'gc_preguntas', [ $pregunta ]);

    // ===== 4) ENLAZAR estación -> prueba =====
    update_post_meta($station_id, 'gc_prueba_ref', (int)$test_id);
  }

  fclose($fh);

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