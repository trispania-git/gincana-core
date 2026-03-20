<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Metabox para escenarios:
 * - tipo de escenario (adulto / infantil)
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'gc_escenario_config',
        'Configuración del Escenario',
        'gc_render_escenario_metabox',
        'escenario',
        'normal',
        'high'
    );
});

function gc_render_escenario_metabox($post) {
    wp_nonce_field('gc_save_escenario_meta', 'gc_escenario_nonce');

    $tipo        = get_post_meta($post->ID, 'gc_tipo_escenario', true) ?: 'adulto';
    $descripcion = get_post_meta($post->ID, 'gc_descripcion', true);
    $audio       = get_post_meta($post->ID, 'gc_audio', true);
    $img1        = get_post_meta($post->ID, 'gc_img_1', true);
    $img2        = get_post_meta($post->ID, 'gc_img_2', true);
    ?>
    <table class="form-table">
        <tr>
            <th><label for="gc_tipo_escenario">Tipo de escenario</label></th>
            <td>
                <select name="gc_tipo_escenario" id="gc_tipo_escenario">
                    <option value="adulto" <?php selected($tipo, 'adulto'); ?>>Adulto</option>
                    <option value="infantil" <?php selected($tipo, 'infantil'); ?>>Infantil</option>
                </select>
                <p class="description">
                    Adulto: el QR de cada estacion abre una pregunta tipo test.<br>
                    Infantil: el QR de cada estacion valida que ha sido encontrada.
                </p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_descripcion">Descripcion</label></th>
            <td>
                <?php
                wp_editor($descripcion, 'gc_esc_descripcion', [
                    'textarea_name' => 'gc_descripcion',
                    'textarea_rows' => 6,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Texto introductorio del escenario. Se muestra al jugador en la pagina principal.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_audio">Audio (URL)</label></th>
            <td>
                <input type="text" name="gc_audio" id="gc_audio" value="<?php echo esc_attr($audio); ?>" style="width:100%;" />
                <p class="description">Audio introductorio o narración. Sube a la biblioteca multimedia y pega la URL.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_img_1">Imagen 1 (URL)</label></th>
            <td>
                <input type="text" name="gc_img_1" id="gc_img_1" value="<?php echo esc_attr($img1); ?>" style="width:100%;" />
            </td>
        </tr>

        <tr>
            <th><label for="gc_img_2">Imagen 2 (URL)</label></th>
            <td>
                <input type="text" name="gc_img_2" id="gc_img_2" value="<?php echo esc_attr($img2); ?>" style="width:100%;" />
            </td>
        </tr>
    </table>
    <?php
}

add_action('save_post', function ($post_id) {
    if ( ! isset($_POST['gc_escenario_nonce']) ) return;
    if ( ! wp_verify_nonce($_POST['gc_escenario_nonce'], 'gc_save_escenario_meta') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( get_post_type($post_id) !== 'escenario' ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $tipo = sanitize_text_field($_POST['gc_tipo_escenario'] ?? 'adulto');
    if ( ! in_array($tipo, ['adulto', 'infantil'], true) ) {
        $tipo = 'adulto';
    }

    update_post_meta($post_id, 'gc_tipo_escenario', $tipo);
    update_post_meta($post_id, 'gc_descripcion', wp_kses_post($_POST['gc_descripcion'] ?? ''));
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));
});