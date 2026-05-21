<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Registro de shortcodes (dentro de 'init')
 */
add_action('init', function(){

  // === Shortcode REAL del quiz: [gincana_prueba] o [gincana_prueba estacion="ID"] ===
  add_shortcode('gincana_prueba', function($atts){

    // Placeholder en builder
    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
      return '<div class="gincana-quiz et_pb_module" style="padding:16px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;">
        <strong>Gincana — Prueba</strong><br/><small>(Vista de maquetación: sin cronómetro ni validaciones)</small>
      </div>';
    }

    // 1) Resolver estación
    $a = shortcode_atts(['estacion' => ''], $atts);
    $estacion_id = 0;

    if ($a['estacion'] !== '') {
      $estacion_id = (int) $a['estacion'];
    }
    if (!$estacion_id) {
      $qo_id = get_queried_object_id();
      if ($qo_id) $estacion_id = (int) $qo_id;
    }
    if (!$estacion_id) {
      $estacion_id = (int) get_the_ID();
    }
    if (!$estacion_id) {
      $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
      $parts = explode('/', $path);
      $maybe_slug = end($parts);
      $maybe_post = get_page_by_path($maybe_slug, OBJECT, 'estacion');
      if ($maybe_post) $estacion_id = (int) $maybe_post->ID;
    }
    if (!$estacion_id) return '<div>No se ha podido determinar la estación.</div>';

    // 2) Prueba vinculada
    $prueba_raw = get_post_meta($estacion_id, 'gc_prueba_ref', true);
    $prueba_id  = gc_resolve_meta_id($prueba_raw);
    if (!$prueba_id) return '<div>No hay una Prueba vinculada a esta estación.</div>';

    // 3) Leer meta de la prueba
    $pregs         = get_post_meta($prueba_id, 'gc_preguntas', true);
    $tipo_global   = get_post_meta($prueba_id, 'gc_tipo', true);
    $tiempo_max_s  = (int) (get_post_meta($prueba_id, 'gc_tiempo_max_s', true) ?: 30);
    $intentos_max  = (int) (get_post_meta($prueba_id, 'gc_intentos_max', true) ?: 2);

    // 3.b) Escenario desde la Estación
    $escenario_id = (int) get_post_meta($estacion_id, 'gc_escenario_ref', true);

    if (empty($pregs)) return '<div>La Prueba no tiene preguntas configuradas.</div>';

    // === GUARD: estación ya superada ===
    $current_user_id = get_current_user_id();
    if ( function_exists('gincana_user_passed') && gincana_user_passed($current_user_id, $estacion_id) ) {
      global $wpdb;
      $pts_station = (int) $wpdb->get_var( $wpdb->prepare("
        SELECT COALESCE(SUM(points),0)
        FROM {$wpdb->prefix}gincana_points_log
        WHERE user_id=%d AND estacion_id=%d
      ", $current_user_id, $estacion_id) );

      $next_id  = ( function_exists('gincana_next_estacion_id') && $escenario_id )
                  ? gincana_next_estacion_id($escenario_id, $estacion_id) : 0;
      $next_url = $next_id ? get_permalink($next_id) : '';

      ob_start(); ?>
      <div class="gincana-quiz et_pb_module" data-estacion="<?php echo esc_attr($estacion_id); ?>">
        <div class="gq-already" style="padding:16px;border:1px solid #e6f0e6;border-radius:10px;background:#f7fff7;">
          <p style="margin:0 0 8px;"><strong>Ya has completado esta estación</strong></p>
          <p style="margin:0 0 12px;">Puntos obtenidos aquí: <strong><?php echo (int)$pts_station; ?></strong>.</p>
          <?php if ($next_url): ?>
            <a class="et_pb_button" href="<?php echo esc_url($next_url); ?>" style="padding:10px 16px;border-radius:8px;">
              Ir a la siguiente estación
            </a>
          <?php else: ?>
            <a class="et_pb_button" href="<?php echo esc_url( get_permalink( $estacion_id ) ); ?>" style="padding:10px 16px;border-radius:8px;">
              Volver
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php
      return ob_get_clean();
    }

    // --- BLOQUEO POR PROGRESO ---
    if ( function_exists('gincana_can_access_estacion') && ! gincana_can_access_estacion($current_user_id, $estacion_id) ) {
      $pista = get_post_meta($estacion_id, 'gc_pista_bloqueo', true);
      if ( empty($pista) ) {
        $pista = 'Esta estación está bloqueada. Supera la anterior o escanea el QR en el lugar para saltarla (sin puntos).';
      }
      ob_start(); ?>
      <div class="gincana-quiz et_pb_module" data-estacion="<?php echo esc_attr($estacion_id); ?>">
        <div class="gq-locked" style="padding:16px;border:1px solid #eee;border-radius:10px;background:#fff9f2;">
          <p style="margin:0 0 10px;"><strong>Estación bloqueada</strong></p>
          <p style="margin:0 0 12px;"><?php echo esc_html($pista); ?></p>
          <button id="gq-skip" class="et_pb_button" style="padding:10px 16px;border-radius:8px;">Saltar con QR (0 puntos)</button>
          <div id="gq-msg" style="margin-top:10px;"></div>
        </div>
      </div>
      <script>
      (function(){
        const wrap = document.currentScript ? document.currentScript.previousElementSibling : null;
        const estacionId = wrap ? parseInt(wrap.dataset.estacion,10) : 0;
        const btn = wrap ? wrap.querySelector('#gq-skip') : null;
        const $msg = wrap ? wrap.querySelector('#gq-msg') : null;
        const nonce = (window.wpApiSettings&&window.wpApiSettings.nonce) || window.gincanaNonce || '';
        if (btn && $msg) {
          btn.addEventListener('click', async function(){
            btn.disabled = true;
            $msg.textContent = 'Procesando...';
            try{
              const r = await fetch('/wp-json/gincana/v1/progress/skip',{
                method:'POST',
                headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
                credentials:'same-origin',
                body: JSON.stringify({estacion_id:estacionId})
              });
              const d = await r.json();
              if(d && d.ok){
                $msg.innerHTML = 'Estación desbloqueada sin puntos. <a href="'+window.location.href+'">Recargar</a>';
              }else{
                $msg.textContent = 'No se pudo desbloquear.';
              }
            }catch(e){
              $msg.textContent = 'Error: '+e.message;
            }finally{
              btn.disabled = false;
            }
          });
        }
      })();
      </script>
      <?php
      return ob_get_clean();
    }

    // 4) Nonce REST
    $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '';

    // 5) Pintar HTML del quiz
    ob_start(); ?>
    <div class="gincana-quiz et_pb_module"
         data-estacion="<?php echo esc_attr($estacion_id); ?>"
         data-escenario="<?php echo esc_attr($escenario_id); ?>"
         data-prueba="<?php echo esc_attr($prueba_id); ?>"
         data-tiempo="<?php echo esc_attr($tiempo_max_s); ?>"
         data-intentos="<?php echo esc_attr($intentos_max); ?>">

      <!-- Intro de inicio -->
      <div class="gq-intro" style="padding:16px;border:1px solid #e8eef5;border-radius:10px;background:#f8fbff;margin-bottom:12px;">
        <h4 style="margin:0 0 8px;">¿Estás preparado?</h4>
        <p style="margin:0 0 12px;">Pulsa el botón para comenzar. El tiempo empezará a contar cuando inicies.</p>
        <button id="gq-start" type="button" onclick="window.__gqStart && window.__gqStart(this)" class="et_pb_button" style="padding:10px 16px;border-radius:8px;">Comenzar la prueba</button>
      </div>

      <!-- Header (oculto hasta que se inicia) -->
      <div class="gq-header" style="display:none;gap:12px;align-items:center;margin-bottom:12px;">
        <div><strong>Tiempo:</strong> <span id="gq-timer"><?php echo (int)$tiempo_max_s; ?></span> s</div>
        <div>·</div>
        <div><strong>Intentos:</strong> <span id="gq-tries"><?php echo (int)$intentos_max; ?></span></div>
        <div id="gq-status" style="margin-left:auto;color:#666;"></div>
      </div>

      <!-- Formulario (oculto hasta que se inicia) -->
      <form id="gq-form" style="display:none;">
        <?php foreach ($pregs as $i => $p):
          $tipo = !empty($p['tipo']) ? $p['tipo'] : $tipo_global; ?>
          <div class="gq-question" style="margin-bottom:16px;">
            <div class="gq-qtext" style="margin-bottom:6px;"><strong><?php echo esc_html($p['enunciado'] ?? ('Pregunta '.($i+1))); ?></strong></div>

            <?php if ($tipo === 'texto'): ?>
              <input type="text" name="q_<?php echo $i; ?>" style="width:100%;max-width:480px;padding:10px;border:1px solid #ddd;border-radius:8px" placeholder="Escribe tu respuesta" disabled />
            <?php else:
              $ops = $p['opciones'] ?? [];
              foreach ($ops as $k => $op): ?>
                <label style="display:block;margin:6px 0;">
                  <input type="radio" name="q_<?php echo $i; ?>" value="<?php echo (int)$k; ?>" disabled />
                  <?php echo esc_html($op['texto'] ?? ('Opción '.($k+1))); ?>
                </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <button type="submit" class="et_pb_button" style="padding:10px 16px;border-radius:8px;">Enviar</button>
        <div id="gq-msg" style="margin-top:12px;"></div>
      </form>
    </div>

    <script>
    (function(){
      let wrap = null;
      try { wrap = document.currentScript ? document.currentScript.previousElementSibling : null; } catch(e){}
      if (!wrap || !wrap.classList || !wrap.classList.contains('gincana-quiz')) {
        const all = document.querySelectorAll('.gincana-quiz');
        if (all.length) wrap = all[all.length - 1];
      }
      if (!wrap) return;

      const estacionId = parseInt(wrap.dataset.estacion,10);
      const escenarioId = parseInt(wrap.dataset.escenario,10);
      const pruebaId   = parseInt(wrap.dataset.prueba,10);
      const tiempoMax  = parseInt(wrap.dataset.tiempo,10);
      let intentosRest = parseInt(wrap.dataset.intentos,10);

      const $intro  = wrap.querySelector('.gq-intro');
      const $start  = wrap.querySelector('#gq-start');
      const $header = wrap.querySelector('.gq-header');
      const $timer  = wrap.querySelector('#gq-timer');
      const $tries  = wrap.querySelector('#gq-tries');
      const $form   = wrap.querySelector('#gq-form');
      const $msg    = wrap.querySelector('#gq-msg');
      const $status = wrap.querySelector('#gq-status');

      const nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || window.gincanaNonce || '<?php echo esc_js($nonce); ?>';

      let timeLeft = tiempoMax;
      let tick = null;
      let started = false;

      function enableInputs() {
        $form.querySelectorAll('input').forEach(el => el.disabled = false);
      }
      function focusFirst() {
        const first = $form.querySelector('input[type="radio"], input[type="text"]');
        if (first) first.focus();
      }

      window.__gqStart = function(){
        if (started) return;
        started = true;

        if ($intro) $intro.style.display = 'none';
        if ($header) $header.style.display = 'flex';
        if ($form) $form.style.display = 'block';
        if ($status) { $status.textContent = 'En curso'; $status.style.color = '#666'; }

        enableInputs();
        focusFirst();

        timeLeft = tiempoMax;
        if ($timer) $timer.textContent = timeLeft;
        if ($tries) $tries.textContent = intentosRest;

        tick = setInterval(() => {
          timeLeft--;
          if ($timer) $timer.textContent = Math.max(0, timeLeft);
          if (timeLeft <= 0) {
            clearInterval(tick);
            if ($status){ $status.textContent = 'Tiempo agotado'; $status.style.color = '#c00'; }
            const btnSubmit = $form.querySelector('button[type="submit"]');
            if (btnSubmit) btnSubmit.disabled = true;
          }
        }, 1000);
      };

      if ($start) {
        $start.addEventListener('click', function(){ window.__gqStart(); });
      } else {
        setTimeout(function(){
          const fb = wrap.querySelector('#gq-start');
          if (!fb) window.__gqStart();
        }, 0);
      }

      function gatherAnswers() {
        const answers = [];
        const qBlocks = wrap.querySelectorAll('.gq-question');
        qBlocks.forEach((qb, i) => {
          const text = qb.querySelector('input[type="text"]');
          if (text) { answers[i] = text.value; }
          else {
            const checked = qb.querySelector('input[type="radio"]:checked');
            answers[i] = checked ? parseInt(checked.value,10) : null;
          }
        });
        return answers;
      }

      $form.addEventListener('submit', async function(e){
        e.preventDefault();
        if (!started) return;
        if (timeLeft <= 0) return;

        const answers = gatherAnswers();
        if (answers.some(v => v === null || v === undefined || v === '')) {
          $msg.textContent = 'Por favor, responde a todas las preguntas.';
          $msg.style.color = '#c00';
          return;
        }

        try {
          const res = await fetch('/wp-json/gincana/v1/quiz/submit', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
            credentials:'same-origin',
            body: JSON.stringify({
              prueba_id: pruebaId,
              answers: answers,
              time_ms: Math.max(0, (tiempoMax - timeLeft)) * 1000
            })
          });
          const data = await res.json();

          if (!data.ok) {
            intentosRest--;
            if ($tries) $tries.textContent = intentosRest;
            $msg.textContent = intentosRest > 0 ? 'Respuesta incorrecta. Inténtalo de nuevo.' : 'Has agotado los intentos.';
            $msg.style.color = '#c00';
            if (intentosRest <= 0) {
              const btnSubmit = $form.querySelector('button[type="submit"]');
              if (btnSubmit) btnSubmit.disabled = true;
              if (tick) clearInterval(tick);
            }
            return;
          }

          try {
            const time_ms = Math.max(0, (tiempoMax - timeLeft)) * 1000;

            const res2 = await fetch('/wp-json/gincana/v1/progress/complete', {
              method:'POST',
              headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
              credentials:'same-origin',
              body: JSON.stringify({
                escenario_id: escenarioId || null,
                estacion_id: estacionId,
                time_ms: time_ms
              })
            });

            const data2 = await res2.json();

            if (tick) clearInterval(tick);

            if (data2 && data2.ok) {
              const pts = data2.points_awarded || 0;
              $msg.innerHTML = '¡Correcto! Has ganado <strong>' + pts + ' puntos</strong>.';
              $msg.style.color = '#090';
              if ($status) { $status.textContent = 'Superada'; $status.style.color = '#090'; }

              <?php
                $next_id  = ( function_exists('gincana_next_estacion_id') && $escenario_id )
                            ? gincana_next_estacion_id($escenario_id, $estacion_id) : 0;
                $next_url = $next_id ? get_permalink($next_id) : '';
              ?>
              (function(){
                const nextWrap = document.createElement('div');
                nextWrap.style.marginTop = '10px';
                const a = document.createElement('a');
                a.className = 'et_pb_button';
                a.style.padding = '10px 16px';
                a.style.borderRadius = '8px';
                a.textContent = '<?php echo $next_url ? 'Ir a la siguiente estación' : 'Volver'; ?>';
                a.href = <?php echo $next_url ? json_encode($next_url) : "window.location.href"; ?>;
                nextWrap.appendChild(a);
                $msg.parentNode.appendChild(nextWrap);
              })();

            } else {
              $msg.textContent = 'Se validó el quiz, pero no se pudo registrar el progreso.';
              $msg.style.color = '#c00';
            }

            const btnSubmit = $form.querySelector('button[type="submit"]');
            if (btnSubmit) btnSubmit.disabled = true;

          } catch (err2) {
            $msg.textContent = 'Error al registrar el progreso: ' + err2.message;
            $msg.style.color = '#c00';
          }

        } catch (err) {
          $msg.textContent = 'Error al enviar: ' + err.message;
          $msg.style.color = '#c00';
        }
      });
    })();
    </script>
    <?php
    return ob_get_clean();
  });

});

