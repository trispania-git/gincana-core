<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Sistema de temas (apariencia) por escenario.
 *
 * Cada escenario puede tener un preset visual (Claro / Oscuro / Aventura /
 * Náutico / Bosque / Pastel / Personalizado) que se aplica a todas las
 * páginas relacionadas (escenario, estaciones, ranking, instrucciones,
 * puntuaciones, header-nav).
 */

/**
 * Devuelve los presets disponibles con sus colores por defecto.
 */
function gc_tema_presets() {
    return [
        'claro' => [
            'label'        => '🌞 Claro',
            'descripcion'  => 'Limpio y neutro, ideal para textos largos.',
            'body_bg'      => '#ffffff',
            'body_color'   => '#1e293b',
            'accent'       => '#2563eb',
            'accent_text'  => '#ffffff',
            'card_bg'      => '#ffffff',
            'card_border'  => '#e2e8f0',
            'muted'        => '#64748b',
            'header_bg'    => '#ffffff',
            'header_color' => '#1e293b',
            'header_border'=> '#e2e8f0',
        ],
        'oscuro' => [
            'label'        => '🌙 Oscuro',
            'descripcion'  => 'Modo oscuro elegante, fondo gris muy oscuro y texto claro.',
            'body_bg'      => '#0f172a',
            'body_color'   => '#e2e8f0',
            'accent'       => '#60a5fa',
            'accent_text'  => '#0f172a',
            'card_bg'      => '#1e293b',
            'card_border'  => '#334155',
            'muted'        => '#94a3b8',
            'header_bg'    => '#1e293b',
            'header_color' => '#e2e8f0',
            'header_border'=> '#334155',
        ],
        'aventura' => [
            'label'        => '🔥 Aventura',
            'descripcion'  => 'Tonos cálidos: crema, naranja y marrón. Para gymkanas con sabor a expedición.',
            'body_bg'      => '#fffbeb',
            'body_color'   => '#451a03',
            'accent'       => '#d97706',
            'accent_text'  => '#ffffff',
            'card_bg'      => '#ffffff',
            'card_border'  => '#fde68a',
            'muted'        => '#92400e',
            'header_bg'    => '#92400e',
            'header_color' => '#fef3c7',
            'header_border'=> '#78350f',
        ],
        'nautico' => [
            'label'        => '🌊 Náutico',
            'descripcion'  => 'Azules y blancos. Pensado para rutas marítimas, ríos o lagos.',
            'body_bg'      => '#eff6ff',
            'body_color'   => '#1e3a8a',
            'accent'       => '#0284c7',
            'accent_text'  => '#ffffff',
            'card_bg'      => '#ffffff',
            'card_border'  => '#bfdbfe',
            'muted'        => '#1d4ed8',
            'header_bg'    => '#1e40af',
            'header_color' => '#dbeafe',
            'header_border'=> '#1e3a8a',
        ],
        'bosque' => [
            'label'        => '🌲 Bosque',
            'descripcion'  => 'Verdes y tierra. Ideal para rutas naturales y senderos.',
            'body_bg'      => '#f0fdf4',
            'body_color'   => '#14532d',
            'accent'       => '#15803d',
            'accent_text'  => '#ffffff',
            'card_bg'      => '#ffffff',
            'card_border'  => '#bbf7d0',
            'muted'        => '#166534',
            'header_bg'    => '#166534',
            'header_color' => '#dcfce7',
            'header_border'=> '#14532d',
        ],
        'pastel' => [
            'label'        => '🌸 Pastel',
            'descripcion'  => 'Rosas y morados suaves. Para escenarios infantiles o festivos.',
            'body_bg'      => '#fdf4ff',
            'body_color'   => '#581c87',
            'accent'       => '#a855f7',
            'accent_text'  => '#ffffff',
            'card_bg'      => '#ffffff',
            'card_border'  => '#f5d0fe',
            'muted'        => '#7e22ce',
            'header_bg'    => '#a855f7',
            'header_color' => '#fdf4ff',
            'header_border'=> '#7e22ce',
        ],
    ];
}

