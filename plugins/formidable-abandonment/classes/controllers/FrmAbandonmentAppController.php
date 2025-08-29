<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class FrmAbandonmentAppController
 *
 * @since 1.0
 *
 * @package formidable-abandonment
 */

/**
 * App controller to manage general services of plugin.
 *
 * @since 1.0
 */
class FrmAbandonmentAppController {

	/**
	 * Shows the incompatible notice.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function show_incompatible_notice() {
		if ( FrmAbandonmentAppHelper::is_compatible() ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'You are running an outdated version of Formidable Forms.', 'formidable-abandonment' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Initializes plugin translation.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function init_translation() {
		load_plugin_textdomain( 'formidable-abandonment', false, FrmAbandonmentAppHelper::plugin_folder() . '/languages/' );
	}

	/**
	 * Includes addon updater.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			FrmAbandonmentUpdate::load_hooks();
		}
	}

	/**
	 * Tie into the main stylesheet updater. This is triggered when Formidable is updated
	 * and when the form css is loaded.
	 *
	 * @since 1.1
	 *
	 * @param bool $needs_upgrade - True if the stylesheet should be updated.
	 *
	 * @return bool - True if needs upgrade.
	 */
	public static function needs_upgrade( $needs_upgrade = false ) {
		if ( $needs_upgrade ) {
			return $needs_upgrade;
		}

		$db = new FrmAbdnDb();
		return $db->need_to_migrate();
	}

	/**
	 * The Formidable update is running. Tie into it.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	public static function trigger_upgrade() {
		$db = new FrmAbdnDb();
		$db->maybe_migrate();
	}

	/**
	 * Adds "In Progress" and "Abandoned" to views filter.
	 *
	 * @since 1.0
	 *
	 * @param array<string> $options Entry statuses.
	 *
	 * @return array<string>
	 */
	public static function add_entry_status_views_filter_options( $options ) {
		$options[ FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS ] = __( 'In Progress', 'formidable-abandonment' );
		$options[ FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS ]   = __( 'Abandoned', 'formidable-abandonment' );

		return $options;
	}

	/**
	 * Add more entry statuses to the ones provided.
	 *
	 * @since 1.0
	 *
	 * @param array<string> $statuses Entry statuses.
	 *
	 * @return array<string>
	 */
	public static function add_entry_status( $statuses ) {
		// "2" is reserved for in progress.
		$statuses[ FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS ] = __( 'In Progress', 'formidable-abandonment' );
		// "3" is reserved for abandonment.
		$statuses[ FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS ] = __( 'Abandoned', 'formidable-abandonment' );

		return $statuses;
	}

	/**
	 * Enqueue assets for settings form and builder page.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets() {
		// Enqueue style.
		wp_enqueue_style( 'formidable-abandonment-admin', FrmAbandonmentAppHelper::plugin_url() . '/assets/css/admin.css', array(), FrmAbandonmentAppHelper::plugin_version() );

		if ( ! self::is_form_settings_page() ) {
			return;
		}

		// Enqueue script.
		wp_enqueue_script( 'formidable-abandonment-admin', FrmAbandonmentAppHelper::use_minified_js_file( 'admin' ), array( 'formidable_dom', 'formidable_admin' ), FrmAbandonmentAppHelper::plugin_version(), true );
	}

	/**
	 * Handle ajax to reset token.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	public static function reset_token() {
		FrmAppHelper::permission_check( 'frm_edit_entries' );
		check_ajax_referer( 'frm_ajax', 'nonce' );

		$entry_id = FrmAppHelper::get_post_param( 'entry_id', 0, 'absint' );

		if ( ! $entry_id ) {
			wp_send_json_error();
		}

		$token = FrmAbdnToken::reset_token( $entry_id );

		if ( is_wp_error( $token ) ) {
			wp_send_json_error();
		}

		$token_link = FrmAbdnEntriesController::entry_edit_link_shortcode(
			array(
				'id'    => $entry_id,
				'label' => '',
			)
		);

		$token_label = esc_html( substr( urlencode( base64_encode( $token ) ), 0, 20 ) );

		$response = array(
			'token_link'  => $token_link,
			'token_label' => $token_label,
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle ajax of insert, update sanitization and validation of abandoned entry.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function maybe_insert_abandoned_entry() {
		// Check if ajax pointer is defined.
		if ( ! FrmAppHelper::doing_ajax() ) {
			wp_die( esc_html__( 'Invalid request.', 'formidable-abandonment' ) );
		}

		$form_id = FrmAppHelper::get_post_param( 'form_id', 0, 'absint' );
		if ( ! $form_id ) {
			wp_send_json_error( esc_html__( 'Invalid form id.', 'formidable-abandonment' ), 400 );
		}

		$errors = self::validate_abandonment_entry();
		// We only need to verify entry is not a spam except recaptcha field which is not our concern to check.
		if ( isset( $errors['spam'] ) ) {
			wp_send_json_error( esc_html__( 'Spam entry.', 'formidable-abandonment' ), 400 );
		}

		$entry_id     = FrmAppHelper::get_post_param( 'id', 0, 'absint' );
		$draft_status = false;
		if ( $entry_id ) {
			$draft_status = FrmAbdnEntry::draft_status( $entry_id );
			self::maybe_skip_draft_entry( $entry_id, $draft_status );
		}

		self::save_entry( compact( 'form_id', 'draft_status', 'entry_id' ) );
	}

	/**
	 * Let the main plugin handle draft entries.
	 *
	 * @since 1.1
	 *
	 * @param int       $entry_id     The entry id.
	 * @param int|false $draft_status The draft status.
	 * @return void
	 */
	private static function maybe_skip_draft_entry( $entry_id, $draft_status ) {
		$auto_save_draft = FrmAppHelper::get_post_param( 'auto_save', 0, 'absint' );
		if ( ! FrmProEntry::is_draft_status( $draft_status ) || $auto_save_draft ) {
			// Allow in progress, abandoned, or auto save entries to be updated.
			return;
		}

		// If we get here, the entry is a draft or already published.
		wp_send_json_success(
			array(
				'status' => 'complete',
			)
		);
	}

