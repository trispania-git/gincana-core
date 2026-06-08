<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Mobile Gate: bloquea acceso desde escritorio en el frontend.
 *
 * - Solo actúa si la opción gc_mobile_only está activada.
 * - Se puede anular completamente con gc_desktop_test_mode (modo testeo).
 * - No afecta: admin, REST API, AJAX, login, cron, CLI, feeds.
 * - URL secreta configurable para bypass (setea cookie de 30 días).
 * - Detección: User-Agent (PHP).
 */

// Aviso visible en todas las pantallas admin cuando el modo test está activo,
// para que no se olvide de desactivarlo antes de salir a producción.
add_action('admin_notices', function () {
    if (get_option('gc_desktop_test_mode', '0') !== '1') return;
    if (!current_user_can('manage_options')) return;
    $settings_url = admin_url('admin.php?page=gincana-settings');
    echo '<div class="notice notice-warning" style="border-left-color:#dc2626;background:#fef2f2;">'
        . '<p style="font-weight:600;">🧪 <strong>Gincana Core:</strong> el modo «Escritorio para test» está ACTIVO. '
        . 'La restricción «solo móvil» queda anulada para todos los visitantes. '
        . '<a href="' . esc_url($settings_url) . '">Desactívalo</a> antes de pasar a producción.</p>'
        . '</div>';
});

// Banner sticky en el FRONTEND mientras el modo test está activo, para que
// cualquier visitante sepa que la web está en modo prueba.
add_action('wp_body_open', 'gc_render_test_mode_banner');
// Fallback por si el tema no llama wp_body_open
add_action('wp_footer', 'gc_render_test_mode_banner_footer_fallback', 1);

function gc_render_test_mode_banner() {
    if (get_option('gc_desktop_test_mode', '0') !== '1') return;
    if (is_admin()) return;
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <div id="gc-test-mode-banner" style="position:sticky;top:0;left:0;right:0;z-index:100000;background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#78350f;text-align:center;padding:8px 40px 8px 14px;font-size:13px;font-weight:600;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;box-shadow:0 2px 6px rgba(0,0,0,0.15);">
        🧪 MODO PRUEBA ACTIVADO — esta web está temporalmente abierta a escritorio
        <button type="button" onclick="this.parentNode.style.display='none';" aria-label="Cerrar" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.15);border:0;color:#78350f;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:14px;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;">×</button>
    </div>
    <?php
}

function gc_render_test_mode_banner_footer_fallback() {
    if (get_option('gc_desktop_test_mode', '0') !== '1') return;
    if (is_admin()) return;
    // Si wp_body_open ya pintó el banner, no duplicar
    ?>
    <script>
    (function(){
        if (document.getElementById('gc-test-mode-banner')) return;
        var b = document.createElement('div');
        b.id = 'gc-test-mode-banner';
        b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:100000;background:linear-gradient(90deg,#fbbf24,#f59e0b);color:#78350f;text-align:center;padding:8px 40px 8px 14px;font-size:13px;font-weight:600;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;box-shadow:0 2px 6px rgba(0,0,0,0.15);';
        b.innerHTML = '🧪 MODO PRUEBA ACTIVADO — esta web está temporalmente abierta a escritorio'
            + '<button type="button" aria-label="Cerrar" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.15);border:0;color:#78350f;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:14px;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;">×</button>';
        b.querySelector('button').onclick = function(){ b.style.display = 'none'; };
        document.body.insertBefore(b, document.body.firstChild);
    })();
    </script>
    <?php
}

// === 1. Interceptar la URL secreta de bypass (antes de template_redirect) ===
add_action('init', function () {
    if (get_option('gc_mobile_only', '0') !== '1') return;

    $bypass_slug = get_option('gc_mobile_bypass_slug', 'accesogymk');
    if (empty($bypass_slug)) return;

    // Detectar si la URL actual coincide con el slug de bypass
    $request_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    if ($request_path === $bypass_slug) {
        // Setear cookie de bypass (30 días)
        setcookie('gc_desktop_bypass', '1', time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['gc_desktop_bypass'] = '1'; // Disponible de inmediato

        // Redirigir a home o wp-admin
        wp_safe_redirect(admin_url());
        exit;
    }
});

/**
 * ¿La petición actual es una página de la gymkana (escenario, estación,
 * acceso por QR o subpágina virtual ranking/instrucciones/puntuaciones)?
 * El aviso "solo móvil" únicamente se aplica a estas páginas; el resto del
 * sitio (home, contacto, etc.) queda libre para escritorio.
 */
function gc_is_gincana_page() {
    // CPTs de la gymkana
    if (is_singular('escenario') || is_singular('estacion')) return true;
    // Subpágina virtual (ranking / instrucciones / puntuaciones)
    if (get_query_var('gc_subpage')) return true;
    // Acceso por QR (?gc_station / ?gc_token) o la página de acceso
    if (isset($_GET['gc_station']) || isset($_GET['gc_token'])) return true;
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if ($uri !== '' && strpos($uri, 'acceso-estacion') !== false) return true;
    return false;
}

// === 2. Bloqueo en template_redirect (solo frontend) ===
add_action('template_redirect', function () {

    // Solo si está activado
    if (get_option('gc_mobile_only', '0') !== '1') return;

    // Modo test escritorio: anula la restricción para todos los visitantes
    if (get_option('gc_desktop_test_mode', '0') === '1') return;

    // No bloquear admin, REST, AJAX, cron, CLI
    if (is_admin()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('DOING_CRON') && DOING_CRON) return;
    if (defined('WP_CLI') && WP_CLI) return;
    if (is_feed()) return;

    // El aviso solo aplica a páginas de la gymkana (no a home, contacto, etc.)
    if (!gc_is_gincana_page()) return;

    // No bloquear login/registro (doble check por URI)
    global $pagenow;
    if (in_array($pagenow, ['wp-login.php', 'wp-register.php'], true)) return;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'wp-login') !== false || strpos($uri, 'wp-admin') !== false) return;

    // Cookie de bypass: si la tiene, dejar pasar
    if (!empty($_COOKIE['gc_desktop_bypass'])) return;

    // Usuarios logueados con rol admin/editor: dejar pasar siempre
    if (is_user_logged_in() && current_user_can('edit_posts')) return;

    // Detectar móvil/tablet por User-Agent
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (gc_is_mobile_ua($ua)) return;

    // Es escritorio sin bypass: mostrar página de bloqueo
    gc_render_mobile_only_page();
    exit;
});

