<?php
/**
 * App controller
 *
 * @package FrmCharts
 */

/**
 * Class FrmChartsAppController
 */
class FrmChartsAppController {

	/**
	 * Shows the incompatible notice.
	 */
	public static function show_incompatible_notice() {
		if ( FrmChartsAppHelper::is_compatible() ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Formidable Charts requires Formidable Forms Pro to be activated.', 'frm-charts' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Initializes plugin translation.
	 */
	public static function init_translation() {
		load_plugin_textdomain( 'frm-charts', false, FrmChartsAppHelper::plugin_folder() . '/languages/' );
	}

	/**
	 * Includes addon updater.
	 */
	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			FrmChartsUpdate::load_hooks();
		}
	}
}
