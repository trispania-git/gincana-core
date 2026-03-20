<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Permalinks Estación (MODO SEGURO Divi 5):
 * Estructura: /escenario/{escenario}/{estacion}/
 * Requiere post_meta: gc_escenario_ref (ID) en Estación.
 *
 * NOTA: Este modo evita reglas “sin prefijo” que chocan con previews del Theme Builder.
 * Cuando Divi 5 esté estable, podemos volver a intentar la versión sin prefijo.
 */

/**
 * REGISTRO DE REWRITES
 * - Siempre (front y admin), pero SOLO si los CPT ya existen.
 * - Prioridad alta (99) para ir después de CPT UI y evitar carreras.
 */
add_action('init', function(){

  if ( ! post_type_exists('estacion') || ! post_type_exists('escenario') ) {
    return; // CPT aún no disponibles (p.ej. carga temprana del builder)
  }

  // 1) Tag del escenario para permastruct
  add_rewrite_tag('%escenario%', '([^/]+)');

  // 2) Permastruct con prefijo fijo "/escenario/"
  // Resultado deseado: /escenario/{escenario}/{estacion}/
  add_permastruct('estacion', 'escenario/%escenario%/%postname%', [
    'with_front'   => false,
    'paged'        => false,
    'feed'         => false,
    'hierarchical' => false,
  ]);

  // 3) Regla genérica con prefijo fijo
  add_rewrite_rule(
    '^escenario/([^/]+)/([^/]+)/?$',         // /escenario/{escenario}/{estacion}
    'index.php?post_type=estacion&name=$matches[2]',
    'top'
  );

  // 4) Regla LEGACY: /estacion/{slug} → resolver para 301
  add_rewrite_rule(
    '^estacion/([^/]+)/?$',                  // antiguo /estacion/{slug}
    'index.php?post_type=estacion&name=$matches[1]&gincana_legacy=1',
    'top'
  );

}, 99);

// Query var para legacy
add_filter('query_vars', function($vars){
  $vars[] = 'gincana_legacy';
  return $vars;
});

// ENLACE de Estación (no tocar en admin/builder)
add_filter('post_type_link', function($permalink, $post, $leavename = false, $sample = false){
  if ($post->post_type !== 'estacion') return $permalink;

  // No reemplazar nada en admin/Builder para no romper previews
  if ( is_admin() || (function_exists('gincana_is_divi_builder') && gincana_is_divi_builder()) ) {
    return $permalink;
  }

  // Obtener slug del escenario desde post_meta
  $esc_slug = 'escenario';
  $esc_id = (int) get_post_meta($post->ID, 'gc_escenario_ref', true);
  if ($esc_id) {
    $esc = get_post($esc_id);
    if ($esc && $esc->post_type === 'escenario') {
      $esc_slug = $esc->post_name;
    }
  }

  // Sustituir %escenario%
  $permalink = str_replace('%escenario%', $esc_slug, $permalink);

  // Si WP dejó %postname% (sample/borrador), usar slug real
  if (strpos($permalink, '%postname%') !== false) {
    $post_slug = $post->post_name ?: sanitize_title($post->post_title);
    $permalink = str_replace('%postname%', $post_slug, $permalink);
  }

  return $permalink;
}, 10, 4);

// 301 LEGACY (bypass admin/REST/Builder)
add_action('template_redirect', function(){
  if ( is_admin() || (defined('REST_REQUEST') && REST_REQUEST) ) return;
  if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) return;

  // Si venimos por /estacion/{slug}, redirige al permalink nuevo
  if ( get_query_var('gincana_legacy') ) {
    if ( is_singular('estacion') ) {
      global $post;
      if ($post) { wp_redirect( get_permalink($post), 301 ); exit; }
    }
    return;
  }
});
