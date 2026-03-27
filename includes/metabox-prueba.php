<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Metabox para pruebas:
 * - Tipo de prueba (multiple, vf, texto)
 * - Tiempo maximo
 * - Intentos maximos
 * - Preguntas con opciones
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'gc_prueba_config',
        'Configuracion de la Prueba',
        'gc_render_prueba_metabox',
        'prueba',
        'normal',
        'high'
    );
});

function gc_render_prueba_metabox($post) {
    wp_nonce_field('gc_save_prueba_meta', 'gc_prueba_nonce');

    $tipo         = get_post_meta($post->ID, 'gc_tipo', true) ?: 'multiple';
    $tiempo_max_s = get_post_meta($post->ID, 'gc_tiempo_max_s', true) ?: 30;
    $intentos_max = get_post_meta($post->ID, 'gc_intentos_max', true) ?: 2;
    $preguntas    = get_post_meta($post->ID, 'gc_preguntas', true);
    $estacion_ref = get_post_meta($post->ID, 'gc_estacion_ref', true);

    if (!is_array($preguntas)) $preguntas = [];
    ?>
    <table class="form-table">
        <tr>
            <th><label>Estacion enlazada</label></th>
            <td>
                <?php if ($estacion_ref): ?>
                    <a href="<?php echo esc_url(get_edit_post_link((int)$estacion_ref)); ?>">
                        <?php echo esc_html(get_the_title((int)$estacion_ref) ?: '#'.$estacion_ref); ?>
                    </a>
                <?php else: ?>
                    <span style="color:#999;">Sin estacion enlazada</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><label for="gc_tipo">Tipo de prueba</label></th>
            <td>
                <select name="gc_tipo" id="gc_tipo">
                    <option value="multiple" <?php selected($tipo, 'multiple'); ?>>Multiple choice</option>
                    <option value="vf" <?php selected($tipo, 'vf'); ?>>Verdadero / Falso</option>
                    <option value="texto" <?php selected($tipo, 'texto'); ?>>Respuesta de texto</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="gc_tiempo_max_s">Tiempo maximo (seg)</label></th>
            <td>
                <input type="number" name="gc_tiempo_max_s" id="gc_tiempo_max_s" value="<?php echo (int)$tiempo_max_s; ?>" min="5" max="300" style="width:100px;" />
            </td>
        </tr>
        <tr>
            <th><label for="gc_intentos_max">Intentos maximos</label></th>
            <td>
                <input type="number" name="gc_intentos_max" id="gc_intentos_max" value="<?php echo (int)$intentos_max; ?>" min="1" max="10" style="width:100px;" />
            </td>
        </tr>
    </table>

    <h3 style="margin-top:20px;">Preguntas</h3>
    <div id="gc-preguntas-wrap">
        <?php if (empty($preguntas)): ?>
            <p style="color:#999;">No hay preguntas configuradas. Pulsa "Anadir pregunta" para crear una.</p>
        <?php endif; ?>

        <?php foreach ($preguntas as $qi => $p):
            $enunciado = $p['enunciado'] ?? '';
            $p_tipo    = $p['tipo'] ?? $tipo;
            $opciones  = $p['opciones'] ?? [];
            $resp_text = $p['respuesta_texto_correcta'] ?? '';
        ?>
        <div class="gc-pregunta-block" style="border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:12px;background:#fafafa;">
            <p style="margin:0 0 8px;">
                <strong>Pregunta <?php echo ($qi+1); ?></strong>
                <button type="button" class="button gc-remove-pregunta" style="float:right;color:#dc2626;" onclick="this.closest('.gc-pregunta-block').remove();">Eliminar</button>
            </p>
            <p>
                <label>Enunciado:</label><br>
                <textarea name="gc_preguntas[<?php echo $qi; ?>][enunciado]" rows="2" style="width:100%;"><?php echo esc_textarea($enunciado); ?></textarea>
            </p>
            <input type="hidden" name="gc_preguntas[<?php echo $qi; ?>][tipo]" value="<?php echo esc_attr($p_tipo); ?>" />

            <?php if ($p_tipo === 'texto'): ?>
                <p>
                    <label>Respuesta correcta (texto):</label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][respuesta_texto_correcta]" value="<?php echo esc_attr($resp_text); ?>" style="width:100%;" />
                </p>
            <?php else: ?>
                <p><strong>Opciones:</strong></p>
                <?php for ($oi = 0; $oi < max(4, count($opciones)); $oi++):
                    $opt_texto = $opciones[$oi]['texto'] ?? '';
                    $opt_correcta = !empty($opciones[$oi]['es_correcta']) ? 1 : 0;
                ?>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][opciones][<?php echo $oi; ?>][texto]" value="<?php echo esc_attr($opt_texto); ?>" style="flex:1;" placeholder="Opcion <?php echo ($oi+1); ?>" />
                    <label style="white-space:nowrap;">
                        <input type="radio" name="gc_preguntas[<?php echo $qi; ?>][correcta]" value="<?php echo $oi; ?>" <?php checked($opt_correcta, 1); ?> />
                        Correcta
                    </label>
                </div>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="button" class="button" id="gc-add-pregunta">Anadir pregunta</button>

    <script>
    (function(){
        var wrap = document.getElementById('gc-preguntas-wrap');
        var btn  = document.getElementById('gc-add-pregunta');
        if (!wrap || !btn) return;

        btn.addEventListener('click', function(){
            var count = wrap.querySelectorAll('.gc-pregunta-block').length;
            var idx = count;
            var html = '<div class="gc-pregunta-block" style="border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:12px;background:#fafafa;">'
                + '<p style="margin:0 0 8px;"><strong>Pregunta ' + (idx+1) + '</strong>'
                + '<button type="button" class="button gc-remove-pregunta" style="float:right;color:#dc2626;" onclick="this.closest(\'.gc-pregunta-block\').remove();">Eliminar</button></p>'
                + '<p><label>Enunciado:</label><br><textarea name="gc_preguntas[' + idx + '][enunciado]" rows="2" style="width:100%;"></textarea></p>'
                + '<input type="hidden" name="gc_preguntas[' + idx + '][tipo]" value="multiple" />'
                + '<p><strong>Opciones:</strong></p>';
            for (var i = 0; i < 4; i++) {
                html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">'
                    + '<input type="text" name="gc_preguntas[' + idx + '][opciones][' + i + '][texto]" style="flex:1;" placeholder="Opcion ' + (i+1) + '" />'
                    + '<label style="white-space:nowrap;"><input type="radio" name="gc_preguntas[' + idx + '][correcta]" value="' + i + '" /> Correcta</label>'
                    + '</div>';
            }
            html += '</div>';
            wrap.insertAdjacentHTML('beforeend', html);
        });
    })();
    </script>
    <?php
}

