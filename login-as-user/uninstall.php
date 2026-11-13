<?php
/* ======================================================
 # Login as User for WordPress - v1.7.6 (free version)
 # -------------------------------------------------------
 # Author: Web357
 # Copyright © 2014-2024 Web357. All rights reserved.
 # License: GNU/GPLv3, http://www.gnu.org/licenses/gpl-3.0.html
 # Website: https://www.web357.com/login-as-user-wordpress-plugin
 # Demo: https://login-as-user-wordpress-demo.web357.com/wp-admin/
 # Support: https://www.web357.com/support
 # Last modified: Friday 13 November 2026, 08:19:52 AM
 ========================================================= */
 
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (
	!defined( 'WP_UNINSTALL_PLUGIN' )
	||
	!WP_UNINSTALL_PLUGIN
	||
	dirname( WP_UNINSTALL_PLUGIN ) != dirname( plugin_basename( __FILE__ ) )
) {
	status_header( 404 );
	exit;
}

// Delete the options from database
// delete_option('login_as_user_options');

/**
 * Removes what this plugin added besides the settings option (which is kept on purpose): the recorded last-login
 * times, the saved cart copies and, in the Pro edition, the audit log table. Skipped while the other edition is still installed, because
 * both editions share the data, and (Pro) when "keep data on uninstall" is on.
 */
if ( ! function_exists( 'web357_lau_uninstall_site' ) ) {
function web357_lau_uninstall_site() {
	global $wpdb;
	
	delete_metadata( 'user', 0, 'web357_lau_last_login', '', true );
	delete_metadata( 'user', 0, '_lau_cart_backup', '', true );
}
}

if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$web357LauOtherMain = '/login-as-user-pro.php';

foreach ( array_keys( get_plugins() ) as $web357LauPluginFile ) {
	if ( substr( $web357LauPluginFile, -strlen( $web357LauOtherMain ) ) === $web357LauOtherMain ) {
		return;
	}
}
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $web357LauSiteId ) {
		switch_to_blog( (int) $web357LauSiteId );
		web357_lau_uninstall_site();
		restore_current_blog();
	}
} else {
	web357_lau_uninstall_site();
}
