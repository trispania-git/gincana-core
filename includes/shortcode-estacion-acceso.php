<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcode: [gincana_estacion_acceso]
 */

add_shortcode('gincana_estacion_acceso', 'gc_shortcode_estacion_acceso');

function gc_shortcode_estacion_acceso() {
    // Evitar ejecución duplicada si Divi renderiza el shortcode más de una vez
    static $already_rendered = false;
    if ($already_rendered) return '';
    $already_rendered = true;

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

    // Estación deshabilitada temporalmente
    if ( get_post_meta($station_id, 'gc_deshabilitada', true) === '1' ) {
        return gc_station_wrap_message('Esta estación está temporalmente deshabilitada. Por favor, vuelve más tarde.', 'warning');
    }

    $escenario_id = (int) get_post_meta($station_id, 'gc_escenario_ref', true);
    if ($escenario_id <= 0) {
        return gc_station_wrap_message('La estación no tiene escenario enlazado.', 'error');
    }

    $tipo_escenario = get_post_meta($escenario_id, 'gc_tipo_escenario', true);
    if (empty($tipo_escenario)) {
        $tipo_escenario = 'adulto';
    }

    $audio     = get_post_meta($station_id, 'gc_audio', true);
    $maps_url  = get_post_meta($station_id, 'gc_maps_url', true);
    $direccion = get_post_meta($station_id, 'gc_direccion', true);
    $img1      = get_post_meta($station_id, 'gc_img_1', true);
    $img2      = get_post_meta($station_id, 'gc_img_2', true);
    $img3      = get_post_meta($station_id, 'gc_img_3', true);
    $title     = get_the_title($station_id);
    $orden     = (int) get_post_meta($station_id, 'gc_orden', true);
    $label     = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $esc_title = get_the_title($escenario_id);
    $is_logged = is_user_logged_in();

    ob_start();

    $descripcion = get_post_meta($station_id, 'gc_descripcion', true);
    $bg_inline = function_exists('gc_bg_featured_inline') ? gc_bg_featured_inline($escenario_id) : '';

    // Ocultar el título de la página WordPress ("acceso-estacion") y cambiar título del navegador
    echo '<style>'
        . '.entry-title, .et_pb_title_container h1, .page .entry-title, h1.entry-title { display:none !important; }'
        . '.gc-station-access { margin-top: -10px; }'
        . '</style>';
    echo '<script>document.title = ' . json_encode(esc_html($title) . ' — ' . esc_html($esc_title)) . ';</script>';

    // Header de navegación (mismo que [gincana_header] pero con escenario correcto)
    echo do_shortcode('[gincana_header escenario="' . (int) $escenario_id . '"]');
    echo '<hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 12px;">';

    if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id);
    echo '<div class="gc-station-access gc-tema-esc-' . (int) $escenario_id . '" style="width:100%;max-width:100%;padding:16px 0;box-sizing:border-box;">';

    // Cabecera: escenario + nº estación + nombre
    echo '<h3 style="margin:0 0 6px;font-size:18px;font-weight:600;color:#2563eb;line-height:1.3;">' . esc_html($esc_title) . '</h3>';
    echo '<h2 style="margin:0 0 8px;font-size:22px;font-weight:700;line-height:1.3;">';
    if ($orden) echo '<span style="color:#64748b;">' . $orden . '.</span> ';
    echo esc_html($title) . '</h2>';

    echo gc_render_action_icons($audio, $maps_url, $direccion);

    if ($descripcion) {
        echo '<div class="gc-station-desc" style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;padding:16px;border-radius:12px;' . $bg_inline . '">';
        echo wp_kses_post(wpautop($descripcion));
        echo '</div>';
    }

    echo '<!-- gincana_estacion_acceso v' . (defined('GINCANA_CORE_VERSION') ? GINCANA_CORE_VERSION : '?') . ' img1=' . ($img1 ? 'set' : 'empty') . ' img2=' . ($img2 ? 'set' : 'empty') . ' img3=' . ($img3 ? 'set' : 'empty') . ' -->';
    if ($img1 || $img2 || $img3) {
        echo '<div style="display:flex;flex-direction:column;gap:12px;margin:0 0 24px;">';
        if ($img1) echo '<img src="' . esc_url($img1) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
        if ($img2) echo '<img src="' . esc_url($img2) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
        if ($img3) echo '<img src="' . esc_url($img3) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
        echo '</div>';
    }

    if ($tipo_escenario === 'infantil') {
        if (!$is_logged) {
            echo gc_render_infantil_station_qr_no_login($station_id, $title, $escenario_id);
        } else {
            echo gc_render_infantil_station_qr($station_id, $title, $escenario_id);
        }
    } else {
        // Adulto: acceso via QR — depende del tipo de QR
        $tipo_qr = get_post_meta($escenario_id, 'gc_tipo_qr', true) ?: 'enlace';

        if ($tipo_qr === 'validacion_quiz' || $tipo_qr === 'solo_pregunta') {
            // Valida mediante pregunta (con QR o sin él)
            echo gc_render_adulto_station($station_id, $title, $escenario_id);
        } elseif ($tipo_qr === 'validacion_boton_quiz') {
            // QR confirma presencia + pregunta. Reutilizamos gc_render_adulto_station
            // con un CTA inicial 'Has llegado a X' antes de la pregunta.
            echo gc_render_adulto_station($station_id, $title, $escenario_id, [
                'titulo'              => '¡Has llegado a ' . $title . '!',
                'subtitulo'           => 'Ahora demuestra tus conocimientos sobre este lugar para validar el ' . $label . '.',
                'boton'               => 'Continuar al desafío 🎯',
                'usar_img_encontrada' => true,
            ]);
        } elseif ($tipo_qr === 'validacion_gps') {
            // GPS verificado (token URL = ya pasó la verificación): quiz si hay prueba, si no botón
            if (gc_requiere_prueba($escenario_id)) {
                echo gc_render_adulto_station($station_id, $title, $escenario_id);
            } else {
                echo gc_render_adulto_station_sin_prueba($station_id, $title, $escenario_id);
            }
        } elseif ($tipo_qr === 'validacion_boton' || $tipo_qr === 'validacion') {
            // QR valida con botón (presencia)
            echo gc_render_adulto_station_sin_prueba($station_id, $title, $escenario_id);
        } else {
            // QR = enlace: depende de si requiere prueba
            if (gc_requiere_prueba($escenario_id)) {
                echo gc_render_adulto_station($station_id, $title, $escenario_id);
            } else {
                echo gc_render_adulto_station_sin_prueba($station_id, $title, $escenario_id);
            }
        }
    }

    // Logos del pie ANTES de cerrar el wrap y antes de la barra fija inferior:
    // así quedan dentro del flujo scroll, encima del espaciador, no debajo de
    // la barra fija del itinerario.
    if (function_exists('gc_render_footer_logos')) echo gc_render_footer_logos($escenario_id);

    echo '</div>';

    // Itinerario de estaciones (círculos) fijo al pie
    echo '<div style="position:fixed;bottom:0;left:0;right:0;z-index:999;background:#fff;border-top:1px solid #e2e8f0;padding:6px 8px;box-shadow:0 -2px 8px rgba(0,0,0,0.08);">';
    echo do_shortcode('[gincana_itinerario escenario="' . (int) $escenario_id . '" estacion="' . (int) $station_id . '"]');
    echo '</div>';

    // Espaciador para que el último contenido (logos incluidos) no quede tapado
    // por la barra fija inferior.
    echo '<div style="height:60px;"></div>';

    return ob_get_clean();
}

/**
 * Adulto — QR escaneado, escenario SIN prueba: completar directamente.
 */
