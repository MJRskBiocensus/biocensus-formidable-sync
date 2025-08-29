<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrmAbandonmentActionController
 *
 * @since 1.0
 *
 * @package formidable-abandonment
 */

/**
 * Handling action related methods for abandoned activated forms.
 *
 * @since 1.0
 */
class FrmAbandonmentActionController {

	/**
	 * Requested action for email could be abandoned, draft, submitted.
	 *
	 * @since 1.1
	 * @var string
	 */
	private $requested_action = '';

	/**
	 * Form id.
	 *
	 * @since 1.1
	 * @var int
	 */
	private $form_id = 0;

	/**
	 * Action Post title.
	 *
	 * @since 1.1
	 * @var string
	 */
	private $post_title = '';

	/**
	 * Action subject.
	 *
	 * @since 1.1
	 * @var string
	 */
	private $email_subject = '';

	/**
	 * Action email message.
	 *
	 * @since 1.1
	 * @var string
	 */
	private $email_message = '';

	/**
	 * After triggering email action with "frm_add_form_action" this method will modify the created email action contents for abandoned form.
	 * Customizing email message contains submitted abandoned entry edit link.
	 *
	 * @since 1.0
	 *
	 * @param WP_Post $email_action Email action content.
	 *
	 * @return WP_Post
	 */
	public function add_customized_email_action( $email_action ) {
		// Nonce is verified before @frm_add_form_action.
		// We are checking the abandonment_form_action to ensure this ajax triggered by quick create a email action button.
		$this->form_id           = FrmAppHelper::get_post_param( 'form_id', 0, 'absint' );
		$this->requested_action  = FrmAppHelper::get_post_param( 'abandonment_form_action', '', 'sanitize_text_field' );

		if ( empty( $this->requested_action ) || ! $this->form_id ) {
			return $email_action;
		}

		$allowed_actions = array(
			'abandoned',
			'create',
			'draft',
		);

		if ( ! in_array( $this->requested_action, $allowed_actions, true ) ) {
			return $email_action;
		}

		return $this->configure_email_action( $email_action );
	}

	/**
	 * Configure email settings based on requested action.
	 *
	 * @since 1.1
	 *
	 * @param WP_Post $email_action Email action content.
	 *
	 * @return WP_Post
	 */
	private function configure_email_action( $email_action ) {
		$first_email_field = '';
		$email_field       = FrmField::get_all_types_in_form( $this->form_id, 'email', 1, 'include' );
		if ( ! empty( $email_field ) ) {
			$first_email_field = '[' . $email_field->id . ']';
		}

		$this->customize_action_strings();

		/* @phpstan-ignore-next-line */
		$email_action->post_content['email_to']      = $first_email_field;
		$email_action->post_content['event']         = array( $this->requested_action );
		$email_action->post_content['email_subject'] = $this->email_subject;
		$email_action->post_content['email_message'] = $this->email_message;
		$email_action->post_title                    = $this->post_title;

		return $email_action;
	}

	/**
	 * Set string based on requested actions "abandoned", "entry_edit", 'draft" for email action.
	 *
	 * @since 1.1
	 *
	 * @return void
	 */
	private function customize_action_strings() {
		$action_strings = array(
			'abandoned' => array(
				'post_title' => esc_html__( 'Abandoned Form Recovery', 'formidable-abandonment' ),
				'email_subject' => sprintf(
					/* translators: %1$s: Site name shortcode */
					esc_html__( 'Your submission on %1$s is incomplete!', 'formidable-abandonment' ),
					'[sitename]'
				),
				'email_message' => sprintf(
					/* translators: %1$s: Abandoned entry link shortcode */
					esc_html__( 'Hello there, It looks like you may have started a form that has not yet been completed. Here is a link for you to pick up right where you left off: %1$s', 'formidable-abandonment' ),
					"\n" . '[frm-signed-edit-link id=[id]]'
				),
			),
			'create' => array(
				'post_title' => esc_html__( 'Send Edit Entry Link', 'formidable-abandonment' ),
				'email_subject' => sprintf(
					/* translators: %1$s: Site name shortcode */
					esc_html__( 'Edit your submission: %1$s access link', 'formidable-abandonment' ),
					'[sitename]'
				),
				'email_message' => sprintf(
					/* translators: %1$s: Abandoned entry link shortcode, %2$s the edit link */
					esc_html__( 'Thank you for submitting your form on %1$s. We understand that you might need to update or revise your submission. To make this process easy and convenient, we have provided a direct link for you to return and edit your submission at any time. %2$sPlease note that the link above will take you directly to your form. You can edit and resubmit your information as needed.', 'formidable-abandonment' ),
					'[sitename]',
					"\n\n" . '[frm-signed-edit-link id=[id]]' . "\n\n"
				) .
				"\n\n" . esc_html__( 'If you have any questions, please contact us.', 'formidable-abandonment' ) .
				"\n\n" . esc_html__( 'Best regards,', 'formidable-abandonment' ) . "\n" . '[sitename]',
			),
		);

		$action_strings['draft']               = $action_strings['abandoned'];
		$action_strings['draft']['post_title'] = esc_html__( 'Save and Resume', 'formidable-abandonment' );

		$current_action      = $action_strings[ $this->requested_action ];
		$this->post_title    = $current_action['post_title'];
		$this->email_subject = $current_action['email_subject'];
		$this->email_message = $current_action['email_message'];
	}

