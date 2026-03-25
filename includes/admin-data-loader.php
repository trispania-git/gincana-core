<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Cargador de datos puntual.
 * Accesible desde Gincana Core > Cargar datos (solo admin).
 * Despues de usarlo se puede eliminar este fichero.
 */

// Submenu temporal
add_action('admin_menu', function(){
  add_submenu_page(
    'gincana-core',
    'Cargar datos',
    'Cargar datos',
    'manage_options',
    'gc-data-loader',
    'gc_render_data_loader_page'
  );
});

function gc_get_ulpiano_checa_data() {
  return [
    1 => [
      'title' => 'Jardines del Museo Ulpiano Checa',
      'descripcion' => 'Comenzamos la gimkana en los jardines del Museo donde se encuentra el busto del pintor, ademas de los monumentos a la Piedra y a la Tinaja, ambos elementos caracteristicos de la localidad. Ulpiano Checa fotografio todo el proceso de fabricacion de tinajas y las canteras de piedra caliza, dejando un valioso testimonio de la artesania local.',
      'maps_url' => 'https://maps.app.goo.gl/H7ybuk5gk245GfQu6',
      'ubicacion' => 'Calle Maria Teresa Freire, 2',
    ],
    2 => [
      'title' => 'Casa Natal de Ulpiano Checa',
      'descripcion' => 'En esta ubicacion nacio Ulpiano Checa y vivio en ella 3 anos. Aunque actualmente la fachada esta totalmente remodelada, se conserva sobre uno de los balcones de la planta superior una placa que recuerda su nacimiento el 3 de abril de 1860.',
      'maps_url' => 'https://maps.app.goo.gl/1zRYNpkfZ4UtUEsN7',
      'ubicacion' => 'Calle de Ulpiano Checa, 5',
    ],
    3 => [
      'title' => 'Iglesia de Santa Maria la Mayor',
      'descripcion' => 'En la iglesia parroquial Ulpiano Checa realiza sus primeros dibujos como nino antes de comenzar sus estudios formativos en Madrid. En 1897 y 1901 pinta tres grandes murales que aun se conservan. Tambien disponemos de varias fotografias realizadas por el propio Checa, tanto del interior como del exterior de la iglesia.',
      'maps_url' => 'https://maps.app.goo.gl/xsXZHqrYHDtbBqKY7',
      'ubicacion' => 'Plaza del Mercado SN',
    ],
    4 => [
      'title' => 'Plaza Mayor - Edificio del Posito',
      'descripcion' => 'Uno de los edificios emblematicos de Colmenar de Oreja ubicado dentro de la Plaza Mayor. Siempre ha sido un deseo por parte del Ayuntamiento su reconversion en Casa Consistorial tras la perdida de su uso original. Desconocemos si el proyecto que realiza Checa fue un encargo del Ayuntamiento o una iniciativa propia. Las fotografias de la Plaza realizadas por Checa son un tesoro documental.',
      'maps_url' => 'https://maps.app.goo.gl/PtHiDrrDJP7BwLZE9',
      'ubicacion' => 'Plaza Mayor, 25',
    ],
    5 => [
      'title' => 'Torre de la Iglesia Sta. Maria la Mayor',
      'descripcion' => 'Unica imagen del chapitel original construido en el siglo XVI y destruido en 1886 en un incendio provocado por una tormenta electrica. La obra que realiza Ulpiano Checa serviria posteriormente para realizar una replica del chapitel que podemos ver en la actualidad. Desde este punto se puede ver con mayor facilidad la torre de la iglesia.',
      'maps_url' => 'https://maps.app.goo.gl/KZBTeDzTFgZMXGKQ6',
      'ubicacion' => 'Inicios de Calle Escarchada y Calle del Nene',
    ],
    6 => [
      'title' => 'Casa Familiar de Ulpiano Checa',
      'descripcion' => 'Un ano despues de su construccion en 1883, Checa se traslada a Roma como pensionado hasta 1887 y de ahi a Paris donde fija su residencia definitiva. A la muerte de sus padres hereda la casa, que posteriormente fue vendida. En el dintel de la portada principal se conserva la inscripcion "F. CH. 1883" que alude al apellido paterno Fernandez-Checa. Las estancias de Checa en Colmenar las realiza en esta casa, como atestiguan sus numerosas fotografias.',
      'maps_url' => 'https://maps.app.goo.gl/FA6bBKmKHEYhBAVe8',
      'ubicacion' => 'Calle Escarchada, 18',
    ],
    7 => [
      'title' => 'Plaza de la Cruz Verde',
      'descripcion' => 'Pequena plaza ubicada justo delante de la casa familiar, escenario de multitud de fotografias y de la acuarela "El vendimiador de Colmenar". Un rincon con encanto que Checa inmortalizo en varias de sus obras.',
      'maps_url' => 'https://maps.app.goo.gl/gdtToHCEqv7WXnqy9',
      'ubicacion' => 'Calle Escarchada - Calle del Pozo de la Nieve',
    ],
    8 => [
      'title' => 'Convento de la Encarnacion',
      'descripcion' => 'Edificio monumental de Colmenar de Oreja y escenario de algunas fotografias de Checa. Se trata de uno de los conventos mejor conservados del barroco madrileno, que actualmente sigue en activo con una Comunidad de Religiosas. En su interior se conservan ocho murales realizados en pleno siglo XVIII por Matias de Torres.',
      'maps_url' => 'https://maps.app.goo.gl/WUutscfctALgreVy8',
      'ubicacion' => 'Plaza de la Solana, 2',
    ],
    9 => [
      'title' => 'Cementerio Parroquial - Mausoleo Ulpiano Checa',
      'descripcion' => 'El recinto original data de 1833, aunque ha sido ampliado y remodelado con el paso de los anos. Fuera de las rutas turisticas, alberga el mausoleo de Ulpiano Checa. El ultimo deseo del pintor fue ser enterrado en su villa natal; tras su muerte en Dax (Francia), sus restos se trasladaron a Colmenar de Oreja, creandose este mausoleo para tal fin.',
      'maps_url' => 'https://maps.app.goo.gl/SfZZvSykTGcFreFP9',
      'ubicacion' => 'Calle Camino del Cementerio SN',
    ],
    10 => [
      'title' => 'Plaza del Arco - Estacion de Autobuses',
      'descripcion' => 'Como fin de la gimkana proponemos esta plaza que alberga una fuente monumental disenada por Juan de Avalos y que generalmente pasa desapercibida para los turistas. Su cercania con la actual estacion de autobuses es perfecta como despedida, conectando con Ulpiano Checa mediante el oleo "El tren de Colmenar de Oreja".',
      'maps_url' => 'https://maps.app.goo.gl/94xF6w7aEScWn3QW9',
      'ubicacion' => 'Calle del Arco - Estacion de autobuses',
    ],
  ];
}

