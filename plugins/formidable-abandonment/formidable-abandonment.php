<?php
/**
 * Plugin Name: Formidable Abandonment
 * Description: Capture form data before it's submitted to save more leads and learn to optimize forms.
 * Version: 1.1.6
 * Plugin URI: https://formidableforms.com/
 * Author URI: https://formidableforms.com/
 * Author: Strategy11
 * Text Domain: formidable-abandonment
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package formidable-abandonment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads all the classes for this plugin.
 *
 * @since 1.0
 *
 * @param string $class_name The name of the class to load.
 *
 * @return void
 */
function frm_abandonment_autoloader( $class_name ) {
	$path = __DIR__;

	if ( ! function_exists( 'frm_class_autoloader' ) ) {
		return;
	}

	// Only load FrmAbandonment classes here.
	if ( ! preg_match( '/^FrmAbandonment.+$/', $class_name ) && ! preg_match( '/^FrmAbdn.+$/', $class_name ) ) {
		return;
	}

	frm_class_autoloader( $class_name, $path );
}
spl_autoload_register( 'frm_abandonment_autoloader' );

add_filter( 'frm_load_controllers', array( 'FrmAbandonmentHooksController', 'add_hooks_controller' ) );
