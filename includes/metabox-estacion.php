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
    $maps_url = get_post_meta($post->ID, 'gc_maps_url', true);
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
            <th><label for="gc_maps_url">Ubicacion (Google Maps)</label></th>
            <td>
                <input type="url" name="gc_maps_url" id="gc_maps_url" value="<?php echo esc_attr($maps_url); ?>" style="width:100%;" placeholder="https://maps.google.com/..." />
                <p class="description">Enlace de Google Maps del lugar. Se mostrara como icono de ubicacion.</p>
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
    update_post_meta($post_id, 'gc_maps_url', esc_url_raw($_POST['gc_maps_url'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));

    $token = get_post_meta($post_id, 'gc_qr_token', true);
    if (empty($token)) {
        update_post_meta($post_id, 'gc_qr_token', gc_generate_station_token($post_id));
    }

    update_post_meta($post_id, 'gc_qr_url', gc_get_station_entry_url($post_id));

    // Auto-actualizar slug cuando cambia el título
    $post = get_post($post_id);
    if ($post) {
        $new_slug = sanitize_title($post->post_title);
        if ($new_slug && $new_slug !== $post->post_name) {
            remove_action('save_post', __FUNCTION__); // evitar loop
            wp_update_post([
                'ID'        => $post_id,
                'post_name' => wp_unique_post_slug($new_slug, $post_id, $post->post_status, $post->post_type, $post->post_parent),
            ]);
        }
    }
});