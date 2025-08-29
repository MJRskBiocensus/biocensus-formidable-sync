<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 3.0
 */
class FrmProFieldCaptcha extends FrmFieldCaptcha {

	/**
	 * The nonce value for the captcha nonce field (used to prevent multiple
	 * challenges and bugs where the CAPTCHA field is on another page).
	 *
	 * @var string|null
	 */
	private static $checked;

	protected function field_settings_for_type() {
		$settings = parent::field_settings_for_type();

		FrmProFieldsHelper::fill_default_field_display( $settings );
		return $settings;
	}

	public function front_field_input( $args, $shortcode_atts ) {
		if ( self::checked() ) {
			return '';
		}

		return parent::front_field_input( $args, $shortcode_atts );
	}

	/**
	 * @since 4.07
	 * @param array $args
	 * @return array
	 */
	public function validate( $args ) {
		if ( ! $this->should_validate() ) {
			return array();
		}

		if ( ! is_callable( array( $this, 'validate_against_api' ) ) ) {
			return parent::validate( $args );
		}

		if ( is_callable( self::class . '::post_data_includes_token' ) ) {
			$post_data_includes_token = self::post_data_includes_token();
		} else {
			// Legacy fallback.
			$post_data_includes_token = ! empty( $_POST['g-recaptcha-response'] ) || ! empty( $_POST['h-captcha-response'] );
		}

		if ( $post_data_includes_token ) {
			$errors = $this->validate_against_api( $args );

			if ( $errors ) {
				return $errors;
			}

			$this->maybe_set_captcha_score_in_form_state();

			if ( self::should_include_captcha_nonce_field() ) {
				self::$checked = wp_create_nonce( 'frm_captcha' );
			}

			return array();
		}

		if ( self::validate_checked() ) {
			$this->maybe_pull_captcha_score_from_form_state();
			return array();
		}

		$error = $this->maybe_change_error_message( __( 'The captcha is missing from this form', 'formidable' ) );

		return array( 'field' . $args['id'] => $error );
	}

	/**
	 * @since 6.22
	 *
	 * @param string $error The original error message.
	 *
	 * @return string
	 */
	private function maybe_change_error_message( $error ) {
		if ( ! current_user_can( 'frm_edit_forms' ) || self::should_include_captcha_nonce_field() ) {
			return $error;
		}

		$form_id     = is_object( $this->field ) ? $this->field->form_id : $this->field['form_id'];
		$page_breaks = FrmProFormsHelper::has_field( 'break', $form_id, false );

		if ( ! $page_breaks || FrmProFormsHelper::has_another_page( $form_id ) ) {
			return $error;
		}

		$last_page_break  = end( $page_breaks );
		$this_field_order = is_object( $this->field ) ? $this->field->field_order : $this->field['field_order'];

		if ( (int) $last_page_break->field_order < (int) $this_field_order ) {
			return $error;
		}

		return __( 'The CAPTCHA field is currently on an incorrect page. Move the CAPTCHA field to the last page of the form.', 'formidable-pro' );
	}

	/**
	 * As the reCAPTCHA is no longer validated once we use the recaptcha_checked nonce, set the score in the state field.
	 *
	 * @since 6.2
	 *
	 * @return void
	 */
	private function maybe_set_captcha_score_in_form_state() {
		global $frm_vars;
		$form_id = $this->get_form_id();
		if ( ! empty( $frm_vars['captcha_scores'][ $form_id ] ) ) {
			FrmProFormState::set_initial_value( 'captcha', $frm_vars['captcha_scores'][ $form_id ] );
		}
	}

	/**
	 * Get the captcha score from the state and put it back into the $frm_vars['captcha_scores'] global.
	 *
	 * @since 6.2
	 *
	 * @return void
	 */
	private function maybe_pull_captcha_score_from_form_state() {
		$score = FrmProFormState::get_from_request( 'captcha', false );
		if ( ! is_numeric( $score ) ) {
			return;
		}

		global $frm_vars;
		if ( ! isset( $frm_vars['captcha_scores'] ) ) {
			$frm_vars['captcha_scores'] = array();
		}
		$frm_vars['captcha_scores'][ $this->get_form_id() ] = $score;
	}

