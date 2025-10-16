<?php
/*
Plugin Name: UserMenu (CloudMentor)
Plugin URI: https://github.com/the1bit/student-lab-backend/tree/main/wordpress/user-menu
Description: Feltételes menüpont megjelenítés: Mindig / Bejelentkezve / Kijelentkezve. Beállítás: Megjelenés → Menük, menüpont szerkesztésnél.
Version: 0.0.1
Author: CloudMentor
Author URI: https://cloudmentor.hu
License: MIT
License URI: https://opensource.org/licenses/MIT
Requires at least: 6.2
Tested up to: 6.7.2
Requires PHP: 8.0
Text Domain: cloudmentor-user-menu
Domain Path: /languages
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EV_UserMenu {
    const META_KEY = '_usermenu_visibility'; // 'always' | 'logged_in' | 'logged_out'

    public function __construct() {
        // Fordítás (ha később .mo kerülne a /languages alá)
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Admin: extra mező a menüpont szerkesztőben
        add_action( 'wp_nav_menu_item_custom_fields', [ $this, 'render_menu_item_field' ], 10, 4 );

        // Mentés kezelése
        add_action( 'wp_update_nav_menu_item', [ $this, 'save_menu_item_meta' ], 10, 3 );

        // Frontend: menüpontok szűrése
        add_filter( 'wp_nav_menu_objects', [ $this, 'filter_menu_items_by_visibility' ], 10, 2 );

        // Export / import kompatibilitás kedvéért regisztráljuk a meta kulcsot
        add_action( 'init', function () {
            register_meta( 'post', self::META_KEY, [
                'type'              => 'string',
                'single'            => true,
                'sanitize_callback' => [ $this, 'sanitize_visibility' ],
                'show_in_rest'      => true,
                'auth_callback'     => function() { return current_user_can( 'edit_theme_options' ); },
            ] );
        } );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'usermenu', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Admin mező kirajzolása minden menüpontnál (Megjelenés → Menük)
     */
    public function render_menu_item_field( $item_id, $item, $depth, $args ) {
        $value   = get_post_meta( $item_id, self::META_KEY, true );
        $value   = $this->sanitize_visibility( $value ?: 'always' );

        $field_id   = 'edit-usermenu-visibility-' . esc_attr( $item_id );
        $field_name = 'usermenu_visibility[' . esc_attr( $item_id ) . ']';
        ?>
        <p class="description description-wide" style="margin-top:8px;">
            <label for="<?php echo esc_attr( $field_id ); ?>">
                <strong><?php esc_html_e( 'Megjelenítés feltétele', 'usermenu' ); ?>:</strong>
            </label><br>
            <select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
                <option value="always"     <?php selected( $value, 'always' ); ?>><?php esc_html_e( 'Mindig', 'usermenu' ); ?></option>
                <option value="logged_in"  <?php selected( $value, 'logged_in' ); ?>><?php esc_html_e( 'Bejelentkezve', 'usermenu' ); ?></option>
                <option value="logged_out" <?php selected( $value, 'logged_out' ); ?>><?php esc_html_e( 'Kijelentkezve', 'usermenu' ); ?></option>
            </select>
            <span class="description" style="display:block;margin-top:4px;">
                <?php esc_html_e( 'Válaszd ki, mikor jelenjen meg ez a menüpont a felhasználóknak.', 'usermenu' ); ?>
            </span>
        </p>
        <?php
    }

    /**
     * Admin mentés: a kiválasztott érték eltárolása menüpont meta-ként
     */
    public function save_menu_item_meta( $menu_id, $menu_item_db_id, $args ) {
        if ( ! current_user_can( 'edit_theme_options' ) ) {
            return;
        }

        if ( isset( $_POST['usermenu_visibility'][ $menu_item_db_id ] ) ) {
            $raw   = wp_unslash( $_POST['usermenu_visibility'][ $menu_item_db_id ] );
            $value = $this->sanitize_visibility( $raw );
            if ( $value ) {
                update_post_meta( $menu_item_db_id, self::META_KEY, $value );
            }
        } else {
            // Ha nem jött mező (pl. eltávolították), állítsuk 'always'-re
            update_post_meta( $menu_item_db_id, self::META_KEY, 'always' );
        }
    }

    /**
     * Frontend: szűrjük a menüpontokat a beállítás szerint.
     * Emellett eltávolítjuk azokat a gyerekeket is, amelyeknek a szülője rejtve van.
     */
    public function filter_menu_items_by_visibility( $items, $args ) {
        if ( is_admin() ) {
            return $items;
        }

        $is_logged_in = is_user_logged_in();

        // 1) Első kör: alap szűrés a saját meta alapján
        $allowed_items = [];
        foreach ( $items as $item ) {
            $visibility = get_post_meta( $item->ID, self::META_KEY, true );
            $visibility = $this->sanitize_visibility( $visibility ?: 'always' );

            $show = true;
            if ( $visibility === 'logged_in' && ! $is_logged_in ) {
                $show = false;
            } elseif ( $visibility === 'logged_out' && $is_logged_in ) {
                $show = false;
            }

            if ( $show ) {
                $allowed_items[ $item->ID ] = $item;
            }
        }

        if ( empty( $allowed_items ) ) {
            return [];
        }

        // 2) Második kör: ha egy szülő rejtve maradt, a gyereket is vegyük ki (teljes ág elrejtése)
        $final = [];
        $allowed_ids = array_keys( $allowed_items );

        foreach ( $allowed_items as $item ) {
            if ( $item->menu_item_parent && $item->menu_item_parent != 0 ) {
                // Ellenőrizzük, hogy a szülő is benne maradt-e
                if ( in_array( (int) $item->menu_item_parent, $allowed_ids, true ) ) {
                    $final[] = $item;
                }
            } else {
                // Fő szintű elem
                $final[] = $item;
            }
        }

        return $final;
    }

    /**
     * Érték tisztítása
     */
    public function sanitize_visibility( $value ) {
        $value = is_string( $value ) ? strtolower( trim( $value ) ) : 'always';
        $allowed = [ 'always', 'logged_in', 'logged_out' ];
        return in_array( $value, $allowed, true ) ? $value : 'always';
    }
}

new EV_UserMenu();
