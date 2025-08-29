<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrmAbdnForm
 *
 * @since 1.1
 *
 * @package formidable-abandonment
 */
class FrmAbdnForm {

	/**
	 * The form instance or ID.
	 *
	 * @var object
	 */
	private $form;

	/**
	 * Constructor to set the form.
	 *
	 * @param array<int|float|object|string> $atts {
	 *    Attributes for forms.
	 *    @type int|float|object|string $form Form id or object.
	 * }
	 */
	public function __construct( $atts ) {
		if ( is_numeric( $atts['form'] ) ) {
			$atts['form'] = absint( $atts['form'] );
		}
		$this->set_form( $atts['form'] );
	}

	/**
	 * Set the form property.
	 *
	 * @param int|float|object|string $form Form id or object.
	 * @return void
	 */
	private function set_form( $form ) {
		FrmForm::maybe_get_form( $form );
		$this->form = $form;
	}

	/**
	 * Get the form ID.
	 *
	 * @return int
	 */
	public function get_form_id() {
		return (int) $this->form->id;
	}

	/**
	 * Check whether abandonment is enabled for a form.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_abandonment_enabled() {
		return ! empty( $this->get_form_option( 'enable_abandonment' ) );
	}

	/**
	 * Check whether any of the settings for this plugin are used for a form.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_plugin_used() {
		return $this->is_abandonment_enabled() ||
			$this->is_auto_save_on() ||
			$this->is_logged_out_draft_on();
	}

	/**
	 * Check whether email is required for a form.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_email_required() {
		return ! empty( $this->get_form_option( 'abandon_email_required' ) );
	}

	/**
	 * Check whether a form is editable.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_editable() {
		return $this->form->editable ? true : false;
	}

	/**
	 * Check whether logged out editing is enabled for a form.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_logged_out_edit_on() {
		$roles = $this->get_form_option( 'editable_role' );
		return $this->is_role_in_array( 'loggedout', $roles );
	}

	/**
	 * Check whether save draft is enabled for a form.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_save_draft_on() {
		return ! empty( $this->get_form_option( 'save_draft' ) ) && ! empty( $this->get_form_option( 'edit_draft_role' ) );
	}

	/**
	 * Are logged out drafts turned on.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_logged_in_draft_on() {
		return $this->is_save_draft_on() && $this->in_save_draft_roles( '' );
	}

	/**
	 * Are logged out drafts turned on.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_logged_out_draft_on() {
		return $this->is_save_draft_on() && $this->in_save_draft_roles( 'loggedout' );
	}

	/**
	 * Check if a role is allowed to save drafts.
	 *
	 * @since 1.1
	 *
	 * @param string $option Role to check.
	 * @return bool
	 */
	private function in_save_draft_roles( $option = '' ) {
		$roles = $this->get_form_option( 'edit_draft_role' );
		return $this->is_role_in_array( $option, $roles );
	}

	/**
	 * Check if a role is included in permissions array.
	 * '' is for logged in users and 'loggedout' is for logged out users.
	 *
	 * @since 1.1
	 *
	 * @param string $option Role to check.
	 * @param mixed  $roles  Roles to check.
	 * @return bool
	 */
	private function is_role_in_array( $option, $roles ) {
		if ( empty( $roles ) || ! is_array( $roles ) ) {
			return false;
		}

		return in_array( $option, $roles, true );
	}

	/**
	 * Check whether "edit_draft_role" enabled for logged out users and user is logged out.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function allow_logged_out_draft() {
		if ( is_user_logged_in() ) {
			return false;
		}

		return $this->is_logged_out_draft_on();
	}

	/**
	 * Check whether auto save draft is enabled for a form.
	 *
	 * @since 1.1
	 *
	 * @return bool
	 */
	public function is_auto_save_on() {
		return $this->is_save_draft_on() && ! empty( $this->get_form_option( 'auto_save_draft' ) );
	}

	/**
	 * Get a form option.
	 *
	 * @param string $option Option to get.
	 *
	 * @return mixed
	 */
	public function get_form_option( $option ) {
		if ( empty( $this->form->options ) ) {
			return false;
		}

		return ! empty( $this->form->options[ $option ] ) ? $this->form->options[ $option ] : false;
	}
}
