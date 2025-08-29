<?php
/**
 * Plugin Name: Formidable Charts
 * Description: Build charts and graphs from your Formidable data.
 * Version: 2.0
 * Plugin URI: https://formidableforms.com/
 * Author URI: https://formidableforms.com/
 * Author: Strategy11
 * Text Domain: frm-charts
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package FrmCharts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads all the classes for this plugin.
 *
 * @param string $class_name The name of the class to load.
 */
function frm_charts_autoloader( $class_name ) {
	$path = dirname( __FILE__ );

	// Only load Frm classes here.
	if ( ! preg_match( '/^FrmCharts.+$/', $class_name ) ) {
		return;
	}

	if ( is_callable( 'frm_class_autoloader' ) ) {
		frm_class_autoloader( $class_name, $path );
	}
}
spl_autoload_register( 'frm_charts_autoloader' );

add_filter( 'frm_load_controllers', array( 'FrmChartsHooksController', 'add_hooks_controller' ) );
