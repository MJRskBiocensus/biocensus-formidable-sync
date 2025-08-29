<?php
/*
Plugin Name: Formidable Locations
Plugin URI: http://strategy11.com/formidable-wordpress-plugin
Description: Create forms and populate with Countries, states/provinces, and U.S. cities
Author: Strategy11
Author URI: http://strategy11.com
Version: 2.03
*/

/**
 * Autoloader for Locations add on.
 *
 * @param string $class_name
 * @return void
 */
function frm_loc_forms_autoloader( $class_name ) {
	$path = dirname( __FILE__ );

	// Only load Frm classes here
	if ( ! preg_match( '/^FrmLoc.+$/', $class_name ) ) {
		return;
	}

	if ( preg_match( '/^.+Controller$/', $class_name ) ) {
		$path .= '/controllers/' . $class_name . '.php';
	} else {
		$path .= '/models/' . $class_name . '.php';
	}

	if ( file_exists( $path ) ) {
		include $path;
	}
}

// Add the autoloader
spl_autoload_register( 'frm_loc_forms_autoloader' );

// Load hooks
FrmLocHooksController::load_admin_hooks();
