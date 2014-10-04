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

function ra_update_option( $option, $value ) {
	return update_option( "ravenauth_" . $option, $value);
}

/*
    Formats a timestamp into the format required by Raven
*/
function ra_format_timestamp( $timestamp = null ) {
    if ( is_null($timestamp) ) {
        $timestamp = time();
    }
    return gmdate('Ymd\THis\Z', $timestamp);
}