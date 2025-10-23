<?php
// admin/user-statuses-page.php

if (!defined('ABSPATH')) { exit; }

// Menü: Cloud Lab alatt "Felhasználói státuszok"
add_action('admin_menu', function () {
    add_submenu_page(
        'cloud-lab',
        'Felhasználói státuszok',
        'Felhasználói státuszok',
        'edit_posts',
        'lab-user-statuses',
        'lab_launcher_render_user_statuses_page'
    );
});

// Biztonság: nonce kulcs
function lab_launcher_statuses_nonce_action() { return 'lab_launcher_statuses_action'; }
function lab_launcher_statuses_nonce_name() { return '_lab_launcher_statuses_nonce'; }

// Egy státusz reset kezelése (admin-post)
add_action('admin_post_lab_launcher_reset_status', function () {
    if (!current_user_can('edit_posts')) { wp_die('Nincs jogosultság.'); }
    check_admin_referer(lab_launcher_statuses_nonce_action(), lab_launcher_statuses_nonce_name());

    $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    $lab_id = isset($_GET['lab_id']) ? sanitize_text_field($_GET['lab_id']) : '';

    if ($email && $lab_id) {
        $statuses = get_option('lab_launcher_statuses', []);
        $key = "{$email}|{$lab_id}";
        if (isset($statuses[$key])) {
            unset($statuses[$key]);
            update_option('lab_launcher_statuses', $statuses);
        }
    }

    $redirect = add_query_arg(['page' => 'lab-user-statuses', 'reset' => '1'], admin_url('admin.php'));
    wp_safe_redirect($redirect);
    exit;
});

// Tömeges reset (POST űrlapról)
add_action('admin_post_lab_launcher_bulk_reset', function () {
    if (!current_user_can('edit_posts')) { wp_die('Nincs jogosultság.'); }
    check_admin_referer(lab_launcher_statuses_nonce_action(), lab_launcher_statuses_nonce_name());

    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? array_map('sanitize_text_field', $_POST['selected']) : [];
    if ($selected) {
        $statuses = get_option('lab_launcher_statuses', []);
        foreach ($selected as $key) {
            if (isset($statuses[$key])) { unset($statuses[$key]); }
        }
        update_option('lab_launcher_statuses', $statuses);
    }

    $redirect = add_query_arg(['page' => 'lab-user-statuses', 'bulk_reset' => '1'], admin_url('admin.php'));
    wp_safe_redirect($redirect);
    exit;
});

// Oldal kirajzolása
function lab_launcher_render_user_statuses_page() {
    if (!current_user_can('edit_posts')) { wp_die('Nincs jogosultság.'); }

    // Szűrők
    $q_email = isset($_GET['s_email']) ? sanitize_email($_GET['s_email']) : '';
    $q_lab   = isset($_GET['s_lab']) ? sanitize_text_field($_GET['s_lab']) : '';

    $statuses = get_option('lab_launcher_statuses', []);
    // $statuses: [ "email|lab_id" => "pending|success|error|..." ]

    // Felhasználóbarát lista: [ [email, lab_id, status, updated_at?] ]
    // (Most nincs külön timestamp, de később bővíthető. Itt csak a key és value van.)
    $rows = [];
    foreach ($statuses as $key => $status) {
        // key: email|labId
        $parts = explode('|', $key, 2);
        if (count($parts) !== 2) { continue; }
        list($email, $lab_id) = $parts;

        // Szűrés
        if ($q_email && stripos($email, $q_email) === false) { continue; }
        if ($q_lab && stripos($lab_id, $q_lab) === false) { continue; }

        $user = get_user_by('email', $email);
        $user_display = $user ? sprintf('%s (#%d)', $user->display_name ?: $user->user_login, $user->ID) : 'Ismeretlen felhasználó';

        $rows[] = [
            'key'    => $key,
            'email'  => $email,
            'user'   => $user_display,
            'lab_id' => $lab_id,
            'status' => $status,
        ];
    }

    // Rendezés email + lab szerint
    usort($rows, function($a, $b){
        return [$a['email'], $a['lab_id']] <=> [$b['email'], $b['lab_id']];
    });

    $nonce_field = wp_nonce_field(lab_launcher_statuses_nonce_action(), lab_launcher_statuses_nonce_name(), true, false);

    ?>
    <div class="wrap">
        <h1>Felhasználói státuszok</h1>

        <?php if (isset($_GET['reset'])): ?>
            <div class="notice notice-success is-dismissible"><p>Státusz visszaállítva.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['bulk_reset'])): ?>
            <div class="notice notice-success is-dismissible"><p>Tömeges visszaállítás kész.</p></div>
        <?php endif; ?>

        <form method="get" style="margin:16px 0;">
            <input type="hidden" name="page" value="lab-user-statuses"/>
            <input type="email" name="s_email" placeholder="Szűrés emailre" value="<?php echo esc_attr($q_email); ?>" />
            <input type="text" name="s_lab" placeholder="Szűrés lab azonosítóra" value="<?php echo esc_attr($q_lab); ?>" />
            <button class="button">Szűrés</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=lab-user-statuses')); ?>">Szűrők törlése</a>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php echo $nonce_field; ?>
            <input type="hidden" name="action" value="lab_launcher_bulk_reset" />

            <table class="widefat striped">
                <thead>
                    <tr>
                        <td style="width:24px;"><input type="checkbox" onclick="jQuery('.ll-check').prop('checked', this.checked);" /></td>
                        <th>Email</th>
                        <th>Felhasználó</th>
                        <th>Lab ID</th>
                        <th>Státusz</th>
                        <th>Műveletek</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6">Nincs megjeleníthető adat.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): 
                        $reset_url = wp_nonce_url(
                            add_query_arg([
                                'action' => 'lab_launcher_reset_status',
                                'email'  => $r['email'],
                                'lab_id' => $r['lab_id']
                            ], admin_url('admin-post.php')),
                            lab_launcher_statuses_nonce_action(),
                            lab_launcher_statuses_nonce_name()
                        );
                    ?>
                    <tr>
                        <td><input type="checkbox" class="ll-check" name="selected[]" value="<?php echo esc_attr($r['key']); ?>" /></td>
                        <td><?php echo esc_html($r['email']); ?></td>
                        <td><?php echo esc_html($r['user']); ?></td>
                        <td><code><?php echo esc_html($r['lab_id']); ?></code></td>
                        <td>
                            <?php
                                $label = [
                                    'pending' => 'Folyamatban',
                                    'success' => 'Elérhető',
                                    'error'   => 'Hiba',
                                ][$r['status']] ?? $r['status'];
                                echo esc_html($label);
                            ?>
                        </td>
                        <td>
                            <a class="button button-secondary" href="<?php echo esc_url($reset_url); ?>"
                               onclick="return confirm('Biztosan visszaállítod ezt a státuszt?');">
                               Reset
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">
                            <button class="button button-primary" 
                                    onclick="return confirm('Biztosan visszaállítod a kijelölteket?');">
                                Kijelöltek resetelése
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </form>
    </div>
    <?php
}