function gc_render_data_loader_page() {
  // Buscar el escenario "El Legado de Ulpiano Checa"
  $escenarios = get_posts([
    'post_type'   => 'escenario',
    'post_status' => 'publish',
    'numberposts' => -1,
  ]);

  $escenario_id = 0;
  foreach ($escenarios as $esc) {
    if (stripos($esc->post_title, 'ulpiano') !== false || stripos($esc->post_title, 'legado') !== false) {
      $escenario_id = $esc->ID;
      break;
    }
  }

  // Procesar si se pulso el boton
  $results = [];
  if (isset($_POST['gc_load_data']) && check_admin_referer('gc_load_data_action')) {
    $escenario_id = (int) $_POST['gc_escenario_id'];
    $data = gc_get_ulpiano_checa_data();

    // Obtener estaciones del escenario ordenadas
    $q = new WP_Query([
      'post_type'      => 'estacion',
      'posts_per_page' => -1,
      'orderby'        => 'meta_value_num',
      'order'          => 'ASC',
      'meta_query'     => [['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'=']],
      'meta_key'       => 'gc_orden',
      'no_found_rows'  => true,
    ]);

    if ($q->have_posts()) {
      foreach ($q->posts as $post) {
        $orden = (int) get_post_meta($post->ID, 'gc_orden', true);
        if (isset($data[$orden])) {
          $d = $data[$orden];

          // Actualizar titulo
          wp_update_post([
            'ID'         => $post->ID,
            'post_title' => $d['title'],
            'post_name'  => sanitize_title($d['title']),
          ]);

          // Actualizar metas
          update_post_meta($post->ID, 'gc_descripcion', $d['descripcion']);
          update_post_meta($post->ID, 'gc_maps_url', $d['maps_url']);

          $results[] = "Estacion #{$orden} (ID {$post->ID}): <strong>{$d['title']}</strong> — Actualizada";
        } else {
          $results[] = "Estacion #{$orden} (ID {$post->ID}): Sin datos para este orden — Omitida";
        }
      }
    } else {
      $results[] = "No se encontraron estaciones para el escenario #{$escenario_id}";
    }
    wp_reset_postdata();
  }

  ?>
  <div class="wrap">
    <h1>Cargar datos de estaciones</h1>

    <?php if (!empty($results)): ?>
      <div class="notice notice-success">
        <h3>Resultado de la carga:</h3>
        <ul>
          <?php foreach ($results as $r): ?>
            <li><?php echo $r; ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card" style="max-width:700px;padding:20px;">
      <h2>Escenario: El Legado de Ulpiano Checa</h2>
      <p>Este proceso actualizara las 10 estaciones del escenario con:</p>
      <ul>
        <li>Titulos reales de cada parada</li>
        <li>Descripciones culturales</li>
        <li>Enlaces a Google Maps</li>
      </ul>

      <?php if ($escenario_id): ?>
        <p><strong>Escenario detectado:</strong> <?php echo esc_html(get_the_title($escenario_id)); ?> (ID: <?php echo $escenario_id; ?>)</p>

        <form method="post">
          <?php wp_nonce_field('gc_load_data_action'); ?>
          <input type="hidden" name="gc_escenario_id" value="<?php echo (int)$escenario_id; ?>">
          <p>
            <button type="submit" name="gc_load_data" value="1" class="button button-primary button-hero">
              Cargar datos de las 10 estaciones
            </button>
          </p>
        </form>
      <?php else: ?>
        <div class="notice notice-warning inline">
          <p>No se encontro el escenario "El Legado de Ulpiano Checa". Selecciona manualmente:</p>
        </div>
        <form method="post">
          <?php wp_nonce_field('gc_load_data_action'); ?>
          <select name="gc_escenario_id">
            <?php foreach ($escenarios as $esc): ?>
              <option value="<?php echo $esc->ID; ?>"><?php echo esc_html($esc->post_title); ?> (ID: <?php echo $esc->ID; ?>)</option>
            <?php endforeach; ?>
          </select>
          <p>
            <button type="submit" name="gc_load_data" value="1" class="button button-primary button-hero">
              Cargar datos de las 10 estaciones
            </button>
          </p>
        </form>
      <?php endif; ?>
    </div>

    <div class="card" style="max-width:700px;padding:20px;margin-top:20px;">
      <h3>Vista previa de los datos</h3>
      <table class="widefat striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Titulo</th>
            <th>Ubicacion</th>
            <th>Maps</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (gc_get_ulpiano_checa_data() as $orden => $d): ?>
            <tr>
              <td><?php echo (int)$orden; ?></td>
              <td><?php echo esc_html($d['title']); ?></td>
              <td><?php echo esc_html($d['ubicacion']); ?></td>
              <td><a href="<?php echo esc_url($d['maps_url']); ?>" target="_blank">Ver</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php
}
