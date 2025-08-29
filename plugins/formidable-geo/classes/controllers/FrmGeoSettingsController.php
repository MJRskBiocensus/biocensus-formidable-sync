<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmGeoSettingsController {

	/**
	 * Loads settings hooks.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function load_hooks() {
		add_action( 'frm_add_settings_section', array( __CLASS__, 'add_settings_section' ) );
		add_action( 'frm_update_settings', array( __CLASS__, 'update' ) );
		add_action( 'frm_store_settings', array( __CLASS__, 'store' ) );
		add_filter( 'frm_clean_address_field_options_before_update', array( __CLASS__, 'update_options' ) );
		add_filter( 'frm_clean_text_field_options_before_update', array( __CLASS__, 'update_options' ) );
		add_filter( 'frm_default_field_options', array( __CLASS__, 'add_default_field_settings' ), 10, 2 );
		add_action( 'frm_text_primary_field_options', array( __CLASS__, 'add_field_setting' ) );
		add_action( 'frm_address_primary_field_options', array( __CLASS__, 'add_field_setting' ) );
	}

	/**
	 * Adds the global settings section.
	 *
	 * @since 1.0
	 *
	 * @param array $sections current global settings sections.
	 * @return array global settings.
	 */
	public static function add_settings_section( $sections ) {
		$sections['geo'] = array(
			'name'     => __( 'Geolocation', 'formidable-geo' ),
			'class'    => 'FrmGeoSettingsController',
			'function' => 'geo_settings',
			'icon'     => 'frm_icon_font frm_location_icon',
		);
		return $sections;
	}

	/**
	 * Geo section callback
	 *
	 * @since 1.0
	 * @deprecated 1.1
	 * @return void
	 */
	public static function route() {
		_deprecated_function( __METHOD__, '1.1' );

		$action = FrmAppHelper::get_param( 'action' );
		if ( 'process-form' === $action ) {
			self::process_form();
		} else {
			self::display_form();
		}
	}

	/**
	 * Processes form when global settings are saved.
	 *
	 * @since 1.0
	 * @deprecated 1.1
	 * @return void
	 */
	public static function process_form() {
		_deprecated_function( __METHOD__, '1.1' );

		$nonce_error = FrmAppHelper::permission_nonce_error( '', '_wpnonce', 'frm_geo_settings' );
		if ( $nonce_error ) {
			print esc_html( $nonce_error );
			exit;
		}

		$frm_geo_settings = new FrmGeoSettings();

		$api_key = FrmAppHelper::get_param( 'frm_geo_api_key', '', 'post', 'sanitize_text_field' );
		if ( ! empty( $api_key ) ) {
			$frm_geo_settings->update(
				array(
					'frm_geo_api_key' => $api_key,
				)
			);
			$frm_geo_settings->store();
		}

		require_once FrmGeoAppController::path() . '/views/settings/form.php';
	}

	/**
	 * Displays global settings for geo.
	 *
	 * @since 1.0
	 * @deprecated 1.1
	 * @return void
	 */
	public static function display_form() {
		_deprecated_function( __METHOD__, '1.1' );

		require_once FrmGeoAppController::path() . '/views/settings/form.php';
	}

	/**
	 * Displays global settings for geo.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	public static function geo_settings() {
		$frm_geo_settings = FrmGeoAppHelper::get_settings();
		require_once FrmGeoAppController::path() . '/views/settings/form.php';
	}

	/**
	 * Adds a field to global settings.
	 *
	 * @since 1.0
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public static function add_field_setting( $args ) {
		$field = $args['field'];
		if ( ! isset( $field['auto_address'] ) ) {
			$field['auto_address'] = 0;
		}
		if ( ! isset( $field['geo_show_map'] ) ) {
			$field['geo_show_map'] = 0;
		}
		if ( ! isset( $field['geo_avoid_autofill'] ) ) {
			$field['geo_avoid_autofill'] = 0;
		}
		if ( ! isset( $field['geo_detect_location'] ) ) {
			$field['geo_detect_location'] = 0;
		}
		include FrmGeoAppController::path() . '/views/settings/field.php';
	}

	/**
	 * Add the autocomplete setting so it will get saved.
	 *
	 * @param array $settings Settings.
	 * @param array $atts Attributes of the field.
	 * @return array
	 */
	public static function add_default_field_settings( $settings, $atts ) {
		if ( empty( $atts['field'] ) ) {
			return $settings;
		}

		$field_type = FrmField::get_original_field_type( $atts['field'] );
		$allowed    = array( 'text', 'address' );
		if ( in_array( $field_type, $allowed, true ) ) {
			$settings['auto_address']        = 0;
			$settings['geo_show_map']        = 0;
			$settings['geo_avoid_autofill']  = 0;
			$settings['geo_detect_location'] = 0;
		}

		return $settings;
	}

	/**
	 * Update field option.
	 *
	 * @param array $field field.
	 * @return array
	 */
	public static function update_options( $field ) {
		$field['field_options']['geo_avoid_autofill'] = ! empty( $field['field_options']['geo_detect_location'] ) ? 0 : 1;
		unset( $field['field_options']['geo_detect_location'] );
		return $field;
	}

	/**
	 * Update setting field according to the new params.
	 *
	 * @since 1.1
	 *
	 * @param mixed $params of updated form.
	 * @see action hook frm_update_settings
	 * @return void
	 */
	public static function update( $params ) {
		$frm_geo_settings = FrmGeoAppHelper::get_settings();
		$frm_geo_settings->update( $params );
	}

	/**
	 * Save updated field to the DB.
	 *
	 * @since 1.1
	 *
	 * @see action hook frm_store_settings
	 * @return void
	 */
	public static function store() {
		$frm_geo_settings = FrmGeoAppHelper::get_settings();
		$frm_geo_settings->store();
	}
}
