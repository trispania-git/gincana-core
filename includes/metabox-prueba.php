<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Metabox para pruebas:
 * - Tipo de prueba (multiple, vf, texto)
 * - Tiempo maximo
 * - Intentos maximos
 * - Preguntas con opciones
 */

/**
 * Desactivar validación ACF "required" en el campo "Estación vinculada"
 * para permitir crear pruebas-pool sin estación enlazada.
 */
add_filter('acf/validate_value', function ($valid, $value, $field, $input_name) {
    // Solo nos interesa el campo de estación ref en pruebas
    if (!$valid || $valid !== true) return $valid;

    $label = $field['label'] ?? '';
    $name  = $field['name'] ?? '';

    // Coincide con el campo ACF de estación (por nombre o label)
    if (
        $name === 'gc_estacion_ref' ||
        $name === 'estacion_vinculada' ||
        stripos($label, 'vinculada') !== false ||
        stripos($label, 'estacion') !== false
    ) {
        // Si estamos editando una prueba, hacerlo opcional
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'prueba') {
            return true; // siempre válido, no required
        }
    }
    return $valid;
}, 10, 4);

/**
 * Alternativa: quitar "required" del campo ACF antes de renderizar
 */
add_filter('acf/load_field', function ($field) {
    if (!is_admin()) return $field;

    $name  = $field['name'] ?? '';
    $label = $field['label'] ?? '';

    if (
        $name === 'gc_estacion_ref' ||
        $name === 'estacion_vinculada' ||
        stripos($label, 'vinculada') !== false
    ) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === 'prueba') {
            $field['required'] = 0;
        }
    }
    return $field;
});

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
    <?php
    // Detectar si esta prueba se usa como pool en algún escenario
    $pool_escenarios = get_posts([
        'post_type'      => 'escenario',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [['key' => 'gc_pool_prueba_ref', 'value' => $post->ID, 'compare' => '=']],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $is_pool = !empty($pool_escenarios);
    ?>

    <?php if ($is_pool): ?>
    <div style="padding:14px 16px;border-radius:10px;background:#f5f3ff;border:1px solid #c4b5fd;margin-bottom:16px;display:flex;gap:10px;align-items:center;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 12l-4-4-4 4"/><path d="M12 16V8"/></svg>
        <div>
            <strong style="color:#5b21b6;">Esta prueba se usa como pool aleatorio</strong>
            <div style="font-size:12px;color:#6d28d9;margin-top:2px;">
                Escenario<?php echo count($pool_escenarios) > 1 ? 's' : ''; ?>:
                <?php
                foreach ($pool_escenarios as $pe_id) {
                    echo '<a href="' . esc_url(get_edit_post_link($pe_id)) . '">' . esc_html(get_the_title($pe_id)) . '</a> ';
                }
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <table class="form-table">
        <?php if (!$is_pool): ?>
        <tr>
            <th><label>Estacion enlazada</label></th>
            <td>
                <?php if ($estacion_ref): ?>
                    <a href="<?php echo esc_url(get_edit_post_link((int)$estacion_ref)); ?>">
                        <?php echo esc_html(get_the_title((int)$estacion_ref) ?: '#'.$estacion_ref); ?>
                    </a>
                <?php else: ?>
                    <span style="color:#999;">Sin estacion enlazada — puede usarse como pool desde un escenario</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
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

    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="button" id="gc-add-pregunta">Anadir pregunta</button>

        <span style="color:#94a3b8;">o</span>

        <button type="button" class="button" id="gc-csv-toggle" style="border-color:#8b5cf6;color:#8b5cf6;">
            Importar desde CSV
        </button>
    </div>

    <!-- Panel de importación CSV -->
    <div id="gc-csv-import-panel" style="display:none;margin-top:16px;padding:20px;border:2px dashed #d1d5db;border-radius:12px;background:#fafafe;">
        <h4 style="margin:0 0 8px;font-size:14px;">Importar preguntas desde CSV</h4>
        <p style="margin:0 0 12px;font-size:13px;color:#64748b;">
            Formato: <code>enunciado;opcion_1;opcion_2;opcion_3;opcion_4;correcta</code><br>
            <strong>correcta</strong> = numero de opcion correcta (1, 2, 3 o 4).<br>
            Separador: <code>;</code> o <code>,</code> (auto-detectado). Primera fila = cabecera (se ignora).
        </p>
        <div style="margin-bottom:12px;">
            <textarea id="gc-csv-textarea" rows="6" style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid #d1d5db;border-radius:8px;"
                      placeholder="enunciado;opcion_1;opcion_2;opcion_3;opcion_4;correcta&#10;¿Capital de España?;Barcelona;Madrid;Sevilla;Valencia;2&#10;¿Color del cielo?;Verde;Rojo;Azul;Amarillo;3"></textarea>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#475569;">
                <input type="checkbox" id="gc-csv-replace" />
                Reemplazar preguntas existentes
            </label>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;">
            <button type="button" class="button button-primary" id="gc-csv-import-btn">Importar preguntas</button>
            <button type="button" class="button" id="gc-csv-cancel">Cancelar</button>
        </div>
        <div id="gc-csv-result" style="margin-top:12px;"></div>
    </div>

    <script>
    (function(){
        var wrap = document.getElementById('gc-preguntas-wrap');
        var btn  = document.getElementById('gc-add-pregunta');
        if (!wrap || !btn) return;

        // === Añadir pregunta manual ===
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

        // === CSV Import ===
        var csvPanel = document.getElementById('gc-csv-import-panel');
        var csvToggle = document.getElementById('gc-csv-toggle');
        var csvCancel = document.getElementById('gc-csv-cancel');
        var csvImportBtn = document.getElementById('gc-csv-import-btn');
        var csvTextarea = document.getElementById('gc-csv-textarea');
        var csvReplace = document.getElementById('gc-csv-replace');
        var csvResult = document.getElementById('gc-csv-result');

        csvToggle.addEventListener('click', function(){
            csvPanel.style.display = csvPanel.style.display === 'none' ? '' : 'none';
        });
        csvCancel.addEventListener('click', function(){
            csvPanel.style.display = 'none';
        });

        csvImportBtn.addEventListener('click', function(){
            var raw = csvTextarea.value.trim();
            if (!raw) { csvResult.innerHTML = '<span style="color:#dc2626;">Pega el contenido CSV.</span>'; return; }

            var lines = raw.split('\n').map(function(l){ return l.trim(); }).filter(function(l){ return l !== ''; });
            if (lines.length < 2) { csvResult.innerHTML = '<span style="color:#dc2626;">Se necesita al menos la cabecera y una fila.</span>'; return; }

            // Auto-detectar separador
            var sep = (lines[0].split(';').length > lines[0].split(',').length) ? ';' : ',';

            // Saltar cabecera
            var dataLines = lines.slice(1);
            var imported = 0;
            var errors = [];

            // Si reemplazar, limpiar preguntas existentes
            if (csvReplace.checked) {
                wrap.innerHTML = '';
            }

            dataLines.forEach(function(line, li){
                var cols = line.split(sep).map(function(c){ return c.trim().replace(/^["']|["']$/g, ''); });
                if (cols.length < 6) { errors.push('Fila ' + (li+2) + ': faltan columnas (necesita 6).'); return; }

                var enunciado = cols[0];
                var op1 = cols[1], op2 = cols[2], op3 = cols[3], op4 = cols[4];
                var correcta = parseInt(cols[5], 10);

                if (!enunciado) { errors.push('Fila ' + (li+2) + ': enunciado vacio.'); return; }
                if (correcta < 1 || correcta > 4) { errors.push('Fila ' + (li+2) + ': correcta debe ser 1-4 (recibido: ' + cols[5] + ').'); return; }

                var idx = wrap.querySelectorAll('.gc-pregunta-block').length;
                var ops = [op1, op2, op3, op4];

                var html = '<div class="gc-pregunta-block" style="border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:12px;background:#fafafa;">'
                    + '<p style="margin:0 0 8px;"><strong>Pregunta ' + (idx+1) + '</strong>'
                    + '<button type="button" class="button gc-remove-pregunta" style="float:right;color:#dc2626;" onclick="this.closest(\'.gc-pregunta-block\').remove();">Eliminar</button></p>'
                    + '<p><label>Enunciado:</label><br><textarea name="gc_preguntas[' + idx + '][enunciado]" rows="2" style="width:100%;">' + enunciado.replace(/</g,'&lt;') + '</textarea></p>'
                    + '<input type="hidden" name="gc_preguntas[' + idx + '][tipo]" value="multiple" />'
                    + '<p><strong>Opciones:</strong></p>';

                for (var i = 0; i < 4; i++) {
                    var checked = ((i + 1) === correcta) ? ' checked' : '';
                    html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">'
                        + '<input type="text" name="gc_preguntas[' + idx + '][opciones][' + i + '][texto]" style="flex:1;" value="' + ops[i].replace(/"/g,'&quot;') + '" placeholder="Opcion ' + (i+1) + '" />'
                        + '<label style="white-space:nowrap;"><input type="radio" name="gc_preguntas[' + idx + '][correcta]" value="' + i + '"' + checked + ' /> Correcta</label>'
                        + '</div>';
                }
                html += '</div>';
                wrap.insertAdjacentHTML('beforeend', html);
                imported++;
            });

            var msg = '<span style="color:#16a34a;font-weight:600;">' + imported + ' preguntas importadas.</span>';
            if (errors.length) {
                msg += '<br><span style="color:#dc2626;">' + errors.join('<br>') + '</span>';
            }
            msg += '<br><span style="color:#64748b;font-size:12px;">Recuerda pulsar <strong>Publicar</strong> o <strong>Actualizar</strong> para guardar.</span>';
            csvResult.innerHTML = msg;
            csvTextarea.value = '';
            csvPanel.style.display = 'none';
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
