<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Generador y validador de sopas de letras para el tipo de pregunta
 * 'sopa_letras'. Una sola palabra escondida en un grid NxN.
 *
 * Persistencia por usuario: user_meta gc_sopa_<prueba_id>_<estacion_id>
 *   {
 *     'palabra'   => 'GYMKANA',          // normalizada (UPPER, sin acentos)
 *     'tamano'    => 10,
 *     'grid'      => [['G','A',...], ...],
 *     'word_path' => [[r,c],[r,c],...]   // posiciones de las letras de la palabra
 *   }
 */

/**
 * Normaliza la palabra: mayúsculas, sin acentos, sin espacios ni signos.
 */
function gc_sopa_normaliza_palabra($palabra) {
    $p = (string) $palabra;
    if (function_exists('remove_accents')) $p = remove_accents($p);
    $p = mb_strtoupper($p);
    // Solo letras A-Z (sin la Ñ para mantener el alfabeto del relleno simple)
    $p = preg_replace('/[^A-Z]/u', '', $p);
    return $p;
}

/**
 * Devuelve las 8 direcciones (dr, dc) posibles.
 */
function gc_sopa_direcciones() {
    return [
        [ 0,  1],  // → este
        [ 0, -1],  // ← oeste
        [ 1,  0],  // ↓ sur
        [-1,  0],  // ↑ norte
        [ 1,  1],  // ↘ SE
        [-1, -1],  // ↖ NW
        [ 1, -1],  // ↙ SW
        [-1,  1],  // ↗ NE
    ];
}

/**
 * Genera un grid NxN con la palabra colocada en una dirección aleatoria
 * y el resto de celdas rellenas con letras aleatorias.
 *
 * @return array|null ['grid'=>..., 'word_path'=>..., 'palabra'=>..., 'tamano'=>N]
 */
function gc_sopa_genera_grid($palabra, $tamano = 10) {
    $palabra = gc_sopa_normaliza_palabra($palabra);
    $len = mb_strlen($palabra);
    $tamano = max(6, min(20, (int) $tamano));
    if ($len < 3 || $len > $tamano) return null;

    $direcciones = gc_sopa_direcciones();
    shuffle($direcciones);

    foreach ($direcciones as $d) {
        list($dr, $dc) = $d;
        // Coordenadas válidas para que la palabra quepa
        $r_min = $dr < 0 ? ($len - 1) : 0;
        $r_max = $dr > 0 ? ($tamano - $len) : ($tamano - 1);
        $c_min = $dc < 0 ? ($len - 1) : 0;
        $c_max = $dc > 0 ? ($tamano - $len) : ($tamano - 1);
        if ($r_min > $r_max || $c_min > $c_max) continue;

        // Probar varias posiciones aleatorias
        $intentos = 50;
        while ($intentos-- > 0) {
            $r0 = random_int($r_min, $r_max);
            $c0 = random_int($c_min, $c_max);

            // Construir path
            $path = [];
            for ($i = 0; $i < $len; $i++) {
                $path[] = [$r0 + $dr * $i, $c0 + $dc * $i];
            }

            // Crear grid y colocar palabra
            $grid = array_fill(0, $tamano, array_fill(0, $tamano, ''));
            $ok = true;
            for ($i = 0; $i < $len; $i++) {
                $r = $path[$i][0]; $c = $path[$i][1];
                if ($grid[$r][$c] !== '' && $grid[$r][$c] !== mb_substr($palabra, $i, 1)) { $ok = false; break; }
                $grid[$r][$c] = mb_substr($palabra, $i, 1);
            }
            if (!$ok) continue;

            // Rellenar resto con letras aleatorias
            $letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            for ($r = 0; $r < $tamano; $r++) {
                for ($c = 0; $c < $tamano; $c++) {
                    if ($grid[$r][$c] === '') {
                        $grid[$r][$c] = $letras[random_int(0, 25)];
                    }
                }
            }

            return [
                'palabra'   => $palabra,
                'tamano'    => $tamano,
                'grid'      => $grid,
                'word_path' => $path,
            ];
        }
    }
    return null;
}

/**
 * Devuelve la sopa de letras del usuario para una prueba+estación;
 * la genera y persiste si no existía.
 */
function gc_sopa_get_or_create($user_id, $prueba_id, $estacion_id, $palabra, $tamano = 10) {
    $user_id     = (int) $user_id;
    $prueba_id   = (int) $prueba_id;
    $estacion_id = (int) $estacion_id;
    $meta_key    = 'gc_sopa_' . $prueba_id . '_' . $estacion_id;
    $cookie_key  = 'gc_sopa_' . $prueba_id . '_' . $estacion_id;

    // Cargar de meta (logueado) o cookie (guest)
    $data = null;
    if ($user_id > 0) {
        $stored = get_user_meta($user_id, $meta_key, true);
        if (is_array($stored) && !empty($stored['grid'])) $data = $stored;
    } elseif (isset($_COOKIE[$cookie_key])) {
        $decoded = json_decode(stripslashes($_COOKIE[$cookie_key]), true);
        if (is_array($decoded) && !empty($decoded['grid'])) $data = $decoded;
    }

    $palabra_norm = gc_sopa_normaliza_palabra($palabra);

    // Si la palabra guardada no coincide con la configurada actual, regenerar
    if ($data && isset($data['palabra']) && $data['palabra'] !== $palabra_norm) {
        $data = null;
    }

    if ($data) return $data;

    $data = gc_sopa_genera_grid($palabra_norm, $tamano);
    if (!$data) return null;

    if ($user_id > 0) {
        update_user_meta($user_id, $meta_key, $data);
    } else {
        $json = wp_json_encode($data);
        if (!headers_sent()) {
            setcookie(
                $cookie_key, $json,
                time() + YEAR_IN_SECONDS,
                defined('COOKIEPATH') ? COOKIEPATH : '/',
                defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                is_ssl(), false
            );
        }
        $_COOKIE[$cookie_key] = $json;
    }
    return $data;
}

/**
 * Compara una selección [[r,c],...] del jugador con el word_path guardado.
 * Acepta también el path invertido (la palabra leída al revés).
 */
function gc_sopa_es_correcta($seleccion, $word_path) {
    if (!is_array($seleccion) || !is_array($word_path)) return false;
    if (count($seleccion) !== count($word_path)) return false;
    $sel_str  = '';
    $path_str = '';
    foreach ($seleccion as $rc) {
        if (!is_array($rc) || count($rc) < 2) return false;
        $sel_str .= (int)$rc[0] . ',' . (int)$rc[1] . ';';
    }
    foreach ($word_path as $rc) {
        $path_str .= (int)$rc[0] . ',' . (int)$rc[1] . ';';
    }
    if ($sel_str === $path_str) return true;
    // Path al revés
    $rev = array_reverse($word_path);
    $rev_str = '';
    foreach ($rev as $rc) $rev_str .= (int)$rc[0] . ',' . (int)$rc[1] . ';';
    return $sel_str === $rev_str;
}

/**
 * Limpia el grid guardado (al completar la estación o al reiniciar).
 */
function gc_sopa_limpiar($user_id, $prueba_id, $estacion_id) {
    $key = 'gc_sopa_' . (int)$prueba_id . '_' . (int)$estacion_id;
    if ($user_id > 0) {
        delete_user_meta($user_id, $key);
    }
    if (isset($_COOKIE[$key])) {
        setcookie(
            $key, '',
            time() - 3600,
            defined('COOKIEPATH') ? COOKIEPATH : '/',
            defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
            is_ssl(), false
        );
        unset($_COOKIE[$key]);
    }
}
