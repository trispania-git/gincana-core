<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Metabox para estaciones:
 * - audio
 * - imágenes extra
 * - token QR
 * - URL QR autogenerada
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'gc_estacion_config',
        'Configuración de Estación',
        'gc_render_estacion_metabox',
        'estacion',
        'normal',
        'high'
    );
});

function gc_get_station_entry_base_url() {
    return home_url('/acceso-estacion/');
}

function gc_generate_station_token($post_id) {
    return wp_hash('gc_station_' . $post_id . '_' . wp_generate_password(12, false, false));
}

function gc_get_station_entry_url($post_id) {
    $token = get_post_meta($post_id, 'gc_qr_token', true);

    if (empty($token)) {
        $token = gc_generate_station_token($post_id);
        update_post_meta($post_id, 'gc_qr_token', $token);
    }

    return add_query_arg([
        'gc_station' => (int) $post_id,
        'gc_token'   => rawurlencode($token),
    ], gc_get_station_entry_base_url());
}

function gc_render_estacion_metabox($post) {
    wp_nonce_field('gc_save_estacion_meta', 'gc_estacion_nonce');

    $descripcion     = get_post_meta($post->ID, 'gc_descripcion', true);
    $pista_busqueda  = get_post_meta($post->ID, 'gc_pista_busqueda', true);
    $audio    = get_post_meta($post->ID, 'gc_audio', true);
    $maps_url  = get_post_meta($post->ID, 'gc_maps_url', true);
    $direccion = get_post_meta($post->ID, 'gc_direccion', true);
    $latitud   = get_post_meta($post->ID, 'gc_latitud', true);
    $longitud  = get_post_meta($post->ID, 'gc_longitud', true);
    $img1     = get_post_meta($post->ID, 'gc_img_1', true);
    $img2     = get_post_meta($post->ID, 'gc_img_2', true);
    $token    = get_post_meta($post->ID, 'gc_qr_token', true);

    if (empty($token)) {
        $token = gc_generate_station_token($post->ID);
        update_post_meta($post->ID, 'gc_qr_token', $token);
    }

    $qr_url = gc_get_station_entry_url($post->ID);
    ?>
    <table class="form-table">

        <tr>
            <th><label for="gc_descripcion">Descripcion cultural</label></th>
            <td>
                <?php
                wp_editor($descripcion, 'gc_descripcion', [
                    'textarea_name' => 'gc_descripcion',
                    'textarea_rows' => 6,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Texto descriptivo del lugar (historia, curiosidades...). Se muestra al jugador en la pagina de la estacion.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_pista_busqueda">Pista para encontrar</label></th>
            <td>
                <input type="text" name="gc_pista_busqueda" id="gc_pista_busqueda"
                       value="<?php echo esc_attr($pista_busqueda); ?>"
                       style="width:100%;" placeholder="Ej: Busca cerca de la fuente del jardín..." />
                <p class="description">Pista que ayuda al jugador a encontrar el QR en el lugar. Se muestra en escenarios infantiles cuando acceden desde la lista (sin QR).</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_audio">Audio</label></th>
            <td>
                <?php gc_render_media_field('gc_audio', $audio, 'audio', 'Seleccionar audio'); ?>
                <p class="description">Se mostrara como icono de auriculares en la pagina de la estacion.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_direccion">Direccion</label></th>
            <td>
                <input type="text" name="gc_direccion" id="gc_direccion" value="<?php echo esc_attr($direccion); ?>" style="width:100%;" placeholder="Plaza Mayor, 1 - Colmenar de Oreja" />
                <p class="description">Direccion o referencia del lugar. Se muestra junto al icono de ubicacion.</p>
            </td>
        </tr>
        <tr>
            <th><label for="gc_maps_url">Ubicacion (Google Maps)</label></th>
            <td>
                <input type="url" name="gc_maps_url" id="gc_maps_url" value="<?php echo esc_attr($maps_url); ?>" style="width:100%;" placeholder="https://maps.google.com/..." />
                <p class="description">Enlace de Google Maps del lugar.</p>
            </td>
        </tr>
        <tr>
            <th><label>Coordenadas GPS</label></th>
            <td>
                <div style="display:flex;gap:12px;align-items:center;">
                    <div style="flex:1;">
                        <label for="gc_latitud" style="font-size:12px;color:#666;">Latitud</label>
                        <input type="text" name="gc_latitud" id="gc_latitud" value="<?php echo esc_attr($latitud); ?>" style="width:100%;" placeholder="40.123456" />
                    </div>
                    <div style="flex:1;">
                        <label for="gc_longitud" style="font-size:12px;color:#666;">Longitud</label>
                        <input type="text" name="gc_longitud" id="gc_longitud" value="<?php echo esc_attr($longitud); ?>" style="width:100%;" placeholder="-3.456789" />
                    </div>
                    <button type="button" id="gc-extract-coords" class="button" style="margin-top:16px;" title="Extraer de la URL de Google Maps">📍 Extraer</button>
                </div>
                <p class="description">Para verificacion por GPS. Pulsa "Extraer" para obtenerlas de la URL de Maps, o introducelas manualmente.</p>
                <script>
                document.getElementById('gc-extract-coords').addEventListener('click', function(){
                    var url = document.getElementById('gc_maps_url').value;
                    if (!url) { alert('Introduce primero una URL de Google Maps.'); return; }
                    // Intentar extraer coordenadas de varios formatos de URL
                    var patterns = [
                        /@(-?\d+\.\d+),(-?\d+\.\d+)/,           // @lat,lng
                        /[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/,       // ?q=lat,lng
                        /place\/[^/]*\/(-?\d+\.\d+),(-?\d+\.\d+)/, // place/.../lat,lng
                        /ll=(-?\d+\.\d+),(-?\d+\.\d+)/,           // ll=lat,lng
                    ];
                    for (var i = 0; i < patterns.length; i++) {
                        var m = url.match(patterns[i]);
                        if (m) {
                            document.getElementById('gc_latitud').value = m[1];
                            document.getElementById('gc_longitud').value = m[2];
                            return;
                        }
                    }
                    alert('No se pudieron extraer las coordenadas de esta URL.\nIntroduce latitud y longitud manualmente.');
                });
                </script>
            </td>
        </tr>

        <tr>
            <th><label for="gc_img_1">Imagen 1</label></th>
            <td>
                <?php gc_render_media_field('gc_img_1', $img1, 'image', 'Seleccionar imagen'); ?>
            </td>
        </tr>

        <tr>
            <th><label for="gc_img_2">Imagen 2</label></th>
            <td>
                <?php gc_render_media_field('gc_img_2', $img2, 'image', 'Seleccionar imagen'); ?>
            </td>
        </tr>

        <?php
        $escenario_ref = (int) get_post_meta($post->ID, 'gc_escenario_ref', true);
        $tipo_qr = ($escenario_ref && function_exists('gc_get_tipo_qr')) ? gc_get_tipo_qr($escenario_ref) : 'enlace';
        $qr_url_final = ($tipo_qr === 'enlace') ? get_permalink($post->ID) : $qr_url;
        ?>

        <?php if ($tipo_qr === 'validacion'): ?>
        <tr>
            <th>Token QR</th>
            <td>
                <code><?php echo esc_html($token); ?></code>
                <p class="description">Se genera automáticamente. Se usa en modo "QR de validación".</p>
            </td>
        </tr>
        <?php endif; ?>

        <tr>
            <th>URL QR</th>
            <td>
                <input type="text" readonly value="<?php echo esc_attr($qr_url_final); ?>" style="width:100%;background:#f6f7f7;" />
                <p class="description">
                    <?php if ($tipo_qr === 'validacion'): ?>
                        <strong>Modo validación:</strong> URL con token. El jugador debe escanear el QR in situ.
                    <?php else: ?>
                        <strong>Modo enlace:</strong> redirige a la página de la estación.
                    <?php endif; ?>
                    <br>Configurable desde el escenario (campo "Tipo de QR").
                </p>
            </td>
        </tr>

    </table>
    <?php
}

/**
 * Auto-actualizar slug al cambiar título de estación.
 * Usa save_post con prioridad alta + update directo en DB para evitar loops.
 */
add_action('save_post_estacion', function ($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_status === 'auto-draft') return;

    $new_slug = sanitize_title($post->post_title);
    if ($new_slug && $new_slug !== $post->post_name) {
        global $wpdb;
        $unique_slug = wp_unique_post_slug($new_slug, $post_id, $post->post_status, $post->post_type, $post->post_parent);
        $wpdb->update($wpdb->posts, ['post_name' => $unique_slug], ['ID' => $post_id]);
        clean_post_cache($post_id);
    }
}, 99, 2);

/**
 * Guardado de datos
 */
add_action('save_post', function ($post_id) {

    if ( ! isset($_POST['gc_estacion_nonce']) ) return;
    if ( ! wp_verify_nonce($_POST['gc_estacion_nonce'], 'gc_save_estacion_meta') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( get_post_type($post_id) !== 'estacion' ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    update_post_meta($post_id, 'gc_descripcion', wp_kses_post($_POST['gc_descripcion'] ?? ''));
    update_post_meta($post_id, 'gc_pista_busqueda', sanitize_text_field($_POST['gc_pista_busqueda'] ?? ''));
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_direccion', sanitize_text_field($_POST['gc_direccion'] ?? ''));
    update_post_meta($post_id, 'gc_maps_url', esc_url_raw($_POST['gc_maps_url'] ?? ''));
    // Coordenadas GPS: sanitizar como float
    $lat = isset($_POST['gc_latitud']) ? preg_replace('/[^0-9.\-]/', '', $_POST['gc_latitud']) : '';
    $lng = isset($_POST['gc_longitud']) ? preg_replace('/[^0-9.\-]/', '', $_POST['gc_longitud']) : '';
    update_post_meta($post_id, 'gc_latitud', $lat);
    update_post_meta($post_id, 'gc_longitud', $lng);
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));

    $token = get_post_meta($post_id, 'gc_qr_token', true);
    if (empty($token)) {
        update_post_meta($post_id, 'gc_qr_token', gc_generate_station_token($post_id));
    }

    update_post_meta($post_id, 'gc_qr_url', gc_get_station_entry_url($post_id));
});