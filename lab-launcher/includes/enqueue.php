<?php
// includes/enqueue.php

add_action('wp_enqueue_scripts', 'lab_launcher_enqueue_assets');

function lab_launcher_enqueue_assets() {
    wp_enqueue_style(
        'lab-launcher-style',
        plugin_dir_url(__FILE__) . 'lab-launcher.css',
        [],
        '1.0'
    );

    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css'
    );
}