function gc_render_adulto_station_sin_prueba($station_id, $title, $escenario_id) {
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $label_uc = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    $escenario_url = get_permalink($escenario_id) ?: home_url('/');
    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';

    $user_id = get_current_user_id();

    // Si no está logueado
    if (!$user_id) {
        ob_start();
        ?>
        <div style="padding:24px 20px;border-radius:14px;background:#eff6ff;border:2px solid #2563eb;text-align:center;">
            <div style="margin-bottom:8px;"><?php echo gc_get_img_encontrada($escenario_id); ?></div>
            <h2 style="margin:0 0 8px;color:#1e40af;">¡<?php echo esc_html($label_uc); ?> encontrada!</h2>
            <p style="margin:0 0 16px;font-size:15px;color:#334155;">
                Has llegado a <strong><?php echo esc_html($title); ?></strong>. Identifícate para validarla.
            </p>
        </div>
        <?php echo gc_render_login_o_guest($escenario_id, 'Empieza a jugar', 'Escribe tu nombre para validar esta ' . esc_html($label) . '.'); ?>
        <?php
        return ob_get_clean();
    }

    // Si ya la ha completado
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

    // Botón para completar directamente
    ob_start();
    ?>
    <div class="gc-adulto-sin-prueba"
         data-station-id="<?php echo esc_attr($station_id); ?>"
         data-escenario-id="<?php echo esc_attr($escenario_id); ?>"
         style="padding:24px 20px;border-radius:14px;background:#eff6ff;border:2px solid #2563eb;text-align:center;">
        <div style="margin-bottom:8px;"><?php echo gc_get_img_encontrada($escenario_id); ?></div>
        <h2 style="margin:0 0 8px;color:#1e40af;">¡Has llegado a <?php echo esc_html($title); ?>!</h2>
        <p style="margin:0 0 16px;font-size:15px;color:#334155;">
            Pulsa el botón para validar esta <?php echo esc_html($label); ?>.
        </p>
        <button type="button" id="gc-adulto-complete-btn"
                style="width:100%;max-width:320px;padding:16px 24px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-size:17px;font-weight:700;cursor:pointer;transition:transform 0.1s;">
            ✓ Validar <?php echo esc_html($label); ?>
        </button>
        <div id="gc-adulto-msg" style="margin-top:16px;"></div>
    </div>

    <script>
    (function(){
        const wrap = document.querySelector('.gc-adulto-sin-prueba');
        if (!wrap) return;
        const stationId = parseInt(wrap.dataset.stationId, 10);
        const btn = wrap.querySelector('#gc-adulto-complete-btn');
        const msg = wrap.querySelector('#gc-adulto-msg');
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
                    msg.innerHTML = '<div style="padding:16px;border-radius:12px;background:#dcfce7;border:1px solid #16a34a;color:#146c2e;font-size:16px;font-weight:600;">✅ ¡<?php echo esc_html($label_uc); ?> validada!</div>'
                        + '<a href="<?php echo esc_url($escenario_url); ?>" style="display:inline-block;margin-top:14px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>';
                } else {
                    msg.innerHTML = '<div style="padding:14px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">No se pudo validar. Inténtalo de nuevo.</div>';
                    btn.disabled = false;
                    btn.textContent = '✓ Validar <?php echo esc_js($label); ?>';
                }
            } catch (err) {
                msg.innerHTML = '<div style="padding:14px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.textContent = '✓ Validar <?php echo esc_js($label); ?>';
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Infantil — QR escaneado + usuario NO logueado: pedir login.
 */
function gc_render_infantil_station_qr_no_login($station_id, $title, $escenario_id) {
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $label_uc = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#ecfdf3;border:2px solid #16a34a;text-align:center;">
        <div style="margin-bottom:8px;"><?php echo gc_get_img_encontrada($escenario_id); ?></div>
        <h2 style="margin:0 0 8px;color:#146c2e;">¡<?php echo esc_html($label_uc); ?> encontrada!</h2>
        <p style="margin:0 0 16px;font-size:15px;color:#334155;">
            Has encontrado <strong><?php echo esc_html($title); ?></strong>. Identifícate para validarla y acumular puntos.
        </p>
    </div>
    <?php echo gc_render_login_o_guest($escenario_id, 'Empieza a jugar', 'Escribe tu nombre y suma tu primer punto.'); ?>
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
    $con_prueba = gc_requiere_prueba($escenario_id);

    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#ecfdf3;border:2px solid #16a34a;text-align:center;margin-bottom:<?php echo $con_prueba ? '16' : '0'; ?>px;">
        <div style="margin-bottom:8px;"><?php echo gc_get_img_encontrada($escenario_id); ?></div>
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

    <?php
    return ob_get_clean();
}

/**
 * Infantil — acceso directo (sin QR): muestra pista para encontrar el QR.
 */
function gc_render_infantil_station_pista($station_id, $title, $escenario_id) {
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $pistas_activas = get_post_meta($escenario_id, 'gc_pistas_activas', true) === '1';
    $pista   = $pistas_activas ? get_post_meta($station_id, 'gc_pista_busqueda', true) : '';
    $pista_2 = $pistas_activas ? get_post_meta($station_id, 'gc_pista_busqueda_2', true) : '';
    $img_busca_qr = get_post_meta($escenario_id, 'gc_img_busca_qr', true);

    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#fffbeb;border:2px solid #f59e0b;text-align:center;">
        <?php if ($img_busca_qr): ?>
        <div style="margin-bottom:12px;">
            <img src="<?php echo esc_url($img_busca_qr); ?>" alt="Busca el código QR" style="max-width:100%;height:auto;border-radius:12px;" />
        </div>
        <?php else: ?>
        <div style="font-size:48px;margin-bottom:8px;">🔍</div>
        <?php endif; ?>
        <h3 style="margin:0 0 8px;color:#92400e;">¡Busca el código QR!</h3>
        <p style="margin:0 0 12px;font-size:15px;color:#78350f;">
            Para validar esta <?php echo esc_html($label); ?>, necesitas encontrar y escanear el código QR en el lugar.
        </p>

        <!-- Botón escanear QR con cámara -->
        <div style="margin:16px 0;">
          <button type="button" id="gc-qr-scan-btn-est" style="display:inline-flex;align-items:center;gap:10px;padding:14px 28px;border:0;border-radius:14px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:17px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(245,158,11,0.35);transition:transform 0.2s,box-shadow 0.2s;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
            </svg>
            Escanear QR
          </button>
        </div>

        <!-- Modal escáner QR estación -->
        <div id="gc-qr-modal-est" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.85);flex-direction:column;align-items:center;justify-content:center;">
          <div style="position:relative;width:100%;max-width:480px;padding:16px;">
            <div style="text-align:center;margin-bottom:12px;">
              <span style="color:#fff;font-size:18px;font-weight:700;">Escanea el codigo QR</span>
            </div>
            <div style="position:relative;border-radius:16px;overflow:hidden;background:#000;aspect-ratio:1/1;">
              <video id="gc-qr-video-est" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
              <div style="position:absolute;inset:15%;border:3px solid rgba(255,255,255,0.7);border-radius:12px;pointer-events:none;"></div>
            </div>
            <canvas id="gc-qr-canvas-est" style="display:none;"></canvas>
            <div id="gc-qr-status-est" style="text-align:center;margin-top:12px;color:#94a3b8;font-size:14px;">Apunta la camara al codigo QR...</div>
            <button type="button" id="gc-qr-close-est" style="display:block;margin:16px auto 0;padding:12px 32px;border:2px solid rgba(255,255,255,0.4);border-radius:12px;background:transparent;color:#fff;font-size:16px;font-weight:600;cursor:pointer;">
              Cerrar
            </button>
          </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
        <script>
        (function(){
          var btn   = document.getElementById('gc-qr-scan-btn-est');
          var modal = document.getElementById('gc-qr-modal-est');
          var video = document.getElementById('gc-qr-video-est');
          var canvas = document.getElementById('gc-qr-canvas-est');
          var status = document.getElementById('gc-qr-status-est');
          var closeBtn = document.getElementById('gc-qr-close-est');
          var ctx = canvas.getContext('2d', {willReadFrequently: true});
          var stream = null;
          var scanning = false;
          var scanInterval = null;
          var siteUrl = <?php echo wp_json_encode(home_url('/')); ?>;

          function stopCamera() {
            scanning = false;
            if (scanInterval) { clearInterval(scanInterval); scanInterval = null; }
            if (stream) {
              stream.getTracks().forEach(function(t){ t.stop(); });
              stream = null;
            }
            video.srcObject = null;
            modal.style.display = 'none';
          }

          function handleQR(url) {
            if (url.indexOf(siteUrl) === 0 || url.indexOf(siteUrl.replace('https://','http://')) === 0) {
              stopCamera();
              status.style.color = '#4ade80';
              status.textContent = '¡QR detectado! Redirigiendo...';
              modal.style.display = 'flex';
              setTimeout(function(){ window.location.href = url; }, 600);
            }
          }

          function scanFrame() {
            if (!scanning || video.readyState !== video.HAVE_ENOUGH_DATA) return;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            if (window._gcUseBarcodeDetector && window._gcBarcodeDetector) {
              window._gcBarcodeDetector.detect(canvas).then(function(barcodes){
                if (barcodes.length > 0) handleQR(barcodes[0].rawValue);
              }).catch(function(){});
              return;
            }
            if (typeof jsQR === 'function') {
              var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              var code = jsQR(imageData.data, canvas.width, canvas.height, { inversionAttempts: 'dontInvert' });
              if (code && code.data) handleQR(code.data);
            }
          }

          if ('BarcodeDetector' in window) {
            BarcodeDetector.getSupportedFormats().then(function(formats) {
              if (formats.indexOf('qr_code') !== -1) {
                window._gcUseBarcodeDetector = true;
                window._gcBarcodeDetector = new BarcodeDetector({formats: ['qr_code']});
              }
            }).catch(function(){});
          }

          btn.addEventListener('click', function(){
            modal.style.display = 'flex';
            status.style.color = '#94a3b8';
            status.textContent = 'Iniciando camara...';
            navigator.mediaDevices.getUserMedia({
              video: { facingMode: 'environment', width: { ideal: 720 }, height: { ideal: 720 } }
            }).then(function(s){
              stream = s;
              video.srcObject = s;
              video.play();
              scanning = true;
              status.textContent = 'Apunta la camara al codigo QR...';
              scanInterval = setInterval(scanFrame, 250);
            }).catch(function(err){
              status.style.color = '#f87171';
              if (err.name === 'NotAllowedError') {
                status.textContent = 'Permiso de camara denegado. Activa el permiso e intentalo de nuevo.';
              } else {
                status.textContent = 'No se pudo acceder a la camara: ' + err.message;
              }
            });
          });

          closeBtn.addEventListener('click', stopCamera);
          document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && scanning) stopCamera();
          });
        })();
        </script>

        <?php if ($pista || $pista_2): ?>
        <div style="margin:16px 0 0;">
            <button type="button" id="gc-pistas-toggle-btn" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border:2px solid #f59e0b;border-radius:12px;background:#fff;color:#92400e;font-size:15px;font-weight:700;cursor:pointer;transition:background 0.2s;">
                💡 Ver pista
            </button>
            <div id="gc-pistas-container" style="display:none;margin-top:12px;flex-direction:column;gap:10px;">
                <?php if ($pista): ?>
                <div class="gc-pista-item" data-idx="1" style="display:none;padding:14px 16px;border-radius:10px;background:#fff;border:1px dashed #f59e0b;text-align:left;">
                    <p style="margin:0;font-size:13px;color:#92400e;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">💡 Pista 1</p>
                    <p style="margin:6px 0 0;font-size:15px;color:#451a03;"><?php echo esc_html($pista); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($pista_2): ?>
                <div class="gc-pista-item" data-idx="2" style="display:none;padding:14px 16px;border-radius:10px;background:#fff;border:1px dashed #f59e0b;text-align:left;">
                    <p style="margin:0;font-size:13px;color:#92400e;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">💡 Pista 2</p>
                    <p style="margin:6px 0 0;font-size:15px;color:#451a03;"><?php echo esc_html($pista_2); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <script>
            (function(){
                var btn = document.getElementById('gc-pistas-toggle-btn');
                var box = document.getElementById('gc-pistas-container');
                if (!btn || !box) return;
                var items = box.querySelectorAll('.gc-pista-item');
                var total = items.length;
                var shown = 0; // nº de pistas visibles actualmente

                function render() {
                    for (var i = 0; i < items.length; i++) {
                        items[i].style.display = (i < shown) ? 'block' : 'none';
                    }
                    box.style.display = shown > 0 ? 'flex' : 'none';
                    if (shown === 0) {
                        btn.innerHTML = total > 1 ? '💡 Ver pista 1' : '💡 Ver pista';
                    } else if (shown < total) {
                        btn.innerHTML = '💡 Ver pista ' + (shown + 1);
                    } else {
                        btn.innerHTML = 'Ocultar pistas';
                    }
                }

                btn.addEventListener('click', function(){
                    if (shown < total) {
                        shown++;
                    } else {
                        shown = 0;
                    }
                    render();
                });

                render();
            })();
            </script>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render GPS: verificación por geolocalización.
 * Se muestra en acceso directo (sin QR) cuando tipo_qr = validacion_gps.
 */
