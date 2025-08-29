<?php
/**
 * Plugin Name: Formidable Datepicker Options
 * Description: Set blackout dates, days of the week, dynamic minimum and maximum dates, and calculate dates.
 * Version: 2.1
 * Plugin URI: https://formidableforms.com
 * Author URI: https://formidableforms.com
 * Author: Strategy11
 * Text Domain: frmdates
 *
 * @package formidable-dates
 */

/**
 * Autoload the classes for this plugin
 *
 * @param string $class_name The name of the class to load.
 */
function frm_dates_autoloader( $class_name ) {
	$path = __DIR__;

	// Only load Frm classes here.
	if ( ! preg_match( '/^FrmDates.+$/', $class_name ) ) {
		return;
	}

	if ( is_callable( 'frm_class_autoloader' ) ) {
		frm_class_autoloader( $class_name, __DIR__ );
	}
}
// Add the autoloader.
spl_autoload_register( 'frm_dates_autoloader' );

function frm_dates_load_hooks_if_class_exists() {
	if ( class_exists( 'FrmDatesHooksController' ) ) {
		FrmDatesHooksController::load_hooks();
	}
}

// Load the plugin.
add_action( 'plugins_loaded', 'frm_dates_load_hooks_if_class_exists' );
