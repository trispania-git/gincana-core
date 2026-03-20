<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Bloquear acceso al escritorio de WP para suscriptores.
 * - Redirige /wp-admin/ a la home
 * - Oculta la barra de admin en el frontend
 */

// Ocultar barra de admin para suscriptores
add_action('after_setup_theme', function(){
    if (!current_user_can('edit_posts')) {
        show_admin_bar(false);
    }
});

// Redirigir wp-admin a home para suscriptores
add_action('admin_init', function(){
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;

    if (!current_user_can('edit_posts')) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
});
