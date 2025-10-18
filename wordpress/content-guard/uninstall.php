<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) { exit; }

// A régi opciókat töröljük (PageGuard kompatibilitás)
delete_option('pageguard_exceptions');
delete_option('pageguard_redirect_page');
delete_option('pageguard_post_cat_exceptions');
