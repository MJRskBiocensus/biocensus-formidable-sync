<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Entries controller
 *
 * @package formidable-abandonment
 */

/**
 * Class FrmAbdnEntriesController
 *
 * @since 1.0
 */
class FrmAbdnEntriesController {

	/**
	 * Add abandonment shortcode to helper shortcode options used in actions and other places.
	 *
	 * @since 1.0
	 *
	 * @param array<string> $options Helper shortcodes.
	 *
	 * @return array<string>
	 */
	public static function helper_shortcodes_options( $options ) {
		$adv_opts = array(
			'frm-signed-edit-link id=[id]' => __( 'Abandonment Edit Link', 'formidable-abandonment' ),
		);

		$options = array_merge( $options, $adv_opts );
		return $options;
	}

	/**
	 * Create an abandonment entry edit link which could be sent via email action and
	 * give a possibility to the link holder to edit the abandoned entry.
	 *
	 * @since 1.0
	 *
	 * @param array<string|int> $atts The params from the shortcode.
	 *
	 * @return string
	 */
	public static function entry_edit_link_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => isset( $atts['entry_id'] ) ? $atts['entry_id'] : 0,
				'label'    => __( 'Continue', 'formidable-abandonment' ),
				'class'    => '',
				'page_id'  => 0,
			),
			$atts
		);

		$url = self::get_edit_link( $atts );

		if ( $url && $atts['label'] ) {
			$url = '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $atts['class'] ) . '">' . $atts['label'] . '</a>';
		}

		return $url;
	}

	/**
	 * Parse together the link to edit an entry.
	 *
	 * @param array<string> $atts Shortcode parameters.
	 *
	 * @return string
	 */
	private static function get_edit_link( $atts ) {
		$token    = FrmAbdnToken::get_by_entry( absint( $atts['id'] ) );
		$base_url = self::get_base_url( $atts );

		if ( ! $token ) {
			$entry         = FrmEntry::getOne( $atts['id'] );
			$frm_abdn_form = new FrmAbdnForm( array( 'form' => (int) $entry->form_id ) );
			if ( ! $frm_abdn_form->is_editable() && ! $frm_abdn_form->is_save_draft_on() ) {
				return '';
			}

			if ( admin_url( 'admin-ajax.php?action=frm_forms_preview' ) === $base_url ) {
				$form     = FrmForm::getOne( $entry->form_id );
				$base_url = add_query_arg(
					array(
						'form' => $form->form_key,
					),
					$base_url
				);
			}

			$url = add_query_arg(
				array(
					'frm_action' => 'edit',
					'entry'      => $atts['id'],
				),
				$base_url
			);

			if ( ! $entry->user_id ) {
				$frm_abdn_form = new FrmAbdnForm( array( 'form' => (int) $entry->form_id ) );
				if ( $frm_abdn_form->is_editable() ) {
					$token = FrmAbdnToken::maybe_create_token( $entry->id );
					if ( is_string( $token ) ) {
						$url = add_query_arg(
							array(
								'secret' => urlencode( base64_encode( $token ) ),
							),
							$url
						);
					}
				}
			}

			return $url;
		}

		// Create a bypass link using openssl encryption.
		return add_query_arg(
			array(
				'frm_action' => 'continue',
				'secret'     => urlencode( base64_encode( $token ) ),
			),
			$base_url
		);
	}

	/**
	 * Returns url for form recovery link.
	 *
	 * @since 1.1.4
	 *
	 * @param array<string> $atts Shortcode atts.
	 * @return mixed
	 */
	private static function get_base_url( $atts ) {
		$base_url = $atts['page_id'] ? get_permalink( (int) $atts['page_id'] ) : admin_url( 'admin-ajax.php?action=frm_forms_preview' );

		/**
		 * Allows updating the url used to recover abandoned entry.
		 *
		 * @since 1.1.4
		 *
		 * @param string $base_url
		 * @param array  $atts
		 */
		return apply_filters( 'frm_abandonment_edit_entry_url', $base_url, $atts );
	}

	/**
	 * Check if form should automatically be in edit mode.
	 *
	 * @since 1.0
	 *
	 * @param string $action The action this form will take.
	 * @param object $form   The form being displayed.
	 *
	 * @return string
	 */
	public static function allow_form_edit( $action, $form ) {
		$frm_action = FrmAppHelper::simple_get( 'frm_action' );
		$actions    = array( 'new', 'edit' );
		if ( ! in_array( $action, $actions, true ) || $frm_action !== 'continue' ) {
			return $action;
		}

		$entry_id = FrmAbdnEntry::should_allow_edit_entry( $form );
		if ( ! $entry_id ) {
			return $action;
		}

		self::set_current_entry( (int) $entry_id );

		add_filter( 'frm_form_replace_shortcodes', 'FrmAbdnEntriesController::maybe_remove_buttons', 9, 2 );

		return 'edit';
	}

	/**
	 * Set the current entry being edited.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id The entry id.
	 *
	 * @return void
	 */
	private static function set_current_entry( $entry_id ) {
		global $frm_vars;
		$frm_vars['editing_entry'] = $entry_id;
	}

	/**
	 * Remove buttons on edit link.
	 *
	 * @since 1.1
	 *
	 * @param string $html HTML.
	 * @param object $form  Form.
	 *
	 * @return string
	 */
	public static function maybe_remove_buttons( $html, $form ) {
		global $frm_vars;

		if ( empty( $frm_vars['editing_entry'] ) ) {
			return $html;
		}

		$entry_status = FrmAbdnEntry::draft_status( (int) $frm_vars['editing_entry'] );

		switch ( $entry_status ) {
			case FrmEntriesHelper::SUBMITTED_ENTRY_STATUS:
				$form->options['start_over'] = 0;
				$form->options['save_draft'] = 0;
				break;
			case FrmEntriesHelper::DRAFT_ENTRY_STATUS:
				$form->options['start_over'] = 0;
				break;
			case FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS:
				$form->options['save_draft'] = 0;
				break;
			default:
				break;
		}

		return $html;
	}

	/**
	 * Bypass user permission check and get all entry data.
	 *
	 * @since 1.0
	 *
	 * @param mixed             $allowed Allowed users.
	 * @param array<int|object> $args {
	 *   Form args.
	 *   @type object     $form
	 *   @type int|object $entry
	 * }
	 *
	 * @return mixed
	 */
	public static function get_all_fields_and_bypass_permission( $allowed, $args ) {
		if ( $allowed || empty( $args['form'] ) ) {
			return $allowed;
		}

		$entry_id = FrmAbdnEntry::should_allow_edit_entry( $args['form'], $args );
		if ( ! $entry_id ) {
			return $allowed;
		}

		$where = array(
			'fr.id' => is_object( $args['form'] ) ? $args['form']->id : $args['form'],
			'it.id' => $entry_id,
		);

		return FrmEntry::getAll( $where, ' ORDER BY created_at DESC', 1, true );
	}

	/**
	 * This would be better to add a new function in Pro that checks is the draft has any status
	 * other than published. For the sake of time, this is a workaround since we won't have another
	 * Pro release before this is released.
	 *
	 * @since 1.1
	 *
	 * @param array<mixed> $values The values to update.
	 * @param int          $id     The entry id.
	 *
	 * @return array<mixed>
	 */
	public static function set_update_actions( $values, $id ) {
		$previous_status = FrmAbdnEntry::draft_status( $id );
		$new_status      = ! empty( $values['is_draft'] ) ? absint( $values['is_draft'] ) : 0;

		if ( ! $previous_status || ! $new_status ) {
			return $values;
		}

		if ( ! FrmAbdnEntry::is_progress_or_abandoned( $previous_status ) &&
			! FrmAbdnEntry::is_progress_or_abandoned( $new_status ) ) {
			// The abandon statuses are not used, so leave it alone.
			return $values;
		}

		remove_action( 'frm_after_update_entry', 'FrmProEntriesController::add_published_hooks', 2 );
		remove_action( 'frm_after_update_entry', 'FrmProFormActionsController::trigger_update_actions', 10 );

		remove_filter( 'frm_update_entry', 'FrmProEntriesController::check_draft_status', 10 );

		if ( FrmProEntry::is_draft_status( $new_status ) ) {
			$values = self::trigger_draft_actions( $values, $id );
		}

		return $values;
	}

	/**
	 * This is copied from FrmProEntriesController::check_draft_status.
	 * Remove this when Pro is updated.
	 *
	 * @since 1.1
	 *
	 * @param array<mixed> $values The values to update.
	 * @param int          $id     The entry id.
	 *
	 * @return array<mixed>
	 */
	private static function trigger_draft_actions( $values, $id ) {
		add_action( 'frm_after_update_entry', 'FrmProEntriesController::add_published_hooks', 2, 2 );

		do_action(
			'frm_after_complete_entry_processed',
			array(
				'entry_id' => $id,
				'form'     => $values['form_id'],
			)
		);

		$values['created_at'] = $values['updated_at'];

		return $values;
	}

	/**
	 * Set the preview form key from the abandonment secret in the edit link.
	 * This hooks in before the FrmFormsController::preview function is called.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function before_preview() {
		if ( ! empty( $_GET['form'] ) ) {
			return;
		}

		$entry_id = ( new FrmAbandonmentEncryptionHelper() )->check_entry_token( 0, array( 'draft', 'published' ) );
		if ( ! $entry_id ) {
			// No secret was included.
			return;
		}

		if ( is_wp_error( $entry_id ) ) {
			wp_die( wp_kses_post( self::show_no_permission_message() ) );
		}

		$form_id = FrmDb::get_var( 'frm_items', array( 'id' => $entry_id ), 'form_id' );
		if ( $form_id ) {
			if ( is_numeric( $entry_id ) ) {
				self::set_current_entry( (int) $entry_id );
			}
			$_GET['form'] = FrmForm::get_key_by_id( $form_id );
		}
	}

	/**
	 * Maybe redirect to login page when logged in links being accessed with logged out users.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	public static function maybe_redirect_to_login() {
		$secret = FrmAppHelper::get_param( 'secret', '', 'get', 'sanitize_text_field' );
		if ( ! $secret || is_user_logged_in() ) {
			// Minimize page load when secret is not available.
			return;
		}

		$permission = self::is_login_required();
		if ( ! is_wp_error( $permission ) ) {
			return;
		}

		$error_code = $permission->get_error_codes();
		if ( in_array( 'require_login', $error_code, true ) ) {
			$redirect_to = FrmAppHelper::get_server_value( 'REQUEST_URI' );
			if ( wp_safe_redirect( wp_login_url( home_url( $redirect_to ) ) ) ) {
				exit;
			}
		}
	}

	/**
	 * Bypass user permission check and get all entry data.
	 *
	 * @since 1.0
	 *
	 * @param float|int|string $entry_id The id of the entry to edit.
	 *
	 * @return WP_Error|int|bool
	 */
	private static function is_login_required( $entry_id = 0 ) {
		if ( ! $entry_id ) {
			$entry_id = FrmAppHelper::get_param( 'id', '', 'post', 'absint' );
		}

		$not_allowed_parameters = array( 'entry_id', 'id' );
		foreach ( $not_allowed_parameters as $qs_key ) {
			if ( isset( $_GET[ $qs_key ] ) ) {
				return new WP_Error(
					'http_request_failed',
					__( 'Not authorized.', 'formidable-abandonment' )
				);
			}
		}

		return FrmAbdnEntry::editable_with_token( $entry_id );
	}

	/**
	 * Show a message when a user does not have permission to edit an entry.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	private static function show_no_permission_message() {
		$frm_settings = FrmAppHelper::get_settings();
		return do_shortcode( $frm_settings->login_msg );
	}

	/**
	 * Re-run the autoid before unique field validation when updating an in-progress or abandoned entry,
	 * but not when making a "frm_abandoned" AJAX request.
	 *
	 * @since 1.1.4
	 *
	 * @param bool        $should_rerun True if the autoid should be re-run.
	 * @param object|null $entry        The entry being saved, or null if the entry does not yet exist.
	 * @return bool
	 */
	public static function maybe_rerun_autoid_before_unique_field_validation( $should_rerun, $entry ) {
		if ( $should_rerun || ! is_object( $entry ) ) {
			return $should_rerun;
		}

		if ( FrmAppHelper::get_post_param( 'action' ) === 'frm_abandoned' ) {
			// Never re-run when saving abandonment data (unless we have never saved).
			return false;
		}

		return isset( $entry->is_draft ) && in_array( (int) $entry->is_draft, array( FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS, FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS ), true );
	}
}
