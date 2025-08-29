<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * @since 3.06.04
 */
class FrmProUsageController {

	/**
	 * Add Pro settings to the settings array.
	 *
	 * @since 3.06.04
	 * @return array
	 */
	public static function settings( $settings ) {
		$setting_list  = FrmProAppHelper::get_settings();
		$default       = $setting_list->default_options();
		$pass_settings = array( 'menu_icon', 'date_format', 'datepicker_library' );

		foreach ( $pass_settings as $setting ) {
			$settings[ $setting ] = $setting_list->{$setting};
		}

		$messages = array( 'edit_msg', 'update_value', 'already_submitted' );
		foreach ( $messages as $message ) {
			$settings['messages'][ 'changed-' . $message ] = $setting_list->{$message} === $default[ $message ] ? 0 : 1;
		}

		$settings['using_chosen_js'] = FrmProAppHelper::use_chosen_js() ? 1 : 0;

		return $settings;
	}

	/**
	 * Combine the rootline settings for usage analysis.
	 *
	 * @since 3.06.04
	 * @return array
	 */
	public static function form( $form, $atts ) {
		$saved_form = $atts['form'];
		if ( ! empty( $saved_form->options['rootline'] ) ) {
			$form['rootline'] = array(
				'type'        => $saved_form->options['rootline'],
				'titles_on'   => isset( $saved_form->options['rootline_titles_on'] ) ? $saved_form->options['rootline_titles_on'] : 0,
				'lines_off'   => isset( $saved_form->options['rootline_lines_off'] ) ? $saved_form->options['rootline_lines_off'] : 0,
				'numbers_off' => isset( $saved_form->options['rootline_numbers_off'] ) ? $saved_form->options['rootline_numbers_off'] : 0,
			);
			$form['rootline'] = json_encode( $form['rootline'] );
		}

		return $form;
	}

	/**
	 * Adds application count to the snapshot.
	 *
	 * @since 6.18
	 *
	 * @param array $snapshot Usage snapshot.
	 * @return array
	 */
	public static function add_application_count( $snapshot ) {
		$snapshot['application_count'] = FrmProApplication::get_applications_count();
		return $snapshot;
	}

	/**
	 * Tracks usage data.
	 *
	 * @since 6.18
	 *
	 * @param array $args See {@see FrmProApplicationsController::xml_response()} for details.
	 *
	 * @return void
	 */
	public static function track_usage_data( $args ) {
		if ( empty( $args['url'] ) ) {
			// The old Formidable Forms doesn't contain the URL in `args`.
			return;
		}

		preg_match_all( '/\/(.+\/)(.+)\.xml/', $args['url'], $matches );
		if ( ! $matches || empty( $matches[2][0] ) ) {
			// Can't extract the xml name from the URL.
			return;
		}

		FrmUsageController::update_flows_data( 'installed_applications', $matches[2][0] );
	}
}
