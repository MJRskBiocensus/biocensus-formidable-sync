<?php
/**
 * Date calculation helper
 *
 * @package formidable-dates
 * @since 2.0
 */

/**
 * Class FrmDatesCalculationHelper
 */
class FrmDatesCalculationHelper {

	/**
	 * Flag to check if shortcodes box is printed or not.
	 *
	 * @var bool
	 */
	private static $printed_shortcodes_box = false;

	/**
	 * Maybe print the shortcode box modal.
	 *
	 * @param array $field Field array.
	 */
	public static function maybe_print_shortcodes_modal( $field ) {
		if ( self::$printed_shortcodes_box ) {
			return;
		}

		FrmFieldsHelper::inline_modal(
			array(
				'title'    => __( 'Smart Tags', 'frmdates' ),
				'callback' => array( __CLASS__, 'shortcodes_modal_callback' ),
				'args'     => $field,
				'id'       => 'frm_dates_shortcodes_box',
			)
		);

		self::$printed_shortcodes_box = true;
	}

	/**
	 * Checks if Formidable Forms and Pro support Date calculation.
	 *
	 * @return bool
	 */
	public static function is_formidable_supported() {
		$required_ver = '6.4.1';
		return version_compare( FrmAppHelper::$plug_version, $required_ver, '>=' ) && version_compare( FrmProDb::$plug_version, $required_ver, '>=' );
	}

	/**
	 * Callback for shortcodes modal.
	 *
	 * @param array $field Field array.
	 */
	public static function shortcodes_modal_callback( $field ) {
		include FrmDatesAppHelper::get_path( '/views/calc-shortcodes-modal.php' );
	}

	/**
	 * Callback for settings modal.
	 *
	 * @param array $field Field array.
	 */
	public static function calc_settings_modal_callback( $field ) {
		add_filter( 'frm_striphtml_allowed_tags', array( __CLASS__, 'add_svg_allowed_attrs' ) );
		include FrmDatesAppHelper::get_path( '/views/calc-settings-modal.php' );
		remove_filter( 'frm_striphtml_allowed_tags', array( __CLASS__, 'add_svg_allowed_attrs' ) );
	}

	/**
	 * Adds custom allowed attributes for the svg element.
	 *
	 * @param array $allowed_html Allowed HTML.
	 * @return array
	 */
	public static function add_svg_allowed_attrs( $allowed_html ) {
		if ( isset( $allowed_html['svg'] ) ) {
			$allowed_html['svg']['data-fid'] = true;
		}
		return $allowed_html;
	}
}
