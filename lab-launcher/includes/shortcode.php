<?php
// includes/shortcode.php

add_shortcode('lab_launcher', 'lab_launcher_render_shortcode');

function lab_launcher_render_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);

    $labs = get_option('lab_launcher_labs', []);
    $id = $atts['id'];

    if (!isset($labs[$id])) {
        return '<p>Hiba: Nem található a megadott lab azonosító.</p>';
    }

    $lab = $labs[$id];
    $output = '';

    if (is_user_logged_in()) {

        global $lab_launcher_user_email;
        $status_key = $lab_launcher_user_email . '|' . $id;
        $statuses = get_option('lab_launcher_statuses', []);
        $lab_status = $statuses[$status_key] ?? null;

        // Előre generáljuk a státuszt
        $status_text = '';
        if ($lab_status === 'pending') {
            $status_text = '<span style="color: orange;">Folyamatban...</span>';
        } elseif ($lab_status === 'success') {
            $status_text = '<span style="color: green;">Készen áll</span>';
        } elseif ($lab_status === 'error') {
            $status_text = '<span style="color: red;">Hiba történt</span>';
        }

        if (!empty($lab['image_id'])) {
            $image_url = wp_get_attachment_image_url($lab['image_id'], 'medium');
            $output .= '<img src="' . esc_url($image_url) . '" style="max-width:100%;height:auto;" />';
        }

        $output .= '<div class="lab-description">' . wp_kses_post($lab['description']) . '</div>';
        $output .= '<div class="lab-launcher-box">';
        $output .= '  <div class="lab-launcher" 
                    data-id="' . esc_attr($id) . '"
                    data-lab="' . esc_attr($lab['lab_name']) . '" 
                    data-cloud="' . esc_attr($lab['cloud']) . '" 
                    data-lab-ttl="' . esc_attr($lab['lab_ttl']) . '">';
        $output .= '    <p><strong>Lab:</strong> ' . esc_html($lab['lab_name']) . ' (' . strtoupper($lab['cloud']) . ')</p>';

        $output .= '    <button class="lab-launch-button">Lab indítása <i class="fa-solid fa-play"></i></button>';
        $output .= '    <div class="lab-status">' . $status_text . '</div>';
        $output .= '    <div class="lab-result" style="margin-top:10px;"></div>';
        $output .= '  </div>';
        $output .= '</div>';

        $refresh_interval = intval(get_option('lab_launcher_settings')['status_refresh_interval'] ?? 30);
        $output .= '<script>window.labLauncherRefreshInterval = ' . $refresh_interval . ';</script>';

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
        echo '[lab_launcher id="' . esc_html($lab['id']) . '"] ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=lab-launcher-labs&edit_lab=' . urlencode($lab['id']))) . '" class="button button-small">Szerkesztés</a>';
        echo '<form method="post" style="display:inline; margin-left:10px;">
        <input type="hidden" name="lab_launcher_delete_index" value="' . esc_attr($lab['id']) . '" />
        ' . wp_nonce_field('lab_launcher_delete_lab', '_wpnonce', true, false) . '
        <input type="submit" class="button button-small" value="Törlés" onclick="return confirm(\'Biztosan törölni szeretnéd ezt a labot?\')">
        </form><br>';
    }
    echo '</p></div>';

    // Törlés feldolgozása
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lab_launcher_delete_index']) && check_admin_referer('lab_launcher_delete_lab')) {
        $index = sanitize_text_field($_POST['lab_launcher_delete_index']);
        if (isset($labs[$index])) {
            unset($labs[$index]);
            update_option('lab_launcher_labs', $labs);
            echo '<div class="updated"><p>Lab sikeresen törölve.</p></div>';
        }
    }
}

function lab_launcher_enqueue_script()
{
    ?>
    <script>
        // 1. Indítás kezelése
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.lab-launch-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const launcher = button.closest('.lab-launcher');
                    const labName = launcher.dataset.lab;
                    const cloudProvider = launcher.dataset.cloud;
                    const labTTL = launcher.dataset.labTtl;
                    const resultBox = launcher.querySelector('.lab-result') || launcher.nextElementSibling;

                    console.log('Küldés indítása:', { labName, cloudProvider, labTTL });

                    button.disabled = true;
                    launcher.querySelector('.lab-status').textContent = 'Indítás folyamatban...';

                    try {
                        const res = await fetch('/wp-json/lab-launcher/v1/start-lab', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                lab_name: labName,
                                cloud_provider: cloudProvider,
                                lab_ttl: parseInt(labTTL)
                            })
                        });

                        const data = await res.json();
                        console.log('Backend response:', data);

                        const copyIcon = (text) => `<button onclick="navigator.clipboard.writeText('${text.replace(/'/g, "\\'")}')" title="Másolás" style="margin-left:6px;cursor:pointer;background:none;border:none;"><i class="fa-solid fa-copy"></i></button>`;

                        if (res.ok) {
                            let username = data.username;
                            if (cloudProvider === 'azure') {
                                username += '@cloudsteak.com';
                            }

                            let loginLink = '';
                            if (cloudProvider === 'azure') {
                                loginLink = `<a href="https://portal.azure.com" target="_blank" rel="noopener noreferrer">Belépés a laborba (Azure) <i class="fa-solid fa-up-right-from-square"></i></a><br>`;
                            } else if (cloudProvider === 'aws') {
                                loginLink = `<a href="https://cloudsteak.signin.aws.amazon.com/console" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-up-right-from-square"></i> Belépés a laborba (AWS)</a><br>`;
                            }

                            resultBox.innerHTML =
                                loginLink +
                                `Felhasználónév: <strong>${username}</strong> ${copyIcon(username)}<br>` +
                                `<strong>Jelszó: <strong>${data.password}</strong> ${copyIcon(data.password)}<br>` +
                                `<br><p>Hamarosan értesítést kapsz a gyakorló környezet állapotáról.</p>`;
                        } else {
                            resultBox.innerHTML = `<span style='color:red;'>Hiba: ${data.message || 'Ismeretlen'}</span>`;
                        }
                    } catch (e) {
                        console.error('Hiba:', e);
                        resultBox.innerHTML = `<span style='color:red;'>Hálózati hiba vagy válasz sikertelen.</span>`;
                    } finally {
                        launcher.querySelector('.lab-status').textContent = '';
                        button.disabled = false;
                    }
                });
            });
        });
        // 2. Automatikus státuszfrissítés
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.lab-launcher').forEach(launcher => {
                const labId = launcher.dataset.id;
                const labStatusDiv = launcher.querySelector('.lab-status');

                const checkStatus = async () => {
                    try {
                        const res = await fetch('/wp-json/lab-launcher/v1/lab-status-update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ lab_id: labId }) // csak a lab_id szükséges
                        });

                        const data = await res.json();
                        if (data && data.status) {
                            let html = '';
                            if (data.status === 'pending') {
                                html = '<span style="color: orange;">Folyamatban...</span>';
                            } else if (data.status === 'success') {
                                html = '<span style="color: green;">Készen áll</span>';
                            } else if (data.status === 'error') {
                                html = '<span style="color: red;">Hiba történt</span>';
                            }
                            labStatusDiv.innerHTML = html;
                        }
                    } catch (e) {
                        console.warn('Státusz lekérdezés sikertelen:', e);
                    }
                };

                checkStatus();
                // Automatikus státuszfrissítés, ha globális érték be van állítva
                if (window.labLauncherRefreshInterval && parseInt(window.labLauncherRefreshInterval) > 0) {
                    setInterval(checkStatus, parseInt(window.labLauncherRefreshInterval) * 1000);
                }
            });
        });
    </script>

    <?php
}



