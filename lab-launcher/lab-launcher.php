<?php
/*
Plugin Name: Lab Launcher (CloudMentor)
Plugin URI: https://github.com/the1bit/student-lab-backend/tree/main/lab-launcher
Description: WordPress plugin a CloudMentor Lab indításhoz (Azure, AWS).
Version: 0.0.19-alpha
Author: CloudMentor
Author URI: https://cloudmentor.hu
License: MIT
License URI: https://opensource.org/licenses/MIT
Requires at least: 6.2
Tested up to: 6.7.2
Requires PHP: 8.0
Text Domain: cloudmentor-lab-launcher
Domain Path: /languages
*/


// Enqueue fájl közvetlenül a fájl elején fusson
require_once plugin_dir_path(__FILE__) . 'includes/enqueue.php';


// Beillesztés: admin oldal, REST API, shortcode, beállítások

// 1. Plugin alap inicializálás
add_action('plugins_loaded', 'lab_launcher_init');
function lab_launcher_init()
{

    require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__FILE__) . 'includes/api-caller.php';
    require_once plugin_dir_path(__FILE__) . 'admin/lab-admin-page.php';
    require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
    require_once plugin_dir_path(__FILE__) . 'admin/courses-page.php';
    require_once plugin_dir_path(__FILE__) . 'admin/lab-launch-shortcode.php';


}

add_action('rest_api_init', function () {
    register_rest_route('lab-launcher/v1', '/start-lab', array(
        'methods' => 'POST',
        'callback' => 'lab_launcher_start_lab_rest',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('lab-launcher/v1', '/verify-lab', array(
        'methods' => 'POST',
        'callback' => 'lab_check_lab_rest',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('lab-launcher/v1', '/lab-status-update', [
        'methods' => 'POST',
        'callback' => 'lab_launcher_status_update',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('lab-launcher/v1', '/lab-status-webhook', [
        'methods' => 'POST',
        'callback' => 'lab_launcher_status_webhook',
        'permission_callback' => '__return_true'
    ]);
});

function lab_launcher_status_webhook($request)
{
    $params = $request->get_json_params();

    $email = sanitize_email($params['email'] ?? '');
    $lab_id = sanitize_text_field($params['lab_id'] ?? '');
    $status = sanitize_text_field($params['status'] ?? '');
    $provided_key = $_GET['secret_key'] ?? '';

    $valid_statuses = ['pending', 'success', 'error'];
    $settings = get_option('lab_launcher_settings', []);
    $expected_key = $settings['status_webhook_token'] ?? '';

    if (!$email || !$lab_id || !in_array($status, $valid_statuses) || $provided_key !== $expected_key) {
        return new WP_REST_Response(['message' => 'Érvénytelen kérés'], 403);
    }

    $statuses = get_option('lab_launcher_statuses', []);
    $statuses["$email|$lab_id"] = $status;
    update_option('lab_launcher_statuses', $statuses);

    return new WP_REST_Response(['message' => 'Státusz frissítve'], 200);
}

function lab_launcher_status_update($request)
{
    $params = $request->get_json_params();
    $lab_id = sanitize_text_field($params['lab_id'] ?? '');

    global $lab_launcher_user_email;
    $email = sanitize_email($lab_launcher_user_email);

    if (!$lab_id || !$email) {
        return new WP_REST_Response(['message' => 'Hiányzó adatok'], 400);
    }

    $statuses = get_option('lab_launcher_statuses', []);
    $status = $statuses["$email|$lab_id"] ?? 'unknown';

    return new WP_REST_Response(['status' => $status], 200);
}


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
        'lab_ttl' => intval($data['lab_ttl'] ?? 5400),
        'email' => $lab_launcher_user_email
    );

    $result = lab_launcher_call_backend($payload, 'start-lab');

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'message' => $result->get_error_message()
        ], $result->get_error_data()['status'] ?? 500);
    }

    return new WP_REST_Response($result, 200);
}

function lab_check_lab_rest($request)
{
    global $lab_launcher_user_email;

    $data = $request->get_json_params();

    if (!isset($data['lab_name']) || !isset($data['cloud_provider']) || !isset($data['user'])) {
        return new WP_REST_Response(['message' => 'Hiányzó paraméterek'], 400);
    }

    if (empty($lab_launcher_user_email) || strpos($lab_launcher_user_email, '@') === false) {
        return new WP_REST_Response(['message' => 'Felhasználói e-mail nem elérhető vagy érvénytelen'], 401);
    }

    $payload = array(
        'lab' => sanitize_text_field($data['lab_name']),
        'cloud' => sanitize_text_field($data['cloud_provider']),
        'user' => sanitize_text_field($data['user']),
        'email' => $lab_launcher_user_email
    );

    $result = lab_launcher_call_backend($payload, '/verify-lab');

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'message' => $result->get_error_message()
        ], $result->get_error_data()['status'] ?? 500);
    }

    // Itt ellenőrizzük a sikert, és ha completed, elmentjük a státuszt
    if (isset($result['success']) && $result['success'] === true) {
        $user_obj = get_user_by('email', $lab_launcher_user_email);
        if ($user_obj) {
            $meta_key = 'lab_' . sanitize_key($data['lab_name']) . '_status';
            update_user_meta($user_obj->ID, $meta_key, 'completed');
        }
    }

    return new WP_REST_Response($result, 200);
}