// === Shortcode CTA primera estación: [gincana_primera_estacion] ===
add_action('init', function(){
  add_shortcode('gincana_primera_estacion', function($atts){
    $a = shortcode_atts([
      'escenario' => '',
      'text'      => 'Comenzar',
      'class'     => 'et_pb_button',
    ], $atts);

    $escenario_id = (int) $a['escenario'];
    if (!$escenario_id && get_post_type(get_the_ID()) === 'escenario') {
      $escenario_id = (int) get_the_ID();
    }
    if (!$escenario_id) return '';

    $q = new WP_Query([
      'post_type'      => 'estacion',
      'posts_per_page' => 1,
      'orderby'        => 'meta_value_num',
      'order'          => 'ASC',
      'meta_query'     => [
        ['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'='],
      ],
      'meta_key'       => 'gc_orden',
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    if (!$q->have_posts()) return '';

    $first_id  = (int) $q->posts[0];
    $first_url = get_permalink($first_id);
    $text      = esc_html($a['text']);
    $class     = esc_attr($a['class']);

    return '<a class="'.$class.'" href="'.esc_url($first_url).'">'.$text.'</a>';
  });
});


// === Shortcode Ranking: [gincana_ranking escenario="ID"] ===
add_action('init', function(){
  add_shortcode('gincana_ranking', function($atts){

    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
      return '<div class="gincana-placeholder et_pb_module" style="padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;">
        <strong>Gincana — Ranking</strong><br/><small>(Vista de maquetación)</small>
      </div>';
    }

    $a = shortcode_atts([
      'escenario'      => '',
      'limit'          => '10',
      'show_avatars'   => '0',
      'title'          => '',
      'show_self_below'=> '1',
      'label_self'     => 'Tu posición',
    ], $atts);

    $limit    = max(1, (int)$a['limit']);
    $avatars  = trim($a['show_avatars']) === '1';
    $showSelf = trim($a['show_self_below']) === '1';
    $labelSelf= sanitize_text_field($a['label_self']);

    // 1) Resolver escenario_id
    $escenario_id = (int)$a['escenario'];
    if (!$escenario_id) {
      $post_id = get_the_ID();
      if ($post_id && get_post_type($post_id) === 'estacion') {
        $escenario_id = (int) get_post_meta($post_id, 'gc_escenario_ref', true);
      }
    }
    if (!$escenario_id) {
      return '<div>No se pudo determinar el escenario. Usa <code>[gincana_ranking escenario="ID"]</code>.</div>';
    }

    // 2) Cargar TOP N
    global $wpdb;
    $table = $wpdb->prefix . 'gincana_points_log';

    $cache_key = 'ginc_ranking_' . $escenario_id . '_' . $limit . '_' . ($avatars?1:0);
    $rows = get_transient($cache_key);
    if ($rows === false) {
      $sql = $wpdb->prepare("
        SELECT pl.user_id, SUM(pl.points) AS total_points
        FROM $table pl
        WHERE pl.escenario_id = %d
        GROUP BY pl.user_id
        HAVING total_points > 0
        ORDER BY total_points DESC
        LIMIT %d
      ", $escenario_id, $limit);
      $rows = $wpdb->get_results($sql);
      set_transient($cache_key, $rows, 60);
    }

    if (!$rows) {
      return '<div>No hay participantes con puntos en este escenario todavía.</div>';
    }

    // 3) Render tabla
    $current_user_id = get_current_user_id();

    $inTop = false;
    if ($current_user_id) {
      foreach ($rows as $r) {
        if ((int)$r->user_id === (int)$current_user_id) { $inTop = true; break; }
      }
    }

    // Datos del escenario para cabecera
    $esc_title = get_the_title($escenario_id);
    $esc_url   = get_permalink($escenario_id);

    ob_start(); ?>
    <style>.gincana-ranking-page .entry-title, .gincana-ranking-page .page-title, .gincana-ranking-page .et_pb_post_title { display:none !important; }</style>
    <script>document.body.classList.add('gincana-ranking-page');</script>
    <?php if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id); ?>
    <div class="gincana-ranking gc-tema-esc-<?php echo (int) $escenario_id; ?> et_pb_module" style="width:95%;max-width:760px;margin:0 auto;">

      <!-- Cabecera -->
      <div style="text-align:center;margin-bottom:24px;">
        <div style="font-size:14px;font-weight:600;color:#2563eb;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:4px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 7 7 7 7"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 17 7 17 7"/><path d="M4 22h16"/><path d="M10 22V8a4 4 0 0 0-4-4H6a4 4 0 0 0 4 4h4a4 4 0 0 0 4-4h0a4 4 0 0 0-4 4v14"/></svg>
          Ranking
        </div>
        <h2 style="margin:0;font-size:24px;font-weight:700;color:#1e293b;line-height:1.3;">
          <?php echo esc_html($esc_title); ?>
        </h2>
      </div>

      <table class="gincana-ranking-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;width:60px;">Pos.</th>
            <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Usuario</th>
            <th style="text-align:right;border-bottom:1px solid #ddd;padding:8px;width:120px;">Puntos</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $pos = 1;
          foreach ($rows as $r):
            $uid  = (int)$r->user_id;
            $user = get_user_by('id', $uid);
            $name = $user ? ($user->display_name ?: $user->user_login) : ('Usuario #'.$uid);
            $tr_style = ($current_user_id && $uid === (int)$current_user_id) ? 'background:#f8fff2;' : '';
            ?>
            <tr style="<?php echo esc_attr($tr_style); ?>">
              <td style="padding:8px;border-bottom:1px solid #f0f0f0;"><?php echo (int)$pos; ?></td>
              <td style="padding:8px;border-bottom:1px solid #f0f0f0;display:flex;gap:10px;align-items:center;">
                <?php if ($avatars): ?>
                  <span><?php echo get_avatar($uid, 28); ?></span>
                <?php endif; ?>
                <span><?php echo esc_html($name); ?></span>
              </td>
              <td style="padding:8px;border-bottom:1px solid #f0f0f0;text-align:right;"><?php echo (int)$r->total_points; ?></td>
            </tr>
          <?php $pos++; endforeach; ?>
        </tbody>
      </table>

      <?php
      // 4) Mostrar posición del usuario fuera del top N
      if ($showSelf && $current_user_id && !$inTop) {
        $user_total = (int) $wpdb->get_var( $wpdb->prepare("
          SELECT SUM(points) FROM $table
          WHERE escenario_id = %d AND user_id = %d
        ", $escenario_id, $current_user_id) );

        if ($user_total > 0) {
          $rank = (int) $wpdb->get_var( $wpdb->prepare("
            SELECT 1 + COUNT(*) AS rank_pos FROM (
              SELECT user_id, SUM(points) AS total_points
              FROM $table
              WHERE escenario_id = %d
              GROUP BY user_id
              HAVING total_points > 0
            ) t
            WHERE t.total_points > %d
          ", $escenario_id, $user_total) );

          if ($rank > $limit) {
            ?>
            <div class="gincana-ranking-self" style="margin-top:12px;padding:10px;border:1px dashed #e2e8f0;border-radius:8px;background:#fcfdff;">
              <strong><?php echo esc_html($labelSelf); ?>:</strong>
              estás en el <strong>#<?php echo (int)$rank; ?></strong> con <strong><?php echo (int)$user_total; ?> puntos</strong>.
            </div>
            <?php
          }
        }
      }
      ?>

      <?php
      $ranking_img = get_post_meta($escenario_id, 'gc_ranking_imagen', true);
      if ($ranking_img): ?>
      <div style="text-align:center;margin-top:24px;">
        <img src="<?php echo esc_url($ranking_img); ?>" alt="" style="max-width:100%;height:auto;border-radius:12px;">
      </div>
      <?php endif; ?>

      <!-- Compartir -->
      <?php
      $share_url  = $esc_url;
      $share_text = sprintf('¡Mira el ranking de "%s"!', $esc_title);
      $share_url_enc  = rawurlencode($share_url);
      $share_text_enc = rawurlencode($share_text);
      $wa_url  = "https://api.whatsapp.com/send?text={$share_text_enc}%20{$share_url_enc}";
      $fb_url  = "https://www.facebook.com/sharer/sharer.php?u={$share_url_enc}";
      $tw_url  = "https://twitter.com/intent/tweet?text={$share_text_enc}&url={$share_url_enc}";
      $tg_url  = "https://t.me/share/url?url={$share_url_enc}&text={$share_text_enc}";
      $em_url  = "mailto:?subject=" . rawurlencode($esc_title) . "&body={$share_text_enc}%20{$share_url_enc}";
      ?>
      <div class="gincana-ranking-share" style="margin-top:28px;padding:18px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;text-align:center;">
        <div style="font-size:13px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Compartir ranking</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
          <a href="<?php echo esc_url($wa_url); ?>" target="_blank" rel="noopener" aria-label="WhatsApp" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;background:#25D366;color:#fff;text-decoration:none;font-weight:600;font-size:13px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.89.5 3.66 1.37 5.19L2 22l5.05-1.33a9.9 9.9 0 0 0 4.99 1.28c5.46 0 9.91-4.45 9.91-9.92A9.93 9.93 0 0 0 12.04 2zm0 18.15a8.13 8.13 0 0 1-4.16-1.14l-.3-.18-3 .78.8-2.92-.19-.31a8.18 8.18 0 0 1-1.26-4.37c0-4.52 3.68-8.2 8.21-8.2 2.19 0 4.26.86 5.81 2.41a8.15 8.15 0 0 1 2.4 5.81c0 4.53-3.68 8.22-8.2 8.22zm4.51-6.15c-.25-.12-1.47-.72-1.69-.8-.23-.09-.39-.13-.56.12s-.64.8-.79.97c-.14.17-.29.18-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.39.11-.51.11-.11.25-.29.38-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.77-1.84-.2-.48-.41-.42-.56-.42l-.48-.01c-.17 0-.44.06-.66.31-.23.25-.87.85-.87 2.08 0 1.22.89 2.41 1.02 2.58.12.17 1.75 2.67 4.23 3.74.59.25 1.05.41 1.41.53.59.19 1.13.16 1.55.1.47-.07 1.47-.6 1.67-1.18.2-.57.2-1.07.14-1.17-.06-.1-.22-.17-.47-.29z"/></svg>
            WhatsApp
          </a>
          <a href="<?php echo esc_url($fb_url); ?>" target="_blank" rel="noopener" aria-label="Facebook" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;background:#1877F2;color:#fff;text-decoration:none;font-weight:600;font-size:13px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.5-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.77l-.44 2.89h-2.33v6.99A10 10 0 0 0 22 12z"/></svg>
            Facebook
          </a>
          <a href="<?php echo esc_url($tw_url); ?>" target="_blank" rel="noopener" aria-label="X / Twitter" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;background:#000;color:#fff;text-decoration:none;font-weight:600;font-size:13px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            X
          </a>
          <a href="<?php echo esc_url($tg_url); ?>" target="_blank" rel="noopener" aria-label="Telegram" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;background:#229ED9;color:#fff;text-decoration:none;font-weight:600;font-size:13px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm4.64 7.04-1.56 7.35c-.12.52-.42.65-.85.4l-2.35-1.73-1.13 1.09a.59.59 0 0 1-.47.23l.17-2.39 4.36-3.94c.19-.17-.04-.26-.3-.09l-5.39 3.4-2.32-.72c-.5-.16-.51-.5.11-.74l9.08-3.5c.42-.15.79.1.65.64z"/></svg>
            Telegram
          </a>
          <a href="<?php echo esc_url($em_url); ?>" aria-label="Email" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;background:#64748b;color:#fff;text-decoration:none;font-weight:600;font-size:13px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            Email
          </a>
          <button type="button" class="gc-share-copy" data-url="<?php echo esc_attr($share_url); ?>" aria-label="Copiar enlace" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #cbd5e1;color:#334155;font-weight:600;font-size:13px;cursor:pointer;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <span class="gc-share-copy-label">Copiar enlace</span>
          </button>
        </div>
      </div>
      <script>
      (function(){
        document.querySelectorAll('.gc-share-copy').forEach(function(btn){
          btn.addEventListener('click', function(){
            var url = btn.getAttribute('data-url') || location.href;
            var label = btn.querySelector('.gc-share-copy-label');
            var setOk = function(){ if (label) { var orig = label.textContent; label.textContent = '¡Copiado!'; setTimeout(function(){ label.textContent = orig; }, 1600); } };
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(url).then(setOk).catch(function(){ setOk(); });
            } else {
              var ta = document.createElement('textarea');
              ta.value = url; document.body.appendChild(ta); ta.select();
              try { document.execCommand('copy'); } catch(e) {}
              document.body.removeChild(ta); setOk();
            }
          });
        });
      })();
      </script>

      <!-- Botones inferiores -->
      <div style="text-align:center;margin-top:24px;display:flex;flex-wrap:wrap;gap:12px;justify-content:center;">
        <?php
        // Botón puntuaciones (solo si hay contenido configurado)
        $tiene_puntuaciones = get_post_meta($escenario_id, 'gc_puntuaciones', true);
        if ($tiene_puntuaciones):
          $punt_url = function_exists('gc_escenario_subpage_url') ? gc_escenario_subpage_url($escenario_id, 'puntuaciones') : '#';
        ?>
        <a href="<?php echo esc_url($punt_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:1px solid #e2e8f0;border-radius:10px;color:#64748b;text-decoration:none;font-weight:600;font-size:14px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
          Puntuaciones
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url($esc_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:1px solid #e2e8f0;border-radius:10px;color:#2563eb;text-decoration:none;font-weight:600;font-size:14px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
          Volver al escenario
        </a>
      </div>
    </div>
    <?php
    return ob_get_clean();
  });
});


// === Shortcode Itinerario: [gincana_itinerario escenario="ID"] ===
add_action('init', function(){
  add_shortcode('gincana_itinerario', function($atts){

    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
      return '<div class="gincana-placeholder et_pb_module" style="padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;">
        <strong>Gincana — Itinerario</strong><br/><small>(Vista de maquetación)</small>
      </div>';
    }

    $a = shortcode_atts([
      'escenario'      => '',
      'estacion'       => '',
      'size'           => '28',
      'current_scale'  => '1.35',
      'link_completed' => '1',
      'sticky'         => '',
      'title'          => '',
    ], $atts);

    $size          = max(22, (int)$a['size']);
    $current_scale = max(1.0, (float)$a['current_scale']);
    $linkify       = trim($a['link_completed']) === '1';
    $sticky_css    = trim($a['sticky']);
    $user_id       = get_current_user_id();

    // Resolver escenario_id
    $escenario_id = (int)$a['escenario'];
    $ctx_id = get_the_ID();
    if (!$escenario_id && $ctx_id) {
      if (get_post_type($ctx_id) === 'escenario') {
        $escenario_id = (int)$ctx_id;
      } elseif (get_post_type($ctx_id) === 'estacion') {
        $escenario_id = (int) get_post_meta($ctx_id, 'gc_escenario_ref', true);
      }
    }
    if (!$escenario_id) return '';

    // Estaciones del escenario
    $q = new WP_Query([
      'post_type'      => 'estacion',
      'posts_per_page' => -1,
      'orderby'        => 'meta_value_num',
      'order'          => 'ASC',
      'meta_query'     => [['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'=']],
      'meta_key'       => 'gc_orden',
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    if (!$q->have_posts()) return '';
    $est_ids = array_map('intval', $q->posts);
    wp_reset_postdata();

    // Detectar deshabilitadas
    $disabled_ids = [];
    foreach ($est_ids as $eid) {
      if (get_post_meta($eid, 'gc_deshabilitada', true) === '1') $disabled_ids[$eid] = true;
    }

    // Estación actual: parámetro explícito > GET gc_station > contexto CPT
    $current_est_id = 0;
    if (!empty($a['estacion'])) {
      $current_est_id = (int) $a['estacion'];
    } elseif (isset($_GET['gc_station'])) {
      $current_est_id = (int) $_GET['gc_station'];
    } elseif ($ctx_id && get_post_type($ctx_id) === 'estacion') {
      $current_est_id = (int) $ctx_id;
    }

    // Progreso del usuario
    global $wpdb;
    $tbl_prog = $wpdb->prefix.'gincana_user_progress';
    $progress = [];
    if ($user_id && $est_ids) {
      $in_ids = implode(',', array_map('intval',$est_ids));
      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT estacion_id, status, best_time_ms
        FROM $tbl_prog
        WHERE user_id=%d AND escenario_id=%d AND estacion_id IN ($in_ids)
      ", $user_id, $escenario_id));
      foreach ($rows as $r) {
        $progress[(int)$r->estacion_id] = [
          'passed'       => ($r->status === 'passed'),
          'best_time_ms' => is_null($r->best_time_ms) ? null : (int)$r->best_time_ms
        ];
      }
    }

    // Siguiente desbloqueada (ignorando deshabilitadas)
    $active_ids = array_values(array_filter($est_ids, function($id) use ($disabled_ids) {
      return empty($disabled_ids[$id]);
    }));
    $next_unlocked_id = 0;
    foreach ($active_ids as $i => $eid) {
      $is_passed = !empty($progress[$eid]['passed']);
      if ($is_passed) continue;
      $prev_ok = ($i === 0) ? true : !empty($progress[$active_ids[$i-1]]['passed']);
      if ($prev_ok) { $next_unlocked_id = $eid; break; }
    }
    if (!$current_est_id && $next_unlocked_id) $current_est_id = $next_unlocked_id;

    // Render
    $uid = 'gq-it-' . uniqid();
    $total_est = count($est_ids);
    $stickyStyle = $sticky_css !== '' ? "position:sticky;{$sticky_css};z-index:50;" : '';

    ob_start(); ?>
    <style>
      #<?php echo $uid; ?> .gqi-track {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: center;
        gap: 4px;
      }
      #<?php echo $uid; ?> .gqi-step {
        display: flex;
        justify-content: center;
        align-items: center;
        width: <?php echo (int)$size; ?>px;
        height: <?php echo (int)$size; ?>px;
        border-radius: 999px;
        font-weight: 700;
        font-size: <?php echo max(10, round($size * 0.45)); ?>px;
        line-height: 1;
        flex-shrink: 0;
        transition: transform 0.2s;
      }
      #<?php echo $uid; ?> .gqi-step.is-current {
        transform: scale(<?php echo number_format($current_scale, 2); ?>);
      }
      /* En movil: reducir si hay muchas estaciones */
      @media (max-width: 420px) {
        #<?php echo $uid; ?> .gqi-track { gap: 2px; }
        #<?php echo $uid; ?> .gqi-step {
          width: min(<?php echo (int)$size; ?>px, calc((100vw - 40px) / <?php echo $total_est; ?> - 3px));
          height: min(<?php echo (int)$size; ?>px, calc((100vw - 40px) / <?php echo $total_est; ?> - 3px));
          font-size: <?php echo max(9, round($size * 0.4)); ?>px;
        }
      }
    </style>
    <?php
    // Puntos del usuario
    $show_points = function_exists('gc_show_points') ? gc_show_points($escenario_id) : true;
    $total_points = 0;
    if ($user_id && $show_points) {
      $pts_table = $wpdb->prefix . 'gincana_points_log';
      $total_points = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(points),0) FROM {$pts_table} WHERE user_id=%d AND escenario_id=%d",
        $user_id, $escenario_id
      ));
    }
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="gincana-itinerario et_pb_module" style="<?php echo esc_attr($stickyStyle); ?>background:#fff;padding:8px 4px;border-radius:10px;">
      <?php if ($a['title'] !== ''): ?>
        <div class="gqi-title" style="font-weight:600;margin-bottom:8px;text-align:center;"><?php echo esc_html($a['title']); ?></div>
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:6px;">
        <div class="gqi-track" role="list" aria-label="Itinerario de estaciones" style="flex:1;min-width:0;">
          <?php foreach ($est_ids as $i => $eid):
            $order = (int) get_post_meta($eid, 'gc_orden', true) ?: ($i+1);
            $title = get_the_title($eid) ?: ('Estación '.$order);
            $url   = get_permalink($eid);

            $is_disabled  = !empty($disabled_ids[$eid]);
            $is_current   = !$is_disabled && ($eid === $current_est_id);
            $is_passed    = !$is_disabled && !empty($progress[$eid]['passed']);
            $is_unlocked  = !$is_disabled && (!$is_passed && $eid === $next_unlocked_id);

            if ($is_disabled) {
              $bg = '#fecaca'; $fg = '#991b1b';
            } else {
              $bg   = $is_current ? '#2563eb' : ($is_passed ? '#16a34a' : ($is_unlocked ? '#f59e0b' : '#e2e8f0'));
              $fg   = $is_current || $is_passed || $is_unlocked ? '#ffffff' : '#334155';
            }
            $ring = $is_current ? '0 0 0 3px rgba(37,99,235,0.25)' : 'none';
            $title_attr = $title . ' (Orden ' . (int)$order . ')'
                          . ($is_disabled ? ' — Deshabilitada' : ($is_passed ? ' — Completada' : ($is_unlocked ? ' — Desbloqueada' : ' — Pendiente')));
            $current_cls = $is_current ? ' is-current' : '';
            $extra_style = $is_disabled ? 'opacity:0.75;text-decoration:line-through;border:1.5px dashed #dc2626;' : '';

            $inner = $is_disabled ? '&times;' : (int)$order;
            $circle_html = '<div class="gqi-step'.$current_cls.'" role="listitem" aria-label="'.esc_attr($title_attr).'"
                style="background:'.$bg.';color:'.$fg.';box-shadow:'.$ring.';'.$extra_style.'">
                  '.$inner.'
                </div>';

            if ($url && !$is_disabled) {
              echo '<a href="'.esc_url($url).'" class="gqi-item" style="text-decoration:none" title="'.esc_attr($title).'">'.$circle_html.'</a>';
            } else {
              echo '<div class="gqi-item" title="'.esc_attr($title_attr).'">'.$circle_html.'</div>';
            }
          endforeach; ?>
        </div>
        <?php if ($user_id && $show_points): ?>
        <div style="flex-shrink:0;text-align:center;padding:4px 8px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:8px;color:#fff;line-height:1;">
          <div style="font-size:16px;font-weight:800;"><?php echo (int)$total_points; ?></div>
          <div style="font-size:8px;font-weight:600;opacity:0.85;text-transform:uppercase;letter-spacing:0.3px;">pts</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
  });
});


// === Shortcode Progreso: [gincana_progreso escenario="ID"] ===
add_action('init', function(){
  add_shortcode('gincana_progreso', function($atts){

    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
      return '<div class="gincana-placeholder et_pb_module" style="padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;">
        <strong>Gincana — Progreso</strong><br/><small>(Vista de maquetación)</small>
      </div>';
    }

    if (!is_user_logged_in()) return '<div>Debes iniciar sesión para ver tu progreso.</div>';

    $a = shortcode_atts([
      'escenario'       => '',
      'show_times'      => '1',
      'show_points'     => '1',
      'show_progressbar'=> '1',
      'title'           => 'Mi progreso',
    ], $atts);

    $user_id = get_current_user_id();
    $show_times   = trim($a['show_times']) === '1';
    $show_points  = trim($a['show_points']) === '1';
    $show_bar     = trim($a['show_progressbar']) === '1';

    // Resolver escenario_id
    $escenario_id = (int) $a['escenario'];
    if (!$escenario_id) {
      $ctx_id = get_the_ID();
      if ($ctx_id) {
        if (get_post_type($ctx_id) === 'escenario') {
          $escenario_id = (int) $ctx_id;
        } elseif (get_post_type($ctx_id) === 'estacion') {
          $escenario_id = (int) get_post_meta($ctx_id, 'gc_escenario_ref', true);
        }
      }
    }
    if (!$escenario_id) return '<div>No se pudo determinar el escenario. Usa <code>[gincana_progreso escenario="ID"]</code>.</div>';

    // Estaciones del escenario
    $q = new WP_Query([
      'post_type'      => 'estacion',
      'posts_per_page' => -1,
      'orderby'        => 'meta_value_num',
      'order'          => 'ASC',
      'meta_query'     => [['key'=>'gc_escenario_ref','value'=>$escenario_id,'compare'=>'=']],
      'meta_key'       => 'gc_orden',
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ]);
    if (!$q->have_posts()) return '<div>Este escenario no tiene estaciones.</div>';
    $estaciones = array_map('intval', $q->posts);
    wp_reset_postdata();

    global $wpdb;
    $tbl_progress = $wpdb->prefix.'gincana_user_progress';
    $tbl_points   = $wpdb->prefix.'gincana_points_log';

    // Progreso por estación
    $progress = [];
    $in_ids = implode(',', array_map('intval', $estaciones));
    if (!empty($in_ids)) {
      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT estacion_id, status, best_time_ms
        FROM $tbl_progress
        WHERE user_id=%d AND escenario_id=%d AND estacion_id IN ($in_ids)
      ", $user_id, $escenario_id));
      foreach ($rows as $r) {
        $progress[(int)$r->estacion_id] = [
          'status' => $r->status,
          'best_time_ms' => is_null($r->best_time_ms) ? null : (int)$r->best_time_ms
        ];
      }
    }

    // Puntos por estación
    $points_by_station = [];
    if ($show_points && !empty($in_ids)) {
      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT estacion_id, SUM(points) AS pts
        FROM $tbl_points
        WHERE user_id=%d AND escenario_id=%d AND estacion_id IN ($in_ids)
        GROUP BY estacion_id
      ", $user_id, $escenario_id));
      foreach ($rows as $r) {
        $points_by_station[(int)$r->estacion_id] = (int)$r->pts;
      }
    }

    // Siguiente desbloqueada
    $passed_count = 0;
    $next_unlocked_id = 0;
    foreach ($estaciones as $idx => $eid) {
      $is_passed = (isset($progress[$eid]['status']) && $progress[$eid]['status'] === 'passed');
      if ($is_passed) {
        $passed_count++; continue;
      }
      $prev_ok = ($idx === 0) ? true : (isset($progress[$estaciones[$idx-1]]['status']) && $progress[$estaciones[$idx-1]]['status'] === 'passed');
      if ($prev_ok && !$next_unlocked_id) $next_unlocked_id = $eid;
    }

    // Total puntos
    $total_points = (int) $wpdb->get_var($wpdb->prepare("
      SELECT COALESCE(SUM(points),0) FROM $tbl_points
      WHERE user_id=%d AND escenario_id=%d
    ", $user_id, $escenario_id));

    // Render
    ob_start(); ?>
    <div class="gincana-progreso et_pb_module" data-escenario="<?php echo esc_attr($escenario_id); ?>">
      <h3 style="margin:0 0 10px;"><?php echo esc_html($a['title']); ?></h3>

      <?php if ($show_bar):
        $total = max(1, count($estaciones));
        $pct = round(($passed_count / $total) * 100);
      ?>
        <div style="margin:8px 0 12px;">
          <div style="height:10px;border-radius:6px;background:#edf2f7;overflow:hidden;">
            <div style="height:10px;width:<?php echo (int)$pct; ?>%;background:#48bb78;"></div>
          </div>
          <div style="margin-top:6px;font-size:13px;color:#555;"><?php echo (int)$passed_count; ?>/<?php echo (int)$total; ?> estaciones completadas (<?php echo (int)$pct; ?>%)</div>
        </div>
      <?php endif; ?>

      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left;border-bottom:1px solid #e2e8f0;padding:8px;">Orden</th>
            <th style="text-align:left;border-bottom:1px solid #e2e8f0;padding:8px;">Estación</th>
            <th style="text-align:left;border-bottom:1px solid #e2e8f0;padding:8px;">Estado</th>
            <?php if ($show_points): ?>
              <th style="text-align:right;border-bottom:1px solid #e2e8f0;padding:8px;">Puntos</th>
            <?php endif; ?>
            <?php if ($show_times): ?>
              <th style="text-align:right;border-bottom:1px solid #e2e8f0;padding:8px;">Mejor tiempo</th>
            <?php endif; ?>
            <th style="text-align:right;border-bottom:1px solid #e2e8f0;padding:8px;">Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($estaciones as $idx => $eid):
            $order = (int) get_post_meta($eid, 'gc_orden', true) ?: ($idx+1);
            $title = get_the_title($eid) ?: ('Estación #'.$eid);
            $url   = get_permalink($eid);
            $is_passed = (isset($progress[$eid]['status']) && $progress[$eid]['status'] === 'passed');
            $pts = $points_by_station[$eid] ?? 0;
            $best_ms = $progress[$eid]['best_time_ms'] ?? null;

            $is_unlock_target = ($eid === $next_unlocked_id);
            ?>
            <tr>
              <td style="padding:8px;border-bottom:1px solid #f1f5f9;"><?php echo (int)$order; ?></td>
              <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
              </td>
              <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                <?php if ($is_passed): ?>
                  <span style="display:inline-block;padding:3px 8px;border-radius:12px;background:#e6ffed;color:#046a38;">Completada</span>
                <?php elseif ($is_unlock_target): ?>
                  <span style="display:inline-block;padding:3px 8px;border-radius:12px;background:#fffbea;color:#8a6d00;">Desbloqueada</span>
                <?php else: ?>
                  <span style="display:inline-block;padding:3px 8px;border-radius:12px;background:#edf2f7;color:#2d3748;">Pendiente</span>
                <?php endif; ?>
              </td>

              <?php if ($show_points): ?>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;"><?php echo (int)$pts; ?></td>
              <?php endif; ?>

              <?php if ($show_times): ?>
                <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;"><?php echo is_null($best_ms) ? '—' : ( floor($best_ms/1000).' s'); ?></td>
              <?php endif; ?>

              <td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;">
                <?php if ($is_passed): ?>
                  <a class="et_pb_button" href="<?php echo esc_url($url); ?>" style="padding:6px 12px;border-radius:8px;">Revisar</a>
                <?php elseif ($is_unlock_target): ?>
                  <a class="et_pb_button" href="<?php echo esc_url($url); ?>" style="padding:6px 12px;border-radius:8px;">Continuar</a>
                <?php else: ?>
                  <a class="et_pb_button" href="#" onclick="return false;" style="opacity:.5;cursor:not-allowed;padding:6px 12px;border-radius:8px;">Bloqueada</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($show_points): ?>
        <div style="margin-top:12px;text-align:right;"><strong>Total:</strong> <?php echo (int)$total_points; ?> puntos</div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
  });
});


// === Shortcode Instrucciones: [gincana_instrucciones escenario="ID"] ===
add_action('init', function(){
  add_shortcode('gincana_instrucciones', function($atts){

    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
      return '<div class="gincana-placeholder et_pb_module" style="padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;">
        <strong>Gincana — Instrucciones</strong><br/><small>(Vista previa)</small>
      </div>';
    }

    $a = shortcode_atts(['escenario' => ''], $atts);
    $escenario_id = (int) $a['escenario'];
    if (!$escenario_id) {
      if (!empty($_GET['escenario'])) {
        $escenario_id = (int) $_GET['escenario'];
      } else {
        $ctx = get_the_ID();
        if ($ctx && get_post_type($ctx) === 'escenario') $escenario_id = (int)$ctx;
      }
    }
    if (!$escenario_id) return '<div>No se pudo determinar el escenario.</div>';

    $contenido = get_post_meta($escenario_id, 'gc_instrucciones', true);
    if (!$contenido) return '<div style="padding:20px;text-align:center;color:#64748b;">No hay instrucciones configuradas para este escenario.</div>';

    $esc_title = get_the_title($escenario_id);
    $esc_url   = get_permalink($escenario_id);

    ob_start();
    echo '<style>.gincana-info-page .entry-title, .gincana-info-page .page-title, .gincana-info-page .et_pb_post_title { display:none !important; }</style>';
    echo '<script>document.body.classList.add("gincana-info-page");</script>';
    ?>
    <?php if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id); ?>
    <div class="gincana-instrucciones gc-tema-esc-<?php echo (int) $escenario_id; ?>" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">
      <div style="text-align:center;margin-bottom:24px;">
        <div style="font-size:14px;font-weight:600;color:#2563eb;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:4px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Instrucciones
        </div>
        <h2 style="margin:0;font-size:24px;font-weight:700;color:#1e293b;"><?php echo esc_html($esc_title); ?></h2>
      </div>

      <div style="font-size:15px;line-height:1.7;color:#334155;">
        <?php echo wp_kses_post($contenido); ?>
      </div>

      <div style="text-align:center;margin-top:32px;">
        <a href="<?php echo esc_url($esc_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:1px solid #e2e8f0;border-radius:10px;color:#2563eb;text-decoration:none;font-weight:600;font-size:14px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
          Volver al escenario
        </a>
      </div>
    </div>
    <?php
    return ob_get_clean();
  });
});


// === Shortcode Puntuaciones: [gincana_puntuaciones escenario="ID"] ===
add_action('init', function(){
  add_shortcode('gincana_puntuaciones', function($atts){

    if ( function_exists('gincana_is_divi_builder') && gincana_is_divi_builder() ) {
      return '<div class="gincana-placeholder et_pb_module" style="padding:12px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;">
        <strong>Gincana — Puntuaciones</strong><br/><small>(Vista previa)</small>
      </div>';
    }

    $a = shortcode_atts(['escenario' => ''], $atts);
    $escenario_id = (int) $a['escenario'];
    if (!$escenario_id) {
      if (!empty($_GET['escenario'])) {
        $escenario_id = (int) $_GET['escenario'];
      } else {
        $ctx = get_the_ID();
        if ($ctx && get_post_type($ctx) === 'escenario') $escenario_id = (int)$ctx;
      }
    }
    if (!$escenario_id) return '<div>No se pudo determinar el escenario.</div>';

    $contenido = get_post_meta($escenario_id, 'gc_puntuaciones', true);
    if (!$contenido) return '<div style="padding:20px;text-align:center;color:#64748b;">No hay informacion de puntuaciones configurada para este escenario.</div>';

    $esc_title = get_the_title($escenario_id);
    $esc_url   = get_permalink($escenario_id);

    ob_start();
    echo '<style>.gincana-info-page .entry-title, .gincana-info-page .page-title, .gincana-info-page .et_pb_post_title { display:none !important; }</style>';
    echo '<script>document.body.classList.add("gincana-info-page");</script>';
    ?>
    <?php if (function_exists('gc_render_tema_style')) echo gc_render_tema_style($escenario_id); ?>
    <div class="gincana-puntuaciones gc-tema-esc-<?php echo (int) $escenario_id; ?>" style="width:95%;max-width:760px;margin:0 auto;padding:16px 0;">
      <div style="text-align:center;margin-bottom:24px;">
        <div style="font-size:14px;font-weight:600;color:#2563eb;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:4px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
          Puntuaciones
        </div>
        <h2 style="margin:0;font-size:24px;font-weight:700;color:#1e293b;"><?php echo esc_html($esc_title); ?></h2>
      </div>

      <div style="font-size:15px;line-height:1.7;color:#334155;">
        <?php echo wp_kses_post($contenido); ?>
      </div>

      <div style="text-align:center;margin-top:32px;">
        <a href="<?php echo esc_url($esc_url); ?>" style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border:1px solid #e2e8f0;border-radius:10px;color:#2563eb;text-decoration:none;font-weight:600;font-size:14px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
          Volver al escenario
        </a>
      </div>
    </div>
    <?php
    return ob_get_clean();
  });
});
