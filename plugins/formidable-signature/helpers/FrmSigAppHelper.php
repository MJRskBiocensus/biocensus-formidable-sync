<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Helper class.
 */
class FrmSigAppHelper {
	/**
	 * Min formidable version.
	 *
	 * @var string
	 */
	private static $min_formidable_version = '3.0';

	/**
	 * Plugin Version.
	 *
	 * @var string $plug_version
	 */
	public static $plug_version = '3.0.5';

	/**
	 * Plugin version.
	 *
	 * @since 2.06
	 *
	 * @return string The version of this plugin.
	 */
	public static function plugin_version() {
		return self::$plug_version;
	}

	/**
	 * Plugin folder name.
	 *
	 * @return string plugin folder name.
	 */
	public static function plugin_folder() {
		return basename( self::plugin_path() );
	}

	/**
	 * Plugin Path.
	 *
	 * @return string plugin path.
	 */
	public static function plugin_path() {
		return dirname( dirname( __FILE__ ) );
	}

	/**
	 * Plugin URL.
	 *
	 * @return string plugin URL.
	 */
	public static function plugin_url() {
		return plugins_url( '', self::plugin_path() . '/signature.php' );
	}

	/**
	 * Check if the current version of Formidable is compatible with Signature add-on.
	 *
	 * @since 2.0
	 *
	 * @return mixed
	 */
	public static function is_formidable_compatible() {
		return self::is_formidable_greater_than_or_equal_to( self::$min_formidable_version );
	}

	/**
	 * Check if the Formidable version is greater than or equal to specific version number.
	 *
	 * @since 2.0
	 * @param string $version version.
	 *
	 * @return boolean
	 */
	public static function is_formidable_greater_than_or_equal_to( $version ) {
		$frm_version = is_callable( 'FrmAppHelper::plugin_version' ) ? FrmAppHelper::plugin_version() : '0';

		return version_compare( $frm_version, $version, '>=' );
	}

	/**
	 * Get svg icon.
	 *
	 * @since 3.0.4
	 * @param string $icon_svg_slug
	 * @param string $icon_by_class
	 * @param boolean $echo
	 *
	 * @return string
	 */
	public static function get_svg_icon( $icon_svg_slug, $icon_by_class, $echo = true ) {

		$icon = '';
		if ( isset( $icon_svg_slug ) && is_callable( 'FrmProAppHelper::get_svg_icon' ) ) {
			$icon = FrmProAppHelper::get_svg_icon( $icon_svg_slug, 'frmsvg' );
		} elseif ( isset( $icon_by_class ) ) {
			FrmAppHelper::include_svg();
			$icon = FrmAppHelper::icon_by_class( $icon_by_class, array( 'echo' => false ) );
		}

		if ( false === $echo ) {
			return $icon;
		}

		echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
