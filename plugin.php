<?php
/*
   Plugin Name: WordPress Raven Auth
   Plugin URI: https://github.com/danielchatfield/wordpress-raven-auth
   Description: Quick and easy auth for University of Cambridge sites.
   Author: Daniel Chatfield
   Author URI: http://www.danielchatfield.com
   Version 0.0.1
  
   GitHub Plugin URI: danielchatfield/wordpress-raven-auth
 */

$required_php_version = '5.2.4';
$required_wp_version  = '3.4';

// Check if PHP and WordPress versions satisfy requirements.
// If they don't then refuse to activate and give a helpful error message

$php_too_old = version_compare( PHP_VERSION, $required_php_version, '<');
$wp_too_old  = version_compare( get_bloginfo( 'version' ), $required_wp_version, '<');
$openssl_missing = !function_exists('openssl_verify');

$problem = $php_too_old || $wp_too_old || $openssl_missing;

if ($problem) {

    require_once( ABSPATH . WPINC . 'plugin.php');
    deactivate_plugins( basename( __FILE__ ) );

    if (isset( $_GET['action'])
        && ($_GET['action'] == 'activate' || $_GET['action'] == 'error_scrape' )
    ) {
        $error = '';

        if($php_too_old) {
            $error .= printf(
                __(
                    'WordPress Raven Auth requires PHP version %s or greater.',
                    'ravenauth'
                ),
                $required_php_version
            );
        }

        if($wp_too_old) {
            $error .= printf(
                __(
                    'WordPress Raven Auth requires WordPress version %s or greater.',
                    'ravenauth'
                ),
                $required_wp_version
            );
        }

        if($openssl_missing) {
            $error .= printf(
                __(
                    'WordPress Raven Auth requires openssl.',
                    'ravenauth'
                ),
                $openssl_missing
            );
        }

        die($error);
    }
}

require_once( dirname(__FILE__) . '/autoloader.php' );

// Registers the autoloader that will load all classes as they are needed
RavenAuthAutoloader::register();

require_once( dirname(__FILE__) . '/helpers.php' );
require_once( dirname(__FILE__) . '/errors.php' );

// Load plugin using singleton
if (function_exists('add_action')) {
    add_action('plugins_loaded', array('RavenAuthPlugin', 'getInstance'));
    register_activation_hook( __FILE__, array( 'RavenAuthPlugin', 'install' ) );
}
