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
        <?php if (!$is_pool):
            // Cargar estaciones disponibles agrupadas por escenario
            $all_est = get_posts([
                'post_type'      => 'estacion',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'meta_value_num title',
                'order'          => 'ASC',
                'meta_key'       => 'gc_orden',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            // Agrupar por escenario
            $grouped = [];
            foreach ($all_est as $e_id) {
                $esc_id  = (int) get_post_meta($e_id, 'gc_escenario_ref', true);
                $esc_key = $esc_id ?: 0;
                if (!isset($grouped[$esc_key])) $grouped[$esc_key] = [];
                $grouped[$esc_key][] = $e_id;
            }
        ?>
        <tr>
            <th><label for="gc_estacion_ref">Estacion enlazada</label></th>
            <td>
                <select name="gc_estacion_ref" id="gc_estacion_ref" style="min-width:340px;">
                    <option value="">— Sin estación (usar como pool) —</option>
                    <?php foreach ($grouped as $esc_id => $eids):
                        $esc_title = $esc_id ? (get_the_title($esc_id) ?: '#'.$esc_id) : 'Sin escenario';
                    ?>
                    <optgroup label="<?php echo esc_attr($esc_title); ?>">
                        <?php foreach ($eids as $eid):
                            $order = (int) get_post_meta($eid, 'gc_orden', true);
                            $label_title = get_the_title($eid) ?: ('Estación #' . $eid);
                            if ($order) $label_title = $order . '. ' . $label_title;
                        ?>
                        <option value="<?php echo (int)$eid; ?>" <?php selected((int)$estacion_ref, (int)$eid); ?>><?php echo esc_html($label_title); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
                <?php if ($estacion_ref): ?>
                    <a href="<?php echo esc_url(get_edit_post_link((int)$estacion_ref)); ?>" style="margin-left:8px;">Ver estación</a>
                <?php endif; ?>
                <p class="description">Asocia esta prueba a una estación concreta (modo "por estación"). Déjalo en blanco si la prueba se usará como pool aleatorio.</p>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><label for="gc_tipo">Tipo de prueba</label></th>
            <td>
                <select name="gc_tipo" id="gc_tipo">
                    <option value="multiple" <?php selected($tipo, 'multiple'); ?>>Multiple choice (texto)</option>
                    <option value="multiple_imagen" <?php selected($tipo, 'multiple_imagen'); ?>>Multiple choice (imágenes) 🖼️</option>
                    <option value="vf" <?php selected($tipo, 'vf'); ?>>Verdadero / Falso</option>
                    <option value="texto" <?php selected($tipo, 'texto'); ?>>Respuesta de texto</option>
                    <option value="cifrado_cesar" <?php selected($tipo, 'cifrado_cesar'); ?>>Descifrar mensaje (cifrado César) 🔐</option>
                    <option value="anagrama" <?php selected($tipo, 'anagrama'); ?>>Anagrama (reordenar letras) 🔀</option>
                    <option value="ahorcado" <?php selected($tipo, 'ahorcado'); ?>>Ahorcado (adivinar palabra letra a letra) 🎯</option>
                    <option value="sopa_letras" <?php selected($tipo, 'sopa_letras'); ?>>Sopa de letras (encontrar la palabra) 🔡</option>
                </select>
                <p class="description" style="margin-top:6px;">
                    <strong>Multiple imágenes:</strong> el jugador elige la imagen correcta entre 4 opciones.
                    <strong>Cifrado César:</strong> muestra un mensaje cifrado y el jugador escribe la palabra original.
                    <strong>Anagrama:</strong> muestra letras desordenadas y el jugador escribe la palabra original.
                    <strong>Ahorcado:</strong> el jugador descubre la palabra letra a letra; cada letra fallada gasta un intento.
                    <strong>Sopa de letras:</strong> una palabra escondida en un grid. El jugador la encuentra arrastrando o tocando primera/última letra.
                </p>
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
            $rotacion  = isset($p['rotacion']) ? (int) $p['rotacion'] : 3;
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

            <?php if ($p_tipo === 'texto' || $p_tipo === 'anagrama'): ?>
                <p>
                    <label><?php echo $p_tipo === 'anagrama' ? 'Palabra a adivinar (las letras se mostrarán desordenadas):' : 'Respuesta correcta (texto):'; ?></label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][respuesta_texto_correcta]" value="<?php echo esc_attr($resp_text); ?>" style="width:100%;" placeholder="<?php echo $p_tipo === 'anagrama' ? 'Ej: GYMKANA' : ''; ?>" />
                </p>
            <?php elseif ($p_tipo === 'sopa_letras'):
                $sopa_tamano = isset($p['tamano_grid']) ? (int) $p['tamano_grid'] : 10;
            ?>
                <p>
                    <label>Palabra a encontrar:</label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][respuesta_texto_correcta]" value="<?php echo esc_attr($resp_text); ?>" style="width:100%;" placeholder="Ej: GYMKANA" />
                    <span style="color:#64748b;font-size:12px;">Se mostrará en MAYÚSCULAS, sin acentos. Mínimo 3 letras, máximo igual al tamaño del grid.</span>
                </p>
                <p>
                    <label>Tamaño del grid:</label><br>
                    <select name="gc_preguntas[<?php echo $qi; ?>][tamano_grid]">
                        <?php foreach ([8, 10, 12, 15] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php selected($sopa_tamano, $t); ?>><?php echo $t; ?> × <?php echo $t; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span style="color:#64748b;font-size:12px;">10×10 funciona bien para móviles. Palabras largas necesitan grid mayor.</span>
                </p>
                <p style="color:#64748b;font-size:12px;">El grid se genera al primer acceso de cada jugador y se mantiene fijo aunque recargue la página.</p>
            <?php elseif ($p_tipo === 'ahorcado'):
                $pista_txt = $p['pista'] ?? '';
                $categoria = $p['categoria'] ?? '';
            ?>
                <p>
                    <label>Palabra a adivinar:</label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][respuesta_texto_correcta]" value="<?php echo esc_attr($resp_text); ?>" style="width:100%;" placeholder="Ej: GYMKANA" />
                    <span style="color:#64748b;font-size:12px;">Solo se mostrarán las letras (los espacios y signos se respetan). Las tildes se ignoran al comparar.</span>
                </p>
                <p>
                    <label>Categoría (opcional):</label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][categoria]" value="<?php echo esc_attr($categoria); ?>" style="width:100%;" placeholder="Ej: Animal · Ciudad · Deporte…" />
                    <span style="color:#64748b;font-size:12px;">Se muestra arriba de los huecos como pista visual de la categoría.</span>
                </p>
                <p>
                    <label>Pista de texto (opcional):</label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][pista]" value="<?php echo esc_attr($pista_txt); ?>" style="width:100%;" placeholder="Ej: Es lo que estás jugando ahora mismo." />
                </p>
                <p style="color:#64748b;font-size:12px;">El número de letras erróneas máximo se controla con <strong>"Intentos máximos"</strong> arriba.</p>
            <?php elseif ($p_tipo === 'cifrado_cesar'): ?>
                <p>
                    <label>Mensaje original (lo que el jugador debe descifrar):</label><br>
                    <input type="text" name="gc_preguntas[<?php echo $qi; ?>][respuesta_texto_correcta]" value="<?php echo esc_attr($resp_text); ?>" style="width:100%;" placeholder="Ej: GYMKANA" />
                </p>
                <p>
                    <label>Rotación del cifrado César (1-25):</label><br>
                    <input type="number" name="gc_preguntas[<?php echo $qi; ?>][rotacion]" value="<?php echo (int)$rotacion; ?>" min="1" max="25" style="width:80px;" />
                    <span style="color:#64748b;font-size:12px;margin-left:8px;">Ej: rotación 3 → 'GYMKANA' se mostrará como 'JBPNDQD'.</span>
                </p>
            <?php elseif ($p_tipo === 'multiple_imagen'): ?>
                <p><strong>Opciones (imágenes):</strong></p>
                <?php for ($oi = 0; $oi < max(4, count($opciones)); $oi++):
                    $opt_texto = $opciones[$oi]['texto'] ?? '';
                    $opt_imagen = $opciones[$oi]['imagen'] ?? '';
                    $opt_correcta = !empty($opciones[$oi]['es_correcta']) ? 1 : 0;
                ?>
                <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;">
                    <div style="flex-shrink:0;width:90px;text-align:center;">
                        <?php if ($opt_imagen): ?>
                            <img src="<?php echo esc_url($opt_imagen); ?>" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                        <?php else: ?>
                            <div style="width:90px;height:90px;border:2px dashed #cbd5e1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:11px;text-align:center;">Sin imagen</div>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                            <input type="text" name="gc_preguntas[<?php echo $qi; ?>][opciones][<?php echo $oi; ?>][imagen]" value="<?php echo esc_attr($opt_imagen); ?>" class="gc-media-input" style="flex:1;" placeholder="URL imagen <?php echo ($oi+1); ?>" />
                            <button type="button" class="button gc-media-select" data-type="image">Imagen</button>
                            <?php if ($opt_imagen): ?>
                                <button type="button" class="button gc-media-clear" title="Quitar">&times;</button>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" name="gc_preguntas[<?php echo $qi; ?>][opciones][<?php echo $oi; ?>][texto]" value="<?php echo esc_attr($opt_texto); ?>" style="flex:1;" placeholder="Texto opcional (pie de imagen)" />
                            <label style="white-space:nowrap;font-size:13px;">
                                <input type="radio" name="gc_preguntas[<?php echo $qi; ?>][correcta]" value="<?php echo $oi; ?>" <?php checked($opt_correcta, 1); ?> />
                                Correcta
                            </label>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
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

        // Helper: tipo actual del select
        function currentTipo() {
            var sel = document.getElementById('gc_tipo');
            return sel ? sel.value : 'multiple';
        }

        // === Cambio de tipo de prueba: limpiar preguntas si hay diferencias ===
        var tipoSelect = document.getElementById('gc_tipo');
        if (tipoSelect) {
            var prevTipo = tipoSelect.value;
            tipoSelect.addEventListener('change', function () {
                var newTipo = tipoSelect.value;
                if (newTipo === prevTipo) return;
                var existing = wrap.querySelectorAll('.gc-pregunta-block');
                if (existing.length > 0) {
                    var ok = confirm('Vas a cambiar el tipo de prueba de "' + prevTipo + '" a "' + newTipo + '". ' +
                                     'Las preguntas actuales (' + existing.length + ') se eliminarán porque los datos no son compatibles. ¿Continuar?');
                    if (!ok) {
                        // Restaurar el valor anterior
                        tipoSelect.value = prevTipo;
                        return;
                    }
                    // Limpiar todas las preguntas
                    existing.forEach(function (b) { b.remove(); });
                }
                prevTipo = newTipo;
            });
        }

        // === Añadir pregunta manual ===
        btn.addEventListener('click', function(){
            var count = wrap.querySelectorAll('.gc-pregunta-block').length;
            var idx = count;
            var tipoNow = currentTipo();
            var html = '<div class="gc-pregunta-block" style="border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:12px;background:#fafafa;">'
                + '<p style="margin:0 0 8px;"><strong>Pregunta ' + (idx+1) + '</strong>'
                + '<button type="button" class="button gc-remove-pregunta" style="float:right;color:#dc2626;" onclick="this.closest(\'.gc-pregunta-block\').remove();">Eliminar</button></p>'
                + '<p><label>Enunciado:</label><br><textarea name="gc_preguntas[' + idx + '][enunciado]" rows="2" style="width:100%;"></textarea></p>'
                + '<input type="hidden" name="gc_preguntas[' + idx + '][tipo]" value="' + tipoNow + '" />';

            if (tipoNow === 'texto' || tipoNow === 'anagrama') {
                var labelTxt = tipoNow === 'anagrama' ? 'Palabra a adivinar (las letras se mostrarán desordenadas):' : 'Respuesta correcta (texto):';
                var ph = tipoNow === 'anagrama' ? 'Ej: GYMKANA' : '';
                html += '<p><label>' + labelTxt + '</label><br>'
                    + '<input type="text" name="gc_preguntas[' + idx + '][respuesta_texto_correcta]" style="width:100%;" placeholder="' + ph + '" /></p>';
            } else if (tipoNow === 'sopa_letras') {
                html += '<p><label>Palabra a encontrar:</label><br>'
                    + '<input type="text" name="gc_preguntas[' + idx + '][respuesta_texto_correcta]" style="width:100%;" placeholder="Ej: GYMKANA" />'
                    + '<span style="color:#64748b;font-size:12px;">MAYÚSCULAS, sin acentos. Mín. 3 letras.</span></p>'
                    + '<p><label>Tamaño del grid:</label><br>'
                    + '<select name="gc_preguntas[' + idx + '][tamano_grid]">'
                    +   '<option value="8">8 × 8</option>'
                    +   '<option value="10" selected>10 × 10</option>'
                    +   '<option value="12">12 × 12</option>'
                    +   '<option value="15">15 × 15</option>'
                    + '</select></p>';
            } else if (tipoNow === 'ahorcado') {
                html += '<p><label>Palabra a adivinar:</label><br>'
                    + '<input type="text" name="gc_preguntas[' + idx + '][respuesta_texto_correcta]" style="width:100%;" placeholder="Ej: GYMKANA" />'
                    + '<span style="color:#64748b;font-size:12px;">Solo letras se mostrarán. Las tildes se ignoran al comparar.</span></p>'
                    + '<p><label>Categoría (opcional):</label><br>'
                    + '<input type="text" name="gc_preguntas[' + idx + '][categoria]" style="width:100%;" placeholder="Ej: Animal · Ciudad · Deporte…" /></p>'
                    + '<p><label>Pista de texto (opcional):</label><br>'
                    + '<input type="text" name="gc_preguntas[' + idx + '][pista]" style="width:100%;" placeholder="Ej: Es lo que estás jugando ahora mismo." /></p>'
                    + '<p style="color:#64748b;font-size:12px;">El número de letras erróneas máximo se controla con <strong>"Intentos máximos"</strong> arriba.</p>';
            } else if (tipoNow === 'cifrado_cesar') {
                html += '<p><label>Mensaje original (lo que el jugador debe descifrar):</label><br>'
                    + '<input type="text" name="gc_preguntas[' + idx + '][respuesta_texto_correcta]" style="width:100%;" placeholder="Ej: GYMKANA" /></p>'
                    + '<p><label>Rotación del cifrado César (1-25):</label><br>'
                    + '<input type="number" name="gc_preguntas[' + idx + '][rotacion]" value="3" min="1" max="25" style="width:80px;" />'
                    + '<span style="color:#64748b;font-size:12px;margin-left:8px;">Ej: rotación 3 → \'GYMKANA\' se mostrará como \'JBPNDQD\'.</span></p>';
            } else if (tipoNow === 'multiple_imagen') {
                html += '<p><strong>Opciones (imágenes):</strong></p>';
                for (var i = 0; i < 4; i++) {
                    html += '<div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:10px;padding:10px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;">'
                        + '<div style="flex-shrink:0;width:90px;text-align:center;"><div style="width:90px;height:90px;border:2px dashed #cbd5e1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:11px;text-align:center;">Sin imagen</div></div>'
                        + '<div style="flex:1;">'
                        + '<div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">'
                        + '<input type="text" name="gc_preguntas[' + idx + '][opciones][' + i + '][imagen]" class="gc-media-input" style="flex:1;" placeholder="URL imagen ' + (i+1) + '" />'
                        + '<button type="button" class="button gc-media-select" data-type="image">Imagen</button>'
                        + '</div>'
                        + '<div style="display:flex;gap:8px;align-items:center;">'
                        + '<input type="text" name="gc_preguntas[' + idx + '][opciones][' + i + '][texto]" style="flex:1;" placeholder="Texto opcional (pie de imagen)" />'
                        + '<label style="white-space:nowrap;font-size:13px;"><input type="radio" name="gc_preguntas[' + idx + '][correcta]" value="' + i + '" /> Correcta</label>'
                        + '</div></div></div>';
                }
            } else {
                // multiple, vf
                html += '<p><strong>Opciones:</strong></p>';
                for (var i = 0; i < 4; i++) {
                    html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:4px;">'
                        + '<input type="text" name="gc_preguntas[' + idx + '][opciones][' + i + '][texto]" style="flex:1;" placeholder="Opcion ' + (i+1) + '" />'
                        + '<label style="white-space:nowrap;"><input type="radio" name="gc_preguntas[' + idx + '][correcta]" value="' + i + '" /> Correcta</label>'
                        + '</div>';
                }
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

    // Solo permitimos modificar gc_estacion_ref si la prueba NO es pool
    $is_pool_now = get_posts([
        'post_type'      => 'escenario',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => 'gc_pool_prueba_ref', 'value' => $post_id, 'compare' => '=']],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    if (empty($is_pool_now) && isset($_POST['gc_estacion_ref'])) {
        $new_ref = (int) $_POST['gc_estacion_ref'];
        if (function_exists('gc_sync_estacion_prueba')) {
            gc_sync_estacion_prueba($post_id, $new_ref);
        } else {
            update_post_meta($post_id, 'gc_estacion_ref', $new_ref);
        }
    }

    // Procesar preguntas
    $raw_preguntas = $_POST['gc_preguntas'] ?? [];
    $preguntas = [];

    if (is_array($raw_preguntas)) {
        foreach ($raw_preguntas as $qi => $p) {
            $enunciado = sanitize_textarea_field($p['enunciado'] ?? '');

            $p_tipo = sanitize_text_field($p['tipo'] ?? 'multiple');
            if ( ! in_array($p_tipo, ['multiple','multiple_imagen','vf','texto','cifrado_cesar','anagrama','ahorcado','sopa_letras'], true) ) {
                $p_tipo = 'multiple';
            }
            $correcta_idx = isset($p['correcta']) ? (int)$p['correcta'] : -1;

            // Comprobamos si la pregunta tiene contenido suficiente para guardarla.
            // No exigimos enunciado: en multiple_imagen / cifrado / anagrama puede ser
            // opcional mientras se configura. Pero al menos un campo con datos.
            $resp_text_raw = trim((string) ($p['respuesta_texto_correcta'] ?? ''));
            $tiene_opciones = false;
            if (isset($p['opciones']) && is_array($p['opciones'])) {
                foreach ($p['opciones'] as $opt) {
                    if (!is_array($opt)) continue;
                    if (!empty(trim((string) ($opt['texto'] ?? ''))) || !empty($opt['imagen'] ?? '')) {
                        $tiene_opciones = true; break;
                    }
                }
            }
            if ($enunciado === '' && $resp_text_raw === '' && !$tiene_opciones) continue;

            $pregunta = [
                'tipo'      => $p_tipo,
                'enunciado' => $enunciado,
            ];

            if (in_array($p_tipo, ['texto', 'anagrama', 'cifrado_cesar', 'ahorcado', 'sopa_letras'], true)) {
                $pregunta['respuesta_texto_correcta'] = sanitize_text_field($p['respuesta_texto_correcta'] ?? '');
                $pregunta['opciones'] = [];
                if ($p_tipo === 'cifrado_cesar') {
                    $rot = isset($p['rotacion']) ? (int) $p['rotacion'] : 3;
                    if ($rot < 1) $rot = 1;
                    if ($rot > 25) $rot = 25;
                    $pregunta['rotacion'] = $rot;
                }
                if ($p_tipo === 'ahorcado') {
                    $pregunta['pista']     = sanitize_text_field($p['pista'] ?? '');
                    $pregunta['categoria'] = sanitize_text_field($p['categoria'] ?? '');
                }
                if ($p_tipo === 'sopa_letras') {
                    $tam = isset($p['tamano_grid']) ? (int) $p['tamano_grid'] : 10;
                    if (!in_array($tam, [8, 10, 12, 15], true)) $tam = 10;
                    $pregunta['tamano_grid'] = $tam;
                }
            } elseif ($p_tipo === 'multiple_imagen') {
                $opciones = [];
                $raw_opts = $p['opciones'] ?? [];
                if (is_array($raw_opts)) {
                    foreach ($raw_opts as $oi => $opt) {
                        $texto  = sanitize_text_field($opt['texto'] ?? '');
                        $imagen = esc_url_raw($opt['imagen'] ?? '');
                        // Una opción es válida si tiene imagen O texto
                        if ($imagen === '' && $texto === '') continue;
                        $opciones[] = [
                            'texto'       => $texto,
                            'imagen'      => $imagen,
                            'es_correcta' => ((int)$oi === $correcta_idx) ? 1 : 0,
                        ];
                    }
                }
                $pregunta['opciones'] = $opciones;
                $pregunta['respuesta_texto_correcta'] = '';
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

// Nota: el auto-papelera al guardar pruebas vacías se eliminó en v1.0.8
// porque podía interferir cuando se acababa de crear una prueba con imágenes.
// La limpieza de pruebas vacías se hace ahora SOLO desde el botón manual
// '🧹 Limpiar pruebas vacías' en el listado admin de Pruebas.

/**
 * Migración única: sincroniza estaciones que tengan una prueba apuntando
 * vía gc_estacion_ref pero que aún no tengan el meta inverso gc_prueba_ref.
 * Se ejecuta una sola vez (flag en options).
 */
add_action('admin_init', function () {
    if (get_option('gc_prueba_estacion_sync_done')) return;
    if (!current_user_can('manage_options')) return;
    $pruebas = get_posts([
        'post_type'      => 'prueba',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [['key' => 'gc_estacion_ref', 'compare' => 'EXISTS']],
        'no_found_rows'  => true,
    ]);
    foreach ($pruebas as $pid) {
        $est_id = (int) get_post_meta($pid, 'gc_estacion_ref', true);
        if ($est_id <= 0) continue;
        $existing = (int) get_post_meta($est_id, 'gc_prueba_ref', true);
        if ($existing === (int) $pid) continue;
        if (function_exists('gc_sync_estacion_prueba')) {
            gc_sync_estacion_prueba($pid, $est_id);
        }
    }
    update_option('gc_prueba_estacion_sync_done', '1');
});

/**
 * Al crear una nueva prueba con ?gc_estacion_id=X en la URL, pre-asigna
 * la estación enlazada en el auto-draft (antes de que el admin guarde).
 * Así se puede "Crear prueba" directamente desde el metabox de la estación.
 */
add_action('admin_init', function () {
    global $pagenow;
    if ($pagenow !== 'post-new.php') return;
    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'prueba') return;
    if (empty($_GET['gc_estacion_id'])) return;

    $est_id = (int) $_GET['gc_estacion_id'];
    if ($est_id <= 0 || get_post_type($est_id) !== 'estacion') return;

    // Al crear un auto-draft nuevo, asociar la estación
    add_action('wp_insert_post', function ($post_id, $post, $update) use ($est_id) {
        if ($update) return;
        if ($post->post_type !== 'prueba') return;
        if ($post->post_status !== 'auto-draft') return;
        if (get_post_meta($post_id, 'gc_estacion_ref', true)) return; // ya tiene
        if (function_exists('gc_sync_estacion_prueba')) {
            gc_sync_estacion_prueba($post_id, $est_id);
        } else {
            update_post_meta($post_id, 'gc_estacion_ref', $est_id);
        }
        // Título por defecto
        $est_title = get_the_title($est_id) ?: ('Estación #' . $est_id);
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => 'Prueba — ' . $est_title,
        ]);
    }, 10, 3);
});
