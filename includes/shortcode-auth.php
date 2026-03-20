<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Shortcodes de autenticación: [gincana_login] y [gincana_registro]
 *
 * Formularios mobile-first con redirect automático a la página de origen.
 * Crear dos páginas en WP (ej: /acceso/ y /registro/) y meter el shortcode.
 * Luego redirigir wp-login a esas páginas.
 */

// ── CSS compartido ──────────────────────────────────────────────
function gc_auth_styles() {
    static $printed = false;
    if ($printed) return '';
    $printed = true;

    return '<style>
    .gc-auth {
        width: 95%;
        max-width: 440px;
        margin: 0 auto;
        padding: 24px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .gc-auth-title {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 6px;
        color: #111827;
    }
    .gc-auth-subtitle {
        font-size: 14px;
        color: #64748b;
        margin: 0 0 24px;
    }
    .gc-auth-field {
        margin-bottom: 16px;
    }
    .gc-auth-field label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
    }
    .gc-auth-field input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-size: 16px;
        color: #111827;
        background: #fff;
        box-sizing: border-box;
        transition: border-color 0.2s;
        -webkit-appearance: none;
    }
    .gc-auth-field input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .gc-auth-btn {
        display: block;
        width: 100%;
        padding: 14px;
        border: 0;
        border-radius: 10px;
        background: #2563eb;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        -webkit-appearance: none;
    }
    .gc-auth-btn:hover { background: #1d4ed8; }
    .gc-auth-btn:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }
    .gc-auth-link {
        display: block;
        text-align: center;
        margin-top: 16px;
        font-size: 14px;
        color: #64748b;
    }
    .gc-auth-link a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 600;
    }
    .gc-auth-link a:hover { text-decoration: underline; }
    .gc-auth-msg {
        padding: 12px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 14px;
        line-height: 1.5;
    }
    .gc-auth-msg.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    .gc-auth-msg.success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }
    .gc-auth-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 20px 0;
        color: #94a3b8;
        font-size: 13px;
    }
    .gc-auth-divider::before,
    .gc-auth-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }
    .gc-auth-pwd-toggle {
        position: relative;
    }
    .gc-auth-pwd-toggle button {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        background: none;
        color: #64748b;
        cursor: pointer;
        font-size: 13px;
        padding: 4px;
    }
    </style>';
}


// ── [gincana_login] ─────────────────────────────────────────────
add_shortcode('gincana_login', function($atts){

    // Si ya está logueado, redirigir o mostrar mensaje
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $redirect = gc_auth_get_redirect();
        return gc_auth_styles() . '<div class="gc-auth">
            <div class="gc-auth-msg success">Ya has iniciado sesion como <strong>' . esc_html($user->display_name) . '</strong>.</div>
            <a href="' . esc_url($redirect) . '" class="gc-auth-btn">Continuar</a>
        </div>';
    }

    $a = shortcode_atts([
        'registro_url' => '',
    ], $atts);

    // URL de la página de registro
    $registro_url = $a['registro_url'];
    if (!$registro_url) {
        $reg_page = get_page_by_path('registro');
        $registro_url = $reg_page ? get_permalink($reg_page) : wp_registration_url();
    }

    $error = '';
    $username_val = '';

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gc_login_nonce'])) {
        if (!wp_verify_nonce($_POST['gc_login_nonce'], 'gc_login_action')) {
            $error = 'Error de seguridad. Recarga la pagina e intentalo de nuevo.';
        } else {
            $username = sanitize_user($_POST['gc_user'] ?? '');
            $password = $_POST['gc_pass'] ?? '';
            $username_val = $username;

            if (empty($username) || empty($password)) {
                $error = 'Rellena todos los campos.';
            } else {
                $user = wp_signon([
                    'user_login'    => $username,
                    'user_password' => $password,
                    'remember'      => !empty($_POST['gc_remember']),
                ], is_ssl());

                if (is_wp_error($user)) {
                    $error = 'Usuario o contrasena incorrectos.';
                } else {
                    $redirect = gc_auth_get_redirect();
                    wp_safe_redirect($redirect);
                    exit;
                }
            }
        }
    }

    $redirect = gc_auth_get_redirect();
    $registro_url = add_query_arg('redirect_to', urlencode($redirect), $registro_url);

    ob_start();
    echo gc_auth_styles();
    ?>
    <div class="gc-auth">
        <h2 class="gc-auth-title">Iniciar sesion</h2>
        <p class="gc-auth-subtitle">Accede para participar en la gimkana</p>

        <?php if ($error): ?>
            <div class="gc-auth-msg error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <?php wp_nonce_field('gc_login_action', 'gc_login_nonce'); ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">

            <div class="gc-auth-field">
                <label for="gc_user">Email o usuario</label>
                <input type="text" id="gc_user" name="gc_user" value="<?php echo esc_attr($username_val); ?>" autocomplete="username" required>
            </div>

            <div class="gc-auth-field">
                <label for="gc_pass">Contrasena</label>
                <div class="gc-auth-pwd-toggle">
                    <input type="password" id="gc_pass" name="gc_pass" autocomplete="current-password" required>
                    <button type="button" onclick="var i=document.getElementById('gc_pass');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'Ver':'Ocultar';">Ver</button>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:14px;color:#64748b;cursor:pointer;">
                    <input type="checkbox" name="gc_remember" value="1" style="width:auto;margin:0;">
                    Recordarme
                </label>
                <a href="<?php echo esc_url(wp_lostpassword_url($redirect)); ?>" style="font-size:13px;color:#2563eb;text-decoration:none;">He olvidado mi contrasena</a>
            </div>

            <button type="submit" class="gc-auth-btn">Entrar</button>
        </form>

        <div class="gc-auth-divider">o</div>

        <p class="gc-auth-link">¿No tienes cuenta? <a href="<?php echo esc_url($registro_url); ?>">Registrate gratis</a></p>
    </div>
    <?php
    return ob_get_clean();
});


