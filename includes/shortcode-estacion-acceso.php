<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcode: [gincana_estacion_acceso]
 *
 * Requiere una página en WordPress con slug:
 * /acceso-estacion/
 *
 * El QR debe apuntar a algo como:
 * /acceso-estacion/?gc_station=123&gc_token=TOKEN
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

    $tipo   = get_post_meta($station_id, 'gc_tipo_estacion', true);
    $audio  = get_post_meta($station_id, 'gc_audio', true);
    $img1   = get_post_meta($station_id, 'gc_img_1', true);
    $img2   = get_post_meta($station_id, 'gc_img_2', true);
    $title  = get_the_title($station_id);

    if (empty($tipo)) {
        $tipo = 'adulto';
    }

    ob_start();

    echo '<div class="gc-station-access" style="max-width:760px;margin:0 auto;padding:24px;">';
    echo '<h1 style="margin-bottom:8px;">' . esc_html($title) . '</h1>';

    if ($audio) {
        echo '<div style="margin:16px 0;">';
        echo '<audio controls style="width:100%;">';
        echo '<source src="' . esc_url($audio) . '">';
        echo 'Tu navegador no soporta audio HTML5.';
        echo '</audio>';
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

    if ($tipo === 'infantil') {
        echo gc_render_infantil_station($station_id, $title);
    } else {
        echo gc_render_adulto_station($station_id, $title);
    }

    echo '</div>';

    return ob_get_clean();
}

function gc_render_infantil_station($station_id, $title) {
    $html  = '<div style="padding:20px;border:1px solid #dcdcde;border-radius:14px;background:#fff;">';
    $html .= '<h2 style="margin-top:0;">¡Puerta encontrada!</h2>';
    $html .= '<p>La estación <strong>' . esc_html($title) . '</strong> ha sido validada correctamente.</p>';
    $html .= '<p style="margin-bottom:0;">En el siguiente paso conectaremos esto con el guardado real de progreso y tiempo.</p>';
    $html .= '</div>';

    return $html;
}

function gc_render_adulto_station($station_id, $title) {
    $test_id = (int) get_post_meta($station_id, 'gc_prueba_ref', true);

    if ($test_id <= 0) {
        return gc_station_wrap_message('Esta estación no tiene una prueba enlazada.', 'error');
    }

    $preguntas = get_post_meta($test_id, 'gc_preguntas', true);

    if ( ! is_array($preguntas) || empty($preguntas[0]) || ! is_array($preguntas[0]) ) {
        return gc_station_wrap_message('La prueba no tiene preguntas configuradas.', 'error');
    }

    $pregunta = $preguntas[0];
    $enunciado = isset($pregunta['enunciado']) ? $pregunta['enunciado'] : '';
    $opciones  = isset($pregunta['opciones']) && is_array($pregunta['opciones']) ? $pregunta['opciones'] : [];

    if (empty($enunciado) || empty($opciones)) {
        return gc_station_wrap_message('La prueba de esta estación no está lista para tipo test.', 'error');
    }

    $feedback = '';
    $is_correct = null;

    if (
        isset($_POST['gc_station_submit'], $_POST['gc_station_answer'], $_POST['gc_station_verify_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gc_station_verify_nonce'])), 'gc_station_answer_' . $station_id)
    ) {
        $selected = sanitize_text_field(wp_unslash($_POST['gc_station_answer']));
        $correct_index = gc_get_correct_option_index($opciones);

        if ($selected !== '' && (int) $selected === $correct_index) {
            $is_correct = true;
            $feedback = '<div style="margin:16px 0;padding:14px 16px;border-radius:12px;background:#ecfdf3;border:1px solid #b7ebc6;color:#146c2e;">✅ Respuesta correcta.</div>';
        } else {
            $is_correct = false;
            $feedback = '<div style="margin:16px 0;padding:14px 16px;border-radius:12px;background:#fff2f0;border:1px solid #ffccc7;color:#a8071a;">❌ Respuesta incorrecta. Puedes volver a intentarlo.</div>';
        }
    }

    ob_start();

    echo '<div style="padding:20px;border:1px solid #dcdcde;border-radius:14px;background:#fff;">';
    echo '<h2 style="margin-top:0;">Pregunta de la estación</h2>';
    echo '<p style="font-size:18px;line-height:1.5;"><strong>' . esc_html($enunciado) . '</strong></p>';

    echo $feedback;

    if ($is_correct !== true) {
        echo '<form method="post">';
        wp_nonce_field('gc_station_answer_' . $station_id, 'gc_station_verify_nonce');

        foreach ($opciones as $index => $opcion) {
            $value = $index + 1;
            $texto = isset($opcion['texto']) ? $opcion['texto'] : '';
            if ($texto === '') continue;

            echo '<label style="display:block;margin:12px 0;padding:14px 16px;border:1px solid #dcdcde;border-radius:12px;cursor:pointer;">';
            echo '<input type="radio" name="gc_station_answer" value="' . esc_attr($value) . '" style="margin-right:10px;">';
            echo esc_html($texto);
            echo '</label>';
        }

        echo '<div style="margin-top:18px;">';
        echo '<button type="submit" name="gc_station_submit" value="1" style="padding:12px 18px;border:0;border-radius:10px;background:#111827;color:#fff;cursor:pointer;">Responder</button>';
        echo '</div>';
        echo '</form>';
    } else {
        echo '<p style="margin-top:16px;">En el siguiente paso conectaremos esta validación con la suma real de puntos y el completado de estación.</p>';
    }

    echo '</div>';

    return ob_get_clean();
}

function gc_get_correct_option_index($opciones) {
    foreach ($opciones as $index => $opcion) {
        if ( ! empty($opcion['es_correcta']) ) {
            return (int) $index + 1;
        }
    }
    return 0;
}

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