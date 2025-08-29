<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrmAbdnEntry
 *
 * @since 1.0
 *
 * @package formidable-abandonment
 */

/**
 * Processing abandonment crud.
 *
 * @since 1.0
 *
 * @internal
 */
class FrmAbdnEntry {

	/**
	 * Hold the current is_draft value before update.
	 *
	 * @since 1.0
	 *
	 * @var int
	 */
	private $is_draft;

	/**
	 * Form id.
	 *
	 * @since 1.1
	 *
	 * @var int
	 */
	private $form_id;

	/**
	 * The entry id to edit.
	 *
	 * @since 1.1
	 *
	 * @var int
	 */
	private $entry_id;

	/**
	 * FrmAbdnEntry constructor.
	 *
	 * @since 1.0
	 *
	 * @param array<int|bool> $atts {
	 *   Passed variables.
	 *   @type int       $form_id  The form id.
	 *   @type int       $entry_id The entry id if editing.
	 *   @type int|false $is_draft The is_draft column value. False if no entry.
	 * }
	 *
	 * @return void
	 */
	public function __construct( $atts ) {
		$this->form_id  = (int) $atts['form_id'];
		$this->is_draft = isset( $atts['draft_status'] ) ? (int) $atts['draft_status'] : 0;

		if ( isset( $atts['entry_id'] ) ) {
			$this->entry_id = (int) $atts['entry_id'];
		}
		$this->exclude_field_to_store( $this->form_id );
	}