// ── [gincana_registro] ──────────────────────────────────────────
add_shortcode('gincana_registro', function($atts){

    // Si ya está logueado
    if (is_user_logged_in()) {
        $redirect = gc_auth_get_redirect();
        return gc_auth_styles() . '<div class="gc-auth">
            <div class="gc-auth-msg success">Ya tienes una cuenta y estas conectado.</div>
            <a href="' . esc_url($redirect) . '" class="gc-auth-btn">Continuar</a>
        </div>';
    }

    // Si el registro está deshabilitado en WP
    if (!get_option('users_can_register')) {
        return gc_auth_styles() . '<div class="gc-auth">
            <div class="gc-auth-msg error">El registro no esta habilitado en este momento.</div>
        </div>';
    }

    $a = shortcode_atts([
        'login_url' => '',
    ], $atts);

    $login_url = $a['login_url'];
    if (!$login_url) {
        $login_page = get_page_by_path('acceso');
        $login_url = $login_page ? get_permalink($login_page) : wp_login_url();
    }

    $error = '';
    $success = false;
    $form_data = ['nombre' => '', 'email' => ''];

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gc_reg_nonce'])) {
        if (!wp_verify_nonce($_POST['gc_reg_nonce'], 'gc_registro_action')) {
            $error = 'Error de seguridad. Recarga la pagina e intentalo de nuevo.';
        } else {
            $nombre   = sanitize_text_field($_POST['gc_nombre'] ?? '');
            $email    = sanitize_email($_POST['gc_email'] ?? '');
            $password = $_POST['gc_pass'] ?? '';
            $pass2    = $_POST['gc_pass2'] ?? '';

            $form_data = ['nombre' => $nombre, 'email' => $email];

            if (empty($nombre) || empty($email) || empty($password)) {
                $error = 'Rellena todos los campos.';
            } elseif (!is_email($email)) {
                $error = 'El email no es valido.';
            } elseif (strlen($password) < 6) {
                $error = 'La contrasena debe tener al menos 6 caracteres.';
            } elseif ($password !== $pass2) {
                $error = 'Las contrasenas no coinciden.';
            } elseif (email_exists($email)) {
                $error = 'Ya existe una cuenta con ese email.';
            } elseif (username_exists(sanitize_user($email))) {
                $error = 'Ya existe una cuenta con ese usuario.';
            } else {
                // Crear usuario (username = email para simplificar)
                $user_id = wp_create_user(sanitize_user($email), $password, $email);

                if (is_wp_error($user_id)) {
                    $error = $user_id->get_error_message();
                } else {
                    // Guardar nombre
                    wp_update_user([
                        'ID'           => $user_id,
                        'display_name' => $nombre,
                        'first_name'   => $nombre,
                        'role'         => 'subscriber',
                    ]);

                    // Auto-login
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id, true, is_ssl());

                    $redirect = gc_auth_get_redirect();
                    wp_safe_redirect($redirect);
                    exit;
                }
            }
        }
    }

    $redirect = gc_auth_get_redirect();
    $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);

    ob_start();
    echo gc_auth_styles();
    ?>
    <div class="gc-auth">
        <h2 class="gc-auth-title">Crear cuenta</h2>
        <p class="gc-auth-subtitle">Registrate para participar en la gimkana</p>

        <?php if ($error): ?>
            <div class="gc-auth-msg error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <?php wp_nonce_field('gc_registro_action', 'gc_reg_nonce'); ?>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>">

            <div class="gc-auth-field">
                <label for="gc_nombre">Tu nombre</label>
                <input type="text" id="gc_nombre" name="gc_nombre" value="<?php echo esc_attr($form_data['nombre']); ?>" autocomplete="name" required placeholder="Como quieres que te vean en el ranking">
            </div>

            <div class="gc-auth-field">
                <label for="gc_email">Email</label>
                <input type="email" id="gc_email" name="gc_email" value="<?php echo esc_attr($form_data['email']); ?>" autocomplete="email" required>
            </div>

            <div class="gc-auth-field">
                <label for="gc_pass">Contrasena</label>
                <div class="gc-auth-pwd-toggle">
                    <input type="password" id="gc_pass" name="gc_pass" autocomplete="new-password" required minlength="6" placeholder="Minimo 6 caracteres">
                    <button type="button" onclick="var i=document.getElementById('gc_pass');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'Ver':'Ocultar';">Ver</button>
                </div>
            </div>

            <div class="gc-auth-field">
                <label for="gc_pass2">Repetir contrasena</label>
                <div class="gc-auth-pwd-toggle">
                    <input type="password" id="gc_pass2" name="gc_pass2" autocomplete="new-password" required minlength="6">
                    <button type="button" onclick="var i=document.getElementById('gc_pass2');i.type=i.type==='password'?'text':'password';this.textContent=i.type==='password'?'Ver':'Ocultar';">Ver</button>
                </div>
            </div>

            <button type="submit" class="gc-auth-btn" style="margin-top:8px;">Crear cuenta</button>
        </form>

        <div class="gc-auth-divider">o</div>

        <p class="gc-auth-link">¿Ya tienes cuenta? <a href="<?php echo esc_url($login_url); ?>">Inicia sesion</a></p>
    </div>
    <?php
    return ob_get_clean();
});