	/**
	 * Create a new entry.
	 *
	 * @since 1.1
	 *
	 * @param array<int|bool> $atts {
	 *   Variables to pass.
	 *   @type int $form_id  The form id.
	 *   @type int $entry_id The entry id if editing.
	 * }
	 * @return void
	 */
	private static function save_entry( $atts ) {
		$abandon_entries = new FrmAbdnEntry( $atts );
		$new_entry_id    = $abandon_entries->submit_entry();
		if ( ! $new_entry_id ) {
			wp_send_json_error( esc_html__( 'Error saving entry.', 'formidable-abandonment' ), 400 );
		}

		if ( $new_entry_id === true ) {
			// The entry was updated if there isn't an entry id.
			wp_send_json_success(
				array(
					'status' => 'update',
				)
			);
		}

		$secret = FrmAbdnToken::get_by_entry( (int) $new_entry_id );

		wp_send_json_success(
			array(
				'id'     => $new_entry_id,
				'status' => 'new',
				'secret' => $secret,
			)
		);
	}

	/**
	 * Display Edit link in logged out draft success message.
	 *
	 * @since 1.1
	 *
	 * @param string $content The success message.
	 * @param object $form    The form object.
	 * @param int    $entry_id The entry id.
	 *
	 * @return string
	 */
	public static function add_button_to_success( $content, $form, $entry_id ) {
		if ( ! is_ssl() || is_user_logged_in() || ! FrmProFormsHelper::saving_draft() ) {
			// The copy clipboard only works on SSL.
			return $content;
		}

		$logged_out_draft = FrmAppHelper::get_post_param( 'loggedout_draft', 0, 'absint' );
		if ( ! $logged_out_draft ) {
			return $content;
		}

		if ( ! self::should_show_entry_success( $entry_id ) ) {
			return $content;
		}

		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		if ( ! $frm_abdn_form->allow_logged_out_draft() ) {
			return $content;
		}

		$token_link = FrmAbdnEntriesController::entry_edit_link_shortcode(
			array(
				'id'      => $entry_id,
				'page_id' => self::get_current_page_id(),
				'label'   => '',
			)
		);
		if ( empty( $token_link ) ) {
			return $content;
		}

		$label = apply_filters(
			'frm_abandonment_copy_link_label',
			__( 'Copy Continue Link', 'formidable-abandonment' )
		);

		ob_start();
		require FrmAbandonmentAppHelper::plugin_path() . '/views/front/success-draft.php';
		$output = ob_get_clean();
		if ( ! $output ) {
			return $content;
		}

		// Replace the last '</div>' with the new content to get it in the container.
		$output = preg_replace( '/<\/div>/', $output . '</div>', $content, 1 );
		if ( $output ) {
			$content = $output;
		}

		return $content;
	}

	/**
	 * Get the page id for the resume link.
	 *
	 * @since 1.1
	 *
	 * @return int
	 */
	private static function get_current_page_id() {
		$page_id = get_queried_object_id();
		if ( $page_id ) {
			return $page_id;
		}

		// In case of ajax submit.
		$page_id = FrmAppHelper::get_post_param( 'current_page', 0, 'absint' );
		if ( $page_id ) {
			return $page_id;
		}

		return 0;
	}