/**
 * Devuelve el tema efectivo de un escenario (combinando preset + overrides).
 */
function gc_get_tema($escenario_id) {
    $escenario_id = (int) $escenario_id;
    $presets = gc_tema_presets();

    $preset_key = get_post_meta($escenario_id, 'gc_tema_preset', true);
    if (!$preset_key || (!isset($presets[$preset_key]) && $preset_key !== 'personalizado')) {
        $preset_key = 'claro';
    }

    if ($preset_key === 'personalizado') {
        $base = $presets['claro'];
    } else {
        $base = $presets[$preset_key];
    }

    // Solo si es personalizado o se ha indicado expresamente, leer overrides.
    $usar_header_propio = get_post_meta($escenario_id, 'gc_tema_header_propio', true) === '1';

    if ($preset_key === 'personalizado') {
        $keys_color = ['body_bg','body_color','accent','accent_text','card_bg','card_border','muted'];
        foreach ($keys_color as $k) {
            $v = get_post_meta($escenario_id, 'gc_tema_' . $k, true);
            if ($v) $base[$k] = $v;
        }
    }

    if ($usar_header_propio) {
        $hk = ['header_bg','header_color','header_border'];
        foreach ($hk as $k) {
            $v = get_post_meta($escenario_id, 'gc_tema_' . $k, true);
            if ($v) $base[$k] = $v;
        }
    }

    $base['preset'] = $preset_key;
    $base['imagen_fondo'] = (string) get_post_meta($escenario_id, 'gc_tema_imagen_fondo', true);
    return $base;
}

/**
 * Devuelve un bloque <style> con las reglas CSS del tema del escenario.
 * Scope: se aplica solo dentro de `.gc-tema-esc-{id}`.
 *
 * Para evitar inyectar el mismo style dos veces si la misma página tiene
 * varios shortcodes, llevamos un registro estático.
 */