/**
 * Detecta si el User-Agent es de un dispositivo móvil o tablet.
 */
function gc_is_mobile_ua($ua) {
    if (empty($ua)) return false;

    $mobile_patterns = [
        'Mobile', 'Android', 'iPhone', 'iPad', 'iPod',
        'webOS', 'BlackBerry', 'Opera Mini', 'Opera Mobi',
        'IEMobile', 'Windows Phone', 'Silk', 'Kindle',
    ];

    foreach ($mobile_patterns as $pattern) {
        if (stripos($ua, $pattern) !== false) return true;
    }

    return false;
}

/**
 * Renderiza la página de "solo móvil".
 */
function gc_render_mobile_only_page() {
    $custom_msg = get_option('gc_mobile_only_message', '');
    $message = $custom_msg ?: 'Esta experiencia ha sido diseñada para disfrutarse desde tu teléfono móvil. Escanea el código QR o abre esta web desde tu smartphone para participar.';
    $custom_img = get_option('gc_mobile_only_image', '');
    $site_name = get_bloginfo('name');
    $site_url = home_url('/');

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> — Solo para móvil</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 50%, #bfdbfe 100%);
            color: #1e293b;
            padding: 32px;
        }
        .gc-mobile-gate {
            max-width: 520px;
            text-align: center;
            background: #fff;
            border-radius: 24px;
            padding: 48px 36px;
            box-shadow: 0 8px 32px rgba(37,99,235,0.12);
        }
        .gc-mobile-gate .gc-icon { margin-bottom: 24px; }
        .gc-mobile-gate h1 { font-size: 24px; font-weight: 700; margin-bottom: 12px; color: #1e293b; }
        .gc-mobile-gate .gc-subtitle { font-size: 14px; font-weight: 600; color: #2563eb; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; }
        .gc-mobile-gate p { font-size: 16px; line-height: 1.6; color: #475569; margin-bottom: 24px; }
        .gc-mobile-gate .gc-qr-hint { display: inline-flex; align-items: center; gap: 10px; padding: 14px 24px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; font-size: 14px; color: #64748b; }
        .gc-mobile-gate .gc-url { margin-top: 20px; font-size: 13px; color: #94a3b8; word-break: break-all; }
    </style>
</head>
<body>
    <div class="gc-mobile-gate">
        <div class="gc-icon">
            <?php if ($custom_img): ?>
                <img src="<?php echo esc_url($custom_img); ?>" alt="" style="max-width:100%;height:auto;border-radius:12px;">
            <?php else: ?>
                <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                    <line x1="12" y1="18" x2="12.01" y2="18"/>
                </svg>
            <?php endif; ?>
        </div>
        <div class="gc-subtitle"><?php echo esc_html($site_name); ?></div>
        <h1>Experiencia solo para móvil</h1>
        <p><?php echo wp_kses_post(nl2br($message)); ?></p>
        <div class="gc-qr-hint">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>
            </svg>
            Abre la web desde tu smartphone
        </div>
        <div class="gc-url"><?php echo esc_html($site_url); ?></div>
    </div>
</body>
</html>
    <?php
}
