<?php
// admin/courses-page.php

add_action('init', function() {
    register_post_type('lab_training', [
        'label' => 'Képzések',
        'public' => false,
        'show_ui' => true,
        'capability_type' => 'post',
        'capabilities' => [
            'edit_post' => 'edit_post',
            'delete_post' => 'delete_post',
            'edit_posts' => 'edit_posts',
            'edit_others_posts' => 'edit_others_posts',
            'publish_posts' => 'publish_posts',
            'read_post' => 'read_post',
            'read_private_posts' => 'read_private_posts'
        ],
        'map_meta_cap' => true,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-welcome-learn-more',
        'show_in_menu' => 'cloud-lab',
        'supports' => ['title', 'editor'],
        'has_archive' => false,
        'rewrite' => false
    ]);
});

add_action('add_meta_boxes', function() {
    add_meta_box('lab_training_shortcode', 'Shortcode', 'render_shortcode_box', 'lab_training', 'side');
    add_meta_box('lab_training_labs', 'Hozzárendelt Lab-ok', 'render_labs_box', 'lab_training');
});

function render_labs_box($post) {
    $selected = get_post_meta($post->ID, 'assigned_labs', true) ?: [];
    $labs = get_option('lab_launcher_labs', []);

    if (empty($labs)) {
        echo '<p><em>Nincs elérhető Lab. Először hozz létre egyet a „Lab kezelő” menüpontban!</em></p>';
        return;
    }

    echo '<ul>';
    foreach ($labs as $lab_id => $lab_data) {
        $checked = in_array($lab_id, $selected) ? 'checked' : '';
        echo "<li><label><input type='checkbox' name='assigned_labs[]' value='{$lab_id}' $checked> " . esc_html($lab_id . ' (' . ucfirst($lab_data['cloud']) . ')') . "</label></li>";
    }
    echo '</ul>';
}

add_action('save_post_lab_training', function($post_id) {
    if (isset($_POST['assigned_labs'])) {
        update_post_meta($post_id, 'assigned_labs', array_map('sanitize_text_field', $_POST['assigned_labs']));
    } else {
        delete_post_meta($post_id, 'assigned_labs');
    }
});

add_shortcode('lab_training', function($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'lab_training') return '';

    $assigned = get_post_meta($post->ID, 'assigned_labs', true) ?: [];
    $all_labs = get_option('lab_launcher_labs', []);

    if (!function_exists('get_user_lab_status')) {
        function get_user_lab_status($user_id, $lab_id) {
            // Teszt funkció: mindig "not_started"
            return 'not_started';
        }
    }

    ob_start();
    echo "<div class='training-box' style='font-family:sans-serif;'>";
    echo "<h2 style='font-size: 28px; margin-bottom: 1rem;'>Képzés: " . esc_html($post->post_title) . "</h2>";

    foreach ($assigned as $lab_id) {
        if (!isset($all_labs[$lab_id])) continue;
        $lab = $all_labs[$lab_id];

        $status = get_user_lab_status(get_current_user_id(), $lab_id); // 'ready', 'in_progress', 'not_started'
        $icon = ($status === 'ready') ? '✅' : (($status === 'in_progress') ? '⏳' : '▶️');

        echo "<div style='border:2px solid #00AAB2;padding:16px;margin-bottom:16px;border-radius:16px;display:flex;align-items:center;'>";
        echo "<div style='font-size: 32px; margin-right: 12px;'>$icon</div>";
        echo "<div>";
        echo "<div style='font-size: 20px; font-weight: bold;'>" . esc_html($lab['lab_title'] ?? $lab_id) . "</div>";

        echo "<div style='color: #555;'>" . esc_html($lab['lab_brief'] ?? '') . "</div>";

        echo "</div></div>";
    }

    echo "</div>";
    return ob_get_clean();
});

function render_shortcode_box($post) {
    echo '<input type="text" readonly value="[lab_training id=' . esc_attr($post->ID) . ']" style="width:100%;">';
}
