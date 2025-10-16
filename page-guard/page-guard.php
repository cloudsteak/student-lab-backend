<?php
/*
Plugin Name: PageGuard (CloudMentor)
Plugin URI: https://github.com/the1bit/student-lab-backend/tree/main/page-guard
Description: Oldalak és bejegyzések védelme — csak bejelentkezett felhasználók láthatják. Kivételként megadható néhány oldal, illetve beállítható az átirányítás céloldala.
Version: 0.0.3-alpha
Author: CloudMentor
Author URI: https://cloudmentor.hu
License: MIT
License URI: https://opensource.org/licenses/MIT
Requires at least: 6.2
Tested up to: 6.7.2
Requires PHP: 8.0
Text Domain: cloudmentor-page-guard
Domain Path: /languages
*/


if ( ! defined('ABSPATH') ) { exit; }

class PageGuard {
    const OPT_EXCEPTIONS         = 'pageguard_exceptions';          // page IDs
    const OPT_REDIRECT           = 'pageguard_redirect_page';       // page ID
    const OPT_POST_CAT_EXCEPTIONS= 'pageguard_post_cat_exceptions'; // category term IDs

    public function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_menu',     [$this, 'add_settings_page']);
        add_action('admin_init',     [$this, 'register_settings']);
        add_action('template_redirect', [$this, 'enforce_guard'], 0);
    }

    public function load_textdomain() {
        load_plugin_textdomain('pageguard', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_settings_page() {
        add_options_page(
            __('PageGuard beállítások', 'pageguard'),
            __('Page Guard (CloudMentor)', 'pageguard'),
            'manage_options',
            'pageguard-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        // Oldal-kivételek
        register_setting(
            'pageguard_settings',
            self::OPT_EXCEPTIONS,
            [
                'type'              => 'array',
                'sanitize_callback' => function($val){
                    $val = is_array($val) ? array_map('intval', $val) : [];
                    return array_values(array_filter($val, function($id){
                        return $id > 0 && get_post_type($id) === 'page';
                    }));
                },
                'default' => [],
            ]
        );

        // Átirányítási oldal
        register_setting(
            'pageguard_settings',
            self::OPT_REDIRECT,
            [
                'type'              => 'integer',
                'sanitize_callback' => function($val){
                    $id = intval($val);
                    return ( $id > 0 && get_post_type($id) === 'page' ) ? $id : 0;
                },
                'default' => 0,
            ]
        );

        // Bejegyzés-kategória kivételek
        register_setting(
            'pageguard_settings',
            self::OPT_POST_CAT_EXCEPTIONS,
            [
                'type'              => 'array',
                'sanitize_callback' => function($val){
                    $val = is_array($val) ? array_map('intval', $val) : [];
                    // Csak létező 'category' taxonómia ID-k maradjanak
                    return array_values(array_filter($val, function($term_id){
                        $term = get_term($term_id, 'category');
                        return $term && ! is_wp_error($term);
                    }));
                },
                'default' => [],
            ]
        );

        add_settings_section(
            'pageguard_section_main',
            __('Általános beállítások', 'pageguard'),
            function(){
                echo '<p>'.esc_html__('Állítsd be az oldal- és kategóriakivételeket, illetve az átirányítás céloldalát.', 'pageguard').'</p>';
            },
            'pageguard_settings'
        );

        add_settings_field(
            'pageguard_exceptions',
            __('Kivételként engedélyezett oldalak', 'pageguard'),
            [$this, 'field_exceptions'],
            'pageguard_settings',
            'pageguard_section_main'
        );

        add_settings_field(
            'pageguard_post_cat_exceptions',
            __('Kivételként engedélyezett bejegyzés-kategóriák', 'pageguard'),
            [$this, 'field_post_cat_exceptions'],
            'pageguard_settings',
            'pageguard_section_main'
        );

        add_settings_field(
            'pageguard_redirect_page',
            __('Átirányítás céloldala', 'pageguard'),
            [$this, 'field_redirect'],
            'pageguard_settings',
            'pageguard_section_main'
        );
    }

    public function field_exceptions() {
        $selected = get_option(self::OPT_EXCEPTIONS, []);
        $pages = get_pages(['post_status' => ['publish','private','draft']]);
        echo '<select name="'.esc_attr(self::OPT_EXCEPTIONS).'[]" multiple style="min-width:320px; min-height:200px;">';
        foreach ($pages as $p) {
            printf(
                '<option value="%d"%s>%s</option>',
                intval($p->ID),
                in_array($p->ID, $selected, true) ? ' selected' : '',
                esc_html($p->post_title . ' (ID: '.$p->ID.')')
            );
        }
        echo '</select>';
        echo '<p class="description">'.esc_html__('A kiválasztott oldalak bejelentkezés nélkül is megtekinthetők.', 'pageguard').'</p>';
    }

    public function field_post_cat_exceptions() {
        $selected = get_option(self::OPT_POST_CAT_EXCEPTIONS, []);
        $cats = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ]);
        echo '<select name="'.esc_attr(self::OPT_POST_CAT_EXCEPTIONS).'[]" multiple style="min-width:320px; min-height:200px;">';
        if (!is_wp_error($cats)) {
            foreach ($cats as $c) {
                printf(
                    '<option value="%d"%s>%s</option>',
                    intval($c->term_id),
                    in_array($c->term_id, $selected, true) ? ' selected' : '',
                    esc_html($c->name . ' (ID: '.$c->term_id.')')
                );
            }
        }
        echo '</select>';
        echo '<p class="description">'.esc_html__('A kiválasztott kategóriákba tartozó bejegyzések bejelentkezés nélkül is megtekinthetők.', 'pageguard').'</p>';
    }

    public function field_redirect() {
        $current = intval(get_option(self::OPT_REDIRECT, 0));
        $pages = get_pages(['post_status' => ['publish','private','draft']]);
        echo '<select name="'.esc_attr(self::OPT_REDIRECT).'" style="min-width:320px;">';
        echo '<option value="0">'.esc_html__('— Válassz oldalt —', 'pageguard').'</option>';
        foreach ($pages as $p) {
            printf(
                '<option value="%d"%s>%s</option>',
                intval($p->ID),
                selected($current, $p->ID, false),
                esc_html($p->post_title . ' (ID: '.$p->ID.')')
            );
        }
        echo '</select>';
        echo '<p class="description">'.esc_html__('Nem bejelentkezett felhasználók ide lesznek átirányítva, ha védett tartalmat nyitnának meg.', 'pageguard').'</p>';
    }

    public function render_settings_page() {
        if ( ! current_user_can('manage_options') ) return; ?>
        <div class="wrap">
            <h1><?php echo esc_html__('PageGuard beállítások', 'pageguard'); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields('pageguard_settings');
                    do_settings_sections('pageguard_settings');
                    submit_button(__('Mentés', 'pageguard'));
                ?>
            </form>
            <hr />
            <p><strong><?php echo esc_html__('Megjegyzés:', 'pageguard'); ?></strong>
                <?php echo esc_html__('A plugin a bejegyzéseket és az oldalakat védi. Kivétel oldalakon és bejegyzés-kategóriákon állítható. Átirányításkor elkerüljük a hurkokat.', 'pageguard'); ?>
            </p>
        </div>
    <?php }

    /**
     * Védelmi logika
     */
    public function enforce_guard() {
        // Admin/REST/cron/CLI: ne avatkozzunk be
        if ( is_admin() || defined('REST_REQUEST') || (defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI) ) {
            return;
        }

        // Csak oldalak és klasszikus bejegyzések érdekelnek
        $is_page = is_page();
        $is_post = is_singular('post'); // HELYES: bejegyzés egyedi nézete

        if ( ! $is_page && ! $is_post ) {
            return;
        }

        // Bejelentkezve? Engedjük.
        if ( is_user_logged_in() ) {
            return;
        }

        $redirect_id   = intval(get_option(self::OPT_REDIRECT, 0));
        if ( $redirect_id <= 0 ) {
            // Nincs beállított cél: ne zárjunk ki mindent amíg nincs konfigurálva
            return;
        }

        $current_id = get_queried_object_id();

        // Ha az aktuális oldal maga az átirányítás célja → ne okozzunk hurkot
        if ( $current_id === $redirect_id ) {
            return;
        }

        // OLDAL: ha kivétel, engedjük
        if ( $is_page ) {
            $page_exceptions = get_option(self::OPT_EXCEPTIONS, []);
            if ( in_array($current_id, $page_exceptions, true) ) {
                return;
            }
        }

        // BEJEGYZÉS: ha bármelyik kategóriája a kivétellistában van, engedjük
        if ( $is_post ) {
            $cat_exceptions = get_option(self::OPT_POST_CAT_EXCEPTIONS, []);
            if ( ! empty($cat_exceptions) ) {
                $terms = get_the_terms($current_id, 'category');
                if ( $terms && ! is_wp_error($terms) ) {
                    $post_cat_ids = array_map(fn($t)=>intval($t->term_id), $terms);
                    if ( array_intersect($post_cat_ids, array_map('intval', $cat_exceptions)) ) {
                        return; // legalább egy egyezik → engedjük
                    }
                }
            }
        }

        // Itt már biztosan védett tartalom → átirányítás
        $target_url = get_permalink($redirect_id);
        if ( $target_url ) {
            $orig = home_url(add_query_arg([], $_SERVER['REQUEST_URI']));
            $url  = add_query_arg('redirect_to', rawurlencode($orig), $target_url);
            wp_safe_redirect($url, 302);
            exit;
        }
    }
}

new PageGuard();
