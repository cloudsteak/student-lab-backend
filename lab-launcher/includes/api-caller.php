<?php
// includes/api-caller.php

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
