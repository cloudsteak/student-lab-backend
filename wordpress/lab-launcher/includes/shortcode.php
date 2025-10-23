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
            $status_text = '<span style="background-color:rgba(255, 255, 0, 0.7); color: black; border-radius:5px;padding:5px 30px 5px 30px;">Folyamatban...</span>';
        } elseif ($lab_status === 'success') {
            $status_text = '<span style="background-color:rgba(0, 0, 255, 0.7); color: white; border-radius:5px;padding:5px 30px 5px 30px;">Elérhető</span>';
        } elseif ($lab_status === 'error') {
            $status_text = '<span style="background-color:rgba(255, 0, 0, 0.7); color: white; border-radius:5px;padding:5px 30px 5px 30px;">Hiba történt</span>';
        }

        if (!empty($lab['image_id'])) {
            $image_url = wp_get_attachment_image_url($lab['image_id'], 'medium');
            $output .= '<img src="' . esc_url($image_url) . '" style="max-width:100%;height:auto;" />';
        }


        $output .= '<div class="lab-launcher-box">';
        $output .= '  <div class="lab-launcher" 
                    data-id="' . esc_attr($id) . '"
                    data-lab="' . esc_attr($lab['lab_name']) . '" 
                    data-cloud="' . esc_attr($lab['cloud']) . '" 
                    data-lab-ttl="' . esc_attr($lab['lab_ttl']) . '">';
        $output .= '<table class="table-lab-header">';
        $output .= '<tr>';
        $output .= '<td><button id="lab-launch-button" class="lab-launch-button before-lab-ready">Kezdés <i class="fa-solid fa-play"></i></button></td>';
        $output .= '<td><span class="lab-name">(' . esc_html($lab['lab_name']) . ' - ' . strtoupper($lab['cloud']) . ')</span></td>';
        $output .= '<td><span class="lab-status">' . $status_text . '</span></td>';
        $output .= '<td><span id="lab-countdown" class="lab-counters"></span></td>';
        $output .= '</tr>';
        $output .= '</table>';


        $output .= '<div class="lab-result" style="margin-top:10px;"></div>';
        $output .= '<div class="lab-checker" 
                    data-lab="' . esc_attr($lab['lab_name']) . '" 
                    data-cloud="' . esc_attr($lab['cloud']) . '" >';


        $output .= '  </div>';
        $output .= '</div>';
        $output .= '<div class="lab-description paginated">';
        $output .= '  <div class="lab-page-group">';

        $paragraphs = explode('<!-- pagebreak -->', wp_kses_post($lab['description']));
        $pageSize = 1;
        $pageIndex = 0;

        foreach (array_chunk($paragraphs, $pageSize) as $chunk) {
            $output .= '<div class="lab-page" style="display: none;" data-page="' . $pageIndex++ . '">';
            foreach ($chunk as $para) {
                if (trim($para)) {
                    $output .= $para . '</p>';
                }
            }
            $output .= '</div>';
        }

        $output .= '  </div>';
        $output .= '</div>';
        $output .= '<div class="lab-pagination-controls">';
        $output .= '  <button class="lab-prev lab-page-button">Előző</button>';
        $output .= '  <span class="lab-page-indicator">1 / 1</span>';
        $output .= '  <button class="lab-next lab-page-button">Következő</button>';
        $output .= '</div>';

        $output .= '<script>
            window.LabLauncherVars = {
            email: ' . json_encode($lab_launcher_user_email) . '
            };
            </script>';

        $refresh_interval = intval(get_option('lab_launcher_settings')['status_refresh_interval'] ?? 30);
        $output .= '<script>window.labLauncherRefreshInterval = ' . $refresh_interval . ';</script>';
        $output .= '<table class="table-lab-result">';
        $output .= '<tr>';
        $output .= '<td><button id="lab-check-button" class="lab-check-button">Kész vagyok - Ellenőrzés <i class="fa-solid fa-check-double"></i></button></td>';
        $output .= '</tr>';
        $output .= '<tr>';
        $output .= '<td><div class="lab-check-result" style="margin-top:10px;"></div></td>';
        $output .= '</tr>';
        $output .= '</table>';

        add_action('wp_enqueue_scripts', function () {
            global $lab_launcher_user_email;

            wp_enqueue_script('lab-launcher-logic'); // ha még nem volt beállítva külön

            wp_localize_script('lab-launcher-logic', 'LabLauncherVars', [
                'email' => $lab_launcher_user_email,
            ]);
        });
        add_action('wp_footer', 'lab_launcher_enqueue_script');
        add_action('wp_footer', 'lab_check_enqueue_script');
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
    if ($screen->base !== 'cloud-lab_page_lab-launcher-labs') {
        return;
    }

    $labs = get_option('lab_launcher_labs', []);
    if (empty($labs))
        return;

    echo '<div class="notice notice-info"><strong>Elérhető Lab-ok:</strong><br>';
    echo '<table class="table-admin-labs">';
    echo '<tr>';
    echo '<th>Azonosító</th>';
    echo '<th>Név</th>';
    echo '<th>Leírás</th>';
    echo '<th>Felhő</th>';
    echo '<th colspan=2></th>';
    echo '</tr>';
    foreach ($labs as $index => $lab) {
        echo '<tr>';
        echo '<td>' . esc_html($lab['id']) . '</td>';
        echo '<td>' . esc_html($lab['lab_title']) . '</td>';
        echo '<td>' . esc_html($lab['lab_brief']) . '</td>';
        echo '<td>' . esc_html($lab['cloud']) . '</td>';
        echo '<td><a href="' . esc_url(admin_url('admin.php?page=lab-launcher-labs&edit_lab=' . urlencode($lab['id']))) . '" class="button button-small lab-admin-button">Szerkesztés</a></td>';
        echo '<td><form method="post" style="display:inline; margin-left:10px;">
        <input type="hidden" name="lab_launcher_delete_index" value="' . esc_attr($lab['id']) . '" />
        ' . wp_nonce_field('lab_launcher_delete_lab', '_wpnonce', true, false) . '
        <input type="submit" class="button button-small lab-admin-button" value="Törlés" onclick="return confirm(\'Biztosan törölni szeretnéd ezt a labot?\')">
        </form></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';

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
        // 0. Törlés sessionStorage
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        sessionStorage.setItem(`lab_lab_countdown_${urlParams.get('id')}`, '0');
        sessionStorage.setItem(`lab_start_countdown_${urlParams.get('id')}`, '0');
        // 1. Indítás kezelése
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.lab-launch-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const launcher = button.closest('.lab-launcher');
                    const labName = launcher.dataset.lab;
                    const labId = launcher.dataset.id
                    const cloudProvider = launcher.dataset.cloud;
                    const labTTL = launcher.dataset.labTtl;
                    const resultBox = launcher.querySelector('.lab-result') || launcher.nextElementSibling;

                    document.getElementById("lab-check-button").style.display = "none";

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

                        const copyIcon = (text) => `<button onclick="navigator.clipboard.writeText('${text.replace(/'/g, "\\'")}')" title="Másolás" style="margin-left:6px;cursor:pointer;background:none;border:none;color:black;"><i class="fa-solid fa-copy"></i></button>`;

                    if (res.ok) {
                        document.getElementById('lab-launch-button').innerHTML = 'Folyamatban <i class="fa-solid fa-hourglass-start"></i>';
                        let username = data.username;
                        const password = data.password;
                        if (cloudProvider === 'azure') {
                            username += '@evolvia.hu';
                        }

                        let loginLink = '';
                        if (cloudProvider === 'azure') {
                            loginLink = `<a href="https://portal.azure.com" target="_blank" rel="noopener noreferrer">Azure Portál <i class="fa-solid fa-up-right-from-square"></i></a><br>`;
                        } else if (cloudProvider === 'aws') {
                            loginLink = `<a href="https://cloudsteak.signin.aws.amazon.com/console" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-up-right-from-square"></i> AWS Console (AWS)</a><br>`;
                        }

                        sessionStorage.setItem(`lab_user_${labId}`, username);
                        sessionStorage.setItem(`lab_pass_${labId}`, password);
                        sessionStorage.setItem(`lab_uri_${labId}`, loginLink);
                        sessionStorage.setItem(`lab_cloud_${labId}`, cloudProvider);
                        sessionStorage.setItem(`lab_is_started_${labId}`, '1');
                        startCountdown(labId, 300, "múlva elérhető a gyakorló környezet.", "Már csak néhány pillanat.");

                        sessionStorage.setItem(`lab_ttl_${labId}`, parseInt(labTTL));


                        resultBox.innerHTML =
                            `<table class="table-lab-login"><tr>` +
                            `<td>Felhasználónév: <strong>${username}</strong> ${copyIcon(username)}</td>` +
                            `<td>|</td>` +
                            `<td>Jelszó: <strong>${password}</strong> ${copyIcon(password)}</td>` +
                            `<td>|</td>` +
                            `<td>${loginLink}</td>` +
                            `</tr><tr>` +
                            `<td colspan=5><span class="before-lab-ready">Hamarosan értesítést kapsz a gyakorló környezet állapotáról.</span></td>` +
                            `</tr></table>` +
                            `<span id="clean-username" hidden="hidden">${username}</span>`;


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

            const resultBox = launcher.querySelector('.lab-result');

            const username = sessionStorage.getItem(`lab_user_${labId}`);
            const password = sessionStorage.getItem(`lab_pass_${labId}`);
            const loginLink = sessionStorage.getItem(`lab_uri_${labId}`);
            const cloudProvider = sessionStorage.getItem(`lab_cloud_${labId}`);
            const is_started = sessionStorage.getItem(`lab_is_started_${labId}`);
            const startTime = sessionStorage.getItem(`lab_start_time_${labId}`);

            if (username && password && loginLink && cloudProvider && resultBox) {
                const copyIcon = (text) => `<button onclick="navigator.clipboard.writeText('${text.replace(/'/g, "\\'")}')" title="Másolás" style="margin-left:6px;cursor:pointer;background:none;border:none;color:black;"><i class="fa-solid fa-copy"></i></button>`;

                let loginLink = '';
                if (cloudProvider === 'azure') {
                    loginLink = `<a href="https://portal.azure.com" target="_blank" rel="noopener noreferrer">Azure Portál <i class="fa-solid fa-up-right-from-square"></i></a><br>`;
                } else if (cloudProvider === 'aws') {
                    loginLink = `<a href="https://cloudsteak.signin.aws.amazon.com/console" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-up-right-from-square"></i> AWS Console</a><br>`;
                }

                resultBox.innerHTML =
                    `<table class="table-lab-login"><tr>` +
                    `<td>Felhasználónév: <strong>${username}</strong> ${copyIcon(username)}</td>` +
                    `<td>|</td>` +
                    `<td>Jelszó: <strong>${password}</strong> ${copyIcon(password)}</td>` +
                    `<td>|</td>` +
                    `<td>${loginLink}</td>` +
                    `</tr><tr>` +
                    `<td colspan=5><span class="before-lab-ready">Hamarosan értesítést kapsz a gyakorló környezet állapotáról.</span></td>` +
                    `</tr></table>` +
                    `<span id="clean-username" hidden="hidden">${username}</span>`;
            }

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

                        if ((data.status === 'pending') && is_started === '1') {
                            html = '<span style="background-color:rgba(255, 255, 0, 0.7); color: black; border-radius:5px;padding:5px 30px 5px 30px;">Folyamatban...</span>';
                            document.getElementById('lab-launch-button').innerHTML = 'Folyamatban <i class="fa-solid fa-hourglass-start"></i>';
                            startCountdown(labId, 180, "múlva elérhető.", "Már csak néhány pillanat.");
                        } else if (data.status === 'success') {
                            const labStartTime = sessionStorage.getItem(`lab_start_time_${labId}`);
                            if (!labStartTime) {
                                const startTime = new Date().toISOString();
                                sessionStorage.setItem(`lab_start_time_${labId}`, startTime);
                            }
                            const labTTL = sessionStorage.getItem(`lab_ttl_${labId}`);
                            startLabCountdown(labId, labTTL, " - A lab teljesítésére szánt idő:", "Sajnos lejárt az idő.");
                            html = '<span style="background-color:rgba(0, 0, 255, 0.7); color: white; border-radius:5px;padding:5px 30px 5px 30px;">Elérhető</span>';
                            document.getElementById("lab-check-button").style.display = "inline";
                            document.querySelectorAll('.before-lab-ready').forEach(el => {
                                el.style.display = 'none';
                            });
                        } else if (data.status === 'error') {
                            html = '<span style="background-color:rgba(255, 0, 0, 0.7); color: white; border-radius:5px;padding:5px 30px 5px 30px;">Hiba történt</span>';
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

    // 3. Leírás tördelése lapokra
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.lab-description.paginated').forEach(desc => {
            const pages = desc.querySelectorAll('.lab-page');
            const controls = desc.nextElementSibling;

            let currentPage = 0;

            const prevBtn = controls.querySelector('.lab-prev');
            const nextBtn = controls.querySelector('.lab-next');
            const pageIndicator = controls.querySelector('.lab-page-indicator');

            const updateView = () => {
                pages.forEach((page, idx) => {
                    page.style.display = (idx === currentPage) ? 'block' : 'none';
                });
                pageIndicator.textContent = `${currentPage + 1} / ${pages.length}`;
                prevBtn.disabled = currentPage === 0;
                nextBtn.disabled = currentPage >= pages.length - 1;

                // Görgetés a tartalom tetejére
                const queryString = window.location.search;
                const urlParams = new URLSearchParams(queryString);
                const labId = urlParams.get('id');
                const is_started = sessionStorage.getItem(`lab_is_started_${labId}`);
                if (is_started === '1') {
                    desc.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            };

            prevBtn.addEventListener('click', () => {
                if (currentPage > 0) {
                    currentPage--;
                    updateView();
                }
            });

            nextBtn.addEventListener('click', () => {
                if (currentPage < pages.length - 1) {
                    currentPage++;
                    updateView();
                }
            });

            updateView();
        });
    });


    // 4. Visszaszámlálás - Várakozás a lab elindulására
    function startCountdown(labId, labDurationSeconds, countdownMessage, timeIsUpMessage, errorMessage = "A visszaszámlálás nem indult el.") {
        const countdownElement = document.getElementById("lab-countdown");
        // const queryString = window.location.search;
        // const urlParams = new URLSearchParams(queryString);
        // const labId = urlParams.get('id');
        const isStarted = sessionStorage.getItem(`lab_is_started_${labId}`);
        const isLabCountdown = sessionStorage.getItem(`lab_start_countdown_${labId}`);

        if (!isStarted || isLabCountdown === '1') {
            countdownElement.innerText = `${errorMessage}`;
            return;
        }

        const startDate = new Date();
        const endDate = new Date(startDate.getTime() + labDurationSeconds * 1000);

        const interval = setInterval(() => {
            const startTime = sessionStorage.getItem(`lab_start_time_${labId}`);
            if (startTime) {
                return;
            }
            sessionStorage.setItem(`lab_start_countdown_${labId}`, '1');
            const now = new Date();
            const remaining = endDate - now;

            if (remaining <= 0) {
                clearInterval(interval);
                countdownElement.innerText = `${timeIsUpMessage}`;
                return;
            }

            const hours = Math.floor((remaining / 1000 / 60 / 60) % 24);
            const minutes = Math.floor((remaining / 1000 / 60) % 60);
            const seconds = Math.floor((remaining / 1000) % 60);

            countdownElement.innerText =
                `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${countdownMessage}`;
        }, 1000);
    }

    // 5. Visszaszámlálás - Felhasználó értesítése a rendelkezésre álló időről
    function startLabCountdown(labId, labDurationSeconds, countdownMessage, timeIsUpMessage, errorMessage = "A visszaszámlálás nem indult el.") {
        const countdownElement = document.getElementById("lab-countdown");
        // const queryString = window.location.search;
        // const urlParams = new URLSearchParams(queryString);
        // const labId = urlParams.get('id');
        const isLabCountdown = sessionStorage.getItem(`lab_lab_countdown_${labId}`);
        const startTime = sessionStorage.getItem(`lab_start_time_${labId}`);
        const isStarted = sessionStorage.getItem(`lab_is_started_${labId}`);

        if (!isStarted || isLabCountdown === '1') {
            return;
        }

        if (!startTime) {
            countdownElement.innerText = `${errorMessage}`;
            return;
        }


        const startDate = new Date(startTime);
        const endDate = new Date(startDate.getTime() + labDurationSeconds * 1000);

        const interval = setInterval(() => {
            sessionStorage.setItem(`lab_lab_countdown_${labId}`, '1');
            const now = new Date();
            const remaining = endDate - now;

            if (remaining <= 0) {
                clearInterval(interval);
                countdownElement.innerText = `${timeIsUpMessage}`;
                return;
            }

            const hours = Math.floor((remaining / 1000 / 60 / 60) % 24);
            const minutes = Math.floor((remaining / 1000 / 60) % 60);
            const seconds = Math.floor((remaining / 1000) % 60);

            countdownElement.innerText =
                `${countdownMessage} ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    }




</script>

<?php
}


function lab_check_enqueue_script()
{
    ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.lab-check-button').forEach(button => {
            button.addEventListener('click', async () => {
                const checker = document.querySelectorAll('.lab-checker')[0];
                const labName = checker.dataset.lab;
                const cloudProvider = checker.dataset.cloud;
                const username = document.getElementById("clean-username")?.textContent;
                const cleanUsername = username?.split('@')[0]; // Extract the part before '@'
                //const resultBox = checker.querySelector('.lab-check-result') || checker.nextElementSibling;
                const resultBox = document.querySelectorAll('.lab-check-result')[0];

                console.log('Ellenőrzés indítása:', { labName, cloudProvider, username });

                button.disabled = true;


                try {
                    const res = await fetch('/wp-json/lab-launcher/v1/verify-lab', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            lab_name: labName,
                            cloud_provider: cloudProvider,
                            user: cleanUsername
                        })
                    });

                    const data = await res.json();

                    if (res.ok) {
                        if (data.success != true) {
                            verifyicon = "<i class='fa-solid fa-triangle-exclamation fa-3x'></i>";
                            verifyclass = "error";
                        } else {
                            verifyicon = "<i class='fa-solid fa-square-check fa-3x'></i>";
                            verifyclass = "success";
                            // Ellenőrző gomb elrejtése
                            document.getElementById("lab-check-button").style.display = "none";
                        }
                        resultBox.innerHTML =
                            `<span class=${verifyclass}>${verifyicon}</span>` +
                            `<br><p>${data.message}</p>` +
                            `${data.success !== true ? "<p style='font-weight: 600; color: red; font-size: 16px;'><i class='fa-solid fa-triangle-exclamation'></i> Javítsd ki a hibát, és próbáld újra. <i class='fa-solid fa-triangle-exclamation'></i> </p>" : "<p style='font-weight: 800; color: #bf9b30; font-size: 22px;'><i class='fa-solid fa-trophy'></i> Gratulálok, teljesítetted a feladatot! <i class='fa-solid fa-trophy'></i></p>"}`;
                    } else {
                        resultBox.innerHTML = `<span style='color:red;'>Hiba: ${data.message || 'Ismeretlen'}</span>`;
                    }
                } catch (e) {
                    console.error('Hiba:', e);
                    resultBox.innerHTML = `<span style='color:red;'>Hálózati hiba vagy válasz sikertelen.</span>`;
                } finally {
                    // Átmenetileg tiltom az újraellenőrzést
                    button.disabled = false;
                    // Azure Portal lab esetén mindig sikeres az ellenőrzés
                    if (labName === 'mk-7-01-portal') {
                        let verifyicon = "<i class='fa-solid fa-square-check fa-3x'></i>";
                        let verifyclass = "success";
                        resultBox.innerHTML =
                            `<span class=${verifyclass}>${verifyicon}</span>` +
                            `<br><p>Sikeres ellenőrzés!</p>`;
                        // Ellenőrző gomb elrejtése
                        document.getElementById("lab-check-button").style.display = "none";
                    }
                }
            });
        });
    });
</script>

<?php
}

