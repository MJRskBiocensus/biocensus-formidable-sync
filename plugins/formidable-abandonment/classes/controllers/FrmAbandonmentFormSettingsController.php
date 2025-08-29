<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class FrmAbandonmentFormSettingsController
 *
 * @since 1.0
 *
 * @package formidable-abandonment
 */

/**
 * Controller for form setting.
 *
 * @since 1.0
 */
class FrmAbandonmentFormSettingsController {

	/**
	 * Add abandonment setting section to form settings.
	 *
	 * @since 1.0
	 *
	 * @param array<mixed> $sections Form settings sections.
	 *
	 * @return array<mixed>
	 */
	public static function add_settings_section( $sections ) {
		$sections['abandonment'] = array(
			'function' => array( __CLASS__, 'settings_section' ),
			'name'     => __( 'Form Abandonment', 'formidable-abandonment' ),
			'icon'     => 'frm_icon_font frm_abandoned_icon',
			'anchor'   => 'abandonment',
		);
		return $sections;
	}

	/**
	 * Form settings.
	 *
	 * @since 1.0
	 *
	 * @param array<mixed> $values Form settings.
	 *
	 * @return void
	 */
	public static function settings_section( $values ) {
		require FrmAbandonmentAppHelper::plugin_path() . '/views/settings/settings.php';
	}

	/**
	 * Add default settings.
	 *
	 * @since 1.1
	 *
	 * @param array<mixed> $settings Pro settings.
	 *
	 * @return array<mixed>
	 */
	public static function default_form_settings( $settings ) {
		$settings['auto_save_draft'] = 0;

		return $settings;
	}

	/**
	 * Entry detail token box.
	 *
	 * @since 1.1
	 *
	 * @param object $entry Entry.
	 *
	 * @return void
	 */
	public static function entry_detail_token_box( $entry ) {
		$token = FrmAbdnToken::get_by_entry( (int) $entry->id );

		// If token is not available try to create a new one if it's eligible.
		if ( ! $token && self::token_box_allowed( $entry ) ) {
			$token = FrmAbdnToken::maybe_create_token( $entry->id );
		}

		// In this step we don't need to display the box.
		if ( ! $token || is_wp_error( $token ) ) {
			return;
		}

		$token      = base64_encode( $token );
		$token_link = FrmAbdnEntriesController::entry_edit_link_shortcode(
			array(
				'id'    => (int) $entry->id,
				'label' => '',
			)
		);

		require FrmAbandonmentAppHelper::plugin_path() . '/views/settings/token-box.php';
	}

	/**
	 * Check entry eligibility to create a entry editing token.
	 *
	 * @since 1.1
	 *
	 * @param Object $entry Entry.
	 *
	 * @return bool
	 */
	private static function token_box_allowed( $entry ) {
		// Abandoned entries are eligible to have a token.
		if ( FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS === (int) $entry->is_draft ) {
			return true;
		}

		$frm_abdn_form = new FrmAbdnForm( array( 'form' => $entry->form_id ) );
		if ( $frm_abdn_form->is_save_draft_on() && FrmProEntry::is_draft_status( (int) $entry->is_draft ) ) {
			return true;
		}

		return $frm_abdn_form->is_editable();
	}

	/**
	 * Append save draft settings.
	 *
	 * @since 1.1
	 *
	 * @param mixed $values Form settings.
	 *
	 * @return void
	 */
	public static function add_save_draft_settings( $values ) {
		require FrmAbandonmentAppHelper::plugin_path() . '/views/settings/save-draft.php';
	}

	/**
	 * Append edit entry settings.
	 *
	 * @since 1.1
	 *
	 * @param mixed $values Form settings.
	 *
	 * @return void
	 */
	public static function add_edit_entry_settings( $values ) {
		require FrmAbandonmentAppHelper::plugin_path() . '/views/settings/front-end-edit.php';
	}

	/**
	 * Append roles to edit draft.
	 *
	 * @since 1.1
	 *
	 * @param array<string> $draft_roles Roles for edit draft.
	 *
	 * @return void
	 */
	public static function add_save_draft_roles( $draft_roles ) {
		self::logged_out_option( $draft_roles );
	}

	/**
	 * Append roles to allow front-end editing of own entries.
	 *
	 * @since 1.1
	 *
	 * @param array<string> $editable_roles Roles for edit draft.
	 *
	 * @return void
	 */
	public static function add_editable_roles( $editable_roles ) {
		self::logged_out_option( $editable_roles );
	}

	/**
	 * Create logged out role option.
	 *
	 * @since 1.1
	 *
	 * @param array<string> $roles Roles for edit draft.
	 *
	 * @return void
	 */
	private static function logged_out_option( $roles ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<option value="loggedout" ' . FrmProAppHelper::selected( $roles, 'loggedout' ) . '>' .
			esc_html__( 'Logged-out Users', 'formidable-abandonment' ) .
			'</option>';
	}
}
