<?php
/**
 * App helper
 *
 * @package FrmCharts
 */

/**
 * Class FrmChartsAppHelper
 */
class FrmChartsAppHelper {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $plug_version = '2.0';

	/**
	 * Gets plugin folder name.
	 *
	 * @return string
	 */
	public static function plugin_folder() {
		return basename( self::plugin_path() );
	}

	/**
	 * Gets plugin file path.
	 *
	 * @return string
	 */
	public static function plugin_file() {
		return self::plugin_path() . '/formidable-charts.php';
	}

	/**
	 * Gets plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return dirname( dirname( dirname( __FILE__ ) ) );
	}

	/**
	 * Gets plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( '', self::plugin_path() . '/formidable-charts.php' );
	}

	/**
	 * Checks if this plugin is safe to run.
	 *
	 * @return bool
	 */
	public static function is_compatible() {
		return class_exists( 'FrmProAppHelper' );
	}

	/**
	 * Gets URL of formidable pro JS or minified JS.
	 *
	 * @return string
	 */
	public static function get_formidable_js_url() {
		$suffix = FrmAppHelper::js_suffix();

		if ( ! $suffix || ! FrmProAppController::has_combo_js_file() ) {
			return FrmProAppHelper::plugin_url() . '/js/formidablepro' . $suffix . '.js';
		}

		return FrmProAppHelper::plugin_url() . '/js/frm.min.js';
	}

	/**
	 * Parses the unit from the value.
	 *
	 * @param string $value The value to parse the unit from.
	 * @return array
	 */
	public static function parse_unit( $value ) {
		$number = floatval( $value );

		if ( strpos( $value, '%' ) !== false ) {
			$unit = '%';
		} else {
			$unit = 'px';
		}

		return compact( 'number', 'unit' );
	}
}
