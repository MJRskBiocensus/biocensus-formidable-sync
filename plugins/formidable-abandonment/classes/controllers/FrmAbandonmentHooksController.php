<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Hooks controller
 *
 * @package formidable-abandonment
 */

/**
 * Class FrmAbandonmentHooksController
 */
class FrmAbandonmentHooksController {

	/**
	 * Adds this class to hook controllers list.
	 *
	 * @since 1.0
	 *
	 * @param array<string> $controllers Hooks controllers.
	 *
	 * @return array<string>
	 */
	public static function add_hooks_controller( $controllers ) {
		if ( ! FrmAbandonmentAppHelper::is_compatible() ) {
			self::load_incompatible_hooks();
			return $controllers;
		}

		$controllers[] = __CLASS__;
		return $controllers;
	}

	/**
	 * Loads hooks when this plugin isn't safe to run.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private static function load_incompatible_hooks() {
		self::load_translation();

		add_action( 'admin_notices', array( 'FrmAbandonmentAppController', 'show_incompatible_notice' ) );
	}

	/**
	 * Loads translation.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private static function load_translation() {
		add_action( 'init', array( 'FrmAbandonmentAppController', 'init_translation' ) );
	}

	/**
	 * Loads plugin hooks.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function load_hooks() {
		add_action( 'init', array( 'FrmAbdnCronController', 'init' ) );

		$observer_controller = FrmAbandonmentObserverController::get_instance();
		add_action( 'frm_include_front_css', array( $observer_controller, 'append_css' ) );
		add_action( 'wp_footer', array( $observer_controller, 'enqueue_assets' ) );

		add_action( 'frm_after_install', 'FrmAbandonmentAppController::trigger_upgrade' );
		add_filter( 'frm_entry_statuses', 'FrmAbandonmentAppController::add_entry_status', 1 );
		add_filter( 'frm_main_feedback', 'FrmAbandonmentAppController::add_button_to_success', 12, 3 );

		add_filter( 'frm_skip_form_action', 'FrmAbandonmentActionController::maybe_skip_action', 99, 2 );

		add_filter( 'frm_saving_draft', 'FrmAbdnEntry::saving_draft' );
		add_filter( 'frm_update_entry', 'FrmAbdnEntry::attach_create_event', 1, 2 );
		add_action( 'frm_after_create_entry', 'FrmAbdnEntry::clean_after_submit', 99, 2 );
		add_action( 'frm_after_draft_entry_processed', 'FrmAbdnEntry::clean_after_save_draft' );
		add_filter( 'frm_validate_entry', 'FrmAbdnEntry::block_google_cache', 10, 2 );

		add_shortcode( 'frm-signed-edit-link', 'FrmAbdnEntriesController::entry_edit_link_shortcode' );
		add_filter( 'frm_user_can_edit', 'FrmAbdnEntriesController::get_all_fields_and_bypass_permission', 10, 2 );

		if ( isset( $_GET['secret'] ) ) {
			add_action( 'wp_loaded', 'FrmAbdnEntriesController::maybe_redirect_to_login', 9 );
		}

		add_filter( 'frm_should_rerun_autoid_before_unique_field_validation', 'FrmAbdnEntriesController::maybe_rerun_autoid_before_unique_field_validation', 10, 2 );

		add_filter( 'frm_usage_form', 'FrmAbandonmentAppController::form_usage_data', 10, 2 );

		self::load_translation();
	}

	/**
	 * These hooks are only needed for front-end forms.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function load_form_hooks() {
		add_action( 'frm_entry_form', 'FrmAbandonmentAppController::insert_hidden_fields' );
		add_filter( 'frm_action_triggers', 'FrmAbandonmentActionController::add_abandoned_trigger' );
		add_filter( 'frm_email_control_settings', 'FrmAbandonmentActionController::email_action_control' );

		if ( isset( $_GET['secret'] ) ) {
			add_action( 'wp_ajax_frm_forms_preview', 'FrmAbdnEntriesController::before_preview', 9 );
			add_action( 'wp_ajax_nopriv_frm_forms_preview', 'FrmAbdnEntriesController::before_preview', 9 );
			add_filter( 'frm_show_new_entry_page', 'FrmAbdnEntriesController::allow_form_edit', 20, 2 );
		}
	}

	/**
	 * These hooks only load during ajax request.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function load_ajax_hooks() {
		add_action( 'wp_ajax_frm_abandoned', 'FrmAbandonmentAppController::maybe_insert_abandoned_entry' );
		add_action( 'wp_ajax_nopriv_frm_abandoned', 'FrmAbandonmentAppController::maybe_insert_abandoned_entry' );
		add_action( 'wp_ajax_frm_abandoned_reset_token', 'FrmAbandonmentAppController::reset_token' );
	}

	/**
	 * These hooks only load in the admin area.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function load_admin_hooks() {
		add_action( 'admin_init', 'FrmAbandonmentAppController::include_updater' );
		add_filter( 'frm_db_needs_upgrade', 'FrmAbandonmentAppController::needs_upgrade' );
		add_action( 'frm_enqueue_builder_scripts', 'FrmAbandonmentAppController::enqueue_admin_assets' );
		add_filter( 'frm_views_entry_status_options', 'FrmAbandonmentAppController::add_entry_status_views_filter_options' );

		add_filter( 'frm_add_form_settings_section', 'FrmAbandonmentFormSettingsController::add_settings_section', 11 );
		add_action( 'frm_settings_after_save_draft', 'FrmAbandonmentFormSettingsController::add_save_draft_settings' );
		add_action( 'frm_settings_after_edit_entry', 'FrmAbandonmentFormSettingsController::add_edit_entry_settings' );
		add_action( 'frm_settings_edit_draft_role', 'FrmAbandonmentFormSettingsController::add_save_draft_roles' );
		add_action( 'frm_settings_editable_role', 'FrmAbandonmentFormSettingsController::add_editable_roles' );
		add_action( 'frm_entry_shared_sidebar_middle', 'FrmAbandonmentFormSettingsController::entry_detail_token_box' );
		add_filter( 'frm_pro_default_form_settings', 'FrmAbandonmentFormSettingsController::default_form_settings' );

		add_filter( 'frm_form_email_action_settings', array( new FrmAbandonmentActionController(), 'add_customized_email_action' ) );

		add_filter( 'frm_helper_shortcodes', 'FrmAbdnEntriesController::helper_shortcodes_options' );
	}
}
