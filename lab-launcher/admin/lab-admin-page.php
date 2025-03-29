<?php
// admin/lab-admin-page.php

add_action('admin_menu', 'lab_launcher_lab_menu');
function lab_launcher_lab_menu() {
    add_menu_page(
        'Labok kezelése',
        'Labok',
        'manage_options',
        'lab-launcher-labs',
        'lab_launcher_labs_page',
        'dashicons-welcome-learn-more',
        30
    );
}

function lab_launcher_labs_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Mentés feldolgozása
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('lab_launcher_save_lab')) {
        $labs = get_option('lab_launcher_labs', []);

        $new_lab = array(
            'lab_name' => sanitize_text_field($_POST['lab_name']),
            'cloud_provider' => sanitize_text_field($_POST['cloud_provider']),
            'description' => wp_kses_post($_POST['description']),
            'image_id' => intval($_POST['image_id'])
        );

        $labs[] = $new_lab;
        update_option('lab_launcher_labs', $labs);

        echo '<div class="updated"><p>Lab sikeresen elmentve!</p></div>';
    }

    // űrlap megjelenítése
    ?>
    <div class="wrap">
        <h1>Lab létrehozása</h1>
        <form method="post">
            <?php wp_nonce_field('lab_launcher_save_lab'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="lab_name">Lab neve</label></th>
                    <td><input name="lab_name" type="text" required class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="cloud_provider">Cloud provider</label></th>
                    <td><input name="cloud_provider" type="text" required class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="description">Leírás</label></th>
                    <td><?php wp_editor('', 'description'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Kép feltöltése</th>
                    <td>
                        <input type="hidden" name="image_id" id="lab_image_id">
                        <button type="button" class="button" id="upload_image_button">Kép kiválasztása</button>
                        <div id="image_preview" style="margin-top: 10px;"></div>
                    </td>
                </tr>
            </table>
            <?php submit_button('Lab mentése'); ?>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($){
        let frame;
        $('#upload_image_button').on('click', function(e){
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Válassz egy képet',
                button: { text: 'Használat' },
                multiple: false
            });
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#lab_image_id').val(attachment.id);
                $('#image_preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto;">');
            });
            frame.open();
        });
    });
    </script>
    <?php
}
