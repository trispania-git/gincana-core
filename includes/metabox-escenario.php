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

    $tipo            = get_post_meta($post->ID, 'gc_tipo_escenario', true) ?: 'adulto';
    $tipo_qr         = get_post_meta($post->ID, 'gc_tipo_qr', true) ?: 'enlace';
    $mostrar_puntos  = get_post_meta($post->ID, 'gc_mostrar_puntos', true);
    if ($mostrar_puntos === '') $mostrar_puntos = '1'; // default activo
    $label_estacion  = get_post_meta($post->ID, 'gc_label_estacion', true);
    $cta_texto       = get_post_meta($post->ID, 'gc_cta_texto', true);
    $descripcion     = get_post_meta($post->ID, 'gc_descripcion', true);
    $audio           = get_post_meta($post->ID, 'gc_audio', true);
    $img1            = get_post_meta($post->ID, 'gc_img_1', true);
    $img2            = get_post_meta($post->ID, 'gc_img_2', true);
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
            <th><label for="gc_tipo_qr">Tipo de QR</label></th>
            <td>
                <select name="gc_tipo_qr" id="gc_tipo_qr">
                    <option value="enlace" <?php selected($tipo_qr, 'enlace'); ?>>Enlace directo a la estación</option>
                    <option value="validacion" <?php selected($tipo_qr, 'validacion'); ?>>Validación (requiere escanear QR in situ)</option>
                </select>
                <p class="description">
                    <strong>Enlace:</strong> el QR lleva directamente a la página de la estación. Ideal para escenarios con pruebas (quiz).<br>
                    <strong>Validación:</strong> el QR valida que el jugador está en el lugar. Ideal para escenarios de búsqueda (infantil).
                </p>
            </td>
        </tr>

        <tr>
            <th>Mostrar puntos</th>
            <td>
                <label style="display:inline-flex;gap:8px;align-items:center;">
                    <input type="checkbox" name="gc_mostrar_puntos" value="1" <?php checked($mostrar_puntos, '1'); ?> />
                    <span>Mostrar puntuación a los jugadores</span>
                </label>
                <p class="description">Si se desactiva, no se muestran puntos en la lista de estaciones, la barra de progreso ni el itinerario. Útil para escenarios infantiles sin sistema de puntos.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_label_estacion">Nombre de las paradas (singular)</label></th>
            <td>
                <input type="text" name="gc_label_estacion" id="gc_label_estacion"
                       value="<?php echo esc_attr($label_estacion); ?>"
                       placeholder="estación" style="width:200px;" />
                <p class="description">Singular: "estación", "puerta", "paso", "parada"…</p>
            </td>
        </tr>
        <tr>
            <th><label for="gc_label_estacion_plural">Nombre plural + artículo</label></th>
            <td>
                <input type="text" name="gc_label_estacion_plural" id="gc_label_estacion_plural"
                       value="<?php echo esc_attr(get_post_meta($post->ID, 'gc_label_estacion_plural', true)); ?>"
                       placeholder="las estaciones" style="width:300px;" />
                <p class="description">Plural con artículo: "las estaciones", "las puertas", "los pasos". Se usa en el CTA y el progreso.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_cta_texto">Texto CTA</label></th>
            <td>
                <input type="text" name="gc_cta_texto" id="gc_cta_texto"
                       value="<?php echo esc_attr($cta_texto); ?>"
                       placeholder="¿Te animas? ¡Comienza la aventura y completa las estaciones!" style="width:100%;" />
                <p class="description">Si se deja vacío se genera automáticamente usando el plural configurado arriba.</p>
                <p class="description">Texto motivacional que aparece antes de la lista de estaciones. Si se deja vacio se usa el texto por defecto.</p>
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
            <th><label for="gc_audio">Audio</label></th>
            <td>
                <?php gc_render_media_field('gc_audio', $audio, 'audio', 'Seleccionar audio'); ?>
                <p class="description">Audio introductorio o narracion del escenario.</p>
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
    $tipo_qr = sanitize_text_field($_POST['gc_tipo_qr'] ?? 'enlace');
    if ( ! in_array($tipo_qr, ['enlace', 'validacion'], true) ) $tipo_qr = 'enlace';
    update_post_meta($post_id, 'gc_tipo_qr', $tipo_qr);
    update_post_meta($post_id, 'gc_mostrar_puntos', isset($_POST['gc_mostrar_puntos']) ? '1' : '0');
    update_post_meta($post_id, 'gc_label_estacion', sanitize_text_field($_POST['gc_label_estacion'] ?? ''));
    update_post_meta($post_id, 'gc_label_estacion_plural', sanitize_text_field($_POST['gc_label_estacion_plural'] ?? ''));
    update_post_meta($post_id, 'gc_cta_texto', sanitize_text_field($_POST['gc_cta_texto'] ?? ''));
    update_post_meta($post_id, 'gc_descripcion', wp_kses_post($_POST['gc_descripcion'] ?? ''));
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));
});