function gc_render_station_gps($station_id, $title, $escenario_id) {
    $label = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
    $label_uc = mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    $geo_radio = (int) get_post_meta($escenario_id, 'gc_geo_radio', true);
    $st_lat    = get_post_meta($station_id, 'gc_latitud', true);
    $st_lng    = get_post_meta($station_id, 'gc_longitud', true);
    $escenario_url = get_permalink($escenario_id) ?: home_url('/');
    $user_id = get_current_user_id();

    // Si no está logueado
    if (!$user_id) {
        ob_start();
        ?>
        <div style="padding:24px 20px;border-radius:14px;background:#eff6ff;border:2px solid #2563eb;text-align:center;">
            <div style="font-size:48px;margin-bottom:8px;">📍</div>
            <h2 style="margin:0 0 8px;color:#1e40af;">¡Verifica tu ubicación!</h2>
            <p style="margin:0 0 16px;font-size:15px;color:#334155;">
                Identifícate para verificar que estás en <strong><?php echo esc_html($title); ?></strong>.
            </p>
        </div>
        <?php echo gc_render_login_o_guest($escenario_id, 'Empieza a jugar', 'Escribe tu nombre y empieza la aventura.'); ?>
        <?php
        return ob_get_clean();
    }

    // Si ya la ha completado
    if (function_exists('gincana_user_passed') && gincana_user_passed($user_id, $station_id)) {
        $has_quiz = gc_requiere_prueba($escenario_id);
        ob_start();
        ?>
        <div style="padding:24px 20px;border-radius:14px;background:#f7fff7;border:2px solid #16a34a;text-align:center;">
            <p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Ubicación verificada</p>
            <?php if ($has_quiz): ?>
            <p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Desafío completado</p>
            <?php endif; ?>
            <a href="<?php echo esc_url($escenario_url); ?>" style="display:inline-block;margin-top:16px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>
        </div>
        <?php
        return ob_get_clean();
    }

    // Sin coordenadas configuradas
    if (!$st_lat || !$st_lng || $geo_radio <= 0) {
        ob_start();
        ?>
        <div style="padding:20px;border-radius:14px;background:#fef2f2;border:1px solid #fecaca;text-align:center;">
            <p style="margin:0;color:#991b1b;">Esta <?php echo esc_html($label); ?> no tiene coordenadas GPS configuradas.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    // Verificación GPS
    ob_start();
    ?>
    <div style="padding:24px 20px;border-radius:14px;background:#fef2f2;border:2px solid #dc2626;text-align:center;">
        <div style="font-size:48px;margin-bottom:8px;">📍</div>
        <h3 style="margin:0 0 8px;color:#991b1b;">Verifica tu ubicacion</h3>
        <p style="margin:0 0 16px;font-size:15px;color:#7f1d1d;">
            Comprueba que estas cerca de <strong><?php echo esc_html($title); ?></strong> para validar esta <?php echo esc_html($label); ?>.
        </p>
        <button type="button" id="gc-geo-verify-btn" style="display:inline-flex;align-items:center;gap:10px;padding:14px 28px;border:0;border-radius:14px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-size:17px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(220,38,38,0.35);transition:transform 0.2s;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Verificar ubicacion
        </button>
        <div id="gc-geo-msg" style="margin-top:14px;font-size:15px;"></div>
    </div>
    <script>
    (function(){
      var btn = document.getElementById('gc-geo-verify-btn');
      var msg = document.getElementById('gc-geo-msg');
      var stLat = <?php echo (float) $st_lat; ?>;
      var stLng = <?php echo (float) $st_lng; ?>;
      var maxDist = <?php echo (int) $geo_radio; ?>;
      var tokenUrl = <?php echo wp_json_encode(gc_get_station_entry_url($station_id)); ?>;
      var requiereQuiz = <?php echo gc_requiere_prueba($escenario_id) ? 'true' : 'false'; ?>;
      var stationId = <?php echo (int) $station_id; ?>;
      var escenarioUrl = <?php echo wp_json_encode($escenario_url); ?>;
      var imgAciertoHtml = <?php echo wp_json_encode( get_post_meta($escenario_id, 'gc_img_acierto', true) ? '<img src="' . esc_url(get_post_meta($escenario_id, 'gc_img_acierto', true)) . '" alt="" style="max-width:100%;height:auto;border-radius:12px;margin-bottom:12px;" />' : '' ); ?>;
      var nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';

      function haversine(lat1, lon1, lat2, lon2) {
        var R = 6371000;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2)
              + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
              * Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      }

      function autoComplete(distRound) {
        // Sin quiz: la verificación GPS ES la validación. Marcamos passed
        // automáticamente y mostramos el éxito sin pasos adicionales.
        fetch('/wp-json/gincana/v1/progress/skip', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
          credentials: 'same-origin',
          body: JSON.stringify({ estacion_id: stationId, time_ms: 0 })
        }).then(function(r){ return r.json(); }).then(function(data){
          var wrap = btn.closest('div');
          if (data && data.ok && wrap) {
            wrap.style.background = '#f7fff7';
            wrap.style.borderColor = '#16a34a';
            wrap.innerHTML = imgAciertoHtml
              + '<p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Ubicación verificada</p>'
              + '<p style="margin:0 0 8px;font-size:15px;color:#166534;">Estabas a <strong>' + distRound + 'm</strong>. ¡' + <?php echo wp_json_encode($label_uc); ?> + ' validada!</p>'
              + '<a href="' + escenarioUrl + '" style="display:inline-block;margin-top:12px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>';
          } else {
            msg.style.color = '#dc2626';
            msg.textContent = 'No se pudo validar. Inténtalo de nuevo.';
            btn.disabled = false;
          }
        }).catch(function(err){
          msg.style.color = '#dc2626';
          msg.textContent = 'Error al validar: ' + err.message;
          btn.disabled = false;
        });
      }

      btn.addEventListener('click', function(){
        btn.disabled = true;
        msg.style.color = '#64748b';
        msg.textContent = 'Obteniendo ubicacion...';

        if (!navigator.geolocation) {
          msg.style.color = '#dc2626';
          msg.textContent = 'Tu navegador no soporta geolocalizacion.';
          btn.disabled = false;
          return;
        }

        navigator.geolocation.getCurrentPosition(function(pos) {
          var dist = haversine(pos.coords.latitude, pos.coords.longitude, stLat, stLng);
          var distRound = Math.round(dist);

          if (dist <= maxDist) {
            msg.style.color = '#16a34a';
            if (requiereQuiz) {
              msg.innerHTML = '<strong>✅ ¡Ubicacion verificada!</strong> Estas a ' + distRound + 'm. Continuando al desafío...';
              // Redirigir a la URL con token: allí se mostrará el quiz
              setTimeout(function(){ window.location.href = tokenUrl; }, 800);
            } else {
              msg.innerHTML = '<strong>✅ ¡Ubicacion verificada!</strong> Estas a ' + distRound + 'm. Validando...';
              autoComplete(distRound);
            }
          } else {
            msg.style.color = '#dc2626';
            msg.innerHTML = '❌ Estas a <strong>' + distRound + 'm</strong>. Necesitas estar a menos de ' + maxDist + 'm.';
            btn.disabled = false;
          }
        }, function(err) {
          msg.style.color = '#dc2626';
          if (err.code === 1) {
            msg.textContent = 'Permiso de ubicacion denegado. Activalo en los ajustes del navegador.';
          } else if (err.code === 2) {
            msg.textContent = 'No se pudo determinar tu ubicacion. Intentalo al aire libre.';
          } else {
            msg.textContent = 'Error al obtener ubicacion. Intentalo de nuevo.';
          }
          btn.disabled = false;
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function gc_render_adulto_station($station_id, $title, $escenario_id, $intro_opts = null) {
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
        // Por estación: elegir pregunta aleatoria de la prueba vinculada.
        // Fuente de verdad: meta 'gc_estacion_ref' en la prueba apuntando a la estación.
        // Compatibilidad: se mantiene el meta legacy 'gc_prueba_ref' en la estación si existiera.
        $legacy_ref = (int) get_post_meta($station_id, 'gc_prueba_ref', true);
        if ($legacy_ref > 0) {
            $test_id = $legacy_ref;
        } else {
            $pq = get_posts([
                'post_type'      => 'prueba',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [['key' => 'gc_estacion_ref', 'value' => (int) $station_id, 'compare' => '=']],
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            $test_id = !empty($pq) ? (int) $pq[0] : 0;
        }
        if ($test_id > 0) {
            $preguntas = get_post_meta($test_id, 'gc_preguntas', true);
            if (is_array($preguntas) && !empty($preguntas)) {
                // Una pregunta es válida si tiene enunciado, respuesta de texto
                // (texto/anagrama/cifrado), o al menos una opción con texto/imagen.
                $valid = [];
                foreach ($preguntas as $i => $p) {
                    if (!is_array($p)) continue;
                    if (!empty($p['enunciado'])) { $valid[$i] = $p; continue; }
                    if (!empty($p['respuesta_texto_correcta'])) { $valid[$i] = $p; continue; }
                    if (!empty($p['opciones']) && is_array($p['opciones'])) {
                        foreach ($p['opciones'] as $opt) {
                            if (is_array($opt) && (!empty($opt['texto']) || !empty($opt['imagen']))) {
                                $valid[$i] = $p; break;
                            }
                        }
                    }
                }
                if (!empty($valid)) {
                    $keys = array_keys($valid);
                    shuffle($keys);
                    $q_index  = $keys[0];
                    $pregunta = $valid[$q_index];
                }
            }
        }
    }

    if (!$test_id || !$pregunta) {
        return gc_station_wrap_message('Este ' . $label . ' no tiene una prueba configurada.', 'error');
    }

    $enunciado   = isset($pregunta['enunciado']) ? $pregunta['enunciado'] : '';
    $opciones    = isset($pregunta['opciones']) && is_array($pregunta['opciones']) ? $pregunta['opciones'] : [];
    $tipo_preg   = isset($pregunta['tipo']) ? $pregunta['tipo'] : 'multiple';
    $resp_text   = trim((string) ($pregunta['respuesta_texto_correcta'] ?? ''));

    // Validación: tipos texto-libres necesitan respuesta_texto_correcta;
    // tipos de selección necesitan opciones.
    $es_texto_libre = in_array($tipo_preg, ['texto', 'anagrama', 'cifrado_cesar', 'ahorcado', 'sopa_letras'], true);
    if ($es_texto_libre) {
        if ($resp_text === '') {
            return gc_station_wrap_message('La prueba de este ' . $label . ' no está lista (falta la respuesta correcta).', 'error');
        }
    } else {
        if (empty($opciones)) {
            return gc_station_wrap_message('La prueba de este ' . $label . ' no está lista (faltan opciones).', 'error');
        }
    }
    if (empty($enunciado)) {
        $enunciado = $tipo_preg === 'multiple_imagen' ? '¿Cuál es la imagen correcta?'
            : ($tipo_preg === 'cifrado_cesar' ? 'Descifra el mensaje'
            : ($tipo_preg === 'anagrama' ? 'Adivina la palabra'
            : ($tipo_preg === 'ahorcado' ? 'Adivina la palabra letra a letra' : '')));
    }

    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';

    // Atajo de emergencia: ?gc_quiz_reset=1 limpia el estado del usuario actual
    // en esta prueba+estación (intentos en gincana_attempts, started_at y meta del
    // ahorcado). Útil para salir de un bloqueo huérfano sin pasar por admin.
    if ( isset($_GET['gc_quiz_reset']) && $_GET['gc_quiz_reset'] === '1' && $user_id > 0 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}gincana_attempts WHERE user_id=%d AND prueba_id=%d AND estacion_id=%d",
            $user_id, $test_id, $station_id
        ));
        delete_user_meta($user_id, 'gc_quiz_state_' . $test_id . '_' . $station_id . '_started');
        delete_user_meta($user_id, 'gc_ahorcado_revealed_' . $test_id . '_' . $station_id);
        delete_user_meta($user_id, 'gc_ahorcado_miss_' . $test_id . '_' . $station_id);
        // Quitar el parámetro para no entrar en bucle
        $clean_url = remove_query_arg('gc_quiz_reset');
        if ( ! headers_sent() ) {
            wp_safe_redirect($clean_url);
            exit;
        }
    }

    // Estado server-side del intento del usuario (intentos previos, tiempo, etc.)
    $quiz_state = function_exists('gc_quiz_user_state')
        ? gc_quiz_user_state($user_id, $test_id, $station_id)
        : ['started_at'=>0,'failed_attempts'=>0,'max_attempts'=>2,'attempts_left'=>2,'time_max_s'=>0,'time_left_s'=>-1,'blocked'=>false,'blocked_reason'=>'','passed'=>false];

    // Si está bloqueado, mostramos pantalla de bloqueo y salimos
    if ($quiz_state['blocked']) {
        $escenario_url_b = get_permalink($escenario_id) ?: home_url('/');
        $reason_msg = $quiz_state['blocked_reason'] === 'time'
            ? '⌛ Se acabó el tiempo para resolver este desafío.'
            : '❌ Has agotado los ' . (int) $quiz_state['max_attempts'] . ' intentos disponibles.';
        $reset_url = add_query_arg('gc_quiz_reset', '1');
        $is_admin  = current_user_can('manage_options');
        ob_start();
        ?>
        <!-- gc_blocked v<?php echo defined('GINCANA_CORE_VERSION') ? GINCANA_CORE_VERSION : '?'; ?>
             reason=<?php echo esc_html($quiz_state['blocked_reason']); ?>
             started_at=<?php echo (int) $quiz_state['started_at']; ?>
             failed=<?php echo (int) $quiz_state['failed_attempts']; ?>
             max=<?php echo (int) $quiz_state['max_attempts']; ?>
             time_max=<?php echo (int) $quiz_state['time_max_s']; ?>
             time_left=<?php echo (int) $quiz_state['time_left_s']; ?>
        -->
        <div style="padding:24px 20px;border-radius:14px;background:#fef2f2;border:2px solid #dc2626;text-align:center;">
            <div style="font-size:48px;margin-bottom:8px;"><?php echo $quiz_state['blocked_reason'] === 'time' ? '⏰' : '🚫'; ?></div>
            <h2 style="margin:0 0 8px;color:#991b1b;">Desafío no superado</h2>
            <p style="margin:0 0 16px;font-size:15px;color:#7f1d1d;"><?php echo esc_html($reason_msg); ?></p>
            <p style="margin:0 0 16px;font-size:14px;color:#7f1d1d;">Intentos usados: <strong><?php echo (int) $quiz_state['failed_attempts']; ?> / <?php echo (int) $quiz_state['max_attempts']; ?></strong></p>
            <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
                <a href="<?php echo esc_url($escenario_url_b); ?>" style="display:inline-block;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>
                <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block;padding:12px 24px;border:2px solid #dc2626;border-radius:10px;background:#fff;color:#dc2626;text-decoration:none;font-weight:600;">🔄 Reiniciar mi intento</a>
            </div>
            <p style="margin:14px 0 0;font-size:12px;color:#94a3b8;">Si has acabado todos tus intentos puedes reiniciar para volver a probar este desafío.</p>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <!-- gc_quiz_render v<?php echo defined('GINCANA_CORE_VERSION') ? GINCANA_CORE_VERSION : '?'; ?>
         tipo=<?php echo esc_html($tipo_preg); ?>
         user=<?php echo (int) $user_id; ?>
         prueba=<?php echo (int) $test_id; ?>
         estacion=<?php echo (int) $station_id; ?>
         started_at=<?php echo (int) $quiz_state['started_at']; ?>
         failed=<?php echo (int) $quiz_state['failed_attempts']; ?>
         max=<?php echo (int) $quiz_state['max_attempts']; ?>
         time_max=<?php echo (int) $quiz_state['time_max_s']; ?>
         time_left=<?php echo (int) $quiz_state['time_left_s']; ?>
    -->
    <div class="gc-adult-station"
         data-station-id="<?php echo esc_attr($station_id); ?>"
         data-escenario-id="<?php echo esc_attr($escenario_id); ?>"
         data-prueba-id="<?php echo esc_attr($test_id); ?>"
         data-q-index="<?php echo esc_attr($q_index); ?>">

        <!-- CTA desafío (visible por defecto) -->
        <?php
            $img_pregunta = get_post_meta($escenario_id, 'gc_img_pregunta', true);
            // CTA personalizable. Por defecto: '¿Preparado para el desafío?'.
            // Si es QR+Quiz, el dispatcher pasa textos tipo '¡Has llegado!' + 'Continuar al desafío'.
            $cta_titulo = is_array($intro_opts) && !empty($intro_opts['titulo'])
                ? $intro_opts['titulo']
                : '¿Preparado para el desafío?';
            $cta_subtitulo = is_array($intro_opts) && !empty($intro_opts['subtitulo'])
                ? $intro_opts['subtitulo']
                : 'Pon a prueba tus conocimientos sobre este lugar. ¡Solo tienes una oportunidad!';
            $cta_boton = is_array($intro_opts) && !empty($intro_opts['boton'])
                ? $intro_opts['boton']
                : '¡Acepto el desafío!';
            $usar_img_encontrada = is_array($intro_opts) && !empty($intro_opts['usar_img_encontrada']);
        ?>
        <div id="gc-challenge-cta" style="padding:24px 20px;border:2px solid #2563eb;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#dbeafe);text-align:center;">
            <?php if ($usar_img_encontrada): ?>
            <div style="margin-bottom:12px;"><?php echo function_exists('gc_get_img_encontrada') ? gc_get_img_encontrada($escenario_id) : ''; ?></div>
            <?php elseif ($img_pregunta): ?>
            <div style="margin-bottom:12px;">
                <img src="<?php echo esc_url($img_pregunta); ?>" alt="" style="max-width:100%;height:auto;border-radius:12px;" />
            </div>
            <?php else: ?>
            <div style="font-size:40px;margin-bottom:12px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <?php endif; ?>
            <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1e293b;"><?php echo esc_html($cta_titulo); ?></h3>
            <p style="margin:0 0 18px;font-size:15px;color:#475569;line-height:1.5;"><?php echo esc_html($cta_subtitulo); ?></p>
            <button type="button" id="gc-start-challenge"
                    style="padding:14px 32px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-size:17px;font-weight:700;cursor:pointer;transition:background 0.2s,transform 0.2s;">
                <?php echo esc_html($cta_boton); ?>
            </button>
        </div>

        <!-- Quiz (oculto hasta pulsar) -->
        <?php
            $p_tipo = isset($pregunta['tipo']) ? $pregunta['tipo'] : 'multiple';
            $p_resp_text = isset($pregunta['respuesta_texto_correcta']) ? (string) $pregunta['respuesta_texto_correcta'] : '';
            $p_rotacion  = isset($pregunta['rotacion']) ? (int) $pregunta['rotacion'] : 0;

            // Helpers para preview en el front
            $cesar_encode = function($text, $rot) {
                $rot = ((int) $rot) % 26;
                if ($rot === 0) $rot = 1;
                $out = '';
                $len = mb_strlen($text);
                for ($i = 0; $i < $len; $i++) {
                    $ch = mb_substr($text, $i, 1);
                    $up = strtoupper($ch);
                    if ($up >= 'A' && $up <= 'Z') {
                        $code = ord($up) - 65;
                        $code = ($code + $rot) % 26;
                        $out .= chr(65 + $code);
                    } else {
                        $out .= $ch;
                    }
                }
                return $out;
            };
            $shuffle_letters = function($text) {
                $clean = preg_replace('/\s+/', '', strtoupper($text));
                $arr = preg_split('//u', $clean, -1, PREG_SPLIT_NO_EMPTY);
                if (count($arr) > 1) {
                    // Asegurar que el shuffle no devuelva la misma palabra
                    $orig = implode('', $arr);
                    $tries = 0;
                    do {
                        shuffle($arr);
                        $tries++;
                    } while (implode('', $arr) === $orig && $tries < 10);
                }
                return implode(' ', $arr);
            };
        ?>
        <?php
            $intentos_max     = (int) $quiz_state['max_attempts'];
            $intentos_left    = (int) $quiz_state['attempts_left'];
            $tiempo_max_s     = (int) $quiz_state['time_max_s'];
            $tiempo_left_init = $quiz_state['time_left_s']; // -1 si no aplica
            $started_at_srv   = (int) $quiz_state['started_at'];
        ?>
        <div id="gc-quiz-panel" style="display:none;padding:20px;border:1px solid #dcdcde;border-radius:14px;background:#fff;">
            <h2 style="margin-top:0;">Pregunta del <?php echo esc_html($label); ?></h2>

            <!-- Barra de intentos + tiempo -->
            <div id="gc-quiz-meta" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin:0 0 14px;padding:10px 14px;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;">
                <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:#334155;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    <span><strong>Intentos:</strong> <span id="gc-intentos-restantes"><?php echo (int) $intentos_left; ?></span> / <?php echo (int) $intentos_max; ?></span>
                </div>
                <?php if ($tiempo_max_s > 0): ?>
                <?php
                    $tl_seconds = $tiempo_left_init >= 0 ? (int) $tiempo_left_init : (int) $tiempo_max_s;
                ?>
                <div style="display:flex;align-items:center;gap:8px;font-size:14px;color:#334155;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span><strong>Tiempo:</strong> <span id="gc-tiempo-restante" data-seconds="<?php echo (int) $tl_seconds; ?>"><?php echo str_pad((string) intdiv($tl_seconds, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) ($tl_seconds % 60), 2, '0', STR_PAD_LEFT); ?></span></span>
                </div>
                <?php endif; ?>
            </div>

            <p style="font-size:18px;line-height:1.5;"><strong><?php echo esc_html($enunciado); ?></strong></p>

            <form id="gc-adult-station-form"
                  data-mode="<?php echo esc_attr($p_tipo); ?>"
                  data-intentos-max="<?php echo (int) $intentos_max; ?>"
                  data-intentos-left="<?php echo (int) $intentos_left; ?>"
                  data-tiempo-max="<?php echo (int) $tiempo_max_s; ?>"
                  data-tiempo-left="<?php echo (int) ($tiempo_left_init >= 0 ? $tiempo_left_init : $tiempo_max_s); ?>"
                  data-started-at="<?php echo (int) $started_at_srv; ?>">

                <?php if ($p_tipo === 'multiple_imagen'): ?>
                    <div class="gc-img-grid" style="display:grid;grid-template-columns:1fr;gap:14px;margin-top:14px;">
                        <?php foreach ($opciones as $index => $opcion):
                            $img = isset($opcion['imagen']) ? $opcion['imagen'] : '';
                            $cap = isset($opcion['texto']) ? $opcion['texto'] : '';
                            if (!$img && !$cap) continue;
                        ?>
                        <label class="gc-img-option" data-img-url="<?php echo esc_attr($img); ?>" data-img-caption="<?php echo esc_attr($cap); ?>" style="display:block;cursor:pointer;border:3px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#f8fafc;transition:border-color 0.15s, transform 0.1s, box-shadow 0.15s;position:relative;">
                            <input type="radio" name="gc_station_answer" value="<?php echo esc_attr($index); ?>" style="display:none;">
                            <?php if ($img): ?>
                                <div style="aspect-ratio:16/10;overflow:hidden;background:#fff;position:relative;">
                                    <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($cap); ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                                    <button type="button" class="gc-img-zoom" aria-label="Ver imagen grande" style="position:absolute;top:10px;right:10px;width:38px;height:38px;border:0;border-radius:999px;background:rgba(0,0,0,0.55);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                            <?php if ($cap): ?>
                                <div style="padding:10px 12px;font-size:14px;font-weight:500;color:#334155;text-align:center;background:#fff;border-top:1px solid #f1f5f9;"><?php echo esc_html($cap); ?></div>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Lightbox para imágenes -->
                    <div id="gc-img-lightbox" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.92);align-items:center;justify-content:center;flex-direction:column;padding:20px;">
                        <button type="button" id="gc-img-lightbox-close" aria-label="Cerrar" style="position:absolute;top:16px;right:16px;width:44px;height:44px;border:0;border-radius:999px;background:rgba(255,255,255,0.18);color:#fff;cursor:pointer;font-size:24px;display:flex;align-items:center;justify-content:center;">&times;</button>
                        <img id="gc-img-lightbox-img" src="" alt="" style="max-width:96vw;max-height:80vh;object-fit:contain;border-radius:10px;">
                        <div id="gc-img-lightbox-caption" style="color:#fff;font-size:15px;margin-top:14px;text-align:center;max-width:80vw;"></div>
                    </div>

                    <style>
                        .gc-img-option:hover { border-color:#93c5fd !important; transform:translateY(-2px); box-shadow:0 6px 18px rgba(37,99,235,0.12); }
                        .gc-img-option.is-selected { border-color:#2563eb !important; box-shadow:0 0 0 4px rgba(37,99,235,0.22) !important; }
                        .gc-img-zoom:hover { background:rgba(0,0,0,0.8) !important; }
                        /* Tablet y desktop: 2 columnas, las imágenes son grandes en cualquier caso */
                        @media (min-width: 720px) {
                            .gc-img-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 20px !important; }
                        }
                    </style>
                    <script>
                    (function(){
                        var lb       = document.getElementById('gc-img-lightbox');
                        var lbImg    = document.getElementById('gc-img-lightbox-img');
                        var lbCap    = document.getElementById('gc-img-lightbox-caption');
                        var lbClose  = document.getElementById('gc-img-lightbox-close');
                        if (!lb || !lbImg) return;

                        function openLb(url, caption) {
                            lbImg.src = url;
                            lbCap.textContent = caption || '';
                            lb.style.display = 'flex';
                            document.body.style.overflow = 'hidden';
                        }
                        function closeLb() {
                            lb.style.display = 'none';
                            lbImg.src = '';
                            document.body.style.overflow = '';
                        }
                        document.querySelectorAll('.gc-img-zoom').forEach(function(btn){
                            btn.addEventListener('click', function(e){
                                e.preventDefault(); e.stopPropagation();
                                var card = btn.closest('.gc-img-option');
                                if (!card) return;
                                openLb(card.dataset.imgUrl || '', card.dataset.imgCaption || '');
                            });
                        });
                        if (lbClose) lbClose.addEventListener('click', closeLb);
                        lb.addEventListener('click', function(e){ if (e.target === lb) closeLb(); });
                        document.addEventListener('keydown', function(e){
                            if (e.key === 'Escape' && lb.style.display === 'flex') closeLb();
                        });
                    })();
                    </script>

                <?php elseif ($p_tipo === 'ahorcado' && $p_resp_text !== ''):
                    $palabra      = mb_strtoupper($p_resp_text);
                    $pista_txt    = isset($pregunta['pista']) ? (string) $pregunta['pista'] : '';
                    $categoria    = isset($pregunta['categoria']) ? (string) $pregunta['categoria'] : '';
                    // Estado server-side de letras descubiertas e intentos fallidos para esta partida
                    $reveal_key   = 'gc_ahorcado_revealed_' . $test_id . '_' . $station_id;
                    $reveal_meta  = get_user_meta($user_id, $reveal_key, true);
                    $revealed     = is_array($reveal_meta) ? array_map('strval', $reveal_meta) : [];
                    $miss_key     = 'gc_ahorcado_miss_' . $test_id . '_' . $station_id;
                    $miss_meta    = get_user_meta($user_id, $miss_key, true);
                    $missed       = is_array($miss_meta) ? array_map('strval', $miss_meta) : [];

                    // Calcular las letras únicas de la palabra (solo letras alfabéticas)
                    $letras_palabra = [];
                    $len = mb_strlen($palabra);
                    for ($i = 0; $i < $len; $i++) {
                        $ch = mb_substr($palabra, $i, 1);
                        if (preg_match('/\p{L}/u', $ch)) {
                            $letras_palabra[mb_strtoupper(remove_accents($ch))] = true;
                        }
                    }
                    $letras_palabra = array_keys($letras_palabra);
                ?>
                    <div style="margin:14px 0 10px;text-align:center;">
                        <?php if ($categoria): ?>
                            <div style="display:inline-block;margin-bottom:10px;padding:4px 12px;border-radius:999px;background:#dbeafe;color:#1e40af;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;"><?php echo esc_html($categoria); ?></div>
                        <?php endif; ?>
                        <?php if ($pista_txt): ?>
                            <div style="margin-bottom:14px;padding:10px 14px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#78350f;font-size:14px;">💡 <?php echo esc_html($pista_txt); ?></div>
                        <?php endif; ?>

                        <!-- Huecos de la palabra -->
                        <div id="gc-ahorcado-palabra" style="display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:10px 0 16px;font-family:'Courier New',monospace;">
                            <?php for ($i = 0; $i < $len; $i++):
                                $ch = mb_substr($palabra, $i, 1);
                                $is_letter = preg_match('/\p{L}/u', $ch);
                                $ch_norm   = $is_letter ? mb_strtoupper(remove_accents($ch)) : '';
                                $is_revealed = !$is_letter || in_array($ch_norm, $revealed, true);
                            ?>
                                <span class="gc-ahorcado-hueco" data-letter="<?php echo esc_attr($ch_norm); ?>" data-original="<?php echo esc_attr($ch); ?>" style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:42px;font-size:24px;font-weight:700;color:#1e40af;border-bottom:<?php echo $is_letter ? '3px solid #1e40af' : 'none'; ?>;">
                                    <?php echo $is_revealed ? esc_html($is_letter ? mb_strtoupper($ch) : $ch) : ''; ?>
                                </span>
                            <?php endfor; ?>
                        </div>

                        <!-- Letras erróneas -->
                        <div style="margin:8px 0 14px;font-size:13px;color:#7f1d1d;min-height:20px;">
                            <span style="font-weight:600;">Erróneas:</span>
                            <span id="gc-ahorcado-erroneas" style="font-family:'Courier New',monospace;letter-spacing:3px;">
                                <?php echo esc_html(implode(' ', $missed)); ?>
                            </span>
                        </div>

                        <!-- Teclado A-Z -->
                        <div id="gc-ahorcado-teclado" style="display:flex;flex-wrap:wrap;gap:5px;justify-content:center;max-width:500px;margin:0 auto 18px;">
                            <?php
                            $letras_az = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','Ñ','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
                            foreach ($letras_az as $L):
                                $usada = in_array($L, $revealed, true) || in_array($L, $missed, true);
                                $en_palabra = in_array($L, $letras_palabra, true);
                                $bg = '#fff'; $fg = '#1e293b'; $border = '#cbd5e1';
                                if ($usada) {
                                    if ($en_palabra) { $bg = '#16a34a'; $fg = '#fff'; $border = '#16a34a'; }
                                    else             { $bg = '#dc2626'; $fg = '#fff'; $border = '#dc2626'; }
                                }
                            ?>
                                <button type="button" class="gc-ahorcado-letra" data-letra="<?php echo esc_attr($L); ?>" <?php echo $usada ? 'disabled' : ''; ?> style="width:36px;height:40px;border:2px solid <?php echo $border; ?>;border-radius:8px;background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;font-size:16px;font-weight:700;cursor:<?php echo $usada ? 'default' : 'pointer'; ?>;transition:transform 0.1s;<?php echo $usada ? 'opacity:0.85;' : ''; ?>"><?php echo esc_html($L); ?></button>
                            <?php endforeach; ?>
                        </div>

                        <!-- Botón resolver -->
                        <details style="margin-top:14px;">
                            <summary style="cursor:pointer;color:#2563eb;font-weight:600;">¿Quieres intentar resolver la palabra completa?</summary>
                            <input type="text" id="gc-ahorcado-resolver" autocomplete="off" autocapitalize="characters" style="width:100%;max-width:320px;margin-top:10px;padding:12px 16px;border:2px solid #2563eb;border-radius:10px;font-size:18px;font-weight:600;letter-spacing:2px;text-transform:uppercase;text-align:center;" placeholder="Escribe la palabra…" />
                        </details>
                    </div>
                    <input type="hidden" name="gc_station_answer" id="gc_station_answer_text" value="" />

                <?php elseif ($p_tipo === 'sopa_letras' && $p_resp_text !== ''):
                    $sopa_tamano = isset($pregunta['tamano_grid']) ? (int) $pregunta['tamano_grid'] : 10;
                    if (!in_array($sopa_tamano, [8,10,12,15], true)) $sopa_tamano = 10;
                    $sopa = function_exists('gc_sopa_get_or_create')
                        ? gc_sopa_get_or_create($user_id, $test_id, $station_id, $p_resp_text, $sopa_tamano)
                        : null;
                ?>
                <?php if (!$sopa): ?>
                    <div style="padding:14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
                        No se ha podido generar la sopa de letras. Comprueba que la palabra tiene entre 3 letras y el tamaño del grid configurado.
                    </div>
                <?php else: ?>
                    <div style="margin:14px 0 10px;text-align:center;">
                        <div style="display:inline-block;margin-bottom:14px;padding:8px 16px;border-radius:999px;background:#dbeafe;color:#1e40af;font-size:14px;font-weight:700;">
                            🔡 Encuentra la palabra de <strong><?php echo (int) mb_strlen($sopa['palabra']); ?></strong> letras
                        </div>
                        <div style="margin-bottom:10px;font-size:13px;color:#64748b;">
                            Mantén pulsada la primera letra y arrastra hasta la última (horizontal, vertical o diagonal).
                            <br>O toca la primera y luego la última.
                        </div>
                    </div>
                    <?php
                        $gtam = (int) $sopa['tamano'];
                        $grid = $sopa['grid'];
                    ?>
                    <div class="gc-sopa-wrap" data-tamano="<?php echo $gtam; ?>" style="margin:0 auto 18px;max-width:100%;overflow:auto;">
                        <table class="gc-sopa-grid" style="margin:0 auto;border-collapse:collapse;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none;">
                            <?php for ($r = 0; $r < $gtam; $r++): ?>
                            <tr>
                                <?php for ($c = 0; $c < $gtam; $c++): ?>
                                    <td class="gc-sopa-cell" data-r="<?php echo $r; ?>" data-c="<?php echo $c; ?>"
                                        style="border:1px solid #cbd5e1;width:32px;height:32px;text-align:center;vertical-align:middle;font-family:'Courier New',monospace;font-weight:700;font-size:16px;color:#1e293b;background:#fff;cursor:pointer;transition:background 0.08s, color 0.08s;">
                                        <?php echo esc_html($grid[$r][$c]); ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </table>
                    </div>
                    <div id="gc-sopa-feedback" style="text-align:center;min-height:24px;font-size:13px;color:#64748b;margin-bottom:8px;"></div>
                    <input type="hidden" name="gc_station_answer" id="gc_station_answer_text" value="" />

                    <style>
                        .gc-sopa-cell.is-hover { background:#dbeafe !important; color:#1e40af !important; }
                        .gc-sopa-cell.is-selected { background:#2563eb !important; color:#fff !important; }
                        .gc-sopa-cell.is-correct { background:#16a34a !important; color:#fff !important; }
                        @media (max-width: 380px) {
                            .gc-sopa-grid td { width: 26px !important; height: 26px !important; font-size: 13px !important; }
                        }
                    </style>
                    <script>
                    (function(){
                        var wrap = document.querySelector('.gc-sopa-wrap');
                        if (!wrap) return;
                        if (wrap.dataset.gcBound) return;
                        wrap.dataset.gcBound = '1';
                        var grid  = wrap.querySelector('.gc-sopa-grid');
                        var fb    = document.getElementById('gc-sopa-feedback');
                        var hidden = document.getElementById('gc_station_answer_text');
                        var dragging = false;
                        var firstCell = null;

                        function clearMarks(cls) {
                            grid.querySelectorAll('td').forEach(function(td){ td.classList.remove(cls); });
                        }
                        function getCellAt(x, y) {
                            var el = document.elementFromPoint(x, y);
                            if (!el) return null;
                            return el.closest('.gc-sopa-cell');
                        }
                        // Devuelve la lista de celdas en línea recta entre dos puntos.
                        // Null si no forman horizontal/vertical/diagonal exacta.
                        function celdasEntre(a, b) {
                            var r1 = parseInt(a.dataset.r,10), c1 = parseInt(a.dataset.c,10);
                            var r2 = parseInt(b.dataset.r,10), c2 = parseInt(b.dataset.c,10);
                            var dr = r2 - r1, dc = c2 - c1;
                            var len = Math.max(Math.abs(dr), Math.abs(dc));
                            if (len === 0) return [a];
                            var srt = function(n){ return n === 0 ? 0 : (n > 0 ? 1 : -1); };
                            var sr = srt(dr), sc = srt(dc);
                            // Validar línea recta exacta (horizontal, vertical o diagonal 45º)
                            if (dr !== 0 && dc !== 0 && Math.abs(dr) !== Math.abs(dc)) return null;
                            var cells = [];
                            for (var i = 0; i <= len; i++) {
                                var rr = r1 + sr * i, cc = c1 + sc * i;
                                var td = grid.querySelector('.gc-sopa-cell[data-r="'+rr+'"][data-c="'+cc+'"]');
                                if (!td) return null;
                                cells.push(td);
                            }
                            return cells;
                        }
                        function previsualizar(b) {
                            if (!firstCell || !b) return;
                            clearMarks('is-hover');
                            var cells = celdasEntre(firstCell, b);
                            if (!cells) return;
                            cells.forEach(function(td){ td.classList.add('is-hover'); });
                        }
                        function confirmarSeleccion(b) {
                            if (!firstCell || !b) return;
                            var cells = celdasEntre(firstCell, b);
                            clearMarks('is-hover');
                            if (!cells) {
                                clearMarks('is-selected');
                                firstCell = null;
                                if (fb) fb.textContent = 'Tiene que ser una línea recta (horizontal, vertical o diagonal).';
                                return;
                            }
                            clearMarks('is-selected');
                            cells.forEach(function(td){ td.classList.add('is-selected'); });
                            // Construir payload JSON [[r,c],...]
                            var coords = cells.map(function(td){
                                return [parseInt(td.dataset.r,10), parseInt(td.dataset.c,10)];
                            });
                            if (hidden) hidden.value = JSON.stringify(coords);
                            if (fb) fb.textContent = cells.length + ' letras seleccionadas. Pulsa Responder.';
                            firstCell = null;
                        }

                        // Drag con ratón
                        grid.addEventListener('mousedown', function(e){
                            var td = e.target.closest('.gc-sopa-cell');
                            if (!td) return;
                            e.preventDefault();
                            // Si ya hay una primera celda y se hace click en la "segunda", confirma
                            if (firstCell && firstCell !== td) { confirmarSeleccion(td); return; }
                            firstCell = td;
                            dragging = true;
                            clearMarks('is-selected');
                            td.classList.add('is-hover');
                            if (fb) fb.textContent = 'Suelta o toca la última letra de la palabra.';
                        });
                        document.addEventListener('mousemove', function(e){
                            if (!dragging) return;
                            var td = getCellAt(e.clientX, e.clientY);
                            if (td) previsualizar(td);
                        });
                        document.addEventListener('mouseup', function(e){
                            if (!dragging) return;
                            dragging = false;
                            var td = getCellAt(e.clientX, e.clientY);
                            // Si soltamos sobre la misma celda inicial, dejamos firstCell para click-click
                            if (td && td !== firstCell) confirmarSeleccion(td);
                        });

                        // Touch
                        grid.addEventListener('touchstart', function(e){
                            var t = e.touches[0]; if (!t) return;
                            var td = getCellAt(t.clientX, t.clientY);
                            if (!td) return;
                            e.preventDefault();
                            if (firstCell && firstCell !== td) { confirmarSeleccion(td); return; }
                            firstCell = td;
                            dragging = true;
                            clearMarks('is-selected');
                            td.classList.add('is-hover');
                            if (fb) fb.textContent = 'Arrastra hasta la última letra.';
                        }, {passive:false});
                        grid.addEventListener('touchmove', function(e){
                            if (!dragging) return;
                            var t = e.touches[0]; if (!t) return;
                            e.preventDefault();
                            var td = getCellAt(t.clientX, t.clientY);
                            if (td) previsualizar(td);
                        }, {passive:false});
                        grid.addEventListener('touchend', function(e){
                            if (!dragging) return;
                            dragging = false;
                            var t = (e.changedTouches && e.changedTouches[0]) || null;
                            var td = t ? getCellAt(t.clientX, t.clientY) : null;
                            if (td && td !== firstCell) confirmarSeleccion(td);
                        });
                    })();
                    </script>
                <?php endif; ?>

                <?php elseif ($p_tipo === 'cifrado_cesar' && $p_resp_text !== ''): ?>
                    <?php $cifrado = $cesar_encode($p_resp_text, $p_rotacion); ?>
                    <div style="margin:14px 0;padding:18px 20px;border-radius:12px;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:2px solid #c4b5fd;text-align:center;">
                        <div style="font-size:13px;color:#6d28d9;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">🔐 Mensaje cifrado</div>
                        <div style="font-family:'Courier New',monospace;font-size:28px;font-weight:700;color:#4c1d95;letter-spacing:6px;word-break:break-all;"><?php echo esc_html($cifrado); ?></div>
                        <div style="margin-top:10px;font-size:13px;color:#5b21b6;">Pista: cada letra está rotada <strong><?php echo (int)$p_rotacion; ?></strong> posiciones hacia delante en el abecedario. Resta <strong><?php echo (int)$p_rotacion; ?></strong> para descifrar.</div>
                    </div>
                    <input type="text" name="gc_station_answer" id="gc_station_answer_text" autocomplete="off" autocapitalize="characters" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:18px;font-weight:600;letter-spacing:2px;text-transform:uppercase;text-align:center;" placeholder="Escribe la palabra descifrada…" />

                <?php elseif ($p_tipo === 'anagrama' && $p_resp_text !== ''): ?>
                    <?php $desordenada = $shuffle_letters($p_resp_text); ?>
                    <div style="margin:14px 0;padding:18px 20px;border-radius:12px;background:linear-gradient(135deg,#fef3c7,#fde68a);border:2px solid #fbbf24;text-align:center;">
                        <div style="font-size:13px;color:#92400e;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">🔀 Letras desordenadas</div>
                        <div style="font-family:'Courier New',monospace;font-size:28px;font-weight:700;color:#78350f;letter-spacing:6px;"><?php echo esc_html($desordenada); ?></div>
                        <div style="margin-top:10px;font-size:13px;color:#92400e;">Reordena estas letras para formar la palabra correcta.</div>
                    </div>
                    <input type="text" name="gc_station_answer" id="gc_station_answer_text" autocomplete="off" autocapitalize="characters" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:18px;font-weight:600;letter-spacing:2px;text-transform:uppercase;text-align:center;" placeholder="Escribe la palabra…" />

                <?php elseif ($p_tipo === 'texto'): ?>
                    <input type="text" name="gc_station_answer" id="gc_station_answer_text" autocomplete="off" style="width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:16px;" placeholder="Escribe tu respuesta…" />

                <?php else: ?>
                    <?php // multiple, vf: radios texto ?>
                    <?php foreach ($opciones as $index => $opcion):
                        $texto = isset($opcion['texto']) ? $opcion['texto'] : '';
                        if ($texto === '') continue;
                    ?>
                        <label style="display:block;margin:12px 0;padding:14px 16px;border:1px solid #dcdcde;border-radius:12px;cursor:pointer;">
                            <input type="radio" name="gc_station_answer" value="<?php echo esc_attr($index); ?>" style="margin-right:10px;">
                            <?php echo esc_html($texto); ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>

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

        // === Contadores de intentos y tiempo (server-side) ===
        const formForMeta  = wrap.querySelector('#gc-adult-station-form');
        const intentosMax  = formForMeta ? parseInt(formForMeta.dataset.intentosMax  || '2', 10) : 2;
        let   intentosRestantes = formForMeta ? parseInt(formForMeta.dataset.intentosLeft || String(intentosMax), 10) : intentosMax;
        const tiempoMaxS   = formForMeta ? parseInt(formForMeta.dataset.tiempoMax   || '0', 10) : 0;
        let   tiempoRestante = formForMeta ? parseInt(formForMeta.dataset.tiempoLeft || '0', 10) : 0;
        const startedAtSrv = formForMeta ? parseInt(formForMeta.dataset.startedAt || '0', 10) : 0;
        const intentosLabel = wrap.querySelector('#gc-intentos-restantes');
        const tiempoLabel   = wrap.querySelector('#gc-tiempo-restante');
        let countdownTimer = null;

        // Revelar quiz al pulsar — también notifica al server el inicio del intento
        if (startBtn && cta && panel) {
            startBtn.addEventListener('click', function(){
                cta.style.display = 'none';
                panel.style.display = 'block';
                panel.scrollIntoView({behavior:'smooth', block:'center'});
                startedAt = Date.now();

                // Si ya había un started_at en server, no volvemos a llamar /quiz/start
                if (!startedAtSrv) {
                    fetch('/wp-json/gincana/v1/quiz/start', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                        credentials: 'same-origin',
                        body: JSON.stringify({ prueba_id: pruebaId, estacion_id: stationId })
                    }).then(function(r){ return r.json(); }).then(function(data){
                        if (data && data.state && data.state.time_left_s >= 0) {
                            tiempoRestante = data.state.time_left_s;
                        }
                        startCountdown();
                    }).catch(function(){
                        // Aun si falla la llamada, arrancamos el countdown local
                        startCountdown();
                    });
                } else {
                    startCountdown();
                }
            });
        }

        function pad2(n){ n = String(n); return n.length < 2 ? '0' + n : n; }
        function fmtTime(s){ if (s < 0) s = 0; return pad2(Math.floor(s/60)) + ':' + pad2(s%60); }

        function lockForm(reason) {
            if (!formForMeta) return;
            const submitBtn = formForMeta.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.55';
                submitBtn.style.cursor  = 'not-allowed';
            }
            formForMeta.querySelectorAll('input, textarea').forEach(function(el){ el.disabled = true; });
            const m = wrap.querySelector('#gc-adult-msg');
            if (m && reason) {
                m.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;font-weight:600;">' + reason + '</div>';
            }
        }

        function startCountdown() {
            if (tiempoMaxS <= 0 || !tiempoLabel) return;
            // Si ya pasó el tiempo, bloquear directamente
            if (tiempoRestante <= 0) {
                tiempoLabel.textContent = '00:00';
                tiempoLabel.style.color = '#dc2626';
                lockForm('⌛ Se acabó el tiempo. Esta pregunta ya no se puede responder.');
                return;
            }
            tiempoLabel.textContent = fmtTime(tiempoRestante);
            countdownTimer = setInterval(function(){
                tiempoRestante -= 1;
                if (tiempoRestante <= 0) {
                    tiempoLabel.textContent = '00:00';
                    tiempoLabel.style.color = '#dc2626';
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                    lockForm('⌛ Se acabó el tiempo. Esta pregunta ya no se puede responder.');
                    return;
                }
                tiempoLabel.textContent = fmtTime(tiempoRestante);
                if (tiempoRestante <= 10) {
                    tiempoLabel.style.color = '#dc2626';
                    tiempoLabel.style.fontWeight = '700';
                }
            }, 1000);
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

        // Estilo "seleccionada" para opciones de imagen
        form.querySelectorAll('input[type="radio"][name="gc_station_answer"]').forEach(function(r){
            r.addEventListener('change', function(){
                form.querySelectorAll('.gc-img-option').forEach(function(lbl){ lbl.classList.remove('is-selected'); });
                var lbl = r.closest('.gc-img-option');
                if (lbl) lbl.classList.add('is-selected');
            });
        });

        // === Lógica del tipo 'ahorcado' ===
        if ((form.dataset.mode || '') === 'ahorcado') {
            const teclado     = wrap.querySelector('#gc-ahorcado-teclado');
            const palabraWrap = wrap.querySelector('#gc-ahorcado-palabra');
            const erroneasEl  = wrap.querySelector('#gc-ahorcado-erroneas');
            const resolverEl  = wrap.querySelector('#gc-ahorcado-resolver');
            const hiddenAns   = wrap.querySelector('#gc_station_answer_text');

            function revelarLetra(L) {
                if (!palabraWrap) return;
                palabraWrap.querySelectorAll('.gc-ahorcado-hueco').forEach(function(h){
                    if ((h.dataset.letter || '') === L) {
                        h.textContent = (h.dataset.original || '').toUpperCase();
                    }
                });
            }
            function pintarBoton(btn, en_palabra) {
                btn.disabled = true;
                btn.style.cursor = 'default';
                btn.style.opacity = '0.85';
                if (en_palabra) {
                    btn.style.background = '#16a34a'; btn.style.color = '#fff'; btn.style.borderColor = '#16a34a';
                } else {
                    btn.style.background = '#dc2626'; btn.style.color = '#fff'; btn.style.borderColor = '#dc2626';
                }
            }

            if (teclado) {
                teclado.addEventListener('click', async function(e){
                    const btn = e.target.closest('.gc-ahorcado-letra');
                    if (!btn || btn.disabled) return;
                    const letra = btn.dataset.letra;

                    btn.disabled = true; // bloqueo óptico mientras llega la respuesta
                    try {
                        const r = await fetch('/wp-json/gincana/v1/quiz/ahorcado/letra', {
                            method:'POST',
                            headers:{'Content-Type':'application/json','X-WP-Nonce': nonce},
                            credentials:'same-origin',
                            body: JSON.stringify({ prueba_id: pruebaId, estacion_id: stationId, q_index: qIndex, letra: letra })
                        });
                        const data = await r.json();
                        if (!data) throw new Error('sin respuesta');

                        if (data.blocked) {
                            lockForm('🚫 Has agotado los intentos disponibles.');
                            setTimeout(function(){ location.reload(); }, 1200);
                            return;
                        }

                        pintarBoton(btn, !!data.en_palabra);
                        if (data.en_palabra) {
                            revelarLetra(letra);
                        } else if (erroneasEl && Array.isArray(data.missed)) {
                            erroneasEl.textContent = data.missed.join(' ');
                        }

                        // Sincronizar contadores
                        if (data.state) {
                            if (typeof data.state.attempts_left === 'number' && intentosLabel) {
                                intentosRestantes = data.state.attempts_left;
                                intentosLabel.textContent = String(Math.max(0, intentosRestantes));
                                if (intentosRestantes <= 1) {
                                    intentosLabel.style.color = '#dc2626';
                                    intentosLabel.style.fontWeight = '700';
                                }
                            }
                        }

                        // Si ha completado la palabra → enviar para validar y otorgar puntos
                        if (data.palabra_completa) {
                            if (hiddenAns) {
                                // Reconstruir la palabra original a partir de los huecos descubiertos
                                let palabraStr = '';
                                palabraWrap.querySelectorAll('.gc-ahorcado-hueco').forEach(function(h){
                                    palabraStr += (h.dataset.original || h.textContent || '');
                                });
                                hiddenAns.value = palabraStr.trim();
                            }
                            form.dispatchEvent(new Event('submit', {cancelable:true}));
                        } else if (data.state && data.state.blocked) {
                            // Sin más intentos
                            lockForm('🚫 Has agotado los intentos disponibles.');
                            setTimeout(function(){ location.reload(); }, 1200);
                        }
                    } catch (err) {
                        btn.disabled = false;
                        if (msg) msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Error: ' + err.message + '</div>';
                    }
                });
            }

            // Resolver palabra completa (atajo)
            if (resolverEl) {
                resolverEl.addEventListener('keydown', function(e){
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (hiddenAns) hiddenAns.value = (resolverEl.value || '').trim();
                        form.dispatchEvent(new Event('submit', {cancelable:true}));
                    }
                });
            }
        }

        form.addEventListener('submit', async function(e){
            e.preventDefault();

            const mode = form.dataset.mode || 'multiple';
            let payloadAnswer = null;

            if (mode === 'texto' || mode === 'cifrado_cesar' || mode === 'anagrama' || mode === 'ahorcado') {
                const txt = (form.querySelector('input[name="gc_station_answer"]') || {}).value || '';
                if (!txt.trim()) {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Escribe tu respuesta.</div>';
                    return;
                }
                payloadAnswer = txt.trim();
            } else if (mode === 'sopa_letras') {
                const sel = (form.querySelector('input[name="gc_station_answer"]') || {}).value || '';
                if (!sel) {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Selecciona la palabra en el grid.</div>';
                    return;
                }
                payloadAnswer = sel; // JSON con [[r,c],...]
            } else {
                const checked = form.querySelector('input[name="gc_station_answer"]:checked');
                if (!checked) {
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">Selecciona una respuesta.</div>';
                    return;
                }
                payloadAnswer = parseInt(checked.value, 10);
            }
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
                        answers: [payloadAnswer],
                        time_ms: timeMs,
                        q_index: qIndex
                    })
                });

                const data1 = await res1.json();

                // Si el server devolvió 'blocked', recargamos para mostrar la pantalla de bloqueo
                if (data1 && data1.blocked) {
                    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
                    var reasonMsg = data1.blocked_reason === 'time'
                        ? '⌛ Se acabó el tiempo. Esta pregunta ya no se puede responder.'
                        : '🚫 Has agotado los intentos disponibles.';
                    lockForm(reasonMsg);
                    setTimeout(function(){ location.reload(); }, 1500);
                    return;
                }

                if (!data1 || !data1.ok) {
                    // Sincronizar contador de intentos con server (fuente única de verdad)
                    if (data1 && data1.state && typeof data1.state.attempts_left === 'number') {
                        intentosRestantes = data1.state.attempts_left;
                    } else {
                        intentosRestantes -= 1;
                    }
                    if (intentosLabel) {
                        intentosLabel.textContent = String(Math.max(0, intentosRestantes));
                        if (intentosRestantes <= 1) {
                            intentosLabel.style.color = '#dc2626';
                            intentosLabel.style.fontWeight = '700';
                        }
                    }
                    if (intentosRestantes <= 0) {
                        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
                        lockForm('❌ Respuesta incorrecta. Sin intentos disponibles.');
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">❌ Respuesta incorrecta. Te quedan <strong>' + intentosRestantes + '</strong> intento' + (intentosRestantes === 1 ? '' : 's') + '.</div>';
                    }
                    return;
                }

                // Acierto: parar contador
                if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }

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
                    <?php
                    $tipo_qr_redirect = get_post_meta($escenario_id, 'gc_tipo_qr', true) ?: 'enlace';
                    $escenario_url_js = get_permalink($escenario_id) ?: home_url('/');
                    $img_acierto_url = get_post_meta($escenario_id, 'gc_img_acierto', true);
                    $acierto_html = $img_acierto_url
                        ? '<img src="' . esc_url($img_acierto_url) . '" alt="" style="max-width:100%;height:auto;border-radius:12px;margin-bottom:12px;" />'
                        : '';
                    ?>
                    <?php if ($tipo_qr_redirect === 'validacion_gps'): ?>
                    // GPS: mostrar resumen en la misma página
                    var wrap = document.querySelector('.gc-adult-station');
                    if (wrap) {
                        wrap.innerHTML = '<div style="padding:24px 20px;border-radius:14px;background:#f7fff7;border:2px solid #16a34a;text-align:center;">'
                            + <?php echo json_encode($acierto_html); ?>
                            + '<p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Ubicación verificada</p>'
                            + '<p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Desafío completado — ' + pts + ' puntos</p>'
                            + '<a href="<?php echo esc_url($escenario_url_js); ?>" style="display:inline-block;margin-top:16px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>'
                            + '</div>';
                    }
                    <?php else: ?>
                    msg.innerHTML = '<div style="padding:14px 16px;border-radius:12px;background:#ecfdf3;border:1px solid #b7ebc6;color:#146c2e;"><?php echo $acierto_html ? addslashes($acierto_html) : ''; ?>✅ Respuesta correcta. Has conseguido <strong>' + pts + ' puntos</strong>.</div>';
                    setTimeout(function(){
                        window.location.href = <?php echo json_encode($escenario_url_js); ?>;
                    }, 1800);
                    <?php endif; ?>
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
    $esc_title   = get_the_title($escenario_id);
    $orden       = (int) get_post_meta($station_id, 'gc_orden', true);
    $descripcion = get_post_meta($station_id, 'gc_descripcion', true);
    $bg_inline   = function_exists('gc_bg_featured_inline') ? gc_bg_featured_inline($escenario_id) : '';
    $audio       = get_post_meta($station_id, 'gc_audio', true);
    $maps_url    = get_post_meta($station_id, 'gc_maps_url', true);
    $direccion   = get_post_meta($station_id, 'gc_direccion', true);
    $img1        = get_post_meta($station_id, 'gc_img_1', true);
    $img2        = get_post_meta($station_id, 'gc_img_2', true);
    $img3        = get_post_meta($station_id, 'gc_img_3', true);
    $is_logged   = is_user_logged_in();

    $user_id     = get_current_user_id();

    // Helper para renderizar contenido visual (cabecera + iconos + descripcion + media)
    $render_content = function() use ($title, $esc_title, $orden, $descripcion, $audio, $maps_url, $direccion, $img1, $img2, $img3, $bg_inline) {
        echo '<h3 style="margin:0 0 6px;font-size:18px;font-weight:600;color:#2563eb;line-height:1.3;">' . esc_html($esc_title) . '</h3>';
        echo '<h2 style="margin:0 0 8px;font-size:22px;font-weight:700;line-height:1.3;">';
        if ($orden) echo '<span style="color:#64748b;">' . $orden . '.</span> ';
        echo esc_html($title) . '</h2>';
        echo gc_render_action_icons($audio, $maps_url, $direccion);
        if ($descripcion) {
            echo '<div class="gc-station-desc" style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;padding:16px;border-radius:12px;' . $bg_inline . '">';
            echo wp_kses_post(wpautop($descripcion));
            echo '</div>';
        }
        echo '<!-- gincana_estacion_contenido v' . (defined('GINCANA_CORE_VERSION') ? GINCANA_CORE_VERSION : '?') . ' img1=' . ($img1 ? 'set' : 'empty') . ' img2=' . ($img2 ? 'set' : 'empty') . ' img3=' . ($img3 ? 'set' : 'empty') . ' -->';
        if ($img1 || $img2 || $img3) {
            echo '<div style="display:flex;flex-direction:column;gap:12px;margin:0 0 24px;">';
            if ($img1) echo '<img src="' . esc_url($img1) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
            if ($img2) echo '<img src="' . esc_url($img2) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
            if ($img3) echo '<img src="' . esc_url($img3) . '" alt="" style="width:100%;height:auto;border-radius:10px;">';
            echo '</div>';
        }
    };

    // Si ya la ha superado (solo si esta logueado)
    if ($is_logged && function_exists('gincana_user_passed') && gincana_user_passed($user_id, $station_id) ) {
        $escenario_url = get_permalink($escenario_id);
        $tipo_qr_check = get_post_meta($escenario_id, 'gc_tipo_qr', true) ?: 'enlace';
        ob_start();
        if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id);
        echo '<div class="gc-station-content gc-tema-esc-' . (int) $escenario_id . '" style="width:100%;max-width:100%;padding:16px 0;box-sizing:border-box;">';
        $render_content();
        echo '<div style="padding:20px;border:1px solid #e6f0e6;border-radius:14px;background:#f7fff7;text-align:center;">';
        if ($tipo_qr_check === 'validacion_gps') {
            echo '<p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Ubicación verificada</p>';
            if (gc_requiere_prueba($escenario_id)) {
                echo '<p style="margin:0 0 8px;font-size:18px;color:#146c2e;font-weight:600;">✅ Desafío completado</p>';
            }
        } else {
            $lbl = function_exists('gc_get_label_estacion') ? gc_get_label_estacion($escenario_id) : 'estación';
            echo '<p style="margin:0 0 12px;font-size:16px;">&#10003; Ya has completado este ' . esc_html($lbl) . '.</p>';
        }
        echo '<a href="' . esc_url($escenario_url) . '" style="display:inline-block;margin-top:8px;padding:12px 24px;border:0;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">Volver al escenario</a>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // Render completo
    ob_start();
    if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id);
    echo '<div class="gc-station-content gc-tema-esc-' . (int) $escenario_id . '" style="width:100%;max-width:100%;padding:16px 0;box-sizing:border-box;">';

    $render_content();

    // Si no esta logueado
    if (!$is_logged) {
        // Infantil: mostrar pista + CTA login
        if ($tipo_escenario === 'infantil') {
            echo gc_render_infantil_station_pista($station_id, $title, $escenario_id);
        }
        echo gc_render_login_o_guest($escenario_id, '¿Quieres participar en la gimkana?', 'Escribe tu nombre y empieza a jugar.');
    } else {
        // Logueado, acceso directo (sin QR)
        $tipo_qr = get_post_meta($escenario_id, 'gc_tipo_qr', true) ?: 'enlace';

        if ($tipo_qr === 'validacion_gps') {
            // GPS: mostrar verificación por geolocalización
            echo gc_render_station_gps($station_id, $title, $escenario_id);
        } elseif ($tipo_qr === 'solo_pregunta') {
            // Sin QR: el quiz es la validación. Mostrar pregunta directamente.
            echo gc_render_adulto_station($station_id, $title, $escenario_id);
        } elseif ($tipo_qr === 'validacion_boton' || $tipo_qr === 'validacion_boton_quiz' || $tipo_qr === 'validacion_quiz' || $tipo_qr === 'validacion') {
            // QR obligatorio: pista para buscar el QR físico
            echo gc_render_infantil_station_pista($station_id, $title, $escenario_id);
        } else {
            // QR como enlace: mostrar quiz/completar directamente
            if (gc_requiere_prueba($escenario_id)) {
                echo gc_render_adulto_station($station_id, $title, $escenario_id);
            } else {
                echo gc_render_adulto_station_sin_prueba($station_id, $title, $escenario_id);
            }
        }
    }

    if (function_exists('gc_render_footer_logos')) echo gc_render_footer_logos($escenario_id);
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
    } elseif ($type === 'warning') {
        $bg = '#fef3c7';
        $border = '#fcd34d';
        $color = '#92400e';
    }

    return '<div style="width:100%;margin:24px 0;padding:16px 18px;border:1px solid ' . esc_attr($border) . ';background:' . esc_attr($bg) . ';color:' . esc_attr($color) . ';border-radius:12px;">' . esc_html($message) . '</div>';
}