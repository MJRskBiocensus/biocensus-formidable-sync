<?php

class FrmLocHooksController {

	/**
	 * @return void
	 */
	public static function load_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', 'FrmLocAppController::include_updater', 1 );
		add_action( 'after_plugin_row_formidable-locations/formidable-locations.php', 'FrmLocAppController::min_version_notice' );
		add_action( 'admin_menu', 'FrmLocAppController::menu', 27 );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action( 'wp_ajax_frm_import_locations_csv', 'FrmLocImport::import_locations_csv' );
		}
	}
}
