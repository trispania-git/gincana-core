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

    // Cargar todos los valores
    $tipo            = get_post_meta($post->ID, 'gc_tipo_escenario', true) ?: 'adulto';
    $tipo_qr         = get_post_meta($post->ID, 'gc_tipo_qr', true) ?: 'enlace';
    $mostrar_puntos  = get_post_meta($post->ID, 'gc_mostrar_puntos', true);
    if ($mostrar_puntos === '') $mostrar_puntos = '1';
    $requiere_prueba = get_post_meta($post->ID, 'gc_requiere_prueba', true);
    if ($requiere_prueba === '') $requiere_prueba = '1';
    $pistas_activas = get_post_meta($post->ID, 'gc_pistas_activas', true);
    if ($pistas_activas === '') $pistas_activas = '0';
    $origen_preguntas = get_post_meta($post->ID, 'gc_origen_preguntas', true) ?: 'por_estacion';
    $pool_prueba_ref  = (int) get_post_meta($post->ID, 'gc_pool_prueba_ref', true);
    $geo_radio       = get_post_meta($post->ID, 'gc_geo_radio', true);
    $accion_final    = get_post_meta($post->ID, 'gc_accion_final', true) ?: 'ninguna';
    $foto_texto      = get_post_meta($post->ID, 'gc_foto_texto', true);
    $enhorabuena_msg = get_post_meta($post->ID, 'gc_enhorabuena_msg', true);
    $diploma_activo  = get_post_meta($post->ID, 'gc_diploma_activo', true);
    $diploma_msg     = get_post_meta($post->ID, 'gc_diploma_msg', true);
    $diploma_pie_activo = get_post_meta($post->ID, 'gc_diploma_pie_activo', true);
    if ($diploma_pie_activo === '') $diploma_pie_activo = '1';
    $diploma_pie_texto = get_post_meta($post->ID, 'gc_diploma_pie_texto', true);
    $diploma_fondo   = get_post_meta($post->ID, 'gc_diploma_fondo', true);
    $label_estacion  = get_post_meta($post->ID, 'gc_label_estacion', true);
    $label_plural    = get_post_meta($post->ID, 'gc_label_estacion_plural', true);
    $cta_texto       = get_post_meta($post->ID, 'gc_cta_texto', true);
    $portada         = get_post_meta($post->ID, 'gc_portada', true);
    $fondo_textos    = get_post_meta($post->ID, 'gc_fondo_textos', true);
    $descripcion     = get_post_meta($post->ID, 'gc_descripcion', true);
    $audio           = get_post_meta($post->ID, 'gc_audio', true);
    $img1            = get_post_meta($post->ID, 'gc_img_1', true);
    $img2            = get_post_meta($post->ID, 'gc_img_2', true);
    $img3            = get_post_meta($post->ID, 'gc_img_3', true);
    $img_encontrada  = get_post_meta($post->ID, 'gc_img_encontrada', true);
    $ranking_imagen  = get_post_meta($post->ID, 'gc_ranking_imagen', true);
    $img_busca_qr    = get_post_meta($post->ID, 'gc_img_busca_qr', true);
    $img_pregunta    = get_post_meta($post->ID, 'gc_img_pregunta', true);
    $img_acierto     = get_post_meta($post->ID, 'gc_img_acierto', true);
    ?>

    <style>
    .gc-wizard { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

    /* Progress bar */
    .gc-wiz-progress { display: flex; gap: 0; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; }
    .gc-wiz-step-tab {
        flex: 1; text-align: center; padding: 12px 8px 10px; cursor: pointer;
        font-size: 12px; font-weight: 600; color: #94a3b8;
        border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.2s;
        user-select: none;
    }
    .gc-wiz-step-tab:hover { color: #64748b; }
    .gc-wiz-step-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
    .gc-wiz-step-tab.done { color: #16a34a; }
    .gc-wiz-step-tab .gc-wiz-num {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 50%; font-size: 12px; font-weight: 700;
        background: #e2e8f0; color: #64748b; margin-bottom: 4px;
    }
    .gc-wiz-step-tab.active .gc-wiz-num { background: #2563eb; color: #fff; }
    .gc-wiz-step-tab.done .gc-wiz-num { background: #16a34a; color: #fff; }

    /* Panels */
    .gc-wiz-panel { display: none; padding: 20px 0 0; }
    .gc-wiz-panel.active { display: block; }
    .gc-wiz-panel h3 { margin: 0 0 4px; font-size: 17px; font-weight: 700; color: #1e293b; }
    .gc-wiz-panel .gc-wiz-subtitle { margin: 0 0 20px; font-size: 13px; color: #64748b; }

    /* Cards de selección */
    .gc-wiz-cards { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .gc-wiz-card {
        flex: 1; min-width: 180px; padding: 16px; border: 2px solid #e2e8f0; border-radius: 12px;
        cursor: pointer; transition: all 0.2s; background: #fff; text-align: center;
    }
    .gc-wiz-card:hover { border-color: #93c5fd; background: #f8fafc; }
    .gc-wiz-card.selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
    .gc-wiz-card .gc-wiz-card-icon { font-size: 28px; margin-bottom: 8px; }
    .gc-wiz-card .gc-wiz-card-title { font-size: 14px; font-weight: 700; color: #1e293b; }
    .gc-wiz-card .gc-wiz-card-desc { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.4; }

    /* Toggle switch */
    .gc-wiz-toggle { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 12px; cursor: pointer; transition: background 0.2s; }
    .gc-wiz-toggle:hover { background: #f8fafc; }
    .gc-wiz-toggle input[type=checkbox] { display: none; }
    .gc-wiz-toggle .gc-switch { width: 44px; height: 24px; background: #cbd5e1; border-radius: 12px; position: relative; transition: background 0.2s; flex-shrink: 0; }
    .gc-wiz-toggle .gc-switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: transform 0.2s; }
    .gc-wiz-toggle input:checked + .gc-switch { background: #2563eb; }
    .gc-wiz-toggle input:checked + .gc-switch::after { transform: translateX(20px); }
    .gc-wiz-toggle .gc-toggle-label { font-size: 14px; font-weight: 500; color: #334155; }
    .gc-wiz-toggle .gc-toggle-desc { font-size: 12px; color: #94a3b8; margin-top: 2px; }

    /* Conditional field */
    .gc-wiz-conditional { margin-top: -4px; margin-bottom: 12px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }

    /* Field groups */
    .gc-wiz-field { margin-bottom: 16px; }
    .gc-wiz-field label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    .gc-wiz-field input[type=text], .gc-wiz-field input[type=url] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
    .gc-wiz-field .gc-hint { font-size: 12px; color: #94a3b8; margin-top: 4px; }

    /* Nav buttons */
    .gc-wiz-nav { display: flex; justify-content: space-between; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
    .gc-wiz-btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; }
    .gc-wiz-btn-prev { background: #f1f5f9; color: #475569; }
    .gc-wiz-btn-prev:hover { background: #e2e8f0; }
    .gc-wiz-btn-next { background: #2563eb; color: #fff; }
    .gc-wiz-btn-next:hover { background: #1d4ed8; }

    /* Resumen */
    .gc-wiz-summary { display: grid; gap: 8px; }
    .gc-wiz-summary-row { display: flex; gap: 8px; padding: 8px 12px; background: #f8fafc; border-radius: 8px; font-size: 13px; }
    .gc-wiz-summary-row .gc-label { font-weight: 600; color: #475569; min-width: 140px; }
    .gc-wiz-summary-row .gc-value { color: #1e293b; }

    @media (max-width: 600px) {
        .gc-wiz-cards { flex-direction: column; }
        .gc-wiz-step-tab { font-size: 11px; padding: 8px 4px; }
    }
    </style>

    <div style="text-align:center;margin-bottom:12px;">
        <button type="button" id="gc-wiz-resumen-btn"
                style="padding:8px 20px;border:2px solid #2563eb;border-radius:10px;background:#eff6ff;color:#2563eb;font-size:13px;font-weight:600;cursor:pointer;">
            Resumen de configuracion
        </button>
    </div>
    <div id="gc-wiz-resumen-panel" style="display:none;margin-bottom:16px;padding:20px;border-radius:12px;border:2px solid #2563eb;background:#f8fafc;"></div>

    <div class="gc-wizard" id="gc-wizard">

        <!-- Progress tabs -->
        <div class="gc-wiz-progress">
            <div class="gc-wiz-step-tab active" data-step="1">
                <div class="gc-wiz-num">1</div><br>Tipo
            </div>
            <div class="gc-wiz-step-tab" data-step="2">
                <div class="gc-wiz-num">2</div><br>Mecanica
            </div>
            <div class="gc-wiz-step-tab" data-step="3">
                <div class="gc-wiz-num">3</div><br>Final
            </div>
            <div class="gc-wiz-step-tab" data-step="4">
                <div class="gc-wiz-num">4</div><br>Textos
            </div>
            <div class="gc-wiz-step-tab" data-step="5">
                <div class="gc-wiz-num">5</div><br>Contenido
            </div>
            <div class="gc-wiz-step-tab" data-step="6">
                <div class="gc-wiz-num">6</div><br>Info
            </div>
            <div class="gc-wiz-step-tab" data-step="7">
                <div class="gc-wiz-num">7</div><br>Apariencia
            </div>
        </div>

        <!-- ========== PASO 1: TIPO ========== -->
        <div class="gc-wiz-panel active" data-step="1">
            <h3>¿Que tipo de escenario vas a crear?</h3>
            <p class="gc-wiz-subtitle">Elige la modalidad principal. Esto preajustara las opciones recomendadas.</p>

            <div class="gc-wiz-cards">
                <div class="gc-wiz-card <?php echo $tipo === 'adulto' ? 'selected' : ''; ?>" data-value="adulto">
                    <div class="gc-wiz-card-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">Adulto / Quiz</div>
                    <div class="gc-wiz-card-desc">Cada estacion tiene una pregunta tipo test. Los puntos dependen de la velocidad y aciertos.</div>
                </div>
                <div class="gc-wiz-card <?php echo $tipo === 'infantil' ? 'selected' : ''; ?>" data-value="infantil">
                    <div class="gc-wiz-card-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">Infantil / Busqueda</div>
                    <div class="gc-wiz-card-desc">Los jugadores buscan QRs fisicos. Al escanearlos, validan que los han encontrado.</div>
                </div>
            </div>
            <input type="hidden" name="gc_tipo_escenario" id="gc_tipo_escenario" value="<?php echo esc_attr($tipo); ?>" />

            <!-- Acceso: con registro o sin registro (invitado) -->
            <?php
                $permitir_guest = get_post_meta($post->ID, 'gc_permitir_guest', true) === '1';
            ?>
            <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e2e8f0;">
                <h4 style="margin:0 0 12px;font-size:15px;color:#334155;">Acceso de jugadores</h4>
                <label class="gc-wiz-toggle">
                    <input type="checkbox" name="gc_permitir_guest" value="1" id="gc_permitir_guest" <?php checked($permitir_guest, true); ?> />
                    <span class="gc-switch"></span>
                    <div>
                        <div class="gc-toggle-label">Permitir jugar sin registro 🙋</div>
                        <div class="gc-toggle-desc">Los jugadores podrán participar simplemente escribiendo su nombre, sin crear cuenta ni iniciar sesión. Aparecerán en el ranking con ese nombre.</div>
                    </div>
                </label>
            </div>

            <div class="gc-wiz-nav">
                <div></div>
                <button type="button" class="gc-wiz-btn gc-wiz-btn-next" data-next="2">Siguiente &rarr;</button>
            </div>
        </div>

        <!-- ========== PASO 2: MECANICA ========== -->
        <div class="gc-wiz-panel" data-step="2">
            <h3>Mecanica del juego</h3>
            <p class="gc-wiz-subtitle">Configura como funcionan las estaciones y los QR.</p>

            <!-- Tipo de QR -->
            <div class="gc-wiz-cards" style="margin-bottom:20px;">
                <div class="gc-wiz-card <?php echo $tipo_qr === 'enlace' ? 'selected' : ''; ?>" data-value="enlace" data-field="gc_tipo_qr">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">QR = Enlace directo</div>
                    <div class="gc-wiz-card-desc">El QR lleva a la pagina de la estacion</div>
                </div>
                <div class="gc-wiz-card <?php echo $tipo_qr === 'validacion_boton' ? 'selected' : ''; ?>" data-value="validacion_boton" data-field="gc_tipo_qr">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">QR = Validacion (boton)</div>
                    <div class="gc-wiz-card-desc">El QR valida presencia con un boton de confirmar</div>
                </div>
                <div class="gc-wiz-card <?php echo $tipo_qr === 'validacion_quiz' ? 'selected' : ''; ?>" data-value="validacion_quiz" data-field="gc_tipo_qr">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">QR = Validacion (quiz)</div>
                    <div class="gc-wiz-card-desc">El QR lleva directamente a una pregunta que valida la estacion</div>
                </div>
                <div class="gc-wiz-card <?php echo $tipo_qr === 'validacion_boton_quiz' ? 'selected' : ''; ?>" data-value="validacion_boton_quiz" data-field="gc_tipo_qr">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/><circle cx="20" cy="6" r="2.5" fill="#0ea5e9"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">QR + Quiz</div>
                    <div class="gc-wiz-card-desc">El QR muestra '¡Has llegado!' y un botón que lleva a la pregunta. Combina presencia + reto.</div>
                </div>
                <div class="gc-wiz-card <?php echo $tipo_qr === 'validacion_gps' ? 'selected' : ''; ?>" data-value="validacion_gps" data-field="gc_tipo_qr">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">Validacion por GPS</div>
                    <div class="gc-wiz-card-desc">Verifica que el jugador esta fisicamente en el lugar</div>
                </div>
                <div class="gc-wiz-card <?php echo $tipo_qr === 'solo_pregunta' ? 'selected' : ''; ?>" data-value="solo_pregunta" data-field="gc_tipo_qr">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">Sin QR · Solo pregunta</div>
                    <div class="gc-wiz-card-desc">Sin código QR: el jugador accede desde la lista y valida respondiendo una pregunta</div>
                </div>
            </div>
            <input type="hidden" name="gc_tipo_qr" id="gc_tipo_qr" value="<?php echo esc_attr($tipo_qr); ?>" />

            <!-- Toggles -->
            <label class="gc-wiz-toggle">
                <input type="checkbox" name="gc_requiere_prueba" value="1" id="gc_requiere_prueba" <?php checked($requiere_prueba, '1'); ?> />
                <span class="gc-switch"></span>
                <div>
                    <div class="gc-toggle-label">Prueba/pregunta en cada estacion</div>
                    <div class="gc-toggle-desc">Si se desactiva, las estaciones se completan sin quiz</div>
                </div>
            </label>

            <label class="gc-wiz-toggle">
                <input type="checkbox" name="gc_mostrar_puntos" value="1" id="gc_mostrar_puntos" <?php checked($mostrar_puntos, '1'); ?> />
                <span class="gc-switch"></span>
                <div>
                    <div class="gc-toggle-label">Gamificacion (puntos y ranking)</div>
                    <div class="gc-toggle-desc">Desactiva para escenarios sin puntuacion ni clasificacion</div>
                </div>
            </label>

            <label class="gc-wiz-toggle">
                <input type="checkbox" name="gc_pistas_activas" value="1" id="gc_pistas_activas" <?php checked($pistas_activas, '1'); ?> />
                <span class="gc-switch"></span>
                <div>
                    <div class="gc-toggle-label">Pistas para encontrar el QR</div>
                    <div class="gc-toggle-desc">Muestra un botón de "Pistas" en el recuadro de "¡Busca el código QR!". Las pistas se configuran en cada estación.</div>
                </div>
            </label>

            <?php
                $orden_libre    = get_post_meta($post->ID, 'gc_orden_libre', true) === '1';
                // Compat: si aún quedaba gc_orden_aleatorio del esquema antiguo, equivale a libre
                if (!$orden_libre) {
                    $orden_libre = get_post_meta($post->ID, 'gc_orden_aleatorio', true) === '1';
                }
                $orden_secreto  = get_post_meta($post->ID, 'gc_orden_secreto', true) === '1';
            ?>
            <label class="gc-wiz-toggle">
                <input type="checkbox" name="gc_orden_libre" value="1" id="gc_orden_libre" <?php checked($orden_libre, true); ?> />
                <span class="gc-switch"></span>
                <div>
                    <div class="gc-toggle-label">Orden libre 🎲</div>
                    <div class="gc-toggle-desc">El jugador elige el orden. Todas las estaciones están visibles desde el inicio y se pueden completar en cualquier orden. El número de cada estación es fijo (la 1 sigue siendo la 1).</div>
                </div>
            </label>

            <label class="gc-wiz-toggle">
                <input type="checkbox" name="gc_orden_secreto" value="1" id="gc_orden_secreto" <?php checked($orden_secreto, true); ?> />
                <span class="gc-switch"></span>
                <div>
                    <div class="gc-toggle-label">Orden aleatorio (secreto) 🎰</div>
                    <div class="gc-toggle-desc">Cada jugador recibe su propio orden personalizado al iniciar. Va secuencial: solo se ve el nombre de la estación actual. Las siguientes aparecen como "¿?" hasta que se desbloquean.</div>
                </div>
            </label>

            <script>
            (function(){
                // Mutuamente excluyentes
                var libre = document.getElementById('gc_orden_libre');
                var secret = document.getElementById('gc_orden_secreto');
                if (!libre || !secret) return;
                libre.addEventListener('change', function(){
                    if (libre.checked) secret.checked = false;
                });
                secret.addEventListener('change', function(){
                    if (secret.checked) libre.checked = false;
                });
            })();
            </script>

            <!-- Radio GPS (visible para validacion_gps) -->
            <div id="gc-geo-section" style="margin-top:16px;padding:14px 16px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;<?php echo $tipo_qr !== 'validacion_gps' ? 'display:none;' : ''; ?>">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span style="font-size:13px;font-weight:600;color:#334155;">Radio de verificacion GPS</span>
                </div>
                <div class="gc-wiz-field" style="margin:0;">
                    <label for="gc_geo_radio">Radio maximo (metros)</label>
                    <input type="number" name="gc_geo_radio" id="gc_geo_radio" value="<?php echo esc_attr($geo_radio ?: 100); ?>" min="10" step="10" style="width:120px;" />
                    <div class="gc-hint">Distancia maxima permitida entre el jugador y la estacion. Recomendado: 50-150 metros. Cada estacion necesita tener coordenadas GPS configuradas.</div>
                </div>
            </div>

            <!-- Origen de preguntas (solo visible si requiere prueba) -->
            <div id="gc-origen-preguntas-section" style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;<?php echo $requiere_prueba !== '1' ? 'display:none;' : ''; ?>">
                <div style="font-size:13px;font-weight:600;color:#334155;margin-bottom:10px;">¿De donde salen las preguntas?</div>
                <div class="gc-wiz-cards">
                    <div class="gc-wiz-card <?php echo $origen_preguntas === 'por_estacion' ? 'selected' : ''; ?>" data-value="por_estacion" data-field="gc_origen_preguntas">
                        <div class="gc-wiz-card-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        </div>
                        <div class="gc-wiz-card-title">Por estacion</div>
                        <div class="gc-wiz-card-desc">Cada estacion tiene su propia pregunta especifica</div>
                    </div>
                    <div class="gc-wiz-card <?php echo $origen_preguntas === 'pool' ? 'selected' : ''; ?>" data-value="pool" data-field="gc_origen_preguntas">
                        <div class="gc-wiz-card-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M16 12l-4-4-4 4"/><path d="M12 16V8"/></svg>
                        </div>
                        <div class="gc-wiz-card-title">Pool aleatorio</div>
                        <div class="gc-wiz-card-desc">Preguntas genericas que se asignan al azar (sin repetir)</div>
                    </div>
                </div>
                <input type="hidden" name="gc_origen_preguntas" id="gc_origen_preguntas" value="<?php echo esc_attr($origen_preguntas); ?>" />

                <!-- Selector de prueba-pool (solo si pool) -->
                <div id="gc-pool-selector" class="gc-wiz-conditional" style="<?php echo $origen_preguntas !== 'pool' ? 'display:none;' : ''; ?>">
                    <div class="gc-wiz-field" style="margin:0;">
                        <label for="gc_pool_prueba_ref">Prueba con el pool de preguntas</label>
                        <select name="gc_pool_prueba_ref" id="gc_pool_prueba_ref" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
                            <option value="">— Seleccionar prueba —</option>
                            <?php
                            $pruebas = get_posts([
                                'post_type'      => 'prueba',
                                'post_status'    => 'publish',
                                'posts_per_page' => -1,
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                            ]);
                            foreach ($pruebas as $pr) {
                                $n_pregs = 0;
                                $pregs = get_post_meta($pr->ID, 'gc_preguntas', true);
                                if (is_array($pregs)) $n_pregs = count($pregs);
                                $sel = selected($pool_prueba_ref, $pr->ID, false);
                                echo '<option value="' . (int)$pr->ID . '" ' . $sel . '>'
                                     . esc_html($pr->post_title) . ' (' . $n_pregs . ' preguntas)'
                                     . '</option>';
                            }
                            ?>
                        </select>
                        <div class="gc-hint">Elige una prueba que contenga todas las preguntas del pool. Se asignaran al azar en cada estacion.</div>
                    </div>
                </div>
            </div>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="1">&larr; Anterior</button>
                <button type="button" class="gc-wiz-btn gc-wiz-btn-next" data-next="3">Siguiente &rarr;</button>
            </div>
        </div>

        <!-- ========== PASO 3: ACCION FINAL ========== -->
        <div class="gc-wiz-panel" data-step="3">
            <h3>¿Que pasa al completar todas las estaciones?</h3>
            <p class="gc-wiz-subtitle">Elige si el jugador debe realizar alguna accion adicional al terminar.</p>

            <div class="gc-wiz-cards">
                <div class="gc-wiz-card <?php echo $accion_final === 'ninguna' ? 'selected' : ''; ?>" data-value="ninguna" data-field="gc_accion_final">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">Solo enhorabuena</div>
                    <div class="gc-wiz-card-desc">Mensaje de completado y acceso al ranking</div>
                </div>
                <div class="gc-wiz-card <?php echo $accion_final === 'subir_foto' ? 'selected' : ''; ?>" data-value="subir_foto" data-field="gc_accion_final">
                    <div class="gc-wiz-card-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="1.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </div>
                    <div class="gc-wiz-card-title">Subir foto</div>
                    <div class="gc-wiz-card-desc">El jugador debe hacer una foto para completar la aventura</div>
                </div>
            </div>
            <input type="hidden" name="gc_accion_final" id="gc_accion_final" value="<?php echo esc_attr($accion_final); ?>" />

            <!-- Opciones de "Solo enhorabuena" -->
            <div id="gc_enhorabuena_row" class="gc-wiz-conditional" style="<?php echo $accion_final !== 'ninguna' ? 'display:none;' : ''; ?>">
                <div class="gc-wiz-field">
                    <label for="gc_enhorabuena_msg">Mensaje de enhorabuena</label>
                    <input type="text" name="gc_enhorabuena_msg" id="gc_enhorabuena_msg"
                           value="<?php echo esc_attr($enhorabuena_msg); ?>"
                           placeholder="¡Enhorabuena! Has completado todas las estaciones." />
                    <div class="gc-hint">Si se deja vacio se usa un texto generico.</div>
                </div>

                <label class="gc-wiz-toggle" style="margin-top:12px;">
                    <input type="checkbox" name="gc_diploma_activo" value="1" id="gc_diploma_activo" <?php checked($diploma_activo, '1'); ?> />
                    <span class="gc-switch"></span>
                    <div>
                        <div class="gc-toggle-label">Diploma / imagen descargable</div>
                        <div class="gc-toggle-desc">Genera una imagen personalizada con el nombre del jugador, escenario y ranking</div>
                    </div>
                </label>

                <div id="gc_diploma_opciones" style="margin-top:12px;padding:14px 16px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;<?php echo $diploma_activo !== '1' ? 'display:none;' : ''; ?>">
                    <div class="gc-wiz-field" style="margin:0 0 12px;">
                        <label for="gc_diploma_msg">Mensaje del diploma</label>
                        <input type="text" name="gc_diploma_msg" id="gc_diploma_msg"
                               value="<?php echo esc_attr($diploma_msg); ?>"
                               placeholder="Ensena esta imagen en la oficina de turismo y recibe tu premio" />
                        <div class="gc-hint">Texto que aparece en la parte inferior de la imagen.</div>
                    </div>
                    <div class="gc-wiz-field" style="margin:0 0 12px;">
                        <label>Imagen de fondo del diploma</label>
                        <?php gc_render_media_field('gc_diploma_fondo', $diploma_fondo, 'image', 'Seleccionar fondo'); ?>
                        <div class="gc-hint">Opcional (800&times;1200px recomendado, vertical). La imagen se oscurece automaticamente para que el texto sea legible. Si no se sube, se usa un degradado por defecto.</div>
                    </div>

                    <label class="gc-wiz-toggle" style="margin:0 0 10px;">
                        <input type="checkbox" name="gc_diploma_pie_activo" value="1" id="gc_diploma_pie_activo" <?php checked($diploma_pie_activo, '1'); ?> />
                        <span class="gc-switch"></span>
                        <div>
                            <div class="gc-toggle-label">Incluir pie en el diploma</div>
                            <div class="gc-toggle-desc">Texto pequeño al pie de la imagen (por defecto "Generado por Gincana")</div>
                        </div>
                    </label>

                    <div id="gc_diploma_pie_row" class="gc-wiz-field" style="margin:0;<?php echo $diploma_pie_activo !== '1' ? 'display:none;' : ''; ?>">
                        <label for="gc_diploma_pie_texto">Texto del pie</label>
                        <input type="text" name="gc_diploma_pie_texto" id="gc_diploma_pie_texto"
                               value="<?php echo esc_attr($diploma_pie_texto); ?>"
                               placeholder="Generado por Gincana" />
                        <div class="gc-hint">Si se deja vacio se usa "Generado por Gincana".</div>
                    </div>
                </div>
            </div>

            <!-- Opciones de "Subir foto" -->
            <div id="gc_foto_texto_row" class="gc-wiz-conditional" style="<?php echo $accion_final !== 'subir_foto' ? 'display:none;' : ''; ?>">
                <div class="gc-wiz-field" style="margin:0;">
                    <label for="gc_foto_texto">Mensaje para el jugador</label>
                    <input type="text" name="gc_foto_texto" id="gc_foto_texto"
                           value="<?php echo esc_attr($foto_texto); ?>"
                           placeholder="¡Hazte una foto en la plaza para completar la aventura!" />
                    <div class="gc-hint">Si se deja vacio se usa un texto generico.</div>
                </div>
            </div>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="2">&larr; Anterior</button>
                <button type="button" class="gc-wiz-btn gc-wiz-btn-next" data-next="4">Siguiente &rarr;</button>
            </div>
        </div>

        <!-- ========== PASO 4: TEXTOS Y PERSONALIZACION ========== -->
        <div class="gc-wiz-panel" data-step="4">
            <h3>Personaliza los textos</h3>
            <p class="gc-wiz-subtitle">Adapta los nombres y mensajes a tu escenario. Todo es opcional — hay valores por defecto.</p>

            <div class="gc-wiz-field">
                <label for="gc_label_estacion">Nombre singular de las paradas</label>
                <input type="text" name="gc_label_estacion" id="gc_label_estacion"
                       value="<?php echo esc_attr($label_estacion); ?>" placeholder="estacion" />
                <div class="gc-hint">Ejemplos: "estacion", "puerta", "paso", "parada"</div>
            </div>

            <div class="gc-wiz-field">
                <label for="gc_label_estacion_plural">Nombre plural con articulo</label>
                <input type="text" name="gc_label_estacion_plural" id="gc_label_estacion_plural"
                       value="<?php echo esc_attr($label_plural); ?>" placeholder="las estaciones" />
                <div class="gc-hint">Ejemplos: "las estaciones", "las puertas", "los pasos"</div>
            </div>

            <div class="gc-wiz-field">
                <label for="gc_cta_texto">Texto motivacional (CTA)</label>
                <input type="text" name="gc_cta_texto" id="gc_cta_texto"
                       value="<?php echo esc_attr($cta_texto); ?>"
                       placeholder="¿Te animas? ¡Comienza la aventura y completa las estaciones!" />
                <div class="gc-hint">Aparece antes de la lista de estaciones. Si se deja vacio se genera automaticamente.</div>
            </div>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="3">&larr; Anterior</button>
                <button type="button" class="gc-wiz-btn gc-wiz-btn-next" data-next="5">Siguiente &rarr;</button>
            </div>
        </div>

        <!-- ========== PASO 5: CONTENIDO ========== -->
        <div class="gc-wiz-panel" data-step="5">
            <h3>Contenido del escenario</h3>
            <p class="gc-wiz-subtitle">Descripcion, audio e imagenes que vera el jugador en la pagina principal.</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="gc-wiz-field">
                    <label>Imagen de portada</label>
                    <?php gc_render_media_field('gc_portada', $portada, 'image', 'Seleccionar portada'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Fondo de textos</label>
                    <p class="description" style="margin:0 0 6px;font-size:12px;color:#64748b;">Aparece muy sutil detras del contenido.</p>
                    <?php gc_render_media_field('gc_fondo_textos', $fondo_textos, 'image', 'Seleccionar fondo'); ?>
                </div>
            </div>

            <div class="gc-wiz-field">
                <label>Descripcion introductoria</label>
                <?php
                wp_editor($descripcion, 'gc_esc_descripcion', [
                    'textarea_name' => 'gc_descripcion',
                    'textarea_rows' => 5,
                    'media_buttons' => false,
                    'teeny'         => true,
                    'quicktags'     => true,
                ]);
                ?>
            </div>

            <div class="gc-wiz-field">
                <label>Audio introductorio</label>
                <?php gc_render_media_field('gc_audio', $audio, 'audio', 'Seleccionar audio'); ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div class="gc-wiz-field">
                    <label>Imagen 1</label>
                    <?php gc_render_media_field('gc_img_1', $img1, 'image', 'Seleccionar imagen'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Imagen 2</label>
                    <?php gc_render_media_field('gc_img_2', $img2, 'image', 'Seleccionar imagen'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Imagen 3</label>
                    <?php gc_render_media_field('gc_img_3', $img3, 'image', 'Seleccionar imagen'); ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
                <div class="gc-wiz-field">
                    <label>Imagen de estacion encontrada</label>
                    <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Se muestra al escanear un QR de validacion.</p>
                    <?php gc_render_media_field('gc_img_encontrada', $img_encontrada, 'image', 'Seleccionar imagen'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Imagen del ranking</label>
                    <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Se muestra debajo de la tabla de ranking.</p>
                    <?php gc_render_media_field('gc_ranking_imagen', $ranking_imagen, 'image', 'Seleccionar imagen'); ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:16px;">
                <div class="gc-wiz-field">
                    <label>Imagen "Busca el QR"</label>
                    <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Sustituye la lupa en la pantalla de buscar QR.</p>
                    <?php gc_render_media_field('gc_img_busca_qr', $img_busca_qr, 'image', 'Seleccionar'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Imagen "Pregunta"</label>
                    <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Sustituye el icono en "¿Preparado para el desafío?".</p>
                    <?php gc_render_media_field('gc_img_pregunta', $img_pregunta, 'image', 'Seleccionar'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Imagen "Acierto"</label>
                    <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Se muestra al acertar la pregunta (en lugar del ✅).</p>
                    <?php gc_render_media_field('gc_img_acierto', $img_acierto, 'image', 'Seleccionar'); ?>
                </div>
            </div>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="4">&larr; Anterior</button>
                <button type="button" class="gc-wiz-btn gc-wiz-btn-next" data-next="6">Siguiente &rarr;</button>
            </div>
        </div>

        <!-- ========== PASO 6: PÁGINAS INFORMATIVAS ========== -->
        <div class="gc-wiz-panel" data-step="6">
            <h3>Páginas informativas</h3>
            <p class="gc-wiz-subtitle">Contenido de las páginas de instrucciones y puntuaciones de este escenario.</p>

            <div class="gc-wiz-field">
                <label>Instrucciones del escenario</label>
                <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Explica el recorrido, las reglas y como participar. Se accede desde <code>/escenario/{slug}/instrucciones/</code>.</p>
                <div style="margin-bottom:8px;">
                    <button type="button" class="button" id="gc-gen-instrucciones" style="font-size:12px;">
                        Generar texto por defecto
                    </button>
                </div>
                <?php
                wp_editor(get_post_meta($post->ID, 'gc_instrucciones', true) ?: '', 'gc_esc_instrucciones', [
                    'textarea_name' => 'gc_instrucciones',
                    'textarea_rows' => 8,
                    'media_buttons' => true,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
            </div>

            <div class="gc-wiz-field" style="margin-top:20px;">
                <label>Sistema de puntuaciones</label>
                <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Explica como se puntua en este escenario. Se accede desde <code>/escenario/{slug}/puntuaciones/</code>.</p>
                <div style="margin-bottom:8px;">
                    <button type="button" class="button" id="gc-gen-puntuaciones" style="font-size:12px;">
                        Generar texto por defecto
                    </button>
                </div>
                <?php
                wp_editor(get_post_meta($post->ID, 'gc_puntuaciones', true) ?: '', 'gc_esc_puntuaciones', [
                    'textarea_name' => 'gc_puntuaciones',
                    'textarea_rows' => 8,
                    'media_buttons' => true,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
            </div>

            <?php
            // Pre-generar textos por defecto para JS (sin AJAX, directo)
            $def_instr = function_exists('gc_default_instrucciones') ? gc_default_instrucciones($post->ID) : '';
            $def_punt  = function_exists('gc_default_puntuaciones') ? gc_default_puntuaciones($post->ID) : '';
            ?>
            <script>
            (function(){
                var defInstr = <?php echo wp_json_encode($def_instr); ?>;
                var defPunt  = <?php echo wp_json_encode($def_punt); ?>;

                function setEditorContent(editorId, html) {
                    // TinyMCE visual mode
                    if (typeof tinyMCE !== 'undefined') {
                        var ed = tinyMCE.get(editorId);
                        if (ed) { ed.setContent(html); ed.fire('change'); return; }
                    }
                    // Fallback: textarea (text mode)
                    var ta = document.getElementById(editorId);
                    if (ta) ta.value = html;
                }

                document.getElementById('gc-gen-instrucciones').addEventListener('click', function(){
                    setEditorContent('gc_esc_instrucciones', defInstr);
                });
                document.getElementById('gc-gen-puntuaciones').addEventListener('click', function(){
                    setEditorContent('gc_esc_puntuaciones', defPunt);
                });
            })();
            </script>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="5">&larr; Anterior</button>
                <button type="button" class="gc-wiz-btn gc-wiz-btn-next" data-next="7">Siguiente &rarr;</button>
            </div>
        </div>

        <!-- ========== PASO 7: APARIENCIA ========== -->
        <?php
            $tema_preset    = get_post_meta($post->ID, 'gc_tema_preset', true) ?: 'claro';
            $tema_header_p  = get_post_meta($post->ID, 'gc_tema_header_propio', true) === '1';
            $tema_img       = get_post_meta($post->ID, 'gc_tema_imagen_fondo', true);
            $presets_data   = function_exists('gc_tema_presets') ? gc_tema_presets() : [];
            // Overrides personalizados (con fallback al preset 'claro' como base)
            $base_claro     = $presets_data['claro'];
            $cust_body_bg     = get_post_meta($post->ID, 'gc_tema_body_bg',     true) ?: $base_claro['body_bg'];
            $cust_body_color  = get_post_meta($post->ID, 'gc_tema_body_color',  true) ?: $base_claro['body_color'];
            $cust_accent      = get_post_meta($post->ID, 'gc_tema_accent',      true) ?: $base_claro['accent'];
            $cust_card_bg     = get_post_meta($post->ID, 'gc_tema_card_bg',     true) ?: $base_claro['card_bg'];
            $cust_card_border = get_post_meta($post->ID, 'gc_tema_card_border', true) ?: $base_claro['card_border'];
            $cust_header_bg   = get_post_meta($post->ID, 'gc_tema_header_bg',   true) ?: $base_claro['header_bg'];
            $cust_header_color= get_post_meta($post->ID, 'gc_tema_header_color',true) ?: $base_claro['header_color'];
        ?>
        <div class="gc-wiz-panel" data-step="7">
            <h3>Apariencia del escenario</h3>
            <p class="gc-wiz-subtitle">Elige el aspecto visual de las páginas del jugador (escenario, estaciones, ranking, instrucciones, puntuaciones).</p>

            <!-- Cards de presets -->
            <div class="gc-wiz-cards" style="grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));">
                <?php foreach ($presets_data as $key => $p):
                    $selected = $tema_preset === $key ? 'selected' : '';
                ?>
                <div class="gc-wiz-card <?php echo $selected; ?>" data-value="<?php echo esc_attr($key); ?>" data-field="gc_tema_preset" style="position:relative;overflow:hidden;">
                    <!-- Mini preview -->
                    <div style="display:flex;height:60px;border-radius:8px;overflow:hidden;margin-bottom:10px;border:1px solid #e2e8f0;">
                        <div style="flex:1;background:<?php echo esc_attr($p['body_bg']); ?>;display:flex;align-items:center;justify-content:center;color:<?php echo esc_attr($p['body_color']); ?>;font-size:11px;font-weight:600;">Aa</div>
                        <div style="width:30%;background:<?php echo esc_attr($p['accent']); ?>;"></div>
                        <div style="width:25%;background:<?php echo esc_attr($p['header_bg']); ?>;"></div>
                    </div>
                    <div class="gc-wiz-card-title" style="font-size:14px;"><?php echo esc_html($p['label']); ?></div>
                    <div class="gc-wiz-card-desc" style="font-size:11px;"><?php echo esc_html($p['descripcion']); ?></div>
                </div>
                <?php endforeach; ?>
                <div class="gc-wiz-card <?php echo $tema_preset === 'personalizado' ? 'selected' : ''; ?>" data-value="personalizado" data-field="gc_tema_preset" style="position:relative;overflow:hidden;">
                    <div style="display:flex;height:60px;border-radius:8px;overflow:hidden;margin-bottom:10px;border:1px solid #e2e8f0;background:linear-gradient(135deg,#fbbf24,#f472b6,#8b5cf6,#06b6d4);">
                        <div style="margin:auto;color:#fff;font-size:22px;">🎨</div>
                    </div>
                    <div class="gc-wiz-card-title" style="font-size:14px;">🎨 Personalizado</div>
                    <div class="gc-wiz-card-desc" style="font-size:11px;">Elige tus propios colores.</div>
                </div>
            </div>
            <input type="hidden" name="gc_tema_preset" id="gc_tema_preset" value="<?php echo esc_attr($tema_preset); ?>" />

            <!-- Colores personalizados (solo visible si preset=personalizado) -->
            <div id="gc-tema-custom" style="<?php echo $tema_preset !== 'personalizado' ? 'display:none;' : ''; ?>margin-top:20px;padding:18px 20px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
                <h4 style="margin:0 0 14px;">Colores personalizados</h4>
                <p style="margin:0 0 12px;font-size:12px;color:#64748b;">Puedes usar el selector visual o escribir un código HEX (ej: <code>#F0F8FF</code>).</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
                    <?php gc_render_color_field('gc_tema_body_bg',     $cust_body_bg,     'Color de fondo'); ?>
                    <?php gc_render_color_field('gc_tema_body_color',  $cust_body_color,  'Color de texto'); ?>
                    <?php gc_render_color_field('gc_tema_accent',      $cust_accent,      'Color de acento (botones, enlaces)'); ?>
                    <?php gc_render_color_field('gc_tema_card_bg',     $cust_card_bg,     'Fondo de tarjetas'); ?>
                    <?php gc_render_color_field('gc_tema_card_border', $cust_card_border, 'Borde de tarjetas'); ?>
                </div>
            </div>

            <!-- Header independiente -->
            <div style="margin-top:20px;">
                <label class="gc-wiz-toggle">
                    <input type="checkbox" name="gc_tema_header_propio" value="1" id="gc_tema_header_propio" <?php checked($tema_header_p, true); ?> />
                    <span class="gc-switch"></span>
                    <div>
                        <div class="gc-toggle-label">Personalizar el color del header de navegación</div>
                        <div class="gc-toggle-desc">Si lo activas, podrás elegir un color distinto para la barra de navegación superior.</div>
                    </div>
                </label>
                <div id="gc-tema-header-colors" style="<?php echo $tema_header_p ? '' : 'display:none;'; ?>margin-top:14px;padding:14px 16px;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                        <?php gc_render_color_field('gc_tema_header_bg',    $cust_header_bg,    'Fondo del header'); ?>
                        <?php gc_render_color_field('gc_tema_header_color', $cust_header_color, 'Texto del header'); ?>
                    </div>
                </div>
            </div>

            <?php if (function_exists('gc_render_color_field_script')) gc_render_color_field_script(); ?>

            <!-- Imagen de fondo -->
            <div class="gc-wiz-field" style="margin-top:20px;">
                <label>Imagen de fondo (opcional)</label>
                <p style="font-size:12px;color:#64748b;margin:2px 0 8px;">Se mostrará detrás del contenido con un velo del color de fondo para mantener la legibilidad. Funciona con cualquier preset.</p>
                <?php gc_render_media_field('gc_tema_imagen_fondo', $tema_img, 'image', 'Seleccionar imagen'); ?>
            </div>

            <!-- Logos del pie de página -->
            <?php
                $logo_1 = get_post_meta($post->ID, 'gc_logo_1', true);
                $logo_2 = get_post_meta($post->ID, 'gc_logo_2', true);
                $logo_3 = get_post_meta($post->ID, 'gc_logo_3', true);
            ?>
            <div style="margin-top:24px;padding:18px 20px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
                <h4 style="margin:0 0 6px;">Logos del pie de página</h4>
                <p style="font-size:12px;color:#64748b;margin:0 0 14px;">Sube de 1 a 3 logos. Aparecerán en la parte inferior de todas las páginas del escenario (escenario, estaciones, ranking, instrucciones, puntuaciones). Tamaño recomendado: PNG con fondo transparente, ~180×180px (se mostrarán a ~90px de ancho).</p>
                <p style="font-size:12px;color:#64748b;margin:0 0 14px;">Distribución automática: 1 logo → centrado · 2 logos → uno a cada lado · 3 logos → izquierda, centro y derecha.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;">
                    <div class="gc-wiz-field" style="margin:0;">
                        <label>Logo 1</label>
                        <?php gc_render_media_field('gc_logo_1', $logo_1, 'image', 'Seleccionar'); ?>
                    </div>
                    <div class="gc-wiz-field" style="margin:0;">
                        <label>Logo 2 (opcional)</label>
                        <?php gc_render_media_field('gc_logo_2', $logo_2, 'image', 'Seleccionar'); ?>
                    </div>
                    <div class="gc-wiz-field" style="margin:0;">
                        <label>Logo 3 (opcional)</label>
                        <?php gc_render_media_field('gc_logo_3', $logo_3, 'image', 'Seleccionar'); ?>
                    </div>
                </div>
            </div>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="6">&larr; Anterior</button>
                <div style="font-size:13px;color:#64748b;display:flex;align-items:center;gap:6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Pulsa <strong style="margin:0 4px;">Publicar</strong> o <strong style="margin:0 4px;">Actualizar</strong> para guardar
                </div>
            </div>
        </div>

    </div>

    <script>
    (function(){
        var wizard = document.getElementById('gc-wizard');
        if (!wizard) return;

        var currentStep = 1;
        var totalSteps = 7;

        function goToStep(n) {
            if (n < 1 || n > totalSteps) return;
            currentStep = n;
            // Panels
            wizard.querySelectorAll('.gc-wiz-panel').forEach(function(p) {
                p.classList.toggle('active', parseInt(p.dataset.step) === n);
            });
            // Tabs
            wizard.querySelectorAll('.gc-wiz-step-tab').forEach(function(t) {
                var s = parseInt(t.dataset.step);
                t.classList.toggle('active', s === n);
                t.classList.toggle('done', s < n);
            });
        }

        // Nav buttons
        wizard.addEventListener('click', function(e) {
            var btn = e.target.closest('.gc-wiz-btn-next');
            if (btn) { goToStep(parseInt(btn.dataset.next)); return; }
            btn = e.target.closest('.gc-wiz-btn-prev');
            if (btn) { goToStep(parseInt(btn.dataset.prev)); return; }
        });

        // Tab clicks
        wizard.querySelectorAll('.gc-wiz-step-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                goToStep(parseInt(this.dataset.step));
            });
        });

        // === Card selection ===
        // Step 1: tipo escenario
        wizard.querySelectorAll('[data-step="1"] .gc-wiz-card').forEach(function(card) {
            card.addEventListener('click', function() {
                this.parentNode.querySelectorAll('.gc-wiz-card').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                var val = this.dataset.value;
                document.getElementById('gc_tipo_escenario').value = val;
                // Presets inteligentes
                if (val === 'infantil') {
                    document.getElementById('gc_tipo_qr').value = 'validacion_boton';
                    document.getElementById('gc_requiere_prueba').checked = false;
                    document.getElementById('gc_mostrar_puntos').checked = false;
                    updateQrCards('validacion_boton');
                    document.getElementById('gc-origen-preguntas-section').style.display = 'none';
                } else {
                    document.getElementById('gc_tipo_qr').value = 'validacion_quiz';
                    document.getElementById('gc_requiere_prueba').checked = true;
                    document.getElementById('gc_mostrar_puntos').checked = true;
                    updateQrCards('validacion_quiz');
                    document.getElementById('gc-origen-preguntas-section').style.display = '';
                }
            });
        });

        function updateQrCards(val) {
            wizard.querySelectorAll('[data-field="gc_tipo_qr"]').forEach(function(c) {
                c.classList.toggle('selected', c.dataset.value === val);
            });
        }

        // Step 2: tipo QR
        wizard.querySelectorAll('[data-field="gc_tipo_qr"]').forEach(function(card) {
            card.addEventListener('click', function() {
                wizard.querySelectorAll('[data-field="gc_tipo_qr"]').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                var val = this.dataset.value;
                document.getElementById('gc_tipo_qr').value = val;
                // validacion_quiz requiere prueba obligatoriamente
                document.getElementById('gc-geo-section').style.display = val === 'validacion_gps' ? '' : 'none';
                if (val === 'validacion_quiz' || val === 'validacion_boton_quiz' || val === 'solo_pregunta') {
                    document.getElementById('gc_requiere_prueba').checked = true;
                    document.getElementById('gc-origen-preguntas-section').style.display = '';
                } else if (val === 'validacion_boton') {
                    document.getElementById('gc_requiere_prueba').checked = false;
                    document.getElementById('gc-origen-preguntas-section').style.display = 'none';
                }
            });
        });

        // Step 2: origen preguntas
        wizard.querySelectorAll('[data-field="gc_origen_preguntas"]').forEach(function(card) {
            card.addEventListener('click', function() {
                wizard.querySelectorAll('[data-field="gc_origen_preguntas"]').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                var val = this.dataset.value;
                document.getElementById('gc_origen_preguntas').value = val;
                document.getElementById('gc-pool-selector').style.display = val === 'pool' ? '' : 'none';
            });
        });

        // Toggle prueba: mostrar/ocultar sección origen preguntas
        document.getElementById('gc_requiere_prueba').addEventListener('change', function() {
            document.getElementById('gc-origen-preguntas-section').style.display = this.checked ? '' : 'none';
        });

        // Step 7: tema (presets)
        wizard.querySelectorAll('[data-field="gc_tema_preset"]').forEach(function(card){
            card.addEventListener('click', function(){
                wizard.querySelectorAll('[data-field="gc_tema_preset"]').forEach(function(c){ c.classList.remove('selected'); });
                this.classList.add('selected');
                var val = this.dataset.value;
                document.getElementById('gc_tema_preset').value = val;
                var custom = document.getElementById('gc-tema-custom');
                if (custom) custom.style.display = val === 'personalizado' ? '' : 'none';
            });
        });

        // Toggle header propio
        var hp = document.getElementById('gc_tema_header_propio');
        if (hp) {
            hp.addEventListener('change', function(){
                var box = document.getElementById('gc-tema-header-colors');
                if (box) box.style.display = this.checked ? '' : 'none';
            });
        }

        // Step 3: accion final
        wizard.querySelectorAll('[data-field="gc_accion_final"]').forEach(function(card) {
            card.addEventListener('click', function() {
                wizard.querySelectorAll('[data-field="gc_accion_final"]').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                var val = this.dataset.value;
                document.getElementById('gc_accion_final').value = val;
                document.getElementById('gc_foto_texto_row').style.display = val === 'subir_foto' ? '' : 'none';
                document.getElementById('gc_enhorabuena_row').style.display = val === 'ninguna' ? '' : 'none';
            });
        });

        // Toggle diploma opciones
        var diplomaCheck = document.getElementById('gc_diploma_activo');
        if (diplomaCheck) {
            diplomaCheck.addEventListener('change', function() {
                document.getElementById('gc_diploma_opciones').style.display = this.checked ? '' : 'none';
            });
        }

        // Toggle pie del diploma
        var piePieCheck = document.getElementById('gc_diploma_pie_activo');
        if (piePieCheck) {
            piePieCheck.addEventListener('change', function() {
                var row = document.getElementById('gc_diploma_pie_row');
                if (row) row.style.display = this.checked ? '' : 'none';
            });
        }

        // Mark done tabs for existing data
        var tipo = document.getElementById('gc_tipo_escenario').value;
        if (tipo) goToStep(1);

        // === Resumen de configuración ===
        var resumenBtn = document.getElementById('gc-wiz-resumen-btn');
        var resumenPanel = document.getElementById('gc-wiz-resumen-panel');
        if (resumenBtn && resumenPanel) {
            resumenBtn.addEventListener('click', function() {
                if (resumenPanel.style.display !== 'none') {
                    resumenPanel.style.display = 'none';
                    return;
                }
                var labels = {
                    tipo_escenario: { adulto: 'Adulto', infantil: 'Infantil' },
                    tipo_qr: { enlace: 'Enlace directo', validacion_boton: 'Validacion (boton)', validacion_boton_quiz: 'QR + Quiz', validacion_quiz: 'Validacion (quiz)', validacion_gps: 'Validacion GPS', solo_pregunta: 'Sin QR · Solo pregunta', validacion: 'Validacion (legacy)' },
                    origen_preguntas: { por_estacion: 'Por estacion', pool: 'Pool aleatorio' },
                    accion_final: { ninguna: 'Solo enhorabuena', subir_foto: 'Subir foto' }
                };

                var v = function(id) { var el = document.getElementById(id); return el ? el.value : ''; };
                var ch = function(id) { var el = document.getElementById(id); return el ? el.checked : false; };
                var lb = function(cat, val) { return (labels[cat] && labels[cat][val]) ? labels[cat][val] : (val || '—'); };
                var esc = function(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

                var tipoEsc = v('gc_tipo_escenario');
                var tipoQr = v('gc_tipo_qr');
                var prueba = ch('gc_requiere_prueba');
                var puntos = ch('gc_mostrar_puntos');
                var origen = v('gc_origen_preguntas');
                var poolSel = document.getElementById('gc_pool_prueba_ref');
                var poolText = poolSel && poolSel.selectedIndex > 0 ? poolSel.options[poolSel.selectedIndex].text : '—';
                var accion = v('gc_accion_final');
                var fotoTexto = v('gc_foto_texto');
                var labelEst = v('gc_label_estacion') || 'estacion';
                var labelPl = v('gc_label_estacion_plural') || 'las estaciones';
                var cta = v('gc_cta_texto') || '(auto)';
                var audio = v('gc_audio');
                var img1 = v('gc_img_1');
                var img2 = v('gc_img_2');
                var img3 = v('gc_img_3');
                var imgEnc = v('gc_img_encontrada');

                var section = function(title, rows) {
                    var h = '<div style="margin-bottom:14px;"><div style="font-size:13px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;border-bottom:1px solid #bfdbfe;padding-bottom:4px;">' + title + '</div>';
                    h += '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
                    for (var i = 0; i < rows.length; i++) {
                        h += '<tr><td style="padding:3px 8px 3px 0;color:#64748b;white-space:nowrap;vertical-align:top;width:180px;">' + rows[i][0] + '</td>';
                        h += '<td style="padding:3px 0;color:#0f172a;font-weight:500;">' + rows[i][1] + '</td></tr>';
                    }
                    h += '</table></div>';
                    return h;
                };

                var dot = function(color) { return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + color + ';margin-right:6px;"></span>'; };
                var si = dot('#16a34a') + 'Si';
                var no = dot('#dc2626') + 'No';

                var html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">'
                    + '<h3 style="margin:0;font-size:16px;color:#1e293b;">Resumen de configuracion</h3>'
                    + '<button type="button" onclick="this.closest(\'#gc-wiz-resumen-panel\').style.display=\'none\'" style="border:0;background:0;font-size:20px;color:#94a3b8;cursor:pointer;">&times;</button>'
                    + '</div>';

                var guestOn = ch('gc_permitir_guest');
                html += section('1. Tipo de escenario', [
                    ['Tipo', lb('tipo_escenario', tipoEsc)],
                    ['Sin registro', guestOn ? si : no]
                ]);

                var pistas = ch('gc_pistas_activas');
                var ordenLibre   = ch('gc_orden_libre');
                var ordenSecreto = ch('gc_orden_secreto');
                var ordenTxt = ordenSecreto ? 'Aleatorio (secreto)' : (ordenLibre ? 'Libre' : 'Secuencial');
                var mecRows = [
                    ['Tipo de QR', lb('tipo_qr', tipoQr)],
                    ['Prueba/quiz', prueba ? si : no],
                    ['Gamificacion', puntos ? si : no],
                    ['Pistas', pistas ? si : no],
                    ['Orden', ordenTxt]
                ];
                if (prueba) {
                    mecRows.push(['Origen preguntas', lb('origen_preguntas', origen)]);
                    if (origen === 'pool') mecRows.push(['Pool seleccionado', esc(poolText)]);
                }
                html += section('2. Mecanica', mecRows);

                var accRows = [['Accion final', lb('accion_final', accion)]];
                if (accion === 'subir_foto' && fotoTexto) accRows.push(['Texto foto', esc(fotoTexto)]);
                if (accion === 'ninguna') {
                    var enhMsg = v('gc_enhorabuena_msg');
                    if (enhMsg) accRows.push(['Mensaje enhorabuena', esc(enhMsg)]);
                    var diploma = ch('gc_diploma_activo');
                    accRows.push(['Diploma descargable', diploma ? si : no]);
                    if (diploma) {
                        var dipMsg = v('gc_diploma_msg');
                        if (dipMsg) accRows.push(['Mensaje diploma', esc(dipMsg)]);
                        accRows.push(['Fondo diploma', v('gc_diploma_fondo') ? dot('#16a34a') + 'Si' : dot('#dc2626') + 'No']);
                        var pieActivo = ch('gc_diploma_pie_activo');
                        accRows.push(['Pie en diploma', pieActivo ? si : no]);
                        if (pieActivo) {
                            var pieTxt = v('gc_diploma_pie_texto');
                            if (pieTxt) accRows.push(['Texto del pie', esc(pieTxt)]);
                        }
                    }
                }
                html += section('3. Accion final', accRows);

                html += section('4. Textos', [
                    ['Nombre singular', esc(labelEst)],
                    ['Nombre plural', esc(labelPl)],
                    ['CTA', esc(cta)]
                ]);

                var contRows = [];
                contRows.push(['Audio', audio ? dot('#16a34a') + 'Si' : dot('#dc2626') + 'No']);
                contRows.push(['Imagen 1', img1 ? dot('#16a34a') + 'Si' : dot('#dc2626') + 'No']);
                contRows.push(['Imagen 2', img2 ? dot('#16a34a') + 'Si' : dot('#dc2626') + 'No']);
                contRows.push(['Imagen 3', img3 ? dot('#16a34a') + 'Si' : dot('#dc2626') + 'No']);
                contRows.push(['Img encontrada', imgEnc ? dot('#16a34a') + 'Si' : dot('#dc2626') + 'No']);
                html += section('5. Contenido', contRows);

                resumenPanel.innerHTML = html;
                resumenPanel.style.display = '';
            });
        }
    })();
    </script>
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
    update_post_meta($post_id, 'gc_permitir_guest', isset($_POST['gc_permitir_guest']) ? '1' : '0');
    $tipo_qr = sanitize_text_field($_POST['gc_tipo_qr'] ?? 'enlace');
    if ( ! in_array($tipo_qr, ['enlace', 'validacion_boton', 'validacion_boton_quiz', 'validacion_quiz', 'validacion_gps', 'solo_pregunta'], true) ) $tipo_qr = 'enlace';
    update_post_meta($post_id, 'gc_tipo_qr', $tipo_qr);
    update_post_meta($post_id, 'gc_mostrar_puntos', isset($_POST['gc_mostrar_puntos']) ? '1' : '0');
    // 'solo_pregunta' requiere prueba obligatoriamente
    $req_prueba = isset($_POST['gc_requiere_prueba']) ? '1' : '0';
    if ($tipo_qr === 'solo_pregunta' || $tipo_qr === 'validacion_boton_quiz') $req_prueba = '1';
    update_post_meta($post_id, 'gc_requiere_prueba', $req_prueba);
    update_post_meta($post_id, 'gc_pistas_activas', isset($_POST['gc_pistas_activas']) ? '1' : '0');
    // Orden libre vs orden aleatorio secreto: mutuamente excluyentes
    $is_libre   = isset($_POST['gc_orden_libre']) ? '1' : '0';
    $is_secreto = isset($_POST['gc_orden_secreto']) ? '1' : '0';
    if ($is_libre === '1' && $is_secreto === '1') {
        // Defensa por si llegan los dos: gana 'secreto'
        $is_libre = '0';
    }
    update_post_meta($post_id, 'gc_orden_libre', $is_libre);
    update_post_meta($post_id, 'gc_orden_secreto', $is_secreto);
    // Limpiar el meta antiguo (v1.0.37) si existía
    delete_post_meta($post_id, 'gc_orden_aleatorio');
    $geo_radio = isset($_POST['gc_geo_radio']) ? max(0, (int) $_POST['gc_geo_radio']) : 0;
    update_post_meta($post_id, 'gc_geo_radio', $geo_radio);

    $origen_preguntas = sanitize_text_field($_POST['gc_origen_preguntas'] ?? 'por_estacion');
    if (!in_array($origen_preguntas, ['por_estacion', 'pool'], true)) $origen_preguntas = 'por_estacion';
    update_post_meta($post_id, 'gc_origen_preguntas', $origen_preguntas);
    update_post_meta($post_id, 'gc_pool_prueba_ref', (int)($_POST['gc_pool_prueba_ref'] ?? 0));

    $accion_final = sanitize_text_field($_POST['gc_accion_final'] ?? 'ninguna');
    if (!in_array($accion_final, ['ninguna', 'subir_foto'], true)) $accion_final = 'ninguna';
    update_post_meta($post_id, 'gc_accion_final', $accion_final);
    update_post_meta($post_id, 'gc_foto_texto', sanitize_text_field($_POST['gc_foto_texto'] ?? ''));

    // Campos de enhorabuena y diploma
    update_post_meta($post_id, 'gc_enhorabuena_msg', sanitize_text_field($_POST['gc_enhorabuena_msg'] ?? ''));
    update_post_meta($post_id, 'gc_diploma_activo', isset($_POST['gc_diploma_activo']) ? '1' : '0');
    update_post_meta($post_id, 'gc_diploma_msg', sanitize_text_field($_POST['gc_diploma_msg'] ?? ''));
    update_post_meta($post_id, 'gc_diploma_pie_activo', isset($_POST['gc_diploma_pie_activo']) ? '1' : '0');
    update_post_meta($post_id, 'gc_diploma_pie_texto', sanitize_text_field($_POST['gc_diploma_pie_texto'] ?? ''));
    update_post_meta($post_id, 'gc_diploma_fondo', esc_url_raw($_POST['gc_diploma_fondo'] ?? ''));

    update_post_meta($post_id, 'gc_label_estacion', sanitize_text_field($_POST['gc_label_estacion'] ?? ''));
    update_post_meta($post_id, 'gc_label_estacion_plural', sanitize_text_field($_POST['gc_label_estacion_plural'] ?? ''));
    update_post_meta($post_id, 'gc_cta_texto', sanitize_text_field($_POST['gc_cta_texto'] ?? ''));
    // Instrucciones y puntuaciones: auto-rellenar SOLO en la primera publicación
    $instr_val = wp_kses_post($_POST['gc_instrucciones'] ?? '');
    $punt_val  = wp_kses_post($_POST['gc_puntuaciones'] ?? '');
    $is_first_save = ! get_post_meta($post_id, '_gc_info_generated', true);
    if ( $is_first_save && function_exists('gc_default_instrucciones') ) {
        if ( ! trim(strip_tags($instr_val)) ) {
            $instr_val = gc_default_instrucciones($post_id);
        }
        if ( ! trim(strip_tags($punt_val)) ) {
            $punt_val = gc_default_puntuaciones($post_id);
        }
        update_post_meta($post_id, '_gc_info_generated', '1');
    }
    update_post_meta($post_id, 'gc_instrucciones', $instr_val);
    update_post_meta($post_id, 'gc_puntuaciones', $punt_val);
    update_post_meta($post_id, 'gc_portada', esc_url_raw($_POST['gc_portada'] ?? ''));
    update_post_meta($post_id, 'gc_fondo_textos', esc_url_raw($_POST['gc_fondo_textos'] ?? ''));
    update_post_meta($post_id, 'gc_descripcion', wp_kses_post($_POST['gc_descripcion'] ?? ''));
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));
    update_post_meta($post_id, 'gc_img_3', esc_url_raw($_POST['gc_img_3'] ?? ''));
    update_post_meta($post_id, 'gc_img_encontrada', esc_url_raw($_POST['gc_img_encontrada'] ?? ''));
    update_post_meta($post_id, 'gc_ranking_imagen', esc_url_raw($_POST['gc_ranking_imagen'] ?? ''));
    update_post_meta($post_id, 'gc_img_busca_qr', esc_url_raw($_POST['gc_img_busca_qr'] ?? ''));
    update_post_meta($post_id, 'gc_img_pregunta', esc_url_raw($_POST['gc_img_pregunta'] ?? ''));
    update_post_meta($post_id, 'gc_img_acierto', esc_url_raw($_POST['gc_img_acierto'] ?? ''));

    // === Apariencia (tema) ===
    $valid_presets = ['claro','oscuro','aventura','nautico','bosque','pastel','personalizado'];
    $tema_preset = sanitize_text_field($_POST['gc_tema_preset'] ?? 'claro');
    if ( ! in_array($tema_preset, $valid_presets, true) ) $tema_preset = 'claro';
    update_post_meta($post_id, 'gc_tema_preset', $tema_preset);
    update_post_meta($post_id, 'gc_tema_header_propio', isset($_POST['gc_tema_header_propio']) ? '1' : '0');
    update_post_meta($post_id, 'gc_tema_imagen_fondo', esc_url_raw($_POST['gc_tema_imagen_fondo'] ?? ''));

    // Logos del pie de página
    update_post_meta($post_id, 'gc_logo_1', esc_url_raw($_POST['gc_logo_1'] ?? ''));
    update_post_meta($post_id, 'gc_logo_2', esc_url_raw($_POST['gc_logo_2'] ?? ''));
    update_post_meta($post_id, 'gc_logo_3', esc_url_raw($_POST['gc_logo_3'] ?? ''));

    // Sanitizar colores hex (#xxxxxx)
    $sanitize_hex = function($v) {
        $v = trim((string) $v);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) return strtolower($v);
        if (preg_match('/^#[0-9a-fA-F]{3}$/', $v)) return strtolower($v);
        return '';
    };
    $color_fields = ['gc_tema_body_bg','gc_tema_body_color','gc_tema_accent','gc_tema_card_bg','gc_tema_card_border','gc_tema_header_bg','gc_tema_header_color'];
    foreach ($color_fields as $cf) {
        $val = $sanitize_hex($_POST[$cf] ?? '');
        update_post_meta($post_id, $cf, $val);
    }

});

// Nota: las páginas de ranking, instrucciones y puntuaciones se generan
// como rutas virtuales en virtual-pages.php — no se crean páginas WP.