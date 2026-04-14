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
 * No requieren crear páginas WP: se renderizan interceptando parse_request.
 * Esto evita conflictos con la rewrite rule de estaciones que usa el
 * mismo patrón /escenario/{esc}/{est}/.
 */

// === 1. Interceptar en parse_request (antes de que WP haga la query) ===
add_action('parse_request', function ($wp) {
    $path = trim($wp->request, '/');

    // Comprobar patrón: escenario/{slug}/(ranking|instrucciones|puntuaciones)
    if ( ! preg_match('#^escenario/([^/]+)/(ranking|instrucciones|puntuaciones)$#', $path, $m) ) {
        return;
    }

    $esc_slug = $m[1];
    $subpage  = $m[2];

    // Buscar el escenario por slug
    $escenario = get_page_by_path($esc_slug, OBJECT, 'escenario');
    if ( ! $escenario || $escenario->post_status !== 'publish' ) {
        return; // dejar que WP devuelva 404 normal
    }

    // Guardar en query_vars para que template_redirect lo recoja
    $wp->query_vars = [
        'post_type'  => 'escenario',
        'name'       => $esc_slug,
        'gc_subpage' => $subpage,
    ];
});

// Registrar query var
add_filter('query_vars', function ($vars) {
    $vars[] = 'gc_subpage';
    return $vars;
});

// === 2. Renderizar en template_redirect ===
add_action('template_redirect', function () {
    $subpage = get_query_var('gc_subpage');
    if ( ! $subpage ) return;
    if ( ! in_array($subpage, ['ranking', 'instrucciones', 'puntuaciones'], true) ) return;

    // Obtener el escenario de la query
    global $wp_query;
    $escenario = $wp_query->get_queried_object();

    // Fallback: buscar por slug si el queried object no es escenario
    if ( ! $escenario || $escenario->post_type !== 'escenario' ) {
        $esc_name = get_query_var('name');
        if ($esc_name) {
            $escenario = get_page_by_path($esc_name, OBJECT, 'escenario');
        }
    }

    if ( ! $escenario ) {
        status_header(404);
        return;
    }

    gc_render_virtual_page($escenario->ID, $subpage);
    exit;
});

/**
 * Renderiza una "página virtual" limpia: solo wp_head/wp_footer para CSS/JS,
 * sin header ni footer de Divi. Incluye el header-nav de gincana.
 */
function gc_render_virtual_page($escenario_id, $type) {

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

    // Título del documento
    add_filter('document_title_parts', function ($parts) use ($page_title) {
        $parts['title'] = $page_title;
        return $parts;
    });

    // Renderizar shortcode
    $content = do_shortcode($shortcode);

    // Header nav de gincana (si existe)
    $header_nav = '';
    if (shortcode_exists('gincana_header_nav')) {
        $header_nav = do_shortcode('[gincana_header_nav escenario="' . (int) $escenario_id . '"]');
    }

    status_header(200);
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
<style>
  body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; }
  .gc-virtual-page { max-width: 860px; margin: 0 auto; padding: 16px; }
</style>
</head>
<body class="gc-virtual-body">
<?php echo $header_nav; ?>
<div class="gc-virtual-page">
  <?php echo $content; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
    <?php
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

// === 3. Limpieza: eliminar páginas WP de ranking antiguas (una sola vez) ===
add_action('admin_init', function () {
    if (get_option('gc_legacy_ranking_pages_cleaned')) return;

    $legacy_pages = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_key'       => '_gc_ranking_escenario_id',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    foreach ($legacy_pages as $page_id) {
        wp_trash_post($page_id);
    }

    // Limpiar meta gc_ranking_url de todos los escenarios
    $escenarios = get_posts([
        'post_type'      => 'escenario',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    foreach ($escenarios as $esc_id) {
        delete_post_meta($esc_id, 'gc_ranking_url');
    }

    update_option('gc_legacy_ranking_pages_cleaned', '1');
});
