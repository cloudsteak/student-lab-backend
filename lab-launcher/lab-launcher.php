<?php
/*
Plugin Name: Lab Launcher
Description: WordPress plugin a CloudMentor lab indításhoz.
Version: 0.0.3
Author: CloudMentor
*/

// Beillesztés: admin oldal, REST API, shortcode, beállítások

// 1. Plugin alap inicializálás
add_action('plugins_loaded', 'lab_launcher_init');
function lab_launcher_init() {
    require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
    require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__FILE__) . 'includes/api-caller.php';
    require_once plugin_dir_path(__FILE__) . 'admin/lab-admin-page.php';
}

// 2. REST API regisztrálás
add_action('rest_api_init', function () {
    register_rest_route('lab-launcher/v1', '/start-lab', array(
        'methods' => 'POST',
        'callback' => 'lab_launcher_start_lab_rest',
        'permission_callback' => '__return_true'
    ));
});

function lab_launcher_start_lab_rest($request)
{
    global $lab_launcher_user_email;

    $data = $request->get_json_params();

    if (!isset($data['lab_name']) || !isset($data['cloud_provider'])) {
        return new WP_REST_Response(['message' => 'Hiányzó paraméterek'], 400);
    }

    if (empty($lab_launcher_user_email) || strpos($lab_launcher_user_email, '@') === false) {
        return new WP_REST_Response(['message' => 'Felhasználói e-mail nem elérhető vagy érvénytelen'], 401);
    }

    $payload = array(
        'lab_name' => sanitize_text_field($data['lab_name']),
        'cloud_provider' => sanitize_text_field($data['cloud_provider']),
        'email' => $lab_launcher_user_email
    );

    $result = lab_launcher_call_backend($payload);

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'message' => $result->get_error_message()
        ], $result->get_error_data()['status'] ?? 500);
    }

    return new WP_REST_Response($result, 200);
}

require_once plugin_dir_path(__FILE__) . 'includes/api-caller.php';