	/**
	 * @since 6.2
	 *
	 * @return int
	 */
	private function get_form_id() {
		$form_id = is_object( $this->field ) ? $this->field->form_id : $this->field['form_id'];
		return (int) $form_id;
	}

	/**
	 * Check the captcha nonce.
	 *
	 * @since 4.07
	 *
	 * @return false|string A nonce string on successful validation. False otherwise.
	 */
	private static function validate_checked() {
		if ( ! self::should_include_captcha_nonce_field() ) {
			return false;
		}

		if ( isset( $_POST['recaptcha_checked'] ) ) {
			$nonce = FrmAppHelper::get_param( 'recaptcha_checked', '', 'post', 'sanitize_text_field' );
			if ( wp_verify_nonce( $nonce, 'frm_captcha' ) ) {
				self::$checked = wp_create_nonce( 'frm_captcha' );
				return self::$checked;
			}
		}

		return false;
	}

	/**
	 * @since 4.07
	 * @return false|string
	 */
	public static function checked() {
		if ( isset( self::$checked ) ) {
			return self::$checked;
		}

		// pass along recaptcha_checked even if there is no captcha being validated
		// (which would happen if we're going to a previous page without a captcha)
		return self::validate_checked();
	}

	/**
	 * Check if CAPTCHA data is being sent in the POST request.
	 *
	 * @since 4.07
	 *
	 * @return bool
	 */
	public static function posting_captcha_data() {
		// First check the nonce field (if it is enabled).
		if ( ! empty( $_POST['recaptcha_checked'] ) && self::should_include_captcha_nonce_field() ) {
			return true;
		}

		// Check for the CAPTCHA token.
		// This function was added in v6.8.4 (released Mar 27, 2024).
		if ( is_callable( self::class . '::post_data_includes_token' ) ) {
			return self::post_data_includes_token();
		}

		return ! empty( $_POST['g-recaptcha-response'] );
	}

	/**
	 * Maybe render a hidden input with a nonce value.
	 * This is checked instead of the captcha token when it is submitted.
	 * The nonce is generated after the user successfully validates a CAPTCHA.
	 *
	 * @since 4.07
	 * @since 6.17 It is now possible to opt-out of this using the frm_should_include_captcha_nonce_field filter.
	 *
	 * @return void
	 */
	public static function render_checked_response() {
		if ( ! self::should_include_captcha_nonce_field() ) {
			// The nonce field is disabled, so we never want to render the input.
			return;
		}

		global $frm_vars;
		$is_in_place_edit = ! empty( $frm_vars['inplace_edit'] );
		if ( $is_in_place_edit ) {
			self::$checked = wp_create_nonce( 'frm_captcha' );
		}
		if ( self::posting_captcha_data() || $is_in_place_edit ) {
			$checked = self::checked();
			if ( $checked ) {
				?>
				<input type="hidden" name="recaptcha_checked" value="<?php echo esc_attr( $checked ); ?>" />
				<?php
			}
		}
	}

	/**
	 * Check if the user has opted out fo the captcha nonce field.
	 * The CAPTCHA nonce field is added to prevent multiple challenges.
	 * But it also means that the nonce can be reused to prevent CAPTCHA validation.
	 *
	 * @since 6.17
	 * @since 6.20 This function was made public.
	 *
	 * @return bool
	 */
	public static function should_include_captcha_nonce_field() {
		/**
		 * @since 6.17
		 * @since 6.20 This was changed from true to false by default. It makes sites too vulnerable to spam.
		 *
		 * @param bool $should_include False by default. Set to true to include the captcha nonce field when necessary.
		 */
		return (bool) apply_filters( 'frm_should_include_captcha_nonce_field', false );
	}
}
