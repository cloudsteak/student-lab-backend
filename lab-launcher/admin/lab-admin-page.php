<?php
// admin/lab-admin-page.php

add_action('admin_menu', 'lab_launcher_lab_menu');
function lab_launcher_lab_menu()
{
    add_menu_page(
        'Cloud Lab kezelő',
        'Cloud Lab kezelő',
        'manage_options',
        'lab-launcher-labs',
        'lab_launcher_labs_page',
        'dashicons-welcome-learn-more',
        30
    );
}

function lab_launcher_labs_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Ha van edit_lab paraméter, betöltjük az adott lab adatait
    $edit_lab_id = isset($_GET['edit_lab']) ? sanitize_text_field($_GET['edit_lab']) : '';
    $existing_lab = null;

    if ($edit_lab_id) {
        $labs = get_option('lab_launcher_labs', []);
        if (isset($labs[$edit_lab_id])) {
            $existing_lab = $labs[$edit_lab_id];
        }
    }

    // Mentés feldolgozása
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('lab_launcher_save_lab')) {

        $labs = get_option('lab_launcher_labs', []);

        $id = sanitize_text_field($_POST['lab_launcher_lab_id']);
        $is_edit = isset($_GET['edit_lab']) && $_GET['edit_lab'] === $id;

        // Duplikáció ellenőrzés (ha nem szerkesztés történik)
        if (!$is_edit && isset($labs[$id])) {
            echo '<div class="error"><p><strong>Hiba:</strong> Már létezik ilyen azonosító: ' . esc_html($id) . '</p></div>';
            return;
        }

        $id = sanitize_text_field($_POST['lab_launcher_lab_id']);
        $labs[$id] = array(
            'id' => $id,
            'lab_name' => sanitize_text_field($_POST['lab_launcher_lab_name']), // amit a backend kap
            'lab_id' => $id, // shortcode ID külön elmentve is, ha kell
            'cloud' => sanitize_text_field($_POST['cloud_provider']),
            'description' => wp_kses_post($_POST['description']),
            'image_id' => intval($_POST['image_id']),
            'lab_ttl' => intval($_POST['lab_launcher_ttl']) ?: 5400
        );

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
                    <th scope="row"><label for="lab_launcher_lab_id">Lab azonosító (shortcode id)</label></th>
                    <td><input name="lab_launcher_lab_id" type="text" required class="regular-text"
                            placeholder="pl. azure-basic" value="<?php echo esc_attr($existing_lab['id'] ?? ''); ?>" <?php echo $existing_lab ? 'readonly' : ''; ?>>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="lab_launcher_lab_name">Lab neve (backend-nek)</label></th>
                    <td><input name="lab_launcher_lab_name" type="text" required class="regular-text"
                            placeholder="pl. basic, vm, vmss"
                            value="<?php echo esc_attr($existing_lab['lab_name'] ?? ''); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Kép feltöltése</th>
                    <td>
                        <input type="hidden" name="image_id" id="lab_image_id">
                        <button type="button" class="button" id="upload_image_button">Kép kiválasztása</button>
                        <div id="image_preview" style="margin-top: 10px;">
                            <?php
                            if (!empty($existing_lab['image_id'])) {
                                $image_url = wp_get_attachment_image_url($existing_lab['image_id'], 'medium');
                                if ($image_url) {
                                    echo '<img src="' . esc_url($image_url) . '" style="max-width:300px;height:auto;">';
                                }
                            }
                            ?>
                        </div>

                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cloud_provider">Felhő szolgáltató</label></th>
                    <td><select name="cloud_provider" required class="regular-text">
                            <option value="aws" <?php selected($existing_lab['cloud'] ?? '', 'aws'); ?>>AWS</option>
                            <option value="azure" <?php selected($existing_lab['cloud'] ?? '', 'azure'); ?>>Azure</option>
                        </select>

                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="description">Leírás</label></th>
                    <td><?php wp_editor($existing_lab['description'] ?? '', 'description'); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="lab_launcher_ttl">Lab TTL (másodpercben)</label></th>
                    <td><input name="lab_launcher_ttl" type="number" required class="regular-text"
                            value="<?php echo esc_attr($existing_lab['lab_ttl'] ?? 5400); ?>">
                    </td>
                </tr>

            </table>
            <?php submit_button('Lab mentése'); ?>
        </form>
    </div>
    <script>
        jQuery(document).ready(function ($) {
            let frame;
            $('#upload_image_button').on('click', function (e) {
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
                frame.on('select', function () {
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
