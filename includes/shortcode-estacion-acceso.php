<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcode: [gincana_estacion_acceso]
 */

add_shortcode('gincana_estacion_acceso', 'gc_shortcode_estacion_acceso');

function gc_shortcode_estacion_acceso() {
    $station_id = isset($_GET['gc_station']) ? absint($_GET['gc_station']) : 0;
    $token      = isset($_GET['gc_token']) ? sanitize_text_field(wp_unslash($_GET['gc_token'])) : '';

    if ( ! $station_id || empty($token) ) {
        return gc_station_wrap_message('Acceso no válido.', 'error');
    }

    $post = get_post($station_id);
    if ( ! $post || $post->post_type !== 'estacion' ) {
        return gc_station_wrap_message('La estación no existe.', 'error');
    }

    $saved_token = get_post_meta($station_id, 'gc_qr_token', true);
    if ( empty($saved_token) || ! hash_equals((string) $saved_token, (string) $token) ) {
        return gc_station_wrap_message('QR no válido.', 'error');
    }

    $escenario_id = (int) get_post_meta($station_id, 'gc_escenario_ref', true);
    if ($escenario_id <= 0) {
        return gc_station_wrap_message('La estación no tiene escenario enlazado.', 'error');
    }

    $tipo_escenario = get_post_meta($escenario_id, 'gc_tipo_escenario', true);
    if (empty($tipo_escenario)) {
        $tipo_escenario = 'adulto';
    }

    $audio    = get_post_meta($station_id, 'gc_audio', true);
    $maps_url = get_post_meta($station_id, 'gc_maps_url', true);
    $img1     = get_post_meta($station_id, 'gc_img_1', true);
    $img2     = get_post_meta($station_id, 'gc_img_2', true);
    $title    = get_the_title($station_id);
    $orden    = (int) get_post_meta($station_id, 'gc_orden', true);
    $label    = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $esc_title = get_the_title($escenario_id);
    $is_logged = is_user_logged_in();

    ob_start();

    $descripcion = get_post_meta($station_id, 'gc_descripcion', true);

    // Ocultar el título de la página WordPress ("acceso-estacion") y cambiar título del navegador
    echo '<style>.entry-title, .page-title, .et_pb_post_title, .et_pb_title_container { display:none !important; }</style>';
    echo '<script>document.title = ' . json_encode(esc_html($title) . ' — ' . esc_html($esc_title)) . ';</script>';

    echo '<div class="gc-station-access" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">';

    // Cabecera: escenario + nº estación + nombre
    echo '<h3 style="margin:0 0 6px;font-size:18px;font-weight:600;color:#2563eb;line-height:1.3;">' . esc_html($esc_title) . '</h3>';
    echo '<h2 style="margin:0 0 8px;font-size:22px;font-weight:700;line-height:1.3;">';
    if ($orden) echo '<span style="color:#64748b;">' . $orden . '.</span> ';
    echo esc_html($title) . '</h2>';

    echo gc_render_action_icons($audio, $maps_url);

    if ($descripcion) {
        echo '<div class="gc-station-desc" style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;">';
        echo wp_kses_post($descripcion);
        echo '</div>';
    }

    if ($img1 || $img2) {
        echo '<div style="display:flex;flex-direction:column;gap:12px;margin:0 0 24px;">';
        if ($img1) echo '<img src="' . esc_url($img1) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
        if ($img2) echo '<img src="' . esc_url($img2) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
        echo '</div>';
    }

    if ($tipo_escenario === 'infantil') {
        if (!$is_logged) {
            echo gc_render_infantil_station_qr_no_login($station_id, $title, $escenario_id);
        } else {
            echo gc_render_infantil_station_qr($station_id, $title, $escenario_id);
        }
    } else {
        echo gc_render_adulto_station($station_id, $title, $escenario_id);
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * Infantil — QR escaneado + usuario NO logueado: pedir login.
 */
function gc_render_infantil_station_qr_no_login($station_id, $title, $escenario_id) {
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $label_uc = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    // Redirigir de vuelta aquí tras login (conservar params QR)
    $current_url = (is_ssl() ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $login_url    = wp_login_url($current_url);
    $register_url = wp_registration_url();
    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#ecfdf3;border:2px solid #16a34a;text-align:center;">
        <div style="margin-bottom:8px;"><img src="https://gymkanaonline.com/wp-content/uploads/2026/03/puerta_encontrada.png" alt="" style="width:80px;height:auto;" /></div>
        <h2 style="margin:0 0 8px;color:#146c2e;">¡<?php echo esc_html($label_uc); ?> encontrada!</h2>
        <p style="margin:0 0 16px;font-size:15px;color:#334155;">
            Has encontrado <strong><?php echo esc_html($title); ?></strong>, pero necesitas iniciar sesión para validarla y acumular puntos.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="<?php echo esc_url($login_url); ?>" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Iniciar sesión</a>
            <a href="<?php echo esc_url($register_url); ?>" style="display:inline-block;padding:12px 24px;border:2px solid #2563eb;border-radius:10px;background:#fff;color:#2563eb;text-decoration:none;font-weight:600;">Registrarse</a>
        </div>
        <a href="<?php echo esc_url(get_permalink($escenario_id)); ?>" style="display:inline-block;margin-top:14px;font-size:14px;color:#64748b;text-decoration:underline;">← Volver al escenario</a>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Infantil — QR escaneado + usuario logueado: botón para completar.
 */
function gc_render_infantil_station_qr($station_id, $title, $escenario_id) {
    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $label_uc = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    $escenario_url = get_permalink($escenario_id) ?: home_url('/');

    // Si ya la ha completado
    $user_id = get_current_user_id();
    if (function_exists('gincana_user_passed') && gincana_user_passed($user_id, $station_id)) {
        ob_start();
        ?>
        <div style="padding:24px 20px;border-radius:14px;background:#f7fff7;border:2px solid #16a34a;text-align:center;">
            <div style="font-size:48px;margin-bottom:8px;">✅</div>
            <h2 style="margin:0 0 8px;color:#146c2e;">¡Ya completaste esta <?php echo esc_html($label); ?>!</h2>
            <a href="<?php echo esc_url($escenario_url); ?>" style="display:inline-block;margin-top:12px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>
        </div>
        <?php
        return ob_get_clean();
    }

    // ¿Requiere prueba este escenario?
    $requiere_prueba = get_post_meta($escenario_id, 'gc_requiere_prueba', true);
    $con_prueba = ($requiere_prueba === '1');

    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#ecfdf3;border:2px solid #16a34a;text-align:center;margin-bottom:<?php echo $con_prueba ? '16' : '0'; ?>px;">
        <div style="margin-bottom:8px;"><img src="https://gymkanaonline.com/wp-content/uploads/2026/03/puerta_encontrada.png" alt="" style="width:80px;height:auto;" /></div>
        <h2 style="margin:0 0 8px;color:#146c2e;">¡<?php echo esc_html($label_uc); ?> encontrada!</h2>
        <p style="margin:0;font-size:15px;color:#334155;">
            Has encontrado <strong><?php echo esc_html($title); ?></strong>.
        </p>
    </div>

    <?php if ($con_prueba): ?>
        <!-- Infantil + prueba: mostrar pregunta después del mensaje de encontrado -->
        <?php echo gc_render_adulto_station($station_id, $title, $escenario_id); ?>
    <?php else: ?>
        <!-- Infantil sin prueba: botón directo de completar -->
        <div class="gc-kids-station"
             data-station-id="<?php echo esc_attr($station_id); ?>"
             data-escenario-id="<?php echo esc_attr($escenario_id); ?>">

            <div style="text-align:center;margin-top:16px;">
                <button type="button" id="gc-kids-complete-btn"
                        style="width:100%;max-width:320px;padding:16px 24px;border:0;border-radius:12px;background:#16a34a;color:#fff;font-size:17px;font-weight:700;cursor:pointer;transition:transform 0.1s;">
                    ¡Completar <?php echo esc_html($label); ?>!
                </button>

                <div id="gc-kids-msg" style="margin-top:16px;"></div>
                <a href="<?php echo esc_url($escenario_url); ?>" style="display:inline-block;margin-top:14px;font-size:14px;color:#64748b;text-decoration:underline;">← Volver al escenario</a>
            </div>
        </div>

        <script>
        (function(){
            const wrap = document.querySelector('.gc-kids-station');
            if (!wrap) return;
            const stationId = parseInt(wrap.dataset.stationId, 10);
            const btn = wrap.querySelector('#gc-kids-complete-btn');
            const msg = wrap.querySelector('#gc-kids-msg');
            const nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js($nonce); ?>';
            if (!stationId || !btn || !msg) return;

            btn.addEventListener('click', async function(){
                btn.disabled = true;
                btn.textContent = 'Validando...';
                try {
                    const res = await fetch('/wp-json/gincana/v1/progress/skip', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                        credentials: 'same-origin',
                        body: JSON.stringify({ estacion_id: stationId, time_ms: 0 })
                    });
                    const data = await res.json();
                    if (data && data.ok) {
                        btn.style.display = 'none';
                        msg.innerHTML = '<div style="padding:16px;border-radius:12px;background:#dcfce7;border:1px solid #16a34a;color:#146c2e;font-size:16px;font-weight:600;">✅ ¡<?php echo esc_html(mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1)); ?> completada!</div>'
                            + '<a href="<?php echo esc_url($escenario_url); ?>" style="display:inline-block;margin-top:14px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>';
                    } else {
                        msg.innerHTML = '<div style="padding:14px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">No se pudo validar. Inténtalo de nuevo.</div>';
                        btn.disabled = false;
                        btn.textContent = '¡Completar <?php echo esc_js($label); ?>!';
                    }
                } catch (err) {
                    msg.innerHTML = '<div style="padding:14px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Error: ' + err.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = '¡Completar <?php echo esc_js($label); ?>!';
                }
            });
        })();
        </script>
    <?php endif; ?>

    <div style="text-align:center;margin-top:12px;">
        <a href="<?php echo esc_url($escenario_url); ?>" style="font-size:14px;color:#64748b;text-decoration:underline;">← Volver al escenario</a>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Infantil — acceso directo (sin QR): muestra pista para encontrar el QR.
 */
function gc_render_infantil_station_pista($station_id, $title, $escenario_id) {
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $pista = get_post_meta($station_id, 'gc_pista_busqueda', true);

    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#fffbeb;border:2px solid #f59e0b;text-align:center;">
        <div style="font-size:48px;margin-bottom:8px;">🔍</div>
        <h3 style="margin:0 0 8px;color:#92400e;">¡Busca el código QR!</h3>
        <p style="margin:0 0 12px;font-size:15px;color:#78350f;">
            Para validar esta <?php echo esc_html($label); ?>, necesitas encontrar y escanear el código QR en el lugar.
        </p>
        <?php if ($pista): ?>
        <div style="margin:16px 0 0;padding:14px 16px;border-radius:10px;background:#fff;border:1px dashed #f59e0b;">
            <p style="margin:0;font-size:13px;color:#92400e;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;">💡 Pista</p>
            <p style="margin:6px 0 0;font-size:15px;color:#451a03;"><?php echo esc_html($pista); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function gc_render_adulto_station($station_id, $title, $escenario_id) {
    $label   = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $user_id = get_current_user_id();

    // Determinar origen de la pregunta: por estación o pool
    $origen = function_exists('gc_get_origen_preguntas') ? gc_get_origen_preguntas($escenario_id) : 'por_estacion';

    $test_id   = 0;
    $pregunta  = null;
    $q_index   = 0;

    if ($origen === 'pool' && function_exists('gc_get_pool_question')) {
        // Pool aleatorio
        $pool_data = gc_get_pool_question($user_id, $escenario_id, $station_id);
        if ($pool_data) {
            $test_id  = $pool_data['prueba_id'];
            $pregunta = $pool_data['pregunta'];
            $q_index  = $pool_data['index'];
        }
    } else {
        // Por estación (comportamiento original)
        $test_id = (int) get_post_meta($station_id, 'gc_prueba_ref', true);
        if ($test_id > 0) {
            $preguntas = get_post_meta($test_id, 'gc_preguntas', true);
            if (is_array($preguntas) && !empty($preguntas[0]) && is_array($preguntas[0])) {
                $pregunta = $preguntas[0];
                $q_index  = 0;
            }
        }
    }

    if (!$test_id || !$pregunta) {
        return gc_station_wrap_message('Este ' . $label . ' no tiene una prueba configurada.', 'error');
    }

    $enunciado = isset($pregunta['enunciado']) ? $pregunta['enunciado'] : '';
    $opciones  = isset($pregunta['opciones']) && is_array($pregunta['opciones']) ? $pregunta['opciones'] : [];

    if (empty($enunciado) || empty($opciones)) {
        return gc_station_wrap_message('La prueba de este ' . $label . ' no está lista.', 'error');
    }

    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';

    ob_start();
    ?>
    <div class="gc-adult-station"
         data-station-id="<?php echo esc_attr($station_id); ?>"
         data-escenario-id="<?php echo esc_attr($escenario_id); ?>"
         data-prueba-id="<?php echo esc_attr($test_id); ?>"
         data-q-index="<?php echo esc_attr($q_index); ?>">

        <!-- CTA desafío (visible por defecto) -->
        <div id="gc-challenge-cta" style="padding:24px 20px;border:2px solid #2563eb;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#dbeafe);text-align:center;">
            <div style="font-size:40px;margin-bottom:12px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1e293b;">¿Preparado para el desafío?</h3>
            <p style="margin:0 0 18px;font-size:15px;color:#475569;line-height:1.5;">Pon a prueba tus conocimientos sobre este lugar. ¡Solo tienes una oportunidad!</p>
            <button type="button" id="gc-start-challenge"
                    style="padding:14px 32px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-size:17px;font-weight:700;cursor:pointer;transition:background 0.2s,transform 0.2s;">
                ¡Acepto el desafío!
            </button>
        </div>

        <!-- Quiz (oculto hasta pulsar) -->
        <div id="gc-quiz-panel" style="display:none;padding:20px;border:1px solid #dcdcde;border-radius:14px;background:#fff;">
            <h2 style="margin-top:0;">Pregunta del <?php echo esc_html($label); ?></h2>
            <p style="font-size:18px;line-height:1.5;"><strong><?php echo esc_html($enunciado); ?></strong></p>

            <form id="gc-adult-station-form">
                <?php foreach ($opciones as $index => $opcion):
                    $value = $index;
                    $texto = isset($opcion['texto']) ? $opcion['texto'] : '';
                    if ($texto === '') continue;
                ?>
                    <label style="display:block;margin:12px 0;padding:14px 16px;border:1px solid #dcdcde;border-radius:12px;cursor:pointer;">
                        <input type="radio" name="gc_station_answer" value="<?php echo esc_attr($value); ?>" style="margin-right:10px;">
                        <?php echo esc_html($texto); ?>
                    </label>
                <?php endforeach; ?>

                <div style="margin-top:18px;">
                    <button type="submit"
                            style="width:100%;padding:14px 18px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-size:16px;font-weight:600;cursor:pointer;">
                        Responder
                    </button>
                </div>
            </form>

            <div id="gc-adult-msg" style="margin-top:16px;"></div>
        </div>
    </div>

    <script>
    (function(){
        const wrap = document.currentScript ? document.currentScript.previousElementSibling : null;
        if (!wrap) return;

        const cta = wrap.querySelector('#gc-challenge-cta');
        const panel = wrap.querySelector('#gc-quiz-panel');
        const startBtn = wrap.querySelector('#gc-start-challenge');

        // Revelar quiz al pulsar
        if (startBtn && cta && panel) {
            startBtn.addEventListener('click', function(){
                cta.style.display = 'none';
                panel.style.display = 'block';
                panel.scrollIntoView({behavior:'smooth', block:'center'});
                startedAt = Date.now();
            });
        }

        const stationId = parseInt(wrap.dataset.stationId, 10);
        const escenarioId = parseInt(wrap.dataset.escenarioId, 10);
        const pruebaId = parseInt(wrap.dataset.pruebaId, 10);
        const qIndex = parseInt(wrap.dataset.qIndex || '0', 10);
        const form = wrap.querySelector('#gc-adult-station-form');
        const msg = wrap.querySelector('#gc-adult-msg');
        const nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js($nonce); ?>';

        if (!form || !msg || !stationId || !pruebaId) return;

        let startedAt = null;

        form.addEventListener('submit', async function(e){
            e.preventDefault();

            const checked = form.querySelector('input[name="gc_station_answer"]:checked');
            if (!checked) {
                msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Selecciona una respuesta.</div>';
                return;
            }

            const answerIndex = parseInt(checked.value, 10);
            const timeMs = Date.now() - startedAt;

            try {
                const res1 = await fetch('/wp-json/gincana/v1/quiz/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        prueba_id: pruebaId,
                        answers: [answerIndex],
                        time_ms: timeMs,
                        q_index: qIndex
                    })
                });

                const data1 = await res1.json();

                if (!data1 || !data1.ok) {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">❌ Respuesta incorrecta. Puedes volver a intentarlo.</div>';
                    return;
                }

                const res2 = await fetch('/wp-json/gincana/v1/progress/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        estacion_id: stationId,
                        time_ms: timeMs
                    })
                });

                const data2 = await res2.json();

                if (data2 && data2.ok) {
                    const pts = data2.points_awarded || 0;
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#ecfdf3;border:1px solid #b7ebc6;color:#146c2e;">✅ Respuesta correcta. Has conseguido <strong>' + pts + ' puntos</strong>.</div>';

                    setTimeout(function(){
                        window.location.href = <?php echo json_encode( get_permalink($escenario_id) ?: home_url('/') ); ?>;
                    }, 1800);
                } else {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">La respuesta era correcta, pero no se pudo registrar el progreso.</div>';
                }

            } catch (err) {
                msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Error: ' + err.message + '</div>';
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [gincana_estacion_contenido]
 *
 * Para usar en la plantilla Theme Builder del CPT "estacion".
 * Detecta la estacion actual, muestra descripcion + media + quiz/validacion.
 * No requiere QR — funciona por acceso directo desde el listado del escenario.
 */
add_shortcode('gincana_estacion_contenido', function($atts){

    // Placeholder para Divi Builder
    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
        return '<div style="padding:16px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;text-align:center;">
            <strong>Gincana — Contenido de Estacion</strong><br><small>(Vista previa del builder)</small>
        </div>';
    }

    $a = shortcode_atts(['estacion' => ''], $atts);

    // Resolver estacion
    $station_id = (int)$a['estacion'];
    if (!$station_id) {
        $station_id = (int) get_queried_object_id();
    }
    if (!$station_id) {
        $station_id = (int) get_the_ID();
    }
    if (!$station_id || get_post_type($station_id) !== 'estacion') {
        return gc_station_wrap_message('No se pudo determinar la estacion.', 'error');
    }

    $escenario_id = (int) get_post_meta($station_id, 'gc_escenario_ref', true);
    if ($escenario_id <= 0) {
        return gc_station_wrap_message('La estacion no tiene escenario enlazado.', 'error');
    }

    $tipo_escenario = get_post_meta($escenario_id, 'gc_tipo_escenario', true) ?: 'adulto';
    $title       = get_the_title($station_id);
    $descripcion = get_post_meta($station_id, 'gc_descripcion', true);
    $audio       = get_post_meta($station_id, 'gc_audio', true);
    $maps_url    = get_post_meta($station_id, 'gc_maps_url', true);
    $img1        = get_post_meta($station_id, 'gc_img_1', true);
    $img2        = get_post_meta($station_id, 'gc_img_2', true);
    $is_logged   = is_user_logged_in();

    $user_id     = get_current_user_id();

    // Helper para renderizar contenido visual (titulo + iconos + descripcion + media)
    $render_content = function() use ($title, $descripcion, $audio, $maps_url, $img1, $img2) {
        echo '<h2 class="gc-station-title" style="margin:0 0 8px;font-size:22px;font-weight:700;line-height:1.3;">' . esc_html($title) . '</h2>';
        echo gc_render_action_icons($audio, $maps_url);
        if ($descripcion) {
            echo '<div class="gc-station-desc" style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;">';
            echo wp_kses_post($descripcion);
            echo '</div>';
        }
        if ($img1 || $img2) {
            echo '<div style="display:flex;flex-direction:column;gap:12px;margin:0 0 24px;">';
            if ($img1) echo '<img src="' . esc_url($img1) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
            if ($img2) echo '<img src="' . esc_url($img2) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
            echo '</div>';
        }
    };

    // Si ya la ha superado (solo si esta logueado)
    if ($is_logged && function_exists('gincana_user_passed') && gincana_user_passed($user_id, $station_id) ) {
        $escenario_url = get_permalink($escenario_id);
        ob_start();
        echo '<div class="gc-station-content" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">';
        $render_content();
        echo '<div style="padding:20px;border:1px solid #e6f0e6;border-radius:14px;background:#f7fff7;text-align:center;">';
        $lbl = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
        echo '<p style="margin:0 0 12px;font-size:16px;">&#10003; Ya has completado este ' . esc_html($lbl) . '.</p>';
        echo '<a href="' . esc_url($escenario_url) . '" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // Render completo
    ob_start();
    echo '<div class="gc-station-content" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">';

    $render_content();

    // Si no esta logueado
    if (!$is_logged) {
        // Infantil: mostrar pista + CTA login
        if ($tipo_escenario === 'infantil') {
            echo gc_render_infantil_station_pista($station_id, $title, $escenario_id);
        }
        $login_url    = wp_login_url(get_permalink($station_id));
        $register_url = wp_registration_url();
        echo '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;text-align:center;margin-top:16px;">';
        echo '<p style="margin:0 0 6px;font-size:16px;font-weight:600;">¿Quieres participar en la gimkana?</p>';
        echo '<p style="margin:0 0 16px;font-size:14px;color:#64748b;">Inicia sesión o regístrate para jugar y acumular puntos.</p>';
        echo '<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">';
        echo '<a href="' . esc_url($login_url) . '" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Iniciar sesión</a>';
        echo '<a href="' . esc_url($register_url) . '" style="display:inline-block;padding:12px 24px;border:2px solid #2563eb;border-radius:10px;background:#fff;color:#2563eb;text-decoration:none;font-weight:600;">Registrarse</a>';
        echo '</div>';
        echo '</div>';
    } else {
        // Logueado: mostrar contenido según tipo de escenario + prueba
        $requiere_prueba = get_post_meta($escenario_id, 'gc_requiere_prueba', true);

        if ($tipo_escenario === 'infantil') {
            // Infantil: siempre mostrar pista (buscar QR)
            echo gc_render_infantil_station_pista($station_id, $title, $escenario_id);
            // Si también requiere prueba, mostrar el quiz debajo de la pista
            if ($requiere_prueba === '1') {
                echo gc_render_adulto_station($station_id, $title, $escenario_id);
            }
        } else {
            // Adulto: mostrar quiz si hay prueba
            if ($requiere_prueba === '1' || $requiere_prueba === '') {
                echo gc_render_adulto_station($station_id, $title, $escenario_id);
            }
        }
    }

    echo '</div>';
    return ob_get_clean();
});

function gc_station_wrap_message($message, $type = 'info') {
    $bg = '#eff6ff';
    $border = '#bfdbfe';
    $color = '#1d4ed8';

    if ($type === 'error') {
        $bg = '#fff2f0';
        $border = '#ffccc7';
        $color = '#a8071a';
    }

    return '<div style="max-width:760px;margin:24px auto;padding:16px 18px;border:1px solid ' . esc_attr($border) . ';background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';border-radius:12px;">' . esc_html($message) . '</div>';
}