function gc_render_tema_style($escenario_id) {
    static $rendered = [];
    $escenario_id = (int) $escenario_id;
    if ($escenario_id <= 0) return '';
    if (isset($rendered[$escenario_id])) return '';
    $rendered[$escenario_id] = true;

    $t = gc_get_tema($escenario_id);
    if (!$t || ($t['preset'] === 'claro' && empty($t['imagen_fondo']) && get_post_meta($escenario_id, 'gc_tema_header_propio', true) !== '1')) {
        // El preset 'claro' por defecto y sin imagen ni header propio → no inyectar nada.
        return '';
    }

    $scope     = '.gc-tema-esc-' . $escenario_id;
    $body_cls  = 'gc-tema-body-esc-' . $escenario_id;
    $bg_img_rule = '';
    if (!empty($t['imagen_fondo'])) {
        $bg_img_rule = "background-image: linear-gradient({$t['body_bg']}EE, {$t['body_bg']}EE), url('" . esc_url($t['imagen_fondo']) . "');"
                     . 'background-size: cover; background-position: center center; background-attachment: fixed;';
    }

    ob_start();
    ?>
    <style id="gc-tema-esc-<?php echo (int) $escenario_id; ?>">
        /* Aplicar a TODA la página (body + html) cuando se añade la clase */
        body.<?php echo $body_cls; ?>,
        body.<?php echo $body_cls; ?> #page,
        body.<?php echo $body_cls; ?> #page-container,
        body.<?php echo $body_cls; ?> #main-content,
        body.<?php echo $body_cls; ?> .et-l,
        body.<?php echo $body_cls; ?> .et_pb_section {
            background-color: <?php echo esc_html($t['body_bg']); ?> !important;
            <?php echo $bg_img_rule; ?>
        }
        body.<?php echo $body_cls; ?> {
            color: <?php echo esc_html($t['body_color']); ?>;
        }
        body.<?php echo $body_cls; ?> p,
        body.<?php echo $body_cls; ?> li,
        body.<?php echo $body_cls; ?> span {
            color: inherit;
        }

        /* Wrap principal del shortcode */
        <?php echo $scope; ?> {
            background-color: transparent;
            color: <?php echo esc_html($t['body_color']); ?>;
        }
        <?php echo $scope; ?> .gc-escenario-content,
        <?php echo $scope; ?> .gc-station-content,
        <?php echo $scope; ?> .gc-station-access,
        <?php echo $scope; ?> .gincana-ranking,
        <?php echo $scope; ?> .gincana-instrucciones,
        <?php echo $scope; ?> .gincana-puntuaciones {
            color: <?php echo esc_html($t['body_color']); ?>;
        }
        <?php echo $scope; ?> h1,
        <?php echo $scope; ?> h2,
        <?php echo $scope; ?> h3,
        <?php echo $scope; ?> h4 {
            color: <?php echo esc_html($t['body_color']); ?>;
        }
        <?php echo $scope; ?> .gc-card {
            background-color: <?php echo esc_html($t['card_bg']); ?> !important;
            border-color: <?php echo esc_html($t['card_border']); ?> !important;
            color: <?php echo esc_html($t['body_color']); ?> !important;
        }
        <?php echo $scope; ?> .gc-card-title {
            color: <?php echo esc_html($t['body_color']); ?> !important;
        }
        <?php echo $scope; ?> .gincana-header-nav,
        <?php echo $scope; ?> .gc-header-nav,
        <?php echo $scope; ?> .gincana-header-wrap {
            background-color: <?php echo esc_html($t['header_bg']); ?> !important;
            color: <?php echo esc_html($t['header_color']); ?> !important;
            border-color: <?php echo esc_html($t['header_border']); ?> !important;
        }
        <?php echo $scope; ?> .gincana-header-nav a,
        <?php echo $scope; ?> .gincana-header-nav span,
        <?php echo $scope; ?> .gc-header-nav a,
        <?php echo $scope; ?> .gc-header-nav span {
            color: <?php echo esc_html($t['header_color']); ?> !important;
        }
        /* Tabla del ranking */
        <?php echo $scope; ?> .gincana-ranking-table {
            color: <?php echo esc_html($t['body_color']); ?>;
        }
        <?php echo $scope; ?> .gincana-ranking-table th,
        <?php echo $scope; ?> .gincana-ranking-table td {
            color: <?php echo esc_html($t['body_color']); ?> !important;
            border-color: <?php echo esc_html($t['card_border']); ?> !important;
        }
    </style>
    <script>
        // Añadir la clase al body para que el fondo se extienda a toda la pantalla
        (function(){
            try { document.body.classList.add(<?php echo wp_json_encode($body_cls); ?>); } catch(e){}
        })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Atajo: imprime el style del tema y abre un wrapper con la clase de scope.
 * Llamar al INICIO de cada render de shortcode relacionado con un escenario.
 * Hay que cerrar el wrapper con </div> después.
 */
function gc_open_tema_wrap($escenario_id, $extra_class = '') {
    echo gc_render_tema_style($escenario_id);
    $cls = 'gc-tema-esc-' . (int) $escenario_id . ($extra_class ? ' ' . $extra_class : '');
    echo '<div class="' . esc_attr($cls) . '">';
}
function gc_close_tema_wrap() {
    echo '</div>';
}

/**
 * Render de un campo de color con color-picker + input HEX sincronizado.
 * El input HEX se puede escribir manualmente (ej: #F0F8FF).
 *
 * @param string $name  Nombre del campo (el value se envía con este name).
 * @param string $value Valor actual del color (hex #xxxxxx).
 * @param string $label Etiqueta opcional sobre el campo.
 */
function gc_render_color_field($name, $value, $label = '') {
    $value = (string) $value;
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        $value = '#000000';
    }
    $uid = 'gccolor_' . md5($name);
    ?>
    <div class="gc-color-field">
        <?php if ($label): ?>
            <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px;color:#334155;"><?php echo esc_html($label); ?></label>
        <?php endif; ?>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="color"
                   id="<?php echo esc_attr($uid); ?>_picker"
                   value="<?php echo esc_attr($value); ?>"
                   data-gc-color-target="<?php echo esc_attr($uid); ?>_hex"
                   style="width:54px;height:42px;border-radius:8px;border:1px solid #cbd5e1;cursor:pointer;padding:2px;background:#fff;" />
            <input type="text"
                   id="<?php echo esc_attr($uid); ?>_hex"
                   name="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   data-gc-color-target="<?php echo esc_attr($uid); ?>_picker"
                   placeholder="#000000"
                   maxlength="7"
                   spellcheck="false"
                   autocomplete="off"
                   style="flex:1;min-width:0;padding:10px 12px;border-radius:8px;border:1px solid #cbd5e1;font-family:monospace;font-size:14px;text-transform:uppercase;" />
        </div>
    </div>
    <?php
}

