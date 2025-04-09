<?php
// includes/shortcode.php

add_shortcode('lab_launcher', 'lab_launcher_render_shortcode');

function lab_launcher_render_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => 0,
    ), $atts);

    $labs = get_option('lab_launcher_labs', []);

    // Ha az 'id' nem numerikus, akkor keressük meg a lab nevét
    if (!is_numeric($atts['id'])) {
        foreach ($labs as $i => $lab) {
            if ($lab['lab_name'] === $atts['id']) {
                $atts['id'] = $i;
                break;
            }
        }
    }

    $index = intval($atts['id']);

    if (!isset($labs[$index])) {
        return '<p>Lab nem található.</p>';
    }

    $lab = $labs[$index];
    $output = '';

    if (is_user_logged_in()) {
        $output .= '<div class="lab-launcher-box">';

        if (!empty($lab['image_id'])) {
            $image_url = wp_get_attachment_image_url($lab['image_id'], 'medium');
            $output .= '<img src="' . esc_url($image_url) . '" style="max-width:100%;height:auto;" />';
        }

        $output .= '<div class="lab-description">' . wp_kses_post($lab['description']) . '</div>';
        $output .= '<button class="lab-start-button" data-lab-name="' . esc_attr($lab['lab_name']) . '" data-cloud-provider="' . esc_attr($lab['cloud_provider']) . '">Lab indítása</button>';
        $output .= '<div class="lab-result" style="margin-top:10px;"></div>';
        $output .= '</div>';

        // Inline JS betöltése
        add_action('wp_footer', 'lab_launcher_enqueue_script');
    } else {
        $output .= '<p>Kérlek, jelentkezz be a lab eléréséhez.</p>';
    }

    return $output;
}

// Shortcode lista admin oldalon lab törléssel
add_action('admin_notices', 'lab_launcher_shortcode_list_notice');
function lab_launcher_shortcode_list_notice()
{
    $screen = get_current_screen();
    if ($screen->id !== 'toplevel_page_lab-launcher-labs')
        return;

    $labs = get_option('lab_launcher_labs', []);
    if (empty($labs))
        return;

    echo '<div class="notice notice-info"><p><strong>Elérhető shortcode-ok:</strong><br>';
    foreach ($labs as $index => $lab) {
        echo '[lab_launcher id="' . esc_html($lab['lab_name']) . '"] ';
        echo '<form method="post" style="display:inline; margin-left:10px;">
            <input type="hidden" name="lab_launcher_delete_index" value="' . esc_attr($index) . '" />
            ' . wp_nonce_field('lab_launcher_delete_lab', '_wpnonce', true, false) . '
            <input type="submit" class="button button-small" value="Törlés" onclick="return confirm(\'Biztosan törölni szeretnéd ezt a labot?\')">
        </form><br>';
    }
    echo '</p></div>';

    // Törlés feldolgozása
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lab_launcher_delete_index']) && check_admin_referer('lab_launcher_delete_lab')) {
        $index = intval($_POST['lab_launcher_delete_index']);
        if (isset($labs[$index])) {
            unset($labs[$index]);
            $labs = array_values($labs); // újraindexelés
            update_option('lab_launcher_labs', $labs);
            echo '<div class="updated"><p>Lab sikeresen törölve.</p></div>';
        }
    }
}

function lab_launcher_enqueue_script()
{
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.lab-start-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const labName = button.dataset.labName;
                    const cloudProvider = button.dataset.cloudProvider;
                    const resultBox = button.nextElementSibling;

                    console.log('Küldés indítása:', { labName, cloudProvider });

                    const res = await fetch('/wp-json/lab-launcher/v1/start-lab', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            lab_name: labName,
                            cloud_provider: cloudProvider
                        })
                    });

                    const data = await res.json();
                    console.log('Backend response:', data);

                    if (res.ok) {
                        let username = data.username;
                        if (cloudProvider === 'azure'){
                            username = username + '@cloudsteak.com';
                        }

                        resultBox.innerHTML = `<strong>Felhasználónév:</strong> ${username}<br><strong>Jelszó:</strong> ${data.password}<p>Hamarosan értesítést kapsz a gyakorló környezet állapotáról.</p>`;
                    } else {
                        resultBox.innerHTML = `<span style='color:red;'>Hiba: ${data.message || 'Ismeretlen'}</span>`;
                    }
                });
            });
        });
    </script>
    <?php
}


