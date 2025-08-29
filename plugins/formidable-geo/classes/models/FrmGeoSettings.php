<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Formidable Geo Settings.
 *
 * @since 1.0
 */
class FrmGeoSettings extends FrmSettings {

	/**
	 * @deprecated 1.1
	 *
	 * @var object $settings
	 */
	public $settings;

	/**
	 * Option name.
	 *
	 * @since 1.1
	 *
	 * @var string $option_name
	 */
	public $option_name = 'frm_geo_options';

	/**
	 * Api key.
	 *
	 * @since 1.1
	 *
	 * @var object
	 */
	public $api_key;

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @since 1.1
	 *
	 * @param mixed $key Key name.
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( 'settings' === $key ) {
			_deprecated_function( __METHOD__, '1.1' );
			return $this->settings;
		}
	}

	/**
	 * Gets default options.
	 *
	 * @since 1.0
	 * @return array Default options.
	 */
	public function default_options() {
		return array(
			'api_key' => '',
		);
	}

	/**
	 * Initializes default options.
	 *
	 * @since 1.0
	 * @deprecated 1.1
	 * @param object|boolean $settings current options. Default false.
	 * @return void
	 */
	public function set_default_options( $settings = false ) {
		_deprecated_function( __METHOD__, '1.1' );

		$default_settings = $this->default_options();

		if ( false === $settings ) {
			$settings = $this->get_options();
		} elseif ( true === $settings ) {
			$settings = new stdClass();
		}

		foreach ( $default_settings as $setting => $default ) {
			if ( isset( $settings->{$setting} ) ) {
				$this->settings->{$setting} = $settings->{$setting};
			} else {
				$this->settings->{$setting} = $default;
			}
		}
	}

	/**
	 * Gets geo options.
	 *
	 * @since 1.0
	 * @deprecated 1.1
	 * @return object list of geo options.
	 */
	public function get_options() {
		_deprecated_function( __METHOD__, '1.1' );

		$settings = get_option( 'frm_geo_options' );

		if ( ! is_object( $settings ) ) {
			if ( $settings ) { // workaround for W3 total cache conflict.
				$this->settings = unserialize( serialize( $settings ) );
			} else {
				$this->set_default_options( true );
			}
			$this->store();
		} else {
			$this->set_default_options( $settings );
		}

		return $this->settings;
	}

	/**
	 * Update values based on changes or initial result.
	 *
	 * @since 1.0
	 *
	 * @param array<string|int> $params post value.
	 * @return void
	 */
	public function update( $params ) {
		$this->api_key = isset( $params['frm_geo_api_key'] ) ? sanitize_text_field( $params['frm_geo_api_key'] ) : '';
	}

	/**
	 * Stores geo options.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function store() {
		update_option( $this->option_name, $this, 'no' );

		set_transient( $this->option_name, $this );
	}
}
