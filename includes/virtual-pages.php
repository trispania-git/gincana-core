<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Rutas virtuales para escenarios: ranking, instrucciones, puntuaciones.
 *
 * URLs:
 *   /escenario/{slug}/ranking/
 *   /escenario/{slug}/instrucciones/
 *   /escenario/{slug}/puntuaciones/
 *
 * No requieren crear páginas WP: se renderizan en template_redirect.
 */

// === 1. Registrar query var y rewrite rules ===
add_action('init', function () {
    if ( ! post_type_exists('escenario') ) return;

    add_rewrite_rule(
        '^escenario/([^/]+)/(ranking|instrucciones|puntuaciones)/?$',
        'index.php?post_type=escenario&name=$matches[1]&gc_subpage=$matches[2]',
        'top'
    );
}, 100); // después de CPT y permalinks

add_filter('query_vars', function ($vars) {
    $vars[] = 'gc_subpage';
    return $vars;
});

// === 2. Interceptar y renderizar ===
add_action('template_redirect', function () {
    $subpage = get_query_var('gc_subpage');
    if ( ! $subpage ) return;
    if ( ! in_array($subpage, ['ranking', 'instrucciones', 'puntuaciones'], true) ) return;

    // Necesitamos el escenario
    global $post;
    if ( ! $post || $post->post_type !== 'escenario' ) {
        status_header(404);
        return;
    }

    $escenario_id = $post->ID;

    // Renderizar página virtual con el theme activo
    switch ($subpage) {
        case 'ranking':
            gc_render_virtual_page($escenario_id, 'ranking');
            break;
        case 'instrucciones':
            gc_render_virtual_page($escenario_id, 'instrucciones');
            break;
        case 'puntuaciones':
            gc_render_virtual_page($escenario_id, 'puntuaciones');
            break;
    }
    exit;
});

/**
 * Renderiza una "página virtual" usando el theme activo (header + footer de WP/Divi).
 *
 * Suplanta $post con una página falsa para que Divi use su layout base.
 */
function gc_render_virtual_page($escenario_id, $type) {
    global $wp_query, $post;

    $titles = [
        'ranking'        => 'Ranking',
        'instrucciones'  => 'Instrucciones',
        'puntuaciones'   => 'Puntuaciones',
    ];

    $shortcodes = [
        'ranking'        => '[gincana_ranking escenario="' . (int) $escenario_id . '"]',
        'instrucciones'  => '[gincana_instrucciones escenario="' . (int) $escenario_id . '"]',
        'puntuaciones'   => '[gincana_puntuaciones escenario="' . (int) $escenario_id . '"]',
    ];

    $esc_title  = get_the_title($escenario_id);
    $page_title = ($titles[$type] ?? ucfirst($type)) . ' — ' . $esc_title;
    $shortcode  = $shortcodes[$type] ?? '';

    // Crear un post virtual para que el theme lo renderice
    $virtual = new stdClass();
    $virtual->ID            = 0;
    $virtual->post_author   = 1;
    $virtual->post_date     = current_time('mysql');
    $virtual->post_date_gmt = current_time('mysql', 1);
    $virtual->post_content  = $shortcode;
    $virtual->post_title    = $page_title;
    $virtual->post_excerpt  = '';
    $virtual->post_status   = 'publish';
    $virtual->comment_status = 'closed';
    $virtual->ping_status   = 'closed';
    $virtual->post_password = '';
    $virtual->post_name     = $type;
    $virtual->to_ping       = '';
    $virtual->pinged        = '';
    $virtual->post_modified     = current_time('mysql');
    $virtual->post_modified_gmt = current_time('mysql', 1);
    $virtual->post_content_filtered = '';
    $virtual->post_parent   = 0;
    $virtual->guid          = '';
    $virtual->menu_order    = 0;
    $virtual->post_type     = 'page';
    $virtual->post_mime_type = '';
    $virtual->comment_count = 0;
    $virtual->filter        = 'raw';

    // Suplantar globals
    $post = new WP_Post($virtual);
    $wp_query->post  = $post;
    $wp_query->posts = [$post];
    $wp_query->found_posts    = 1;
    $wp_query->post_count     = 1;
    $wp_query->max_num_pages  = 1;
    $wp_query->is_page        = true;
    $wp_query->is_singular    = true;
    $wp_query->is_single      = false;
    $wp_query->is_attachment  = false;
    $wp_query->is_archive     = false;
    $wp_query->is_category    = false;
    $wp_query->is_tag         = false;
    $wp_query->is_tax         = false;
    $wp_query->is_author      = false;
    $wp_query->is_date        = false;
    $wp_query->is_year        = false;
    $wp_query->is_month       = false;
    $wp_query->is_day         = false;
    $wp_query->is_time        = false;
    $wp_query->is_search      = false;
    $wp_query->is_feed        = false;
    $wp_query->is_comment_feed = false;
    $wp_query->is_trackback   = false;
    $wp_query->is_home        = false;
    $wp_query->is_404         = false;
    $wp_query->is_embed       = false;
    $wp_query->is_paged       = false;

    // Título del documento
    add_filter('document_title_parts', function ($parts) use ($page_title) {
        $parts['title'] = $page_title;
        return $parts;
    });

    // Cargar la plantilla de página del theme
    $template = get_page_template();
    if ( ! $template ) {
        $template = get_index_template();
    }
    if ( ! $template ) {
        $template = ABSPATH . WPINC . '/template-canvas.php';
    }

    include $template;
    exit;
}

/**
 * Helper: genera la URL de una subpágina virtual de un escenario.
 *
 * @param int    $escenario_id  ID del escenario
 * @param string $subpage       'ranking', 'instrucciones' o 'puntuaciones'
 * @return string               URL completa
 */
function gc_escenario_subpage_url($escenario_id, $subpage) {
    $esc_permalink = get_permalink($escenario_id);
    return trailingslashit($esc_permalink) . $subpage . '/';
}
