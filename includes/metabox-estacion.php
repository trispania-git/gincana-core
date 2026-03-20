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

    $descripcion = get_post_meta($post->ID, 'gc_descripcion', true);
    $audio = get_post_meta($post->ID, 'gc_audio', true);
    $img1  = get_post_meta($post->ID, 'gc_img_1', true);
    $img2  = get_post_meta($post->ID, 'gc_img_2', true);
    $token = get_post_meta($post->ID, 'gc_qr_token', true);

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
            <th><label for="gc_audio">Audio (URL)</label></th>
            <td>
                <input type="text" name="gc_audio" id="gc_audio" value="<?php echo esc_attr($audio); ?>" style="width:100%;" />
                <p class="description">Sube el audio a la biblioteca multimedia y pega aquí la URL.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_img_1">Imagen extra 1</label></th>
            <td>
                <input type="text" name="gc_img_1" id="gc_img_1" value="<?php echo esc_attr($img1); ?>" style="width:100%;" />
            </td>
        </tr>

        <tr>
            <th><label for="gc_img_2">Imagen extra 2</label></th>
            <td>
                <input type="text" name="gc_img_2" id="gc_img_2" value="<?php echo esc_attr($img2); ?>" style="width:100%;" />
            </td>
        </tr>

        <tr>
            <th>Token QR</th>
            <td>
                <code><?php echo esc_html($token); ?></code>
                <p class="description">Se genera automáticamente y se usa para validar el acceso por QR.</p>
            </td>
        </tr>

        <tr>
            <th>URL QR</th>
            <td>
                <input type="text" readonly value="<?php echo esc_attr($qr_url); ?>" style="width:100%;background:#f6f7f7;" />
                <p class="description">Esta es la URL que debes convertir en QR.</p>
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
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));

    $token = get_post_meta($post_id, 'gc_qr_token', true);
    if (empty($token)) {
        update_post_meta($post_id, 'gc_qr_token', gc_generate_station_token($post_id));
    }

    update_post_meta($post_id, 'gc_qr_url', gc_get_station_entry_url($post_id));
});