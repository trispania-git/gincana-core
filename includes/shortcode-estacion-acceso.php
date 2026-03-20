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

    $audio = get_post_meta($station_id, 'gc_audio', true);
    $img1  = get_post_meta($station_id, 'gc_img_1', true);
    $img2  = get_post_meta($station_id, 'gc_img_2', true);
    $title = get_the_title($station_id);

    ob_start();

    $descripcion = get_post_meta($station_id, 'gc_descripcion', true);

    echo '<div class="gc-station-access" style="max-width:760px;margin:0 auto;padding:24px;">';
    echo '<h1 style="margin-bottom:8px;">' . esc_html($title) . '</h1>';

    if ($descripcion) {
        echo '<div class="gc-station-desc" style="margin:12px 0 20px;padding:16px 18px;background:#f8fafc;border-left:4px solid #2563eb;border-radius:0 12px 12px 0;font-size:15px;line-height:1.6;color:#334155;">';
        echo wp_kses_post($descripcion);
        echo '</div>';
    }

    if ($audio) {
        echo '<div style="margin:16px 0;">';
        echo '<audio controls style="width:100%;"><source src="' . esc_url($audio) . '">Tu navegador no soporta audio HTML5.</audio>';
        echo '</div>';
    }

    if ($img1 || $img2) {
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:16px 0 24px;">';
        if ($img1) {
            echo '<div><img src="' . esc_url($img1) . '" alt="" style="width:100%;height:auto;border-radius:12px;"></div>';
        }
        if ($img2) {
            echo '<div><img src="' . esc_url($img2) . '" alt="" style="width:100%;height:auto;border-radius:12px;"></div>';
        }
        echo '</div>';
    }

    if ($tipo_escenario === 'infantil') {
        echo gc_render_infantil_station($station_id, $title, $escenario_id);
    } else {
        echo gc_render_adulto_station($station_id, $title, $escenario_id);
    }

    echo '</div>';

    return ob_get_clean();
}

