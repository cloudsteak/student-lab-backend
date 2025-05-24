<?php
// includes/enqueue.php

add_action('wp_enqueue_scripts', 'lab_launcher_enqueue_assets');
add_action('admin_enqueue_scripts', 'lab_launcher_enqueue_assets');

function lab_launcher_enqueue_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    wp_enqueue_style(
        'lab-launcher-style',
        $plugin_url . 'includes/lab-launcher.css',
        [],
        '1.3'
    );

    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css'
    );
}
