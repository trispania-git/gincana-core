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

    $tipo = get_post_meta($post->ID, 'gc_tipo_escenario', true);
    if (empty($tipo)) {
        $tipo = 'adulto';
    }
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
                    Adulto: el QR de cada estación abre una pregunta tipo test.<br>
                    Infantil: el QR de cada estación valida que ha sido encontrada.
                </p>
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
});