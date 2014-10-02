<?php
/**
 * Plugin Name: WordPress Raven Auth
 * Plugin URI: https://github.com/robinsoncollegeboatclub/wordpress-raven-auth
 * Description: Quick and easy auth for University of Cambridge sites.
 * Author: Daniel Chatfield
 * Author URI: http://www.danielchatfield.com
 * Version 0.0.0
 *
 * GitHub Plugin URI: robinsoncollegeboatclub/wordpress-raven-auth
 */

$required_php_version = '5.2.4';
$required_wp_version  = '3.4';

// Check if PHP and WordPress versions satisfy requirements.
// If they don't then refuse to activate and give a helpful error message

$php_too_old = version_compare( PHP_VERSION, $required_php_version, '<');
$wp_too_old  = version_compare( get_bloginfo( 'version' ), $required_wp_version, '<');

if ($php_too_old || $wp_too_old) {

    require_once( ABSPATH . WPINC . 'plugin.php');
    deactivate_plugins( basename( __FILE__ ) );

    if (isset( $_GET['action'])
        && ($_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' )
    ) {
        die(
            printf(
                __(
                    'WordPress Raven Auth requires PHP version %s or greater and WordPress version %s or greater',
                    'ravenauth'
                ),
                $required_php_version,
                $required_wp_version
            )
        );
    }
}

require_once( dirname(__FILE__) . '/autoloader.php' );

// Registers the autoloader that will load all classes as they are needed
RavenAuth_Autoloader::register();

require_once( dirname(__FILE__) . '/helpers.php' );

// Load plugin using singleton
if (function_exists('add_action')) {
    add_action('plugins_loaded', array('RavenAuth', 'getInstance'));
    register_activation_hook( __FILE__, array( 'RavenAuth', 'install' ) );
}
