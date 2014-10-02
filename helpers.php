<?php
// Provides various helper functions

/**
 * Loads a php file relative to the plugin root.
 * @param  string $path The path relative to the current directory.
 *                      Should start with a forward slash.
 */
function ra_load_file( $path, $once=true ) {
    if($once) {
      require_once(dirname(__file__) . $path);
    } else {
      require(dirname(__file__) . $path);
    }
}

function ra_get_option( $option, $default = false ) {
	return get_option( "ravenauth_" . $option, $default);
}

function ra_set_option( $option, $value ) {
	return set_option( "ravenauth_" . $option, $value);
}