/**
 * Guardado de datos de prueba
 */
add_action('save_post', function ($post_id) {
    if ( ! isset($_POST['gc_prueba_nonce']) ) return;
    if ( ! wp_verify_nonce($_POST['gc_prueba_nonce'], 'gc_save_prueba_meta') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( get_post_type($post_id) !== 'prueba' ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    update_post_meta($post_id, 'gc_tipo', sanitize_text_field($_POST['gc_tipo'] ?? 'multiple'));
    update_post_meta($post_id, 'gc_tiempo_max_s', max(5, (int)($_POST['gc_tiempo_max_s'] ?? 30)));
    update_post_meta($post_id, 'gc_intentos_max', max(1, (int)($_POST['gc_intentos_max'] ?? 2)));

    // Procesar preguntas
    $raw_preguntas = $_POST['gc_preguntas'] ?? [];
    $preguntas = [];

    if (is_array($raw_preguntas)) {
        foreach ($raw_preguntas as $qi => $p) {
            $enunciado = sanitize_textarea_field($p['enunciado'] ?? '');
            if ($enunciado === '') continue;

            $p_tipo = sanitize_text_field($p['tipo'] ?? 'multiple');
            $correcta_idx = isset($p['correcta']) ? (int)$p['correcta'] : -1;

            $pregunta = [
                'tipo'      => $p_tipo,
                'enunciado' => $enunciado,
            ];

            if ($p_tipo === 'texto') {
                $pregunta['respuesta_texto_correcta'] = sanitize_text_field($p['respuesta_texto_correcta'] ?? '');
                $pregunta['opciones'] = [];
            } else {
                $opciones = [];
                $raw_opts = $p['opciones'] ?? [];
                if (is_array($raw_opts)) {
                    foreach ($raw_opts as $oi => $opt) {
                        $texto = sanitize_text_field($opt['texto'] ?? '');
                        if ($texto === '') continue;
                        $opciones[] = [
                            'texto'       => $texto,
                            'es_correcta' => ((int)$oi === $correcta_idx) ? 1 : 0,
                        ];
                    }
                }
                $pregunta['opciones'] = $opciones;
                $pregunta['respuesta_texto_correcta'] = '';
            }

            $preguntas[] = $pregunta;
        }
    }

    update_post_meta($post_id, 'gc_preguntas', $preguntas);
});
