<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Helper para campos de medios con selector de biblioteca WP.
 * Encola wp.media en pantallas de edicion de estacion/escenario.
 */

// Encolar scripts de la media library en nuestros CPTs
add_action('admin_enqueue_scripts', function($hook){
  // CPTs: estacion, escenario, prueba
  $is_cpt = false;
  if (in_array($hook, ['post.php', 'post-new.php'])) {
    $screen = get_current_screen();
    if ($screen && in_array($screen->post_type, ['estacion', 'escenario', 'prueba'])) {
      $is_cpt = true;
    }
  }
  // Página de ajustes del plugin (el hook contiene el slug del submenú)
  $is_settings = (strpos($hook, 'gincana-settings') !== false);

  if (!$is_cpt && !$is_settings) return;

  wp_enqueue_media();
  wp_enqueue_script('jquery');

  wp_add_inline_script('jquery', '
    jQuery(document).on("click", ".gc-media-select", function(e){
      e.preventDefault();
      var btn = jQuery(this);
      var input = btn.siblings("input.gc-media-input");
      var preview = btn.siblings(".gc-media-preview");
      var type = btn.data("type") || "image";

      var frame = wp.media({
        title: type === "audio" ? "Seleccionar audio" : "Seleccionar imagen",
        library: { type: type },
        multiple: false
      });

      frame.on("select", function(){
        var attachment = frame.state().get("selection").first().toJSON();
        input.val(attachment.url);
        if (type === "image" && preview.length) {
          preview.html("<img src=\"" + attachment.url + "\" style=\"max-width:200px;max-height:120px;border-radius:8px;margin-top:8px;\">");
        } else if (type === "audio" && preview.length) {
          preview.html("<span style=\"color:#16a34a;margin-top:4px;display:inline-block;\">Audio seleccionado</span>");
        }
      });

      frame.open();
    });

    jQuery(document).on("click", ".gc-media-clear", function(e){
      e.preventDefault();
      var btn = jQuery(this);
      btn.siblings("input.gc-media-input").val("");
      btn.siblings(".gc-media-preview").html("");
    });
  ');
});

/**
 * Renderiza un campo de media con boton selector y preview.
 *
 * @param string $name     Nombre del campo (name/id del input)
 * @param string $value    Valor actual (URL)
 * @param string $type     Tipo de media: 'image' o 'audio'
 * @param string $label    Texto del boton
 */
function gc_render_media_field($name, $value, $type = 'image', $label = '') {
  if (!$label) {
    $label = ($type === 'audio') ? 'Seleccionar audio' : 'Seleccionar imagen';
  }

  $preview_html = '';
  if ($value && $type === 'image') {
    $preview_html = '<img src="' . esc_url($value) . '" style="max-width:200px;max-height:120px;border-radius:8px;margin-top:8px;">';
  } elseif ($value && $type === 'audio') {
    $preview_html = '<span style="color:#16a34a;margin-top:4px;display:inline-block;">Audio seleccionado</span>';
  }

  ?>
  <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
    <input type="text" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>"
           value="<?php echo esc_attr($value); ?>" style="flex:1;min-width:200px;"
           class="gc-media-input regular-text" />
    <button type="button" class="button gc-media-select" data-type="<?php echo esc_attr($type); ?>">
      <?php echo esc_html($label); ?>
    </button>
    <?php if ($value): ?>
      <button type="button" class="button gc-media-clear" title="Quitar">&times;</button>
    <?php endif; ?>
  </div>
  <div class="gc-media-preview"><?php echo $preview_html; ?></div>
  <?php
}
