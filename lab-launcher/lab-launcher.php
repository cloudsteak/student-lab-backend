<?php
/*
Plugin Name: Lab Launcher
Description: WordPress plugin a CloudMentor lab indításhoz.
Version: 1.0
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
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));
});

function lab_launcher_start_lab_rest($request) {
    $data = $request->get_json_params();

    if (!isset($data['lab_name']) || !isset($data['cloud_provider'])) {
        return new WP_Error('invalid_data', 'Hiányzó paraméterek', array('status' => 400));
    }

    $email = wp_get_current_user()->user_email;

    $payload = array(
        'lab_name' => sanitize_text_field($data['lab_name']),
        'cloud_provider' => sanitize_text_field($data['cloud_provider']),
        'email' => sanitize_email($email)
    );

    $result = lab_launcher_call_backend($payload);

    if (is_wp_error($result)) {
        return $result;
    }

    return new WP_REST_Response($result, 200);
}

// 3. Backend hívás Auth0 tokennel
function lab_launcher_call_backend($payload) {
    $settings = get_option('lab_launcher_settings');
    $auth0_domain = $settings['auth0_domain'] ?? '';
    $client_id = $settings['auth0_client_id'] ?? '';
    $client_secret = $settings['auth0_client_secret'] ?? '';
    $audience = $settings['auth0_audience'] ?? '';
    $backend_url = $settings['backend_url'] ?? '';

    if (!$auth0_domain || !$client_id || !$client_secret || !$audience || !$backend_url) {
        return new WP_Error('config_error', 'A plugin nincs megfelelően konfigurálva', array('status' => 500));
    }

    $token_response = wp_remote_post("https://$auth0_domain/oauth/token", array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'audience' => $audience
        ))
    ));

    if (is_wp_error($token_response)) {
        return new WP_Error('token_error', 'Nem sikerült Auth0 tokent lekérni', array('status' => 500));
    }

    $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
    $access_token = $token_data['access_token'] ?? '';

    if (!$access_token) {
        return new WP_Error('token_empty', 'Hiányzó access token', array('status' => 500));
    }

    $backend_response = wp_remote_post("$backend_url/start-lab", array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($payload)
    ));

    if (is_wp_error($backend_response)) {
        return new WP_Error('backend_error', 'Nem sikerült elérni a backendet', array('status' => 500));
    }

    return json_decode(wp_remote_retrieve_body($backend_response), true);
}
