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
    $origen_preguntas = get_post_meta($post->ID, 'gc_origen_preguntas', true) ?: 'por_estacion';
    $pool_prueba_ref  = (int) get_post_meta($post->ID, 'gc_pool_prueba_ref', true);
    $accion_final    = get_post_meta($post->ID, 'gc_accion_final', true) ?: 'ninguna';
    $foto_texto      = get_post_meta($post->ID, 'gc_foto_texto', true);
    $label_estacion  = get_post_meta($post->ID, 'gc_label_estacion', true);
    $label_plural    = get_post_meta($post->ID, 'gc_label_estacion_plural', true);
    $cta_texto       = get_post_meta($post->ID, 'gc_cta_texto', true);
    $ranking_url     = get_post_meta($post->ID, 'gc_ranking_url', true);
    $descripcion     = get_post_meta($post->ID, 'gc_descripcion', true);
    $audio           = get_post_meta($post->ID, 'gc_audio', true);
    $img1            = get_post_meta($post->ID, 'gc_img_1', true);
    $img2            = get_post_meta($post->ID, 'gc_img_2', true);
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
                    <div class="gc-wiz-card-desc">El QR lleva a una pregunta que valida la estacion</div>
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

            <div class="gc-wiz-field">
                <label for="gc_ranking_url">Pagina de ranking (URL)</label>
                <input type="url" name="gc_ranking_url" id="gc_ranking_url"
                       value="<?php echo esc_attr($ranking_url); ?>"
                       placeholder="Se crea automaticamente al publicar" />
                <div class="gc-hint">Se autocrea al publicar el escenario. Solo rellenar para override manual.</div>
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

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="gc-wiz-field">
                    <label>Imagen 1</label>
                    <?php gc_render_media_field('gc_img_1', $img1, 'image', 'Seleccionar imagen'); ?>
                </div>
                <div class="gc-wiz-field">
                    <label>Imagen 2</label>
                    <?php gc_render_media_field('gc_img_2', $img2, 'image', 'Seleccionar imagen'); ?>
                </div>
            </div>

            <div class="gc-wiz-nav">
                <button type="button" class="gc-wiz-btn gc-wiz-btn-prev" data-prev="4">&larr; Anterior</button>
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
        var totalSteps = 5;

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
                if (val === 'validacion_quiz') {
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

        // Step 3: accion final
        wizard.querySelectorAll('[data-field="gc_accion_final"]').forEach(function(card) {
            card.addEventListener('click', function() {
                wizard.querySelectorAll('[data-field="gc_accion_final"]').forEach(function(c) { c.classList.remove('selected'); });
                this.classList.add('selected');
                var val = this.dataset.value;
                document.getElementById('gc_accion_final').value = val;
                document.getElementById('gc_foto_texto_row').style.display = val === 'subir_foto' ? '' : 'none';
            });
        });

        // Mark done tabs for existing data
        var tipo = document.getElementById('gc_tipo_escenario').value;
        if (tipo) goToStep(1);
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
    $tipo_qr = sanitize_text_field($_POST['gc_tipo_qr'] ?? 'enlace');
    if ( ! in_array($tipo_qr, ['enlace', 'validacion'], true) ) $tipo_qr = 'enlace';
    update_post_meta($post_id, 'gc_tipo_qr', $tipo_qr);
    update_post_meta($post_id, 'gc_mostrar_puntos', isset($_POST['gc_mostrar_puntos']) ? '1' : '0');
    update_post_meta($post_id, 'gc_requiere_prueba', isset($_POST['gc_requiere_prueba']) ? '1' : '0');

    $origen_preguntas = sanitize_text_field($_POST['gc_origen_preguntas'] ?? 'por_estacion');
    if (!in_array($origen_preguntas, ['por_estacion', 'pool'], true)) $origen_preguntas = 'por_estacion';
    update_post_meta($post_id, 'gc_origen_preguntas', $origen_preguntas);
    update_post_meta($post_id, 'gc_pool_prueba_ref', (int)($_POST['gc_pool_prueba_ref'] ?? 0));

    $accion_final = sanitize_text_field($_POST['gc_accion_final'] ?? 'ninguna');
    if (!in_array($accion_final, ['ninguna', 'subir_foto'], true)) $accion_final = 'ninguna';
    update_post_meta($post_id, 'gc_accion_final', $accion_final);
    update_post_meta($post_id, 'gc_foto_texto', sanitize_text_field($_POST['gc_foto_texto'] ?? ''));

    update_post_meta($post_id, 'gc_label_estacion', sanitize_text_field($_POST['gc_label_estacion'] ?? ''));
    update_post_meta($post_id, 'gc_label_estacion_plural', sanitize_text_field($_POST['gc_label_estacion_plural'] ?? ''));
    update_post_meta($post_id, 'gc_cta_texto', sanitize_text_field($_POST['gc_cta_texto'] ?? ''));
    // Ranking URL: si viene del formulario, guardarla. Si no, auto-crear página.
    $ranking_url_manual = esc_url_raw($_POST['gc_ranking_url'] ?? '');
    if ($ranking_url_manual) {
        update_post_meta($post_id, 'gc_ranking_url', $ranking_url_manual);
    }

    update_post_meta($post_id, 'gc_descripcion', wp_kses_post($_POST['gc_descripcion'] ?? ''));
    update_post_meta($post_id, 'gc_audio', esc_url_raw($_POST['gc_audio'] ?? ''));
    update_post_meta($post_id, 'gc_img_1', esc_url_raw($_POST['gc_img_1'] ?? ''));
    update_post_meta($post_id, 'gc_img_2', esc_url_raw($_POST['gc_img_2'] ?? ''));

    // Auto-crear página de ranking si no existe
    gc_maybe_create_ranking_page($post_id);
});

