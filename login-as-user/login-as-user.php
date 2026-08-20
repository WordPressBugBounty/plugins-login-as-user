<?php
/**
 * Plugin Name:       Login as User
 * Plugin URI:        https://www.web357.com/login-as-user-wordpress-plugin
 * Description:       Login as User is a free WordPress plugin that helps admins switch user accounts instantly to check data.
 * Version:           1.7.3
 * Author:            Web357
 * Author URI:        https://www.web357.com/
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       login-as-user
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Currently plugin version.
 */
if ( !defined( 'LOGINASUSER_VERSION' ) ) {
	define( 'LOGINASUSER_VERSION', '1.7.3' );
}


/**
 * The code that runs during plugin activation.
 */
function login_as_user_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';
	LoginAsUser_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function login_as_user_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-deactivator.php';
	LoginAsUser_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'login_as_user_activate' );
register_deactivation_hook( __FILE__, 'login_as_user_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-main.php';

/**
 * Begins execution of the plugin.
 */
function login_as_user_run() 
{
	global $LoginAsUser;
	if (!$LoginAsUser) {
		$LoginAsUser = new LoginAsUser();
	}
	$LoginAsUser->run();
}

// Initialize plugin on plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'login_as_user_run');

// Load the main functionality of plugin
require_once (plugin_dir_path( __FILE__ ) . 'includes/class-w357-login-as-user.php');