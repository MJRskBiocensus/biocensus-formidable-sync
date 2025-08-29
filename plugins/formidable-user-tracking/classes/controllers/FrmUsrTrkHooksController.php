<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 2.0
 */
class FrmUsrTrkHooksController {

	/**
	 * Adds this class to hook controllers list.
	 *
	 * @param array $controllers Hooks controllers.
	 * @return array
	 */
	public static function add_hooks_controller( $controllers ) {
		$controllers[] = __CLASS__;
		return $controllers;
	}

	/**
	 * @return void
	 */
	public static function load_hooks() {
		if ( ! FrmAppHelper::is_admin() ) {
			// Update the session data.
			add_action( 'wp', 'FrmUsrTrkSession::compile_referer_session', 1 );
		}

		if ( false === strpos( FrmAppHelper::get_server_value( 'REQUEST_URI' ), '/' . rest_get_url_prefix() . '/' ) && ! FrmAppHelper::is_admin() ) {
			add_action( 'init', 'FrmUsrTrkSession::try_starting_session', 1 );
		}

		add_action( 'init', 'FrmUsrTrkAppController::load_lang', 0 );
		add_action( 'frm_after_create_entry', 'FrmUsrTrkSession::insert_tracking_into_entry', 15 );
		add_filter( 'frm_user_info_referrer', 'FrmUsrTrkAppController::append_user_journey', 10, 2 );

		add_filter( 'frm_duplicate_check_val', 'FrmUsrTrkAppController::exclude_description_from_duplicate_check' );
	}

	/**
	 * Load hooks that are only needed in the admin area.
	 *
	 * @since 2.0
	 * @return void
	 */
	public static function load_admin_hooks() {
		add_action( 'admin_init', 'FrmUsrTrkAppHelper::include_auto_updater', 1 );
		add_action( 'frm_after_show_entry', 'FrmUsrTrkAppController::show_tracking_data' );
		add_filter( 'frm_sidebar_data', 'FrmUsrTrkAppController::remove_from_sidebar' );
		add_action( 'frm_after_show_entry', 'FrmUsrTrkAppController::enqueue_scripts' );
	}
}