// ── Redirigir wp-login a nuestras páginas ───────────────────────
add_action('login_init', function(){
    // No interceptar si es logout, postpass, o petición AJAX/REST
    if (isset($_GET['action']) && in_array($_GET['action'], ['logout', 'postpass', 'rp', 'resetpass', 'lostpassword'])) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (is_admin()) return;

    // Buscar páginas de acceso/registro
    $login_page = get_page_by_path('acceso');
    $reg_page   = get_page_by_path('registro');

    $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';

    // Si es registro
    if (isset($_GET['action']) && $_GET['action'] === 'register' && $reg_page) {
        $url = get_permalink($reg_page);
        if ($redirect_to) $url = add_query_arg('redirect_to', urlencode($redirect_to), $url);
        wp_safe_redirect($url);
        exit;
    }

    // Si es login normal
    if ($login_page) {
        // No redirigir POST (el form se procesa en el shortcode)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') return;

        $url = get_permalink($login_page);
        if ($redirect_to) $url = add_query_arg('redirect_to', urlencode($redirect_to), $url);
        wp_safe_redirect($url);
        exit;
    }
});


// ── Helper: obtener URL de redirect ─────────────────────────────
function gc_auth_get_redirect() {
    // 1) Parámetro redirect_to explícito
    if (!empty($_REQUEST['redirect_to'])) {
        return esc_url_raw($_REQUEST['redirect_to']);
    }
    // 2) Referer (la página de donde vino)
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        // No redirigir de vuelta a login/registro/wp-admin
        if (strpos($ref, 'wp-login') === false
            && strpos($ref, '/acceso') === false
            && strpos($ref, '/registro') === false
            && strpos($ref, 'wp-admin') === false) {
            return esc_url_raw($ref);
        }
    }
    // 3) Home por defecto
    return home_url('/');
}
