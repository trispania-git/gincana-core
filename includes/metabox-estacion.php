<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Metabox para estaciones (tipo, multimedia, etc.)
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

function gc_render_estacion_metabox($post) {
    wp_nonce_field('gc_save_estacion_meta', 'gc_estacion_nonce');

    $tipo = get_post_meta($post->ID, 'gc_tipo_estacion', true);
    $audio = get_post_meta($post->ID, 'gc_audio', true);
    $img1 = get_post_meta($post->ID, 'gc_img_1', true);
    $img2 = get_post_meta($post->ID, 'gc_img_2', true);

    ?>
    <table class="form-table">

        <tr>
            <th><label>Tipo de estación</label></th>
            <td>
                <select name="gc_tipo_estacion">
                    <option value="adulto" <?php selected($tipo, 'adulto'); ?>>Adulto</option>
                    <option value="infantil" <?php selected($tipo, 'infantil'); ?>>Infantil</option>
                </select>
            </td>
        </tr>

        <tr>
            <th><label>Audio (URL)</label></th>
            <td>
                <input type="text" name="gc_audio" value="<?php echo esc_attr($audio); ?>" style="width:100%;" />
                <p class="description">Sube el audio a la biblioteca y pega la URL.</p>
            </td>
        </tr>

        <tr>
            <th><label>Imagen extra 1</label></th>
            <td>
                <input type="text" name="gc_img_1" value="<?php echo esc_attr($img1); ?>" style="width:100%;" />
            </td>
        </tr>

        <tr>
            <th><label>Imagen extra 2</label></th>
            <td>
                <input type="text" name="gc_img_2" value="<?php echo esc_attr($img2); ?>" style="width:100%;" />
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

    update_post_meta($post_id, 'gc_tipo_estacion', sanitize_text_field($_POST['gc_tipo_estacion'] ?? 'adulto'));
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));

});