function gc_render_infantil_station($station_id, $title, $escenario_id) {
    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';
    ob_start();
    ?>
    <div class="gc-kids-station"
         data-station-id="<?php echo esc_attr($station_id); ?>"
         data-escenario-id="<?php echo esc_attr($escenario_id); ?>"
         data-started-at="<?php echo esc_attr( round(microtime(true) * 1000) ); ?>">

        <div style="padding:20px;border:1px solid #dcdcde;border-radius:14px;background:#fff;">
            <h2 style="margin-top:0;">¡Puerta encontrada!</h2>
            <p>Has encontrado la estación <strong><?php echo esc_html($title); ?></strong>.</p>
            <p>Pulsa el botón para validarla y continuar.</p>

            <div style="margin-top:18px;">
                <button type="button"
                        id="gc-kids-complete-btn"
                        style="padding:12px 18px;border:0;border-radius:10px;background:#111827;color:#fff;cursor:pointer;">
                    Validar estación
                </button>
            </div>

            <div id="gc-kids-msg" style="margin-top:16px;"></div>
        </div>
    </div>

    <script>
    (function(){
        const wrap = document.currentScript ? document.currentScript.previousElementSibling : null;
        if (!wrap) return;

        const stationId = parseInt(wrap.dataset.stationId, 10);
        const escenarioId = parseInt(wrap.dataset.escenarioId, 10);
        const startedAt = parseInt(wrap.dataset.startedAt, 10) || Date.now();
        const btn = wrap.querySelector('#gc-kids-complete-btn');
        const msg = wrap.querySelector('#gc-kids-msg');
        const nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js($nonce); ?>';

        if (!btn || !msg || !stationId) return;

        btn.addEventListener('click', async function(){
            btn.disabled = true;
            msg.innerHTML = 'Validando estación...';

            const timeMs = Math.max(0, Date.now() - startedAt);

            try {
                const res = await fetch('/wp-json/gincana/v1/progress/skip', {
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

                const data = await res.json();

                if (data && data.ok) {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#ecfdf3;border:1px solid #b7ebc6;color:#146c2e;">✅ Estación validada correctamente.</div>';

                    setTimeout(function(){
                        window.location.href = <?php echo json_encode( get_permalink($escenario_id) ?: home_url('/') ); ?>;
                    }, 1600);
                } else {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">No se pudo validar la estación.</div>';
                    btn.disabled = false;
                }

            } catch (err) {
                msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Error: ' + err.message + '</div>';
                btn.disabled = false;
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function gc_render_adulto_station($station_id, $title, $escenario_id) {
    $test_id = (int) get_post_meta($station_id, 'gc_prueba_ref', true);

    if ($test_id <= 0) {
        return gc_station_wrap_message('Esta estación no tiene una prueba enlazada.', 'error');
    }

    $preguntas = get_post_meta($test_id, 'gc_preguntas', true);

    if ( ! is_array($preguntas) || empty($preguntas[0]) || ! is_array($preguntas[0]) ) {
        return gc_station_wrap_message('La prueba no tiene preguntas configuradas.', 'error');
    }

    $pregunta  = $preguntas[0];
    $enunciado = isset($pregunta['enunciado']) ? $pregunta['enunciado'] : '';
    $opciones  = isset($pregunta['opciones']) && is_array($pregunta['opciones']) ? $pregunta['opciones'] : [];

    if (empty($enunciado) || empty($opciones)) {
        return gc_station_wrap_message('La prueba de esta estación no está lista para tipo test.', 'error');
    }

    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';

    ob_start();
    ?>
    <div class="gc-adult-station"
         data-station-id="<?php echo esc_attr($station_id); ?>"
         data-escenario-id="<?php echo esc_attr($escenario_id); ?>"
         data-prueba-id="<?php echo esc_attr($test_id); ?>">

        <div style="padding:20px;border:1px solid #dcdcde;border-radius:14px;background:#fff;">
            <h2 style="margin-top:0;">Pregunta de la estación</h2>
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
                            style="padding:12px 18px;border:0;border-radius:10px;background:#111827;color:#fff;cursor:pointer;">
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

        const stationId = parseInt(wrap.dataset.stationId, 10);
        const escenarioId = parseInt(wrap.dataset.escenarioId, 10);
        const pruebaId = parseInt(wrap.dataset.pruebaId, 10);
        const form = wrap.querySelector('#gc-adult-station-form');
        const msg = wrap.querySelector('#gc-adult-msg');
        const nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js($nonce); ?>';

        if (!form || !msg || !stationId || !pruebaId) return;

        const startedAt = Date.now();

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
                        time_ms: timeMs
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

    // Debe estar logueado
    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink($station_id));
        return '<div style="max-width:760px;margin:24px auto;padding:20px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;text-align:center;">
            <p style="margin:0 0 12px;font-size:16px;">Debes iniciar sesion para participar en la gimkana.</p>
            <a href="' . esc_url($login_url) . '" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Iniciar sesion</a>
        </div>';
    }

    $escenario_id = (int) get_post_meta($station_id, 'gc_escenario_ref', true);
    if ($escenario_id <= 0) {
        return gc_station_wrap_message('La estacion no tiene escenario enlazado.', 'error');
    }

    $tipo_escenario = get_post_meta($escenario_id, 'gc_tipo_escenario', true) ?: 'adulto';
    $title       = get_the_title($station_id);
    $descripcion = get_post_meta($station_id, 'gc_descripcion', true);
    $audio       = get_post_meta($station_id, 'gc_audio', true);
    $img1        = get_post_meta($station_id, 'gc_img_1', true);
    $img2        = get_post_meta($station_id, 'gc_img_2', true);

    // Comprobar si ya la ha superado
    $user_id = get_current_user_id();
    if ( function_exists('gincana_user_passed') && gincana_user_passed($user_id, $station_id) ) {
        $escenario_url = get_permalink($escenario_id);
        ob_start();
        echo '<div class="gc-station-content" style="max-width:760px;margin:0 auto;padding:24px;">';

        if ($descripcion) {
            echo '<div class="gc-station-desc" style="margin:0 0 20px;padding:16px 18px;background:#f8fafc;border-left:4px solid #16a34a;border-radius:0 12px 12px 0;font-size:15px;line-height:1.6;color:#334155;">';
            echo wp_kses_post($descripcion);
            echo '</div>';
        }

        echo '<div style="padding:20px;border:1px solid #e6f0e6;border-radius:14px;background:#f7fff7;text-align:center;">';
        echo '<p style="margin:0 0 12px;font-size:16px;">&#10003; Ya has completado esta estacion.</p>';
        echo '<a href="' . esc_url($escenario_url) . '" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // Render completo
    ob_start();
    echo '<div class="gc-station-content" style="max-width:760px;margin:0 auto;padding:24px;">';

    if ($descripcion) {
        echo '<div class="gc-station-desc" style="margin:0 0 20px;padding:16px 18px;background:#f8fafc;border-left:4px solid #2563eb;border-radius:0 12px 12px 0;font-size:15px;line-height:1.6;color:#334155;">';
        echo wp_kses_post($descripcion);
        echo '</div>';
    }

    if ($audio) {
        echo '<div style="margin:0 0 16px;">';
        echo '<audio controls style="width:100%;"><source src="' . esc_url($audio) . '">Tu navegador no soporta audio HTML5.</audio>';
        echo '</div>';
    }

    if ($img1 || $img2) {
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:0 0 24px;">';
        if ($img1) echo '<div><img src="' . esc_url($img1) . '" alt="" style="width:100%;height:auto;border-radius:12px;"></div>';
        if ($img2) echo '<div><img src="' . esc_url($img2) . '" alt="" style="width:100%;height:auto;border-radius:12px;"></div>';
        echo '</div>';
    }

    if ($tipo_escenario === 'infantil') {
        echo gc_render_infantil_station($station_id, $title, $escenario_id);
    } else {
        echo gc_render_adulto_station($station_id, $title, $escenario_id);
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