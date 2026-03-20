<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcode: [gincana_header]
 *
 * Barra de navegación para la cabecera de la plantilla.
 * Tres iconos: Inicio (escenario) | Usuario (login/nombre) | Salir
 *
 * Para usar en la plantilla Theme Builder (escenario y estacion).
 */
add_shortcode('gincana_header', function($atts){

    // Placeholder para Divi Builder
    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
        return '<div style="padding:12px 16px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;text-align:center;">
            <strong>Gincana — Header Nav</strong><br><small>(Vista previa del builder)</small>
        </div>';
    }

    $a = shortcode_atts([
        'escenario'  => '',
        'login_url'  => '',
        'home_url'   => '',
    ], $atts);

    // Resolver escenario
    $escenario_id = (int)$a['escenario'];
    if (!$escenario_id) {
        $ctx = get_the_ID();
        if ($ctx) {
            if (get_post_type($ctx) === 'escenario') {
                $escenario_id = (int)$ctx;
            } elseif (get_post_type($ctx) === 'estacion') {
                $escenario_id = (int) get_post_meta($ctx, 'gc_escenario_ref', true);
            }
        }
    }

    // URLs
    $escenario_url = $escenario_id ? get_permalink($escenario_id) : ($a['home_url'] ?: home_url('/'));
    $escenario_name = $escenario_id ? get_the_title($escenario_id) : 'Inicio';

    $login_url = $a['login_url'];
    if (!$login_url) {
        $login_page = get_page_by_path('acceso');
        $login_url = $login_page ? get_permalink($login_page) : wp_login_url();
    }

    $is_logged = is_user_logged_in();
    $user = $is_logged ? wp_get_current_user() : null;
    $display_name = $user ? ($user->display_name ?: $user->user_login) : '';
    $logout_url = wp_logout_url(get_permalink());
    $current_url = get_permalink();
    $login_url_with_redirect = add_query_arg('redirect_to', urlencode($current_url), $login_url);

    $uid = 'gc-hdr-' . uniqid();

    ob_start();
    ?>
    <style>
    #<?php echo $uid; ?> {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 95%;
        max-width: 760px;
        margin: 0 auto;
        padding: 10px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    #<?php echo $uid; ?> a {
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        color: #334155;
        font-size: 13px;
        font-weight: 500;
        padding: 8px 12px;
        border-radius: 8px;
        transition: background 0.2s;
    }
    #<?php echo $uid; ?> a:hover {
        background: #f1f5f9;
    }
    #<?php echo $uid; ?> svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    #<?php echo $uid; ?> .gc-hdr-user {
        color: #2563eb;
        font-weight: 600;
    }
    #<?php echo $uid; ?> .gc-hdr-logout {
        color: #94a3b8;
    }
    #<?php echo $uid; ?> .gc-hdr-name {
        max-width: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    @media (max-width: 380px) {
        #<?php echo $uid; ?> a { padding: 8px; font-size: 12px; }
        #<?php echo $uid; ?> .gc-hdr-name { max-width: 70px; }
    }
    </style>

    <nav id="<?php echo esc_attr($uid); ?>" role="navigation" aria-label="Navegacion gimkana">

        <!-- Inicio / Escenario -->
        <a href="<?php echo esc_url($escenario_url); ?>" title="<?php echo esc_attr($escenario_name); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Inicio</span>
        </a>

        <!-- Usuario -->
        <?php if ($is_logged): ?>
            <span class="gc-hdr-user" style="display:flex;align-items:center;gap:6px;font-size:13px;padding:8px 12px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span class="gc-hdr-name"><?php echo esc_html($display_name); ?></span>
            </span>
        <?php else: ?>
            <a href="<?php echo esc_url($login_url_with_redirect); ?>" class="gc-hdr-user" title="Iniciar sesion">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Acceder</span>
            </a>
        <?php endif; ?>

        <!-- Salir -->
        <?php if ($is_logged): ?>
            <a href="<?php echo esc_url($logout_url); ?>" class="gc-hdr-logout" title="Cerrar sesion">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1-2 2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Salir</span>
            </a>
        <?php else: ?>
            <span style="width:70px;"></span>
        <?php endif; ?>

    </nav>
    <?php
    return ob_get_clean();
});
