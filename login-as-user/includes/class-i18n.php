<?php
/* ======================================================
 # Login as User for WordPress - v1.7.3 (free version)
 # -------------------------------------------------------
 # Author: Web357
 # Copyright © 2014-2024 Web357. All rights reserved.
 # License: GNU/GPLv3, http://www.gnu.org/licenses/gpl-3.0.html
 # Website: https://www.web357.com/login-as-user-wordpress-plugin
 # Demo: https://login-as-user-wordpress-demo.web357.com/wp-admin/
 # Support: https://www.web357.com/support
 # Last modified: Wednesday 19 August 2026, 11:46:40 PM
 ========================================================= */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define the internationalization functionality
 */
class LoginAsUser_i18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {

		/*
		 * Still required: the plugin ships its own translations in /languages, which
		 * WordPress does not pick up automatically. Called on `init` so that it never
		 * runs before translations are allowed to load (WP 6.7+).
		 */
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Needed for the translations bundled with the plugin.
		load_plugin_textdomain(
			'login-as-user',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages'
		);

	}
}