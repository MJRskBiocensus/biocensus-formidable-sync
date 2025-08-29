<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

#[\AllowDynamicProperties]
class FrmProSettings extends FrmSettings {
	public $option_name = 'frmpro_options';

	// Options.
	public $edit_msg;
	public $update_value;
	public $already_submitted;
	public $cal_date_format;
	public $date_format;
	public $menu_icon;
	public $inbox;
	public $repeater_row_delete_confirmation;
	public $hide_dashboard_videos;
	public $entry_delete_message;
	public $datepicker_library;

	// Currency Options.
	public $use_custom_currency_format;
	public $thousand_separator;
	public $decimal_separator;
	public $decimals;

	/**
	 * @return array
	 */
	public function default_options() {
		return array(
			'edit_msg'                         => __( 'Your submission was successfully saved.', 'formidable-pro' ),
			'update_value'                     => __( 'Update', 'formidable-pro' ),
			'already_submitted'                => __( 'You have already submitted that form', 'formidable-pro' ),
			'date_format'                      => 'm/d/Y',
			'datepicker_library'               => 'default',
			'cal_date_format'                  => $this->get_cal_date(),
			'menu_icon'                        => '',
			'currency'                         => 'USD',
			'use_custom_currency_format'       => 0,
			'thousand_separator'               => '',
			'decimal_separator'                => '',
			'decimals'                         => '',
			'inbox'                            => array(
				'set'      => FrmProDb::$plug_version,
				'badge'    => 1,
				'promo'    => 1,
				'news'     => 1,
				'feedback' => 1,
			),
			'repeater_row_delete_confirmation' => __( 'Are you sure you want to delete this row?', 'formidable-pro' ),
			'hide_dashboard_videos'            => 0,
			'entry_delete_message'             => __( 'Your entry was successfully deleted.', 'formidable-pro' ),
		);
	}

	public function set_default_options() {
		$this->fill_with_defaults();
	}

	/**
	 * @since 4.06.01
	 */
	public function fill_with_defaults( $params = array() ) {
		$params['additional_filter_keys'] = array(
			'edit_msg',
			'update_value',
			'already_submitted',
			'repeater_row_delete_confirmation',
			'hide_dashboard_videos',
			'entry_delete_message',
			'use_custom_currency_format',
			'thousand_separator',
			'decimal_separator',
			'decimals',
		);
		parent::fill_with_defaults( $params );
		$this->fill_inbox_defaults();
	}

	/**
	 * Since inbox settings are on by default, add any new options to
	 * prevent it from being off when added.
	 *
	 * @since 4.06.01
	 */
	private function fill_inbox_defaults() {
		$added = array(
			'feedback' => '4.06.01',
		);
		foreach ( $added as $type => $v ) {
			if ( ! isset( $this->inbox[ $type ] ) && version_compare( $this->inbox['set'], $v, '<' ) ) {
				$this->inbox[ $type ] = 1;
			}
		}
	}

	public function update( $params ) {
		if ( isset( $params['frm_date_format'] ) ) {
			$this->date_format = $params['frm_date_format'];
		}
		if ( isset( $params['frm_datepicker_library'] ) && $this->datepicker_library !== $params['frm_datepicker_library'] ) {
			$this->datepicker_library = sanitize_key( $params['frm_datepicker_library'] );
			$this->on_datepicker_library_change();
		}

		$this->get_cal_date();

		$this->fill_with_defaults( $params );
		$this->update_checkbox_settings( $params );
	}

	/**
	 * Make changes when the datepicker setting changes.
	 * We update the combined JS file, as well as update the inbox notices.
	 *
	 * @since 6.19
	 *
	 * @return void
	 */
	private function on_datepicker_library_change() {
		FrmAppHelper::save_combined_js();

		$frm_style = new FrmStyle();
		$frm_style->save_settings();

		$inbox = new FrmInbox();
		$inbox->dismiss( 'try-flatpickr' );
		$inbox->dismiss( 'jquery-datepickr-feedback' );
		$inbox->dismiss( 'flatpickr-feedback' );

		if ( 'flatpickr' === $this->datepicker_library ) {
			$message = array(
				'key'     => 'flatpickr-feedback',
				'subject' => __( 'Thank you for trying Flatpickr!', 'formidable-pro' ),
				'message' => __( 'We would like to hear your feedback! If you notice any issues, please let us know.', 'formidable-pro' ),
				'cta'     => '<a href="https://feedback.strategy11.com/flatpickr-bug-report/">' . esc_html__( 'Send Feedback', 'formidable-pro' ) . '</a>',
				'type'    => 'feedback',
			);
			$inbox->add_message( $message );
		} elseif ( 'jquery' === $this->datepicker_library ) {
			$message = array(
				'key'     => 'jquery-datepickr-feedback',
				'subject' => __( 'Did we get something wrong?', 'formidable-pro' ),
				'message' => __( 'Our flatpickr library should support everything our jQuery datepicker does now. Please let us know why you don\'t want to use flatpickr so we can improve it!', 'formidable-pro' ), // phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
				'cta'     => '<a href="https://feedback.strategy11.com/flatpickr-bug-report/">' . esc_html__( 'Send Feedback', 'formidable-pro' ) . '</a>',
				'type'    => 'feedback',
			);
			$inbox->add_message( $message );
		}
	}

	/**
	 * @since 6.10.1
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	private function update_checkbox_settings( $params ) {
		$checkboxes = array( 'hide_dashboard_videos', 'use_custom_currency_format' );
		foreach ( $checkboxes as $set ) {
			$this->$set = isset( $params[ 'frm_' . $set ] ) ? absint( $params[ 'frm_' . $set ] ) : 0;
		}
		$this->menu_icon = empty( $params['frm_menu_icon'] ) ? '' : 'frm_white_label_icon';
	}

	/**
	 * Get the conversions from php date format to datepicker
	 * Set the cal_date_format to make sure it's not empty
	 *
	 * @since 2.0.2
	 */
	public function get_cal_date() {
		$formats = FrmProAppHelper::display_to_datepicker_format();
		if ( isset( $formats[ $this->date_format ] ) ) {
			$this->cal_date_format = $formats[ $this->date_format ];
		} else {
			$this->cal_date_format = 'mm/dd/yy';
		}
	}

	/**
	 * @since 4.06.01
	 */
	public function inbox_types() {
		return array(
			'badge'    => __( 'Show unread message count in menu', 'formidable-pro' ),
			'promo'    => __( 'Sales and promotions', 'formidable-pro' ),
			'news'     => __( 'New features', 'formidable-pro' ),
			'feedback' => __( 'Requests for feedback', 'formidable-pro' ),
		);
	}

	public function store() {
		// Save the posted value in the database
		update_option( $this->option_name, $this, 'no' );

		delete_transient( $this->option_name );
		set_transient( $this->option_name, $this );
	}
}
