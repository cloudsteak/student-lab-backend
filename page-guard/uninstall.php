<?php
// Közvetlen meghívás tiltása
if ( ! defined('WP_UNINSTALL_PLUGIN') ) { exit; }

delete_option('pageguard_exceptions');
delete_option('pageguard_redirect_page');
