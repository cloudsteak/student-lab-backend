<?php
// admin/lab-launch-shortcode.php

// E-mail elmentése globálisan INIT alatt
global $lab_launcher_user_email;
if (empty($lab_launcher_user_email)) {
    $current_user = wp_get_current_user();
    $lab_launcher_user_email = sanitize_email($current_user->user_email ?? '');
    
}

add_shortcode('lab_start', function () {
    if (!isset($_GET['id']))
        return '<p>Hiányzó lab azonosító.</p>';

    $lab_id = sanitize_text_field($_GET['id']);
    $labs = get_option('lab_launcher_labs', []);

    if (!isset($labs[$lab_id]))
        return '<p>Ismeretlen lab: ' . esc_html($lab_id) . '</p>';

    $lab = $labs[$lab_id];

    ob_start();

    // Vissza a képzésre link
    $ref_path = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
    if ($ref_path) {
        echo '<a href="' . esc_url(home_url($ref_path)) . '" class="lab-back-button"><i class="fas fa-arrow-left"></i> Vissza a képzéshez</a>';
    }

    // Lab cím és rövid leírás
    echo '<p><span class="lab-header">' . esc_html($lab['lab_title'] ?? $lab_id) . '</span>';
    echo '<br><span>' . esc_html($lab['lab_brief'] ?? '') . '</span></p>';
    echo '<hr>';

    // FONTOS: Ugyanazt jelenítjük meg, mint a [lab_launcher id="..."] shortcode
    echo do_shortcode('[lab_launcher id="' . esc_attr($lab_id) . '"]');

    return ob_get_clean();
});
