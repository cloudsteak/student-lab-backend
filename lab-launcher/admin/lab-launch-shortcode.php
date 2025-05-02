<?php
// admin/lab-launch-shortcode.php

add_shortcode('lab_start', function () {
    if (!isset($_GET['id']))
        return '<p>Hiányzó lab azonosító.</p>';

    $lab_id = sanitize_text_field($_GET['id']);
    $labs = get_option('lab_launcher_labs', []);

    if (!isset($labs[$lab_id]))
        return '<p>Ismeretlen lab: ' . esc_html($lab_id) . '</p>';

    $lab = $labs[$lab_id];

    ob_start();
    $ref_path = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
    if ($ref_path) {
        echo '<a href="' . esc_url(home_url($ref_path)) . '" class="lab-back-button"><i class="fas fa-arrow-left"></i> Vissza a képzéshez</a>';
    }


    echo '<h2>' . esc_html($lab['lab_title'] ?? $lab_id) . '</h2>';
    echo '<p>' . esc_html($lab['lab_brief'] ?? '') . '</p>';
    echo '<p><strong>Indítás azonosító:</strong> ' . esc_html($lab_id) . '</p>';
    return ob_get_clean();
});