	/**
	 * Add the abandoned action trigger to actions.
	 *
	 * @since 1.0
	 *
	 * @param array<string> $triggers Array of event triggers.
	 *
	 * @return array<string>
	 */
	public static function add_abandoned_trigger( $triggers ) {
		$triggers['abandoned'] = esc_html__( 'Entry is abandoned', 'formidable-abandonment' );
		return $triggers;
	}

	/**
	 * Add the abandoned action trigger to email action.
	 *
	 * @since 1.0
	 *
	 * @param array<array<string>> $settings Array of event triggers.
	 *
	 * @return array<array<string>|int>
	 */
	public static function email_action_control( $settings ) {
		if ( ! in_array( 'abandoned', $settings['event'], true ) ) {
			$settings['event'][] = 'abandoned';
		}

		return $settings;
	}

	/**
	 * Trigger abandonment action.
	 *
	 * @since 1.0
	 *
	 * @param int $entry_id Entry id.
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	public static function trigger_abandonment_actions( $entry_id, $form_id ) {
		FrmFormActionsController::trigger_actions( 'abandoned', $form_id, $entry_id );
	}

	/**
	 * Prepare and trigger abandoned action.
	 *
	 * @since 1.0
	 *
	 * @param bool         $skip If the form action should be skipped.
	 * @param array<mixed> $args {
	 *   Array of args.
	 *   @type array      $action
	 *   @type object|int $entry
	 *   @type object     $form
	 *   @type string     $event
	 * }
	 *
	 * @return boolean
	 */
	public static function maybe_skip_action( $skip, $args = array() ) {
		if ( $skip ) {
			return $skip;
		}

		$entry = $args['entry'];
		FrmEntry::maybe_get_entry( $entry );
		if ( ! $entry || ! $entry->is_draft ) {
			return $skip;
		}

		if ( FrmAbandonmentAppHelper::IN_PROGRESS_ENTRY_STATUS === $entry->is_draft ) {
			// Always skip in progress entries.
			$skip = true;
		} elseif ( FrmAbandonmentAppHelper::ABANDONED_ENTRY_STATUS === $entry->is_draft && 'abandoned' !== $args['event'] ) {
			// Skip abandoned entries if the trigger is not abandoned.
			$skip = true;
		}

		return self::maybe_skip_edit_link_action( $skip, $args );
	}

	/**
	 * Skip the email action whenever logged in or logged out users selected in options
	 * but the entry submitter doesn't match the user type.
	 *
	 * @since 1.1
	 *
	 * @param bool         $skip If the form action should be skipped.
	 * @param array<mixed> $args {
	 *   Array of args.
	 *   @type object     $action
	 *   @type object|int $entry
	 *   @type object     $form
	 *   @type string     $event
	 * }
	 *
	 * @return boolean
	 */
	public static function maybe_skip_edit_link_action( $skip, $args = array() ) {
		if ( $skip || ! is_object( $args['action'] ) || 'email' !== $args['action']->post_excerpt ) {
			return $skip;
		}

		if ( ! has_shortcode( $args['action']->post_content['email_message'], 'frm-signed-edit-link' ) ) {
			return $skip;
		}

		$frm_abdn_form = new FrmAbdnForm( array( 'form' => $args['form'] ) );
		$editable_role = $frm_abdn_form->get_form_option( 'editable_role' );

		if ( ! $frm_abdn_form->is_editable() || empty( $editable_role ) || ! is_array( $editable_role ) ) {
			return $skip;
		}

		$logged_in = is_user_logged_in();
		if ( ! $logged_in && ! $frm_abdn_form->is_logged_out_edit_on() ) {
			$skip = true;
		}

		if ( $logged_in && ! in_array( '', $editable_role, true ) ) {
			$skip = true;
		}

		return $skip;
	}
}