	/**
	 * Maybe insert new abandonment entry or update them.
	 *
	 * @since 1.0
	 *
	 * @return int|bool Entry id.
	 */
	public function submit_entry() {
		if ( $this->missing_gdpr_consent() ) {
			return false;
		}

		$can_edit = $this->user_can_edit_entry();
		if ( is_wp_error( $can_edit ) ) {
			// Avoid creating a bunch of entries if something went wrong.
			return false;
		}

		if ( $can_edit ) {
			$this->update_entry( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		if ( FrmAppHelper::get_post_param( 'frm_action', '', 'sanitize_text_field' ) === 'update' ) {
			// Don't create a new entry if the form should be updating an existing entry.
			return false;
		}

		// Remove pro process entry, It will remove duplicate entry.
		remove_action( 'frm_process_entry', 'FrmProEntriesController::process_update_entry', 10 );
		return $this->insert_entry();
	}

	/**
	 * Check if a GDPR field is present but unchecked.
	 * We only save data if there is no GDPR field, or if it is checked.
	 *
	 * @since 1.1.5
	 *
	 * @return bool
	 */
	private function missing_gdpr_consent() {
		if ( ! is_callable( 'FrmAppHelper::is_gdpr_enabled' ) || ! FrmAppHelper::is_gdpr_enabled() ) {
			return false;
		}

		$form_id = FrmAppHelper::get_post_param( 'form_id', 0, 'absint' );
		if ( ! $form_id ) {
			return false;
		}

		$gdpr_field = FrmField::get_all_types_in_form( $form_id, 'gdpr', 1 );
		if ( ! $gdpr_field ) {
			return false;
		}

		return empty( $_POST['item_meta'][ $gdpr_field->id ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Check if the current user has permission to edit this entry.
	 * Can be draft or abandoned.
	 *
	 * @since 1.1
	 *
	 * @return bool|WP_Error Return `true` if the current user has permission to edit this entry.
	 */
	private function user_can_edit_entry() {
		$frm_abdn_form = new FrmAbdnForm( array( 'form' => $this->form_id ) );
		if ( ! $frm_abdn_form->is_plugin_used() ) {
			return false;
		}

		$entry = FrmEntry::getOne( $this->entry_id );

		// Allow draft or abandoned.
		if ( ! is_object( $entry ) || ! $entry->is_draft ) {
			return false;
		}

		$user_edit_drafts = self::logged_in_user_can_edit_draft( $entry, $this->form_id );
		if ( is_wp_error( $user_edit_drafts ) ) {
			return false;
		}

		if ( $user_edit_drafts ) {
			// Don't check the token if the user has permission to edit their own draft.
			return true;
		}

		// Check if the token matches this entry.
		return self::compare_entry_token( $this->entry_id );
	}

	/**
	 * Check if the current logged in user can edit the draft entry.
	 *
	 * @since 1.1
	 *
	 * @param object     $entry Entry object.
	 * @param object|int $form  Form object.
	 * @return bool|WP_Error Return `true` if the current user has permission to edit this entry.
	 */
	private static function logged_in_user_can_edit_draft( $entry, $form ) {
		$id_matches = self::user_id_matches( $entry );
		if ( is_wp_error( $id_matches ) ) {
			return $id_matches;
		}

		$entry_user = (int) $entry->user_id;
		if ( ! $entry_user || ! FrmProEntry::is_draft_status( $entry->is_draft ) ) {
			// Only check draft entries here.
			return false;
		}

		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		return $frm_abdn_form->is_logged_in_draft_on();
	}

	/**
	 * Check if the current logged in user can edit the submitted entry.
	 *
	 * @since 1.1
	 *
	 * @param object $entry Entry object.
	 * @return bool|WP_Error Return `true` if the current user has permission to edit this entry.
	 */
	private static function user_id_matches( $entry ) {
		$current_user = get_current_user_id();
		$entry_user   = (int) $entry->user_id;
		if ( $entry_user && $current_user !== $entry_user ) {
			if ( ! $current_user ) {
				return new WP_Error( 'require_login', 'User not logged in' );
			}
			return new WP_Error( 'no_permission', 'User ID mismatch' );
		}

		return true;
	}

	/**
	 * Bypass user permission check and get all entry data.
	 *
	 * @since 1.1
	 *
	 * @param float|int|string $entry_id The id of the entry to edit or 0.
	 *
	 * @return WP_Error|int|bool
	 */
	public static function editable_with_token( $entry_id ) {
		$entry_id = ( new FrmAbandonmentEncryptionHelper() )->check_entry_token( $entry_id, array( 'draft', 'published' ) );
		if ( is_wp_error( $entry_id ) ) {
			return $entry_id;
		}

		// If entry id not accessible from this stage it means link is expired or submitted before etc.
		if ( ! $entry_id ) {
			return new WP_Error(
				'http_request_failed',
				__( 'Not authorized.', 'formidable-abandonment' )
			);
		}

		$permission_check = self::check_permission_by_status( $entry_id );
		if ( is_wp_error( $permission_check ) || ! $permission_check ) {
			return $permission_check;
		}

		return $entry_id;
	}

	/**
	 * Check for front end editing options.
	 * The token has already been checked.
	 * Return 'require_login' if the user should log in.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return WP_Error|bool
	 */
	private static function check_permission_by_status( $entry_id ) {
		$entry = FrmEntry::getOne( $entry_id );
		$form  = FrmForm::getOne( $entry->form_id );
		$error = new WP_Error(
			'http_request_failed',
			__( 'Not authorized.', 'formidable-abandonment' )
		);

		switch ( $entry->is_draft ) {
			case FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS:
			case FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS:
				return self::user_can_edit_progress( $entry, $form );
			case FrmEntriesHelper::DRAFT_ENTRY_STATUS:
				return self::user_can_edit_draft( $entry, $form );
			default:
				return self::user_can_edit_submitted( $entry, $form );
		}
	}

	/**
	 * Check front end editing based on "Allow front-end editing of entries" option.
	 * Token has already been checked.
	 *
	 * @since 1.1
	 *
	 * @param object $entry Entry object.
	 * @param object $form  Form object.
	 *
	 * @return true|WP_Error
	 */
	private static function user_can_edit_submitted( $entry, $form ) {
		if ( FrmProEntriesHelper::user_can_edit_check( $entry, $form ) ) {
			return true;
		}

		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		// Grant permission when entry submitted by logged out user and loggedout role is set.
		if ( ! $entry->user_id && $frm_abdn_form->is_logged_out_edit_on() ) {
			return true;
		}

		return new WP_Error(
			'require_login',
			__( 'Not authorized.', 'formidable-abandonment' )
		);
	}

	/**
	 * Check if the entry should be allowed to be edited.
	 *
	 * @since 1.1
	 *
	 * @param int|object   $form The form being edited.
	 * @param array<mixed> $args {
	 *   Array of args.
	 *   @type int $entry The entry id.
	 * }
	 * @return int|bool
	 */
	public static function should_allow_edit_entry( $form, $args = array() ) {
		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		if ( ! $frm_abdn_form->is_abandonment_enabled() && ! $frm_abdn_form->is_save_draft_on() && ! $frm_abdn_form->is_editable() ) {
			return false;
		}

		if ( ! isset( $args['entry'] ) || ! is_numeric( $args['entry'] ) ) {
			$args['entry'] = 0;
		}

		$entry_id = self::editable_with_token( $args['entry'] );
		if ( is_wp_error( $entry_id ) || ! $entry_id ) {
			return false;
		}

		return $entry_id;
	}

	/**
	 * Check if the current user can edit the progress entry.
	 *
	 * @since 1.1
	 *
	 * @param object     $entry Entry object.
	 * @param object|int $form  Form object.
	 * @return bool|WP_Error Return `true` if the current user has permission to edit this entry.
	 */
	private static function user_can_edit_progress( $entry, $form ) {
		$id_matches = self::user_id_matches( $entry );
		if ( is_wp_error( $id_matches ) ) {
			return $id_matches;
		}

		if ( ! self::is_progress_or_abandoned( $entry->is_draft ) ) {
			// Only check in progress and abandoned entries here.
			return false;
		}

		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		return $frm_abdn_form->is_abandonment_enabled();
	}

	/**
	 * Check draft permission based on "Allow to save drafts".
	 * Token has already been checked.
	 *
	 * @since 1.1
	 *
	 * @param object $entry Entry object.
	 * @param object $form  Form object.
	 *
	 * @return true|WP_Error
	 */
	private static function user_can_edit_draft( $entry, $form ) {
		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		if ( ! $frm_abdn_form->is_save_draft_on() ) {
			return new WP_Error(
				'draft_limited_permission',
				__( 'Not authorized.', 'formidable-abandonment' )
			);
		}

		// When draft entry by logged out user, Grant permission unconditionally.
		if ( empty( $entry->user_id ) && $frm_abdn_form->is_logged_out_draft_on() ) {
			return true;
		}

		// When draft entry by logged in users. Check the same user wants to edit their own entry.
		$user_can_edit = self::logged_in_user_can_edit_draft( $entry, $form );
		if ( $user_can_edit && ! is_wp_error( $user_can_edit ) ) {
			return true;
		}

		return new WP_Error(
			'require_login',
			__( 'Not authorized.', 'formidable-abandonment' )
		);
	}

	/**
	 * Switch from only logged in users saving drafts to all users.
	 *
	 * @since 1.1
	 *
	 * @param bool $allowed If the current user is allowed to save drafts.
	 * @return bool
	 */
	public static function saving_draft( $allowed ) {
		if ( $allowed ) {
			return $allowed;
		}

		$saving_draft = FrmAppHelper::get_post_param( 'frm_saving_draft', '', 'sanitize_title' );
		$form_id      = FrmAppHelper::get_post_param( 'form_id', '', 'absint' );
		if ( ! $saving_draft || ! $form_id ) {
			return $allowed;
		}

		$frm_abdn_form = new FrmAbdnForm( array( 'form' => $form_id ) );
		if ( $frm_abdn_form->is_logged_out_draft_on() ) {
			return true;
		}

		return $allowed;
	}

	/**
	 * Check the token and compare it with saved one.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id Entry ID.
	 * @return bool|WP_Error Return true if the token matches this entry.
	 */
	private static function compare_entry_token( $entry_id ) {
		$entry_id = ( new FrmAbandonmentEncryptionHelper() )->check_entry_token( $entry_id );
		if ( ! $entry_id ) {
			return false;
		}
		return is_wp_error( $entry_id ) ? $entry_id : true;
	}

	/**
	 * Update is_draft column to 2 "In Progress".
	 *
	 * @since 1.0
	 *
	 * @param int          $entry_id Entry id.
	 * @param int          $form_id  Form ID.
	 * @param array<mixed> $args     Arguments. Includes 'is_child' bool key value.
	 *
	 * @return void
	 */
	public function after_create_abandonment_entry( $entry_id, $form_id = 0, $args = array() ) {
		global $wpdb;
		$entry = FrmEntry::getOne( $entry_id );
		if ( $entry->is_draft ) {
			// Don't set a draft to in progress.
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'frm_items',
			array(
				'is_draft' => FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS,
			),
			array(
				'id' => $entry_id,
			)
		);

		if ( empty( $args['is_child'] ) ) {
			// Only remove the hook for the main entry (not repeater entries).
			// This is to avoid entries getting set to submitted status prematurely when there are repeaters.
			remove_action( 'frm_after_create_entry', array( $this, 'after_create_abandonment_entry' ), 1 );
		}
	}

	/**
	 * Change is_draft column to 3 "Abandoned" from 2 "In progress".
	 *
	 * @see FrmAbdnCronController
	 *
	 * @since 1.0
	 *
	 * @param array<object> $in_progress_entries {
	 *   Array of "in progress" entries.
	 *   @type int    $id
	 *   @type int    $form_id
	 *   @type string $name
	 * }
	 *
	 * @return void
	 */
	public static function mark_entries_abandoned( $in_progress_entries ) {
		foreach ( $in_progress_entries as $entry ) {
			FrmEntry::update(
				$entry->id,
				array(
					'is_draft' => FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS,
					'form_id'  => $entry->form_id,
					'name'     => $entry->name,
				)
			);
		}
	}

	/**
	 * Get "in progress" entries.
	 *
	 * @see FrmAbdnCronController
	 *
	 * @since 1.0
	 *
	 * @param int    $page Page.
	 * @param int    $items_per_page Item per page.
	 * @param string $updated_at updated_at date offset.
	 *
	 * @return array<object>|null
	 */
	public static function get_in_progress_entries( $page, $items_per_page, $updated_at ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,form_id,name FROM {$wpdb->prefix}frm_items WHERE is_draft=2 AND updated_at < %s ORDER BY id ASC LIMIT %d, %d",
				$updated_at,
				( $page - 1 ) * $items_per_page,
				$items_per_page
			)
		);
	}

	/**
	 * Watch on after create entry hook to unlink token from items_meta.
	 *
	 * @since 1.0
	 *
	 * @param int $entry_id Entry ID.
	 * @param int $form_id Form ID.
	 *
	 * @return void
	 */
	public static function clean_after_submit( $entry_id, $form_id ) {
		$frm_abdn_form = new FrmAbdnForm( array( 'form' => $form_id ) );
		if ( ! $frm_abdn_form->is_plugin_used() ) {
			return;
		}

		if ( $frm_abdn_form->is_logged_out_draft_on() && FrmProEntry::is_draft( $entry_id ) ) {
			// Don't clear if this is a logged out draft.
			return;
		}

		$previous_entry_id = $entry_id;
		$entry_id          = ( new FrmAbandonmentEncryptionHelper() )->check_entry_token( $entry_id, array() );
		$is_submitted      = is_wp_error( $entry_id ) && 'already_submitted' === $entry_id->get_error_code();

		if ( $is_submitted && ! $frm_abdn_form->is_editable() ) {
			FrmAbdnToken::unlink_token( $previous_entry_id );
		}
	}

	/**
	 * Get draft status of an entry.
	 *
	 * @since 1.1
	 *
	 * @param int|object $entry Entry ID.
	 * @return int|false
	 */
	public static function draft_status( $entry ) {
		FrmEntry::maybe_get_entry( $entry );
		if ( ! $entry ) {
			return false;
		}

		return (int) $entry->is_draft;
	}

	/**
	 * Check if the entry is in progress or abandoned.
	 *
	 * @since 1.1
	 *
	 * @param int|string $draft_status Can be '', 0, 1, 2, 3.
	 * @return bool
	 */
	public static function is_progress_or_abandoned( $draft_status ) {
		if ( (int) $draft_status === FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS ) {
			return true;
		}

		if ( (int) $draft_status === FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS ) {
			return true;
		}

		return false;
	}

	/**
	 * Unlink token from draft entries.
	 *
	 * @since 1.0
	 *
	 * @param array<string,mixed> $args {
	 *    Arguments of entry id and form.
	 *    @type int                     $entry_id Entry ID.
	 *    @type int|float|object|string $form     Form This may be a Form ID.
	 * }
	 *
	 * @return void
	 */
	public static function clean_after_save_draft( $args ) {
		if ( ! is_object( $args['form'] ) && ! is_numeric( $args['form'] ) ) {
			return;
		}

		// We only need to check the token whenever the form is saving draft.
		if ( ! FrmProFormsHelper::saving_draft() ) {
			return;
		}

		$frm_abdn_form = new FrmAbdnForm( array( 'form' => $args['form'] ) );
		if ( $frm_abdn_form->allow_logged_out_draft() ) {
			FrmAbdnToken::create_new_draft_token( $args );
			return;
		}

		if ( $frm_abdn_form->is_abandonment_enabled() && ! $frm_abdn_form->is_logged_out_draft_on() ) {
			FrmAbdnToken::unlink_token( absint( $args['entry_id'] ) );
		}
	}

	/**
	 * By default when "Abandoned" and "In Progress" turning to "Submitted" status, "Update" event would trigger.
	 * This method will trigger create event instead update.
	 *
	 * @since 1.1
	 *
	 * @param array<string> $values Updated values.
	 * @param int           $id Entry ID.
	 *
	 * @return array<string>
	 */
	public static function attach_create_event( $values, $id ) {
		$frm_abdn_form = new FrmAbdnForm( array( 'form' => (int) $values['form_id'] ) );
		if ( ! $frm_abdn_form->is_abandonment_enabled() ) {
			return $values;
		}

		add_filter( 'frm_update_entry', 'FrmAbdnEntriesController::set_update_actions', 9, 2 );

		// Only continue if we are changing the entry to submitted.
		if ( FrmEntriesHelper::SUBMITTED_ENTRY_STATUS !== (int) $values['is_draft'] ) {
			return $values;
		}

		// Only continue if the current status value is Abandoned or In-Progress.
		$is_draft = (int) FrmDb::get_var( 'frm_items', array( 'id' => $id ), 'is_draft' );
		if ( ! in_array( $is_draft, array( FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS, FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS ), true ) ) {
			return $values;
		}

		// add the create hooks since the entry is switching draft status.
		add_action( 'frm_after_update_entry', 'FrmProEntriesController::add_published_hooks', 2, 2 );

		// change created timestamp.
		$values['created_at'] = $values['updated_at'];

		return $values;
	}

	/**
	 * By default in packaging the update entry is_draft column is set to 0 but
	 * we need to keep it to 2 (in progress).
	 *
	 * @since 1.0
	 *
	 * @param array<mixed> $values Unsanitized entry values.
	 *
	 * @return void
	 */
	private function update_entry( $values ) {
		$values['action'] = $values['frm_action'];
		$entry            = FrmEntry::getOne( $this->entry_id );
		if ( FrmProEntry::is_draft_status( $entry->is_draft ) ) {
			$values['is_draft'] = $entry->is_draft;
		} else {
			// Don't set a draft to in progress.
			$values['is_draft'] = FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS;
		}

		$values['frm_saving_draft'] = $values['is_draft'];

		FrmEntry::update( $this->entry_id, $values );
	}

	/**
	 * Process entry to submit and remove create trigger action hook and add the abandoned trigger action and modifying is_draft.
	 *
	 * @since 1.0
	 *
	 * @return false|int The entry id or false if the entry was not created.
	 */
	private function insert_entry() {
		$_POST['action'] = 'create'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Remove create actions.
		remove_action( 'frm_after_create_entry', 'FrmFormActionsController::trigger_create_actions', 20 );
		add_action( 'frm_after_create_entry', array( $this, 'after_create_abandonment_entry' ), 1, 3 );

		$this->force_message_on_success();

		FrmEntriesController::process_entry( '', true );
		$form_id = $this->form_id ? $this->form_id : FrmAppHelper::get_post_param( 'form_id', '', 'absint' );

		global $frm_vars;
		if ( empty( $frm_vars['created_entries'][ $form_id ]['entry_id'] ) ) {
			return false;
		}

		$this->entry_id = absint( $frm_vars['created_entries'][ $form_id ]['entry_id'] );
		FrmAbdnToken::maybe_create_token( $this->entry_id );

		return $this->entry_id;
	}

	/**
	 * Make sure that the success filter is NOT set to redirect.
	 * If it is, no JSON response is sent on form updates.
	 * This causes issues with multiple entries because of no entry ID in the response.
	 *
	 * @since 1.1.1
	 *
	 * @return void
	 */
	private function force_message_on_success() {
		add_filter(
			'frm_success_filter',
			function () {
				return 'message';
			}
		);
	}

	/**
	 * Exclude fields to store on abandoned entry.
	 *
	 * @since 1.0
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	private function exclude_field_to_store( $form_id ) {
		/**
		 * Allows exclude the abandonment fields from storing.
		 *
		 * @since 1.0
		 *
		 * @param array $excluded_fields Excluded fields type.
		 */
		$excluded_fields = apply_filters( 'frm_abandonment_exclude_field_types', array( 'password', 'credit_card' ) );

		if ( ! is_array( $excluded_fields ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Please return an array of field types to exclude from saving.', 'formidable-abandonment' ), '1.0' );
			$excluded_fields = array( 'password', 'credit_card' );
		}

		$form_fields = FrmField::getAll(
			array(
				'fi.type not' => FrmField::no_save_fields(),
				'fi.form_id'  => $form_id,
			),
			'field_order'
		);

		foreach ( $form_fields as $field ) {
			if ( in_array( $field->type, $excluded_fields, true ) ) {
				unset( $_POST['item_meta'][ $field->id ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
	}

	/**
	 * Block Google cache by checking the hostname based on IP.
	 * When a link is clicked from Gmail, a Google bot will visit the page.
	 * This prevents the form from being submitted.
	 *
	 * @since 1.1.6
	 *
	 * @param array<string> $errors Error messages.
	 * @param array<mixed>  $values Entry values.
	 *
	 * @return array<string>
	 */
	public static function block_google_cache( $errors, $values ) {
		if ( ! function_exists( 'gethostbyaddr' ) || ! empty( $errors ) ) {
			return $errors;
		}

		if ( ! isset( $values['form_id'] ) || ! is_numeric( $values['form_id'] ) ) {
			return $errors;
		}

		$frm_abdn_form = new FrmAbdnForm( array( 'form' => (int) $values['form_id'] ) );
		if ( ! $frm_abdn_form->is_plugin_used() ) {
			return $errors;
		}

		/**
		 * Allow people to opt-out of doing the reverse DNS lookup.
		 *
		 * @since 1.1.6
		 *
		 * @param bool $block Whether to block Google cache.
		 */
		if ( ! apply_filters( 'frm_abandonment_block_google_cache', true ) ) {
			return $errors;
		}

		$ip = FrmAppHelper::get_ip_address();
		if ( ! $ip ) {
			return $errors;
		}

		$hostname = gethostbyaddr( $ip );
		if ( 'cache.google.com' === $hostname ) {
			$errors['spam'] = 'Google Bot detected';
		}

		return $errors;
	}
}
