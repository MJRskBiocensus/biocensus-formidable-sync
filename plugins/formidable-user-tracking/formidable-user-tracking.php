<?php
/**
 * Plugin Name: Formidable User Flow
 * Description: Track the steps a user takes before submitting a form
 * Version: 2.0.3
 * Plugin URI: https://formidableforms.com/
 * Author URI: https://strategy11.com
 * Author: Strategy11
 * Text Domain: formidable-usr-trk
 *
 * @package FormidableUserFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @param string $class_name
 * @return void
 */
function frm_usr_trk_autoloader( $class_name ) {
	$deprecated    = array( 'Frm_User_Tracking' );
	$is_deprecated = in_array( $class_name, $deprecated ) || preg_match( '/^.+Deprecate/', $class_name );
	$filepath = dirname( __FILE__ );
	if ( $is_deprecated ) {
		$filepath .= '/deprecated/' . $class_name . '.php';
		if ( file_exists( $filepath ) ) {
			require( $filepath );
		}
	} elseif ( function_exists( 'frm_class_autoloader' ) ) {
		frm_class_autoloader( $class_name, $filepath );
	}
}
spl_autoload_register( 'frm_usr_trk_autoloader' );

add_action( 'frm_load_controllers', array( 'FrmUsrTrkHooksController', 'add_hooks_controller' ) );
