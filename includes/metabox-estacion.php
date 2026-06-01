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

    $escenario_ref   = (int) get_post_meta($post->ID, 'gc_escenario_ref', true);
    $orden           = get_post_meta($post->ID, 'gc_orden', true);
    $descripcion     = get_post_meta($post->ID, 'gc_descripcion', true);
    $pista_busqueda  = get_post_meta($post->ID, 'gc_pista_busqueda', true);
    $pista_busqueda_2 = get_post_meta($post->ID, 'gc_pista_busqueda_2', true);
    $audio    = get_post_meta($post->ID, 'gc_audio', true);
    $maps_url  = get_post_meta($post->ID, 'gc_maps_url', true);
    $direccion = get_post_meta($post->ID, 'gc_direccion', true);
    $latitud   = get_post_meta($post->ID, 'gc_latitud', true);
    $longitud  = get_post_meta($post->ID, 'gc_longitud', true);
    $img1     = get_post_meta($post->ID, 'gc_img_1', true);
    $img2     = get_post_meta($post->ID, 'gc_img_2', true);
    $img3     = get_post_meta($post->ID, 'gc_img_3', true);
    $token    = get_post_meta($post->ID, 'gc_qr_token', true);
    $deshabilitada = get_post_meta($post->ID, 'gc_deshabilitada', true);

    if (empty($token)) {
        $token = gc_generate_station_token($post->ID);
        update_post_meta($post->ID, 'gc_qr_token', $token);
    }

    $qr_url = gc_get_station_entry_url($post->ID);

    // Prueba asociada a esta estación (origen = por_estacion)
    $prueba_asociada = null;
    if ($post->ID) {
        $pq = get_posts([
            'post_type'      => 'prueba',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [['key' => 'gc_estacion_ref', 'value' => (int) $post->ID, 'compare' => '=']],
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if (!empty($pq)) $prueba_asociada = (int) $pq[0];
    }

    // Información del origen de preguntas del escenario
    $origen_preg = $escenario_ref && function_exists('gc_get_origen_preguntas') ? gc_get_origen_preguntas($escenario_ref) : '';
    $requiere_prueba_esc = $escenario_ref && function_exists('gc_requiere_prueba') ? gc_requiere_prueba($escenario_ref) : false;
    ?>
    <?php if ($deshabilitada === '1'): ?>
    <div style="margin:12px 0 14px;padding:14px 16px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;display:flex;align-items:center;gap:12px;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        <div>
            <div style="font-weight:700;color:#991b1b;">Estación deshabilitada</div>
            <div style="font-size:13px;color:#7f1d1d;">No se mostrará como accesible a los jugadores y no contará en el progreso del escenario.</div>
        </div>
    </div>
    <?php endif; ?>
    <table class="form-table">

        <tr>
            <th><label for="gc_deshabilitada">Estado</label></th>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px;">
                    <input type="checkbox" name="gc_deshabilitada" id="gc_deshabilitada" value="1" <?php checked($deshabilitada, '1'); ?> />
                    <span>Deshabilitar temporalmente esta estación</span>
                </label>
                <p class="description">Si se marca, la estación queda inhabilitada pero sin borrarse ni pasar a borrador. Los jugadores la verán claramente deshabilitada y no se podrá acceder a ella. Tampoco se cuenta para calcular el progreso del escenario.</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_escenario_ref">Escenario</label></th>
            <td>
                <?php
                $escenarios = get_posts([
                    'post_type'      => 'escenario',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
                ?>
                <select name="gc_escenario_ref" id="gc_escenario_ref" style="width:100%;max-width:400px;">
                    <option value="">— Seleccionar escenario —</option>
                    <?php foreach ($escenarios as $esc): ?>
                        <option value="<?php echo (int) $esc->ID; ?>" <?php selected($escenario_ref, $esc->ID); ?>>
                            <?php echo esc_html($esc->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Escenario al que pertenece esta estacion. Obligatorio.</p>
            </td>
        </tr>
        <tr>
            <th><label for="gc_orden">Orden</label></th>
            <td>
                <input type="number" name="gc_orden" id="gc_orden" value="<?php echo esc_attr($orden); ?>" min="1" step="1" style="width:80px;" />
                <p class="description">Posicion de esta estacion dentro del escenario (1, 2, 3...).</p>
            </td>
        </tr>

        <?php if ($requiere_prueba_esc && $origen_preg === 'por_estacion'): ?>
        <tr>
            <th><label>Prueba / pregunta</label></th>
            <td>
                <?php if ($prueba_asociada):
                    $edit_url = get_edit_post_link($prueba_asociada);
                    $t_title  = get_the_title($prueba_asociada) ?: '(sin título)';
                    $preguntas_arr = get_post_meta($prueba_asociada, 'gc_preguntas', true);
                    $n_preg = is_array($preguntas_arr) ? count($preguntas_arr) : 0;
                ?>
                <div style="padding:12px 14px;border-radius:10px;background:#ecfdf5;border:1px solid #86efac;display:flex;gap:10px;align-items:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <div style="flex:1;">
                        <strong style="color:#166534;"><?php echo esc_html($t_title); ?></strong>
                        <div style="font-size:12px;color:#15803d;margin-top:2px;">
                            <?php echo (int) $n_preg; ?> pregunta<?php echo $n_preg === 1 ? '' : 's'; ?> configurada<?php echo $n_preg === 1 ? '' : 's'; ?>.
                        </div>
                    </div>
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-primary">Editar prueba</a>
                </div>
                <?php else:
                    $create_url = admin_url('post-new.php?post_type=prueba&gc_estacion_id=' . (int) $post->ID);
                ?>
                <div style="padding:12px 14px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;display:flex;gap:10px;align-items:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div style="flex:1;">
                        <strong style="color:#92400e;">Sin prueba asociada</strong>
                        <div style="font-size:12px;color:#a16207;margin-top:2px;">
                            Este escenario usa "preguntas por estación" y requiere que cada <?php echo esc_html(function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_ref) : 'estación'); ?> tenga su propia prueba.
                        </div>
                    </div>
                    <a href="<?php echo esc_url($create_url); ?>" class="button button-primary">Crear prueba</a>
                </div>
                <p class="description" style="margin-top:8px;">Guarda primero la estación si acabas de crearla, luego pulsa "Crear prueba" para configurar la pregunta que validará esta <?php echo esc_html(function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_ref) : 'estación'); ?>.</p>
                <?php endif; ?>
            </td>
        </tr>
        <?php elseif ($requiere_prueba_esc && $origen_preg === 'pool' && $escenario_ref): ?>
        <tr>
            <th><label>Prueba / pregunta</label></th>
            <td>
                <?php
                $pool_id = (int) get_post_meta($escenario_ref, 'gc_pool_prueba_ref', true);
                if ($pool_id):
                    $pool_title = get_the_title($pool_id) ?: '(sin título)';
                    $pool_edit  = get_edit_post_link($pool_id);
                ?>
                <div style="padding:12px 14px;border-radius:10px;background:#f5f3ff;border:1px solid #c4b5fd;display:flex;gap:10px;align-items:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 12l-4-4-4 4"/><path d="M12 16V8"/></svg>
                    <div style="flex:1;">
                        <strong style="color:#5b21b6;">Pool aleatorio</strong>
                        <div style="font-size:12px;color:#6d28d9;margin-top:2px;">
                            Esta estación toma una pregunta al azar de <strong><?php echo esc_html($pool_title); ?></strong>.
                        </div>
                    </div>
                    <a href="<?php echo esc_url($pool_edit); ?>" class="button">Editar pool</a>
                </div>
                <?php else: ?>
                <div style="padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
                    El escenario usa <strong>pool aleatorio</strong> pero no tiene ninguna prueba asignada como pool. Edita el escenario para configurarlo.
                </div>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>

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

        <?php
            $moraleja = get_post_meta($post->ID, 'gc_moraleja', true);
            $moraleja_esc_activa = $escenario_ref && function_exists('gc_moraleja_activa') && gc_moraleja_activa($escenario_ref);
        ?>
        <?php if ($moraleja_esc_activa): ?>
        <tr>
            <th><label for="gc_moraleja">💡 Moraleja</label></th>
            <td>
                <?php
                wp_editor($moraleja, 'gc_moraleja', [
                    'textarea_name' => 'gc_moraleja',
                    'textarea_rows' => 4,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Mensaje que se muestra al jugador <strong>tras superar la prueba</strong> de esta estación. Suele ser una curiosidad, dato cultural o reflexión relacionada con el lugar. Si se deja vacío, no se muestra nada.</p>
            </td>
        </tr>
        <?php elseif ($moraleja): ?>
        <tr>
            <th>💡 Moraleja</th>
            <td>
                <div style="padding:10px 14px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
                    Tienes una moraleja guardada, pero la opción <strong>«Moraleja tras cada estación»</strong> está desactivada en el escenario.
                    Para que se muestre al jugador, edita el escenario y activa el toggle en el paso 2 (Mecánica).
                </div>
            </td>
        </tr>
        <?php endif; ?>

        <tr>
            <th><label for="gc_pista_busqueda">Pista 1</label></th>
            <td>
                <input type="text" name="gc_pista_busqueda" id="gc_pista_busqueda"
                       value="<?php echo esc_attr($pista_busqueda); ?>"
                       style="width:100%;" placeholder="Ej: Busca cerca de la fuente del jardín..." />
                <p class="description">Primera pista que ayuda al jugador a encontrar el QR en el lugar. Se muestra en escenarios infantiles dentro del recuadro "¡Busca el código QR!".</p>
            </td>
        </tr>

        <tr>
            <th><label for="gc_pista_busqueda_2">Pista 2</label></th>
            <td>
                <input type="text" name="gc_pista_busqueda_2" id="gc_pista_busqueda_2"
                       value="<?php echo esc_attr($pista_busqueda_2); ?>"
                       style="width:100%;" placeholder="Ej: Mira hacia arriba en la pared norte..." />
                <p class="description">Segunda pista (opcional) para ayudar al jugador. Se muestra en escenarios infantiles dentro del recuadro "¡Busca el código QR!".</p>
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

        <tr>
            <th><label for="gc_img_3">Imagen 3</label></th>
            <td>
                <?php gc_render_media_field('gc_img_3', $img3, 'image', 'Seleccionar imagen'); ?>
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

    update_post_meta($post_id, 'gc_escenario_ref', (int) ($_POST['gc_escenario_ref'] ?? 0));
    update_post_meta($post_id, 'gc_orden', (int) ($_POST['gc_orden'] ?? 0));
    update_post_meta($post_id, 'gc_deshabilitada', isset($_POST['gc_deshabilitada']) ? '1' : '0');
    update_post_meta($post_id, 'gc_descripcion', wp_kses_post($_POST['gc_descripcion'] ?? ''));
    // Solo persistir 'gc_moraleja' si el campo viene en el POST (es decir, si
    // estaba visible en el formulario porque la opción del escenario está
    // activa). Si no, conservamos lo que hubiera para no perder datos.
    if (isset($_POST['gc_moraleja'])) {
        update_post_meta($post_id, 'gc_moraleja', wp_kses_post($_POST['gc_moraleja']));
    }
    update_post_meta($post_id, 'gc_pista_busqueda', sanitize_text_field($_POST['gc_pista_busqueda'] ?? ''));
    update_post_meta($post_id, 'gc_pista_busqueda_2', sanitize_text_field($_POST['gc_pista_busqueda_2'] ?? ''));
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
    update_post_meta($post_id, 'gc_img_3', esc_url_raw($_POST['gc_img_3'] ?? ''));

    $token = get_post_meta($post_id, 'gc_qr_token', true);
    if (empty($token)) {
        update_post_meta($post_id, 'gc_qr_token', gc_generate_station_token($post_id));
    }

    update_post_meta($post_id, 'gc_qr_url', gc_get_station_entry_url($post_id));

    // Invalidar cachés de Divi (Static CSS, Theme Builder) y de WP al guardar la estación
    if ( function_exists('et_core_clear_wp_cache') ) {
        et_core_clear_wp_cache();
    }
    if ( function_exists('et_fb_delete_static_resources') ) {
        et_fb_delete_static_resources();
    }
    clean_post_cache($post_id);
    wp_cache_delete('alloptions', 'options');
});