<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class Frm_User_Tracking {

	/**
	 * @return void
	 */
	public static function compile_referer_session() {
		_deprecated_function( __METHOD__, '2.0', 'FrmUsrTrkSession::compile_referer_session' );

		FrmUsrTrkSession::compile_referer_session();
	}

	/**
	 * @param int $entry_id
	 * @return void
	 */
	public static function insert_tracking_into_entry( $entry_id ) {
		_deprecated_function( __METHOD__, '2.0', 'FrmUsrTrkSession::insert_tracking_into_entry' );

		FrmUsrTrkSession::insert_tracking_into_entry( $entry_id );
	}

	/**
	 * @return void
	 */
	public static function include_auto_updater() {
		_deprecated_function( __METHOD__, '2.0', 'FrmUsrTrkAppHelper::include_auto_updater' );

		FrmUsrTrkAppHelper::include_auto_updater();
	}

	/**
	 * @return void
	 */
	public static function get_referer_info() {
		_deprecated_function( __METHOD__, '2.0', 'FrmUsrTrkSession::compile_referer_session' );

		FrmUsrTrkSession::compile_referer_session();
	}
}
