<?php
// includes/settings.php

add_action('admin_menu', 'lab_launcher_settings_menu');
function lab_launcher_settings_menu() {
    add_options_page(
        'Lab Launcher Beállítások',
        'Lab Launcher',
        'manage_options',
        'lab-launcher-settings',
        'lab_launcher_settings_page'
    );
}

add_action('admin_init', 'lab_launcher_register_settings');
function lab_launcher_register_settings() {
    register_setting('lab_launcher_settings_group', 'lab_launcher_settings');

    add_settings_section('lab_launcher_main_section', '', null, 'lab-launcher-settings');

    add_settings_field('auth0_domain', 'Auth0 domain', 'lab_launcher_text_field', 'lab-launcher-settings', 'lab_launcher_main_section', ['name' => 'auth0_domain']);
    add_settings_field('auth0_client_id', 'Auth0 client ID', 'lab_launcher_text_field', 'lab-launcher-settings', 'lab_launcher_main_section', ['name' => 'auth0_client_id']);
    add_settings_field('auth0_client_secret', 'Auth0 client Secret', 'lab_launcher_text_field', 'lab-launcher-settings', 'lab_launcher_main_section', ['name' => 'auth0_client_secret']);
    add_settings_field('auth0_audience', 'Auth0 Audience', 'lab_launcher_text_field', 'lab-launcher-settings', 'lab_launcher_main_section', ['name' => 'auth0_audience']);
    add_settings_field('backend_url', 'Backend URL', 'lab_launcher_text_field', 'lab-launcher-settings', 'lab_launcher_main_section', ['name' => 'backend_url']);
}

function lab_launcher_text_field($args) {
    $options = get_option('lab_launcher_settings');
    $name = $args['name'];
    $value = esc_attr($options[$name] ?? '');
    echo "<input type='text' name='lab_launcher_settings[$name]' value='$value' class='regular-text' />";
}

function lab_launcher_settings_page() {
    echo '<div class="wrap">';
    echo '<h1>Lab Launcher Beállítások</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('lab_launcher_settings_group');
    do_settings_sections('lab-launcher-settings');
    submit_button();
    echo '</form>';
    echo '</div>';
}