	/**
	 * Check if the entry should show to copy link in success message.
	 *
	 * @since 1.1
	 *
	 * @param int $entry_id The entry id.
	 * @return bool
	 */
	private static function should_show_entry_success( $entry_id ) {
		$previous_entry_id = $entry_id;

		$entry_id = ( new FrmAbandonmentEncryptionHelper() )->check_entry_token( $entry_id );
		if ( ! $entry_id ) {
			// No secret was included.
			return false;
		}

		if ( is_wp_error( $entry_id ) ) {
			$published_or_draft = 'already_submitted' === $entry_id->get_error_code();
			$entry_id           = $previous_entry_id;
			if ( ! $published_or_draft ) {
				return false;
			}
		}

		if ( ! is_numeric( $entry_id ) || ! FrmProEntry::is_draft( $entry_id ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Prepare, Sanitize and validate entry fields.
	 *
	 * @since 1.0
	 *
	 * @return array<string>|array<bool> Sanitized value.
	 */
	private static function validate_abandonment_entry() {
		$global_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Extract data index and implement it in main post to simulate the valid entry order.
		$global_post_data = isset( $global_post['data'] ) && is_string( $global_post['data'] ) ? wp_unslash( $global_post['data'] ) : false;

		if ( ! $global_post_data ) {
			return array( 'spam' => true );
		}

		parse_str( $global_post_data, $decoded_post_data );
		$global_post = array_merge( $global_post, $decoded_post_data );

		unset( $global_post_data );
		unset( $global_post['data'] );
		unset( $global_post['create'] );

		if ( empty( $global_post['item_meta'] ) ) {
			return array( 'spam' => true );
		}

		$global_post['item_meta'] = map_deep(
			$global_post['item_meta'],
			function ( $value ) {
				$json_decoded = json_decode( $value, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					return $json_decoded;
				}
				return $value;
			}
		);

		$_POST = $global_post;

		return FrmEntryValidate::validate( wp_unslash( $global_post ) );
	}

	/**
	 * Check if the current page is formidable action.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	private static function is_form_settings_page() {
		$page   = FrmAppHelper::simple_get( 'page', 'sanitize_title' );
		$action = FrmAppHelper::simple_get( 'frm_action', 'sanitize_title' );
		$entry_page = FrmAppHelper::is_admin_page( 'formidable-entries' ) && in_array( $action, array( 'edit', 'show' ), true );
		return $entry_page || ( 'formidable' === $page && 'settings' === $action );
	}

	/**
	 * Pass the secret in a multi-page form.
	 *
	 * @since 1.1
	 *
	 * @param object $form The form object.
	 * @return void
	 */
	public static function insert_hidden_fields( $form ) {
		$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
		if ( ! $frm_abdn_form->is_plugin_used() ) {
			return;
		}

		$observer_controller = FrmAbandonmentObserverController::get_instance();
		$observer_controller->register_observer( $form );

		self::load_assets();

		if ( $frm_abdn_form->is_auto_save_on() || $frm_abdn_form->is_abandonment_enabled() ) {
			$auto_save_interval = FrmAbandonmentAppHelper::auto_save_interval();
			echo '<input type="hidden" name="auto_save" value="' . absint( $auto_save_interval ) . '"/>';
		}

		self::add_page_id_field( $frm_abdn_form );

		$posted_form = FrmAppHelper::get_post_param( 'form_id', 0, 'absint' );
		$secret      = '';
		if ( $posted_form !== (int) $form->id ) {
			// Get the secret from url if we shouldn't get it from the posted form.
			$secret = FrmAbandonmentAppHelper::get_url_token();
			if ( empty( $secret ) ) {
				return;
			}
		}

		$secret = $secret ? $secret : FrmAppHelper::get_post_param( 'secret', '', 'sanitize_text_field' );
		if ( $secret ) {
			echo '<input type="hidden" name="secret" value="' . esc_attr( $secret ) . '"/>';
		}
	}

	/**
	 * Add the current page id to the form for use in the edit draft link.
	 *
	 * @since 1.1
	 *
	 * @param FrmAbdnForm $frm_abdn_form The form object.
	 * @return void
	 */
	private static function add_page_id_field( $frm_abdn_form ) {
		if ( ! $frm_abdn_form->allow_logged_out_draft() ) {
			return;
		}

		$posted_page_id = FrmAppHelper::get_post_param( 'current_page', 0, 'absint' );
		if ( wp_doing_ajax() && $posted_page_id ) {
			$page_id = $posted_page_id;
		} else {
			$page_id = get_the_ID();
		}

		if ( $page_id ) {
			echo '<input type="hidden" name="current_page" value="' . esc_attr( $page_id ) . '"/>';
		}
	}

	/**
	 * Load assets for abandonment forms only.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	private static function load_assets() {
		$observer_controller = FrmAbandonmentObserverController::get_instance();
		add_action( 'wp_footer', array( $observer_controller, 'enqueue_assets' ) );
		self::include_draft_modal_css();
	}

	/**
	 * Success message style.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	private static function include_draft_modal_css() {
		echo '<style>';
		readfile( FrmAbandonmentAppHelper::plugin_path() . '/assets/css/modal.css' );
		echo '</style>';
	}

	/**
	 * Modifies form usage data.
	 *
	 * @since 1.1.4
	 *
	 * @param array<string,mixed>     $data Form usage data.
	 * @param array{'form': stdClass} $args Filter arguments.
	 * @return array<string,mixed>
	 */
	public static function form_usage_data( $data, $args ) {
		$form = $args['form'];
		if ( ! empty( $form->options['enable_abandonment'] ) ) {
			$data['enable_abandonment']     = 1;
			$data['abandon_email_required'] = ! empty( $form->options['abandon_email_required'] ) ? 1 : 0;
		}

		if ( ! empty( $form->options['save_draft'] ) ) {
			$data['auto_save_draft'] = ! empty( $form->options['auto_save_draft'] ) ? 1 : 0;
		}

		return $data;
	}
}
