<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrmAbandonmentObserver
 *
 * @since 1.0
 *
 * @package formidable-abandonment
 */

/**
 * Observe called forms and fetch the necessary data for abandonment.
 *
 * @since 1.0
 */
class FrmAbandonmentObserverController {

	/**
	 * Collected forms with frm_pre_get_form hook.
	 *
	 * @since 1.0
	 *
	 * @var array<object> $forms
	 */
	private $forms = array();

	/**
	 * Class holder.
	 *
	 * @since 1.0
	 *
	 * @var FrmAbandonmentObserverController|null $instance
	 */
	private static $instance = null;

	/**
	 * Private constructor used in singleton.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Singleton pattern to prevent direct access we need to ensure this class initiated only once.
	 *
	 * @since 1.0
	 *
	 * @return FrmAbandonmentObserverController
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Collect form and store it on the property for later use.
	 *
	 * @since 1.0
	 *
	 * @param object $form Form object.
	 *
	 * @return void
	 */
	public function register_observer( $form ) {
		$this->forms[] = $form;
	}

	/**
	 * Append abandonment css to the FF css on save.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	public function append_css() {
		include FrmAbandonmentAppHelper::plugin_path() . '/assets/css/abandonment.css';
	}

	/**
	 * Enqueue abandonment js for front end in case there are enabled form exist.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Return on ajax requests except the preview page.
		if ( FrmAppHelper::doing_ajax() ) {
			return;
		}

		// Don't load JS when there is no activated form on a page.
		if ( empty( $this->forms ) ) {
			return;
		}

		// Get form ids which abandonment is activated on.
		$localize_script = array();
		$form_ids        = self::abandonment_form_ids();
		if ( $form_ids ) {
			$localize_script['formSettings'] = $form_ids;
		}

		$logged_out = self::logged_out_draft_forms();
		if ( $logged_out ) {
			$localize_script['loggedOutDraft'] = $logged_out;
		}

		if ( empty( $localize_script ) ) {
			return;
		}

		$localize_script['timeFormat']   = get_option( 'time_format' );
		$localize_script['translatable'] = self::js_strings( $logged_out );

		wp_register_script( 'formidable-abandoned', FrmAbandonmentAppHelper::use_minified_js_file( 'front' ), array( 'formidable' ), FrmAbandonmentAppHelper::plugin_version(), true );

		wp_localize_script(
			'formidable-abandoned',
			'frmAbdn',
			$localize_script
		);

		wp_enqueue_script( 'formidable-abandoned' );
	}

	/**
	 * Prepare translatable strings for JS.
	 *
	 * @since 1.1
	 *
	 * @param array<int, array<mixed>> $forms Forms for logged out drafts.
	 *
	 * @return array<string>
	 */
	private static function js_strings( $forms ) {
		$strings = array(
			'close'     => esc_html__( 'Close', 'formidable-abandonment' ),
			'title'     => esc_html__( 'Just one more step', 'formidable-abandonment' ),
			'label'     => esc_html__( 'Email address', 'formidable-abandonment' ),
			'content'   => esc_html__( 'Enter your email address to save this form as a draft. Drafts cannot be saved without a valid email address.', 'formidable-abandonment' ),
			'error'     => esc_html__( 'Please enter correct email', 'formidable-abandonment' ),
			'button'    => esc_html__( 'Save Draft', 'formidable-abandonment' ),
			'autoSaved' => esc_html__( 'Auto saved at', 'formidable-abandonment' ),
		);

		if ( $forms ) {
			$first_form = reset( $forms );
			if ( isset( $first_form['button'] ) ) {
				$strings['button'] = $first_form['button'];
			}
		}

		$filtered_strings = apply_filters( 'frm_abandonment_strings', $strings );
		if ( is_array( $filtered_strings ) ) {
			$strings = $filtered_strings;
		} else {
			_doing_it_wrong( __METHOD__, esc_html__( 'Please return an array of strings.', 'formidable-abandonment' ), '1.1.1' );
		}

		return $strings;
	}

	/**
	 * Prepared enabled abandoned form ids and settings.
	 *
	 * @since 1.0
	 *
	 * @return array<mixed>
	 */
	private function abandonment_form_ids() {
		$observable_form_ids = array();

		// Check if abandonment or auto save draft is enabled for a form.
		foreach ( $this->forms as $k => $form ) {
			$js_values = array(
				'form_id' => $form->id,
			);

			$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
			$this->add_abandonment_js( $frm_abdn_form, $js_values );
			$this->add_autosave_js( $frm_abdn_form, $js_values );

			if ( count( $js_values ) > 1 ) {
				$observable_form_ids[ $k ] = $js_values;
			}
		}

		return $observable_form_ids;
	}

	/**
	 * Include the form id when abandonment is enabled.
	 *
	 * @since 1.1
	 *
	 * @param FrmAbdnForm  $frm_abdn_form Form object.
	 * @param array<mixed> $js_values     JS values for this form.
	 * @return void
	 */
	private function add_abandonment_js( $frm_abdn_form, &$js_values ) {
		if ( ! $frm_abdn_form->is_abandonment_enabled() ) {
			return;
		}

		$js_values['enable_abandon'] = true;

		if ( ! $frm_abdn_form->is_email_required() ) {
			return;
		}

		$observable_fields = self::get_observable_fields( $frm_abdn_form->get_form_id() );
		if ( $observable_fields ) {
			$js_values['abandon_email_required'] = true;
			$js_values['observable_fields']      = $observable_fields;
		}
	}

