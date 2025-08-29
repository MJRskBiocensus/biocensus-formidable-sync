<?php
/**
 * Hooks controller
 *
 * @package FrmCharts
 */

/**
 * Class FrmChartsHooksController
 */
class FrmChartsHooksController {

	/**
	 * Adds this class to hook controllers list.
	 *
	 * @param array $controllers Hooks controllers.
	 * @return array
	 */
	public static function add_hooks_controller( $controllers ) {
		if ( ! FrmChartsAppHelper::is_compatible() ) {
			self::load_incompatible_hooks();
			return $controllers;
		}

		$controllers[] = __CLASS__;
		return $controllers;
	}

	/**
	 * Loads hooks when this plugin isn't safe to run.
	 */
	private static function load_incompatible_hooks() {
		self::load_translation();

		add_action( 'admin_notices', array( 'FrmChartsAppController', 'show_incompatible_notice' ) );
	}

	/**
	 * Loads translation.
	 */
	private static function load_translation() {
		add_action( 'init', array( 'FrmChartsAppController', 'init_translation' ) );
	}

	/**
	 * Loads plugin hooks.
	 */
	public static function load_hooks() {
		self::load_translation();

		add_action( 'init', array( 'FrmChartsBlocksController', 'register' ) );
		add_action( 'rest_api_init', array( 'FrmChartsRESTController', 'register_routes' ) );
		add_filter( 'frm_pro_graph_defaults', array( 'FrmChartsGraphImageController', 'add_graph_image_atts' ) );
		add_filter( 'frm_graph_shortcode_custom_html', array( 'FrmChartsGraphImageController', 'graph_html' ), 10, 2 );
		add_action( 'init', array( 'FrmChartsGraphImageController', 'handle_graph_request' ) );

		// Set flag to check if it is processing email content, then clear it after that.
		add_filter( 'frm_email_subject', array( 'FrmChartsGraphImageController', 'before_process_email_content' ) );
		add_filter( 'frm_notification_attachment', array( 'FrmChartsGraphImageController', 'after_process_email_content' ) );
	}

	/**
	 * These hooks only load in the admin area.
	 */
	public static function load_admin_hooks() {
		add_action( 'admin_init', array( 'FrmChartsAppController', 'include_updater' ) );
		add_action( 'enqueue_block_assets', array( 'FrmChartsGraphController', 'load_scripts' ) );

		if ( ! FrmChartsGraphImageController::is_gd_supported() ) {
			add_action( 'admin_notices', array( 'FrmChartsGraphImageController', 'show_admin_notice' ) );
		}
	}
}