/**
 * Migración: crear páginas de ranking para escenarios existentes (una sola vez).
 */
add_action('admin_init', function () {
    if (get_option('gc_ranking_pages_migrated')) return;

    $escenarios = get_posts([
        'post_type'      => 'escenario',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    foreach ($escenarios as $esc_id) {
        gc_maybe_create_ranking_page((int) $esc_id);
    }

    update_option('gc_ranking_pages_migrated', '1');
});

/**
 * Crea automáticamente una página de ranking para un escenario.
 * Solo si el escenario está publicado y no tiene ya una página de ranking.
 */
function gc_maybe_create_ranking_page($escenario_id) {
    // Solo para escenarios publicados
    if (get_post_status($escenario_id) !== 'publish') return;

    // Si ya tiene URL de ranking, no crear
    $existing_url = get_post_meta($escenario_id, 'gc_ranking_url', true);
    if (!empty($existing_url)) return;

    // Verificar que no exista ya una página con el meta enlazado
    $existing_page = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => '_gc_ranking_escenario_id', 'value' => (int)$escenario_id, 'compare' => '=']],
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    if (!empty($existing_page)) {
        // Ya existe pero no estaba enlazada, re-enlazar
        update_post_meta($escenario_id, 'gc_ranking_url', get_permalink($existing_page[0]));
        return;
    }

    $esc_title = get_the_title($escenario_id);
    $page_title = 'Ranking — ' . $esc_title;
    $page_slug  = 'ranking-' . sanitize_title($esc_title);

    $page_id = wp_insert_post([
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => $page_title,
        'post_name'    => $page_slug,
        'post_content' => '[gincana_ranking escenario="' . (int)$escenario_id . '"]',
    ], true);

    if (!is_wp_error($page_id) && $page_id) {
        // Marcar la página como ranking de este escenario
        update_post_meta($page_id, '_gc_ranking_escenario_id', (int)$escenario_id);
        // Guardar la URL en el escenario
        update_post_meta($escenario_id, 'gc_ranking_url', get_permalink($page_id));
    }
}