	/**
	 * Include the auto draft save when auto save draft is enabled.
	 *
	 * @since 1.1
	 *
	 * @param FrmAbdnForm  $frm_abdn_form Form object.
	 * @param array<mixed> $js_values     JS values for this form.
	 * @return void
	 */
	private function add_autosave_js( $frm_abdn_form, &$js_values ) {
		global $frm_vars;
		if ( ! $frm_abdn_form->is_auto_save_on() ) {
			return;
		}

		$js_values['is_draft'] = false;
		if ( empty( $frm_vars['editing_entry'] ) ) {
			return;
		}

		$entry = FrmEntry::getOne( $frm_vars['editing_entry'] );
		if ( (int) $entry->form_id === $frm_abdn_form->get_form_id() ) {
			$js_values['is_draft'] = true;
		}
	}

	/**
	 * Prepare form ids for enabled draft for logged out visitors.
	 *
	 * @since 1.1
	 *
	 * @return array<int, array<mixed>>
	 */
	private function logged_out_draft_forms() {
		$observable_form_ids = array();

		if ( is_user_logged_in() ) {
			return $observable_form_ids;
		}

		// Check if logged out draft is enabled.
		foreach ( $this->forms as $k => $form ) {
			$frm_abdn_form = new FrmAbdnForm( compact( 'form' ) );
			if ( ! $frm_abdn_form->allow_logged_out_draft() ) {
				continue;
			}

			$save_label = ! empty( $form->options['draft_label'] ) ? $form->options['draft_label'] : esc_html__( 'Save Draft', 'formidable-abandonment' );
			$observable_form_ids[ $k ] = array(
				'form_id' => $form->id,
				'button'  => $save_label,
			);

			/**
			 * Enable/Disable modal for logged-out roles to require email field however whenever there is no
			 * field on any actions available for draft event and content has no value with frm-signed-edit-link shortcode
			 * modal won't display. To disable the modal for all form you could use following filter add_filter( 'frm_abandonment_logged_out_modal', '__return_false' );
			 *
			 * @since 1.1
			 *
			 * @param int $form Form object.
			 */
			$logged_out_modal = (bool) apply_filters( 'frm_abandonment_logged_out_modal', true, $form );
			if ( ! $logged_out_modal ) {
				continue;
			}

			$observable_fields = self::get_observable_field_from_actions( $form->id );
			if ( $observable_fields ) {
				$observable_form_ids[ $k ]['observable_fields'] = $observable_fields;
			}
		}

		return $observable_form_ids;
	}

	/**
	 * When a phone or email is required to save a partial entry,
	 * this method will extract phone and email fields of a form.
	 *
	 * @since 1.0
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array<int>|false
	 */
	private static function get_observable_fields( $form_id ) {
		$fields = FrmField::get_all_types_in_form( $form_id, 'email', '', 'include' );
		$fields = array_merge( $fields, FrmField::get_all_types_in_form( $form_id, 'phone', '', 'include' ) );

		$observable_fields = array_values( wp_list_pluck( $fields, 'id' ) );
		$observable_fields = array_map( 'intval', $observable_fields );

		return $observable_fields ? $observable_fields : false;
	}

	/**
	 * Search for form actions to get "email_to" field id.
	 * This method will return the first email action with "draft" event and possible "email_to" field id.
	 *
	 * @since 1.1
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array<int>|false
	 */
	private static function get_observable_field_from_actions( $form_id ) {
		if ( is_user_logged_in() ) {
			return false;
		}

		$email_actions = FrmFormAction::get_action_for_form( $form_id, 'email' );

		if ( ! $email_actions ) {
			return false;
		}

		$email_field_ids = array();
		foreach ( $email_actions as $action ) {
			// Search only for "draft" event.
			if ( ! in_array( 'draft', $action->post_content['event'], true ) || empty( $action->post_content['email_to'] ) ) {
				continue;
			}

			if ( ! has_shortcode( $action->post_content['email_message'], 'frm-signed-edit-link' ) ) {
				continue;
			}

			$email_to = FrmFieldsHelper::get_shortcodes( $action->post_content['email_to'], $form_id );

			foreach ( $email_to[0] as $short_key => $tag ) {
				if ( empty( $tag ) ) {
					continue;
				}

				$tag = FrmShortcodeHelper::get_shortcode_tag( $email_to, $short_key );

				// Skip [admin_email], etc and get the field id only.
				if ( is_numeric( $tag ) ) {
					$email_field_ids[] = (int) $tag;
					continue;
				}

				if ( $tag === 'admin_email' ) {
					continue;
				}

				// If it's s field key, switch to the field id.
				$id = FrmField::get_id_by_key( $tag );
				if ( $id ) {
					$email_field_ids[] = (int) $id;
				}
			}

			// If email field id collected within current action we cut off the further search.
			if ( ! empty( $email_field_ids ) ) {
				break;
			}
		}

		return $email_field_ids ? $email_field_ids : false;
	}
}