/**
 * Devuelve el HTML del bloque de logos de pie de página del escenario.
 * - 1 logo  → centrado
 * - 2 logos → uno a la izquierda, otro a la derecha
 * - 3 logos → distribuidos (izquierda, centro, derecha)
 * Ancho ~90px por logo. Solo renderiza si hay al menos uno configurado.
 *
 * Se llama una vez por shortcode; si la misma página tiene varios shortcodes
 * del mismo escenario, solo se imprime uno (guard estático).
 */
function gc_render_footer_logos($escenario_id) {
    static $rendered = [];
    $escenario_id = (int) $escenario_id;
    if ($escenario_id <= 0) return '';
    if (isset($rendered[$escenario_id])) return '';

    $logos = [];
    for ($i = 1; $i <= 3; $i++) {
        $url = get_post_meta($escenario_id, 'gc_logo_' . $i, true);
        if ($url) $logos[] = $url;
    }
    if (empty($logos)) return '';
    $rendered[$escenario_id] = true;

    $count = count($logos);
    // Justificación: 1 centro, 2 extremos, 3 space-between
    $justify = $count === 1 ? 'center' : 'space-between';

    // Saber desde qué sitio se está llamando (helper para diagnóstico)
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
    $caller_file = isset($trace[0]['file']) ? basename($trace[0]['file']) : '?';
    $caller_line = isset($trace[0]['line']) ? (int) $trace[0]['line'] : 0;

    ob_start();
    ?>
    <!-- gc_logos_render desde=<?php echo esc_html($caller_file . ':' . $caller_line); ?> escenario=<?php echo (int) $escenario_id; ?> n=<?php echo (int) $count; ?> -->
    <div class="gc-footer-logos" style="margin:20px auto 0;padding:14px 12px;max-width:760px;width:95%;display:flex;align-items:center;gap:18px;justify-content:<?php echo esc_attr($justify); ?>;flex-wrap:wrap;border-top:1px solid #e2e8f0;">
        <?php foreach ($logos as $url): ?>
            <img src="<?php echo esc_url($url); ?>" alt="" style="max-width:120px;width:120px;height:auto;object-fit:contain;display:block;" />
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Devuelve el escenario_id de la página actual (para hooks fallback).
 * Cubre todos los casos: singular escenario/estación, páginas virtuales
 * (ranking/instrucciones/puntuaciones) y acceso por QR con ?gc_station=X.
 */
function gc_pagina_actual_escenario_id() {
    // 1. CPT singular escenario
    if (is_singular('escenario')) {
        return (int) get_queried_object_id();
    }
    // 2. CPT singular estación → su escenario
    if (is_singular('estacion')) {
        $est_id = (int) get_queried_object_id();
        return $est_id ? (int) get_post_meta($est_id, 'gc_escenario_ref', true) : 0;
    }
    // 3. Página virtual (ranking/instrucciones/puntuaciones)
    $subpage = get_query_var('gc_subpage');
    if ($subpage) {
        $slug = get_query_var('name');
        if ($slug) {
            $esc = get_page_by_path($slug, OBJECT, 'escenario');
            if ($esc) return (int) $esc->ID;
        }
    }
    // 4. Acceso de estación por QR: ?gc_station=X
    if (!empty($_GET['gc_station'])) {
        $est_id = (int) $_GET['gc_station'];
        if ($est_id > 0 && get_post_type($est_id) === 'estacion') {
            return (int) get_post_meta($est_id, 'gc_escenario_ref', true);
        }
    }
    // 5. Como último recurso: queried object si es escenario o estación
    $qo = get_queried_object();
    if ($qo && isset($qo->post_type)) {
        if ($qo->post_type === 'escenario') return (int) $qo->ID;
        if ($qo->post_type === 'estacion')  return (int) get_post_meta($qo->ID, 'gc_escenario_ref', true);
    }
    return 0;
}

/**
 * wp_footer: imprime el bloque de logos al cerrar el body. Es el único
 * punto de render desde v1.0.26 para evitar que aparezcan en medio de la
 * página por orden de shortcodes.
 *
 * Imprime también un comentario HTML diagnóstico SIEMPRE (mientras estemos
 * en frontend) con la versión activa, el escenario_id detectado y cuántos
 * logos hay configurados. Útil para depurar por qué no salen.
 */
add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('gc_render_footer_logos')) return;
    $escenario_id = gc_pagina_actual_escenario_id();
    $logo_count = 0;
    if ($escenario_id > 0) {
        for ($i = 1; $i <= 3; $i++) {
            if (get_post_meta($escenario_id, 'gc_logo_' . $i, true)) $logo_count++;
        }
    }

    $html = '';
    $accion = 'skip';
    if ($escenario_id > 0 && $logo_count > 0) {
        $html = gc_render_footer_logos($escenario_id);
        $accion = $html === '' ? 'guard_block' : 'rendered';
    }

    echo "\n<!-- gc_footer_logos v" . (defined('GINCANA_CORE_VERSION') ? GINCANA_CORE_VERSION : '?')
        . " accion={$accion}"
        . " escenario_id={$escenario_id}"
        . " logos_configurados={$logo_count}"
        . " is_singular_escenario=" . (is_singular('escenario') ? '1' : '0')
        . " is_singular_estacion=" . (is_singular('estacion') ? '1' : '0')
        . " gc_subpage=" . esc_html( get_query_var('gc_subpage') ?: '-' )
        . " gc_station=" . (isset($_GET['gc_station']) ? (int)$_GET['gc_station'] : '-')
        . " -->\n";

    if ($html !== '') {
        echo $html;
        // Mover el bloque para que quede ANTES del footer de Divi.
        ?>
        <script>
        (function(){
            function gcIsFixed(el) {
                if (!el || !window.getComputedStyle) return false;
                var s = window.getComputedStyle(el);
                return s.position === 'fixed';
            }
            function gcAjustarMarginPorFooterFijo(blk, diviFooter) {
                if (!blk || !diviFooter) return;
                // Buscar el ancestro real con position:fixed (Divi a veces lo
                // aplica al footer o a una section interior)
                var target = diviFooter;
                var node = diviFooter;
                for (var i = 0; i < 5 && node; i++, node = node.parentElement) {
                    if (gcIsFixed(node)) { target = node; break; }
                }
                // Y buscar también dentro del footer por si la sección fixed es hija
                if (!gcIsFixed(target)) {
                    var inner = diviFooter.querySelector('.et_pb_section--fixed')
                             || diviFooter.querySelector('[class*="--fixed"]');
                    if (gcIsFixed(inner)) target = inner;
                }
                if (gcIsFixed(target)) {
                    var h = target.offsetHeight || 70;
                    blk.style.marginBottom = (h + 16) + 'px';
                    blk.setAttribute('data-gc-fixed-footer', h);
                }
            }
            function gcMoveLogos() {
                var blk = document.querySelector('.gc-footer-logos');
                if (!blk) { console.log('[gc-logos] sin bloque'); return; }
                var diviFooter = document.querySelector('footer.et-l.et-l--footer')
                              || document.querySelector('footer.et-l--footer')
                              || document.querySelector('.et-l.et-l--footer');
                console.log('[gc-logos] divi footer?', !!diviFooter);
                if (diviFooter && diviFooter.parentNode) {
                    diviFooter.parentNode.insertBefore(blk, diviFooter);
                    blk.setAttribute('data-gc-moved', 'before-divi-footer');
                    gcAjustarMarginPorFooterFijo(blk, diviFooter);
                    console.log('[gc-logos] movido antes del footer Divi');
                    return;
                }
                var main = document.querySelector('#main-content')
                        || document.querySelector('#page-container')
                        || document.querySelector('#page');
                if (main) {
                    main.appendChild(blk);
                    blk.setAttribute('data-gc-moved', 'main-fallback');
                    console.log('[gc-logos] fallback en #main-content/#page');
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', gcMoveLogos);
            } else {
                gcMoveLogos();
            }
            // Reintento por si Divi añade el footer tarde
            setTimeout(gcMoveLogos, 500);
            setTimeout(gcMoveLogos, 1500);
            // Re-ajustar margin si la ventana cambia de tamaño
            window.addEventListener('resize', function(){
                var blk = document.querySelector('.gc-footer-logos');
                var diviFooter = document.querySelector('footer.et-l.et-l--footer')
                              || document.querySelector('footer.et-l--footer')
                              || document.querySelector('.et-l.et-l--footer');
                if (blk && diviFooter) gcAjustarMarginPorFooterFijo(blk, diviFooter);
            });
        })();
        </script>
        <?php
    }
});

