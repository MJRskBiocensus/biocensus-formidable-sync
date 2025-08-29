<?php

class FrmDatesHooksController {

	private static $min_formidable_version = 3.0;

	/**
	 * Loads plugin hooks.
	 *
	 * @return void
	 */
	public static function load_hooks() {
		if ( ! self::is_formidable_compatible() ) {
			add_action( 'admin_notices', array( 'FrmDatesAppController', 'pro_not_installed_notice' ) );
			return;
		}

		add_action( 'init', __CLASS__ . '::load_lang', 0 );
		self::load_admin_hooks();

		add_filter( 'frm_date_field_options', array( 'FrmDatesAppController', 'date_field_options_js' ), 20, 2 );

		add_filter( 'frm_get_field_type_class', array( 'FrmDatesField', 'get_field_type_class' ), 11, 2 );
		add_filter( 'frm_clean_date_field_options_before_update', array( 'FrmDatesField', 'sanitize_field_options' ) );
		add_action( 'frm_load_ajax_field_scripts', array( 'FrmDatesAppController', 'load_date_js' ), 10 );
		add_filter( 'frm_setup_new_fields_vars', array( 'FrmDatesField', 'remove_default_if_disabled' ), 1 );
		add_action( 'frm_enqueue_form_scripts', array( 'FrmDatesAppController', 'maybe_enqueue_date_js_earlier' ), 5 );
		add_filter( 'frm_build_date_diff_calc', array( 'FrmDatesCalculationController', 'maybe_build_date_diff_calc' ), 10, 2 );
	}

	/**
	 * Initialize support for translations.
	 *
	 * @since 2.0.4
	 *
	 * @return void
	 */
	public static function load_lang() {
		load_plugin_textdomain( 'frmdates', false, basename( FrmDatesAppHelper::get_path() ) . '/languages/' );
	}

	/**
	 * Loads hooks in the admin area.
	 *
	 * @return void
	 */
	public static function load_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', 'FrmDatesAppController::include_updater' );
		add_action( 'admin_init', 'FrmDatesAppController::maybe_enqueue_frontend_scripts' );
		add_action( 'admin_enqueue_scripts', array( 'FrmDatesAppController', 'enqueue_admin_assets' ) );

		add_action( 'frm_date_field_options_form', array( 'FrmDatesAppController', 'add_settings_to_form' ), 10, 3 );

		if ( FrmDatesCalculationHelper::is_formidable_supported() ) {
			add_filter( 'frm_default_value_types', array( 'FrmDatesCalculationController', 'add_default_value_type' ), 10, 2 );
			add_action( 'frm_default_value_setting', array( 'FrmDatesCalculationController', 'add_default_value_type_box' ) );
		}
	}

	/**
	 * Check if the current version of Formidable is compatible with Dates add-on
	 *
	 * @since 1.0
	 * @return bool
	 */
	private static function is_formidable_compatible() {
		$frm_version = is_callable( 'FrmAppHelper::plugin_version' ) ? FrmAppHelper::plugin_version() : 0;
		return version_compare( $frm_version, self::$min_formidable_version, '>=' ) && FrmAppHelper::pro_is_installed();
	}
}
