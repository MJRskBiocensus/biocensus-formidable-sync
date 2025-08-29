<?php

class FrmGeoAppHelper {

	/**
	 * Settings holder.
	 *
	 * @since 1.1
	 *
	 * @var FrmGeoSettings|null $settings
	 */
	private static $settings;

	/**
	 * @var string
	 */
	public static $plug_version = '1.3.4';

	/**
	 * Get the geo settings
	 *
	 * @since 1.1
	 *
	 * @return FrmGeoSettings
	 */
	public static function get_settings() {
		if ( ! isset( self::$settings ) ) {
			self::$settings = new FrmGeoSettings();
		}
		return self::$settings;
	}

	/**
	 * @return string
	 */
	public static function plugin_version() {
		return self::$plug_version;
	}

	/**
	 * @return string
	 */
	public static function path() {
		return dirname( dirname( __DIR__ ) );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public static function plugin_url( $path = '' ) {
		return plugins_url( $path, self::plugin_file() );
	}

	/**
	 * @return string
	 */
	public static function plugin_file() {
		return self::path() . '/formidable-geo.php';
	}
}