/**
 * JS global para sincronizar color picker ↔ input hex.
 * Se imprime una sola vez por carga; se enchufa a cualquier campo creado con
 * gc_render_color_field() y a cualquier color picker que tenga
 * data-gc-color-target apuntando al input HEX (y viceversa).
 */
function gc_render_color_field_script() {
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <script>
    (function(){
        function normHex(v) {
            v = String(v || '').trim();
            if (!v) return null;
            if (v.charAt(0) !== '#') v = '#' + v;
            // expand 3 → 6 (#abc → #aabbcc)
            if (/^#[0-9a-fA-F]{3}$/.test(v)) {
                v = '#' + v[1]+v[1] + v[2]+v[2] + v[3]+v[3];
            }
            if (!/^#[0-9a-fA-F]{6}$/.test(v)) return null;
            return v.toUpperCase();
        }
        document.addEventListener('input', function(e){
            var src = e.target;
            if (!src || !src.dataset || !src.dataset.gcColorTarget) return;
            var tgt = document.getElementById(src.dataset.gcColorTarget);
            if (!tgt) return;
            if (src.type === 'color') {
                tgt.value = src.value.toUpperCase();
            } else {
                var h = normHex(src.value);
                if (h) {
                    tgt.value = h;
                    src.value = h; // normaliza el propio input
                    src.style.borderColor = '';
                } else {
                    src.style.borderColor = '#dc2626';
                }
            }
        });
        // Al perder foco, si el hex no es válido restaurar al del picker
        document.addEventListener('blur', function(e){
            var src = e.target;
            if (!src || !src.dataset || !src.dataset.gcColorTarget) return;
            if (src.type !== 'text') return;
            var h = normHex(src.value);
            if (!h) {
                var picker = document.getElementById(src.dataset.gcColorTarget);
                if (picker) {
                    src.value = picker.value.toUpperCase();
                    src.style.borderColor = '';
                }
            } else {
                src.value = h;
            }
        }, true);
    })();
    </script>
    <